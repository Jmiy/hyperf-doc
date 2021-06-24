<?php

/**
 * 会员服务
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services;

use Exception;
use Hyperf\DbConnection\Db as DB;
use Hyperf\Utils\Arr;
use Carbon\Carbon;
use App\Models\Customer;
use App\Models\CustomerInfo;
use App\Utils\FunctionHelper;
use App\Utils\Support\Facades\Cache;
use App\Utils\Curl;
use App\Services\Traits\GetDefaultConnectionModel;
use App\Constants\Constant;
use App\Utils\Response;
use App\Services\Store\PlatformServiceManager;
use App\Utils\Support\Facades\Redis;
use App\Services\Monitor\MonitorServiceManager;
use App\Exception\Handler\AppExceptionHandler as ExceptionHandler;

class CustomerService extends BaseService {

    use GetDefaultConnectionModel;

    public static function getCacheTags() {
        return Constant::REGISTERED;
    }

    /**
     * 检查会员是否存在
     * @param int $storeId
     * @param int $customerId
     * @param string $account
     * @param int $storeCustomerId
     * @return bool
     * @author harry
     */
    public static function customerExists($storeId = 0, $customerId = 0, $account = '', $storeCustomerId = 0, $getData = false, $select = null) {
        $where = [];

        if ($customerId) {
            $where[Constant::DB_TABLE_CUSTOMER_PRIMARY] = $customerId;
        }

        if ($storeId) {
            $where[Constant::DB_TABLE_STORE_ID] = $storeId;
        }

        if ($account) {
            $where[Constant::DB_TABLE_ACCOUNT] = $account;
        }

        if ($storeCustomerId) {
            $where['store_customer_id'] = $storeCustomerId;
        }

        return static::existsOrFirst($storeId, '', $where, $getData, $select);
    }

    /**
     * 会员基本资料编辑
     * @param $params
     */
    public static function edit($storeId, $customerId, $data, $extData = []) {

        if (empty($customerId)) {
            return false;
        }

        if (isset($data[Constant::DB_TABLE_COUNTRY_CODE]) && $data[Constant::DB_TABLE_COUNTRY_CODE]) {
            $data[Constant::DB_TABLE_COUNTRY] = $data[Constant::DB_TABLE_COUNTRY_CODE];
            unset($data[Constant::DB_TABLE_COUNTRY_CODE]);
        }

        $data['mtime'] = data_get($data, Constant::DB_TABLE_UPDATED_AT, Carbon::now()->toDateTimeString());
        if (isset($data[Constant::DB_TABLE_UPDATED_AT])) {
            unset($data[Constant::DB_TABLE_UPDATED_AT]);
        }

        $where = [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId];
        $country = '';
        return CustomerInfoService::updateOrCreate($storeId, $where, $data, $country);
    }

    /**
     * api编辑会员信息
     * @param int $customerId 会员id
     * @param int $params 请求参数
     * @param obj $customerInfo 会员基本信息
     * @return boolean true:编辑成功 false:编辑失败
     */
    public static function apiEdit($customerId, $params, $customerInfo = null) {

        $paramsEdit = [];
        if (isset($params[Constant::DB_TABLE_FIRST_NAME])) {
            $paramsEdit[Constant::DB_TABLE_FIRST_NAME] = $params[Constant::DB_TABLE_FIRST_NAME];
        }
        if (isset($params[Constant::DB_TABLE_LAST_NAME])) {
            $paramsEdit[Constant::DB_TABLE_LAST_NAME] = $params[Constant::DB_TABLE_LAST_NAME];
        }
        if (isset($params[Constant::DB_TABLE_GENDER])) {
            $paramsEdit[Constant::DB_TABLE_GENDER] = $params[Constant::DB_TABLE_GENDER];
        }
        if (isset($params[Constant::DB_TABLE_BRITHDAY])) {
            $paramsEdit[Constant::DB_TABLE_BRITHDAY] = $params[Constant::DB_TABLE_BRITHDAY];
        }

        if (isset($params[Constant::DB_TABLE_COUNTRY]) && !empty($params[Constant::DB_TABLE_COUNTRY])) {
            $paramsEdit[Constant::DB_TABLE_COUNTRY] = $params[Constant::DB_TABLE_COUNTRY];
        }

        if (isset($params[Constant::DB_TABLE_PROFILE_URL])) {
            $paramsEdit[Constant::DB_TABLE_PROFILE_URL] = $params[Constant::DB_TABLE_PROFILE_URL];
        }

        if (isset($params[Constant::DB_TABLE_IP]) && !empty($params[Constant::DB_TABLE_IP])) {
            $paramsEdit[Constant::DB_TABLE_IP] = $params[Constant::DB_TABLE_IP];
        }

        if (isset($params[Constant::DB_TABLE_IS_ORDER])) {
            $paramsEdit[Constant::DB_TABLE_IS_ORDER] = $params[Constant::DB_TABLE_IS_ORDER];
        }
        if (isset($params[Constant::DB_TABLE_UPDATED_AT])) {
            data_set($paramsEdit, Constant::DB_TABLE_UPDATED_AT, $params[Constant::DB_TABLE_UPDATED_AT]);
        }

        if (isset($params[Constant::DB_TABLE_EDIT_AT])) {//基本资料编辑时间
            data_set($paramsEdit, Constant::DB_TABLE_EDIT_AT, $params[Constant::DB_TABLE_EDIT_AT]);
        }

        if (isset($params[Constant::AVATAR])) {//头像
            data_set($paramsEdit, Constant::AVATAR, $params[Constant::AVATAR]);
        }

        $res = true;
        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, 0);
        if ($paramsEdit) {
            $res = static::edit($storeId, $customerId, $paramsEdit, $params);
        }

        //地址
        if (isset($params['address']) || isset($params[Constant::DB_TABLE_REGION]) || isset($params['city'])) {
            CustomerAddressService::edit($storeId, $customerId, $params);
        }

        //编辑兴趣
        InterestService::edit($storeId, $customerId, $params);

        if (empty($customerInfo)) {
            $customerInfo = CustomerInfoService::getModel($storeId, '')->select(['id', 'is_complete_edit'])->where(Constant::DB_TABLE_CUSTOMER_PRIMARY, $customerId)->first();
        }

        if (empty($customerInfo)) {
            return $res;
        }

        if ($customerInfo->is_complete_edit == 1) {//如果已经完成基本资料编辑，就直接返回
            return $res;
        }

        /*         * ***************完善基本资料送 10 积分 start ************************** */
        $is_complete_edit = 1; //是否完成基本资料编辑 0:未完成 1:已完成 默认:0
        //获取会员编辑配置
        $type = 'customer_edit';
        $orderby = 'sorts asc';
        $keyField = 'conf_key';
        $valueField = 'conf_value';
        $customerEditConfig = DictStoreService::getListByType($storeId, $type, $orderby, $keyField, $valueField);
        $customerEditCompleteField = data_get($customerEditConfig, 'complete_field', ''); //  获取 完善基本资料必须填写的字段
        if ($customerEditCompleteField) {
            $customerEditCompleteField = explode(',', $customerEditCompleteField);
            foreach ($customerEditCompleteField as $field) {
                if (!isset($params[$field]) || empty($params[$field])) {
                    $is_complete_edit = 0;
                }

                if (empty($is_complete_edit)) {
                    break;
                }
            }
        }

        if ($is_complete_edit == 0) {
            return $res;
        }

        //更新 完成基本资料 为 1
        CustomerInfoService::getModel($storeId, '')->where(Constant::DB_TABLE_CUSTOMER_PRIMARY, $customerId)->update(['is_complete_edit' => $is_complete_edit]);

        /*         * ***************完善基本资料送 10 积分 start  ************************** */
        //完善基本资料送积分
        $customerEditCredit = data_get($customerEditConfig, Constant::DB_TABLE_CREDIT, 0); //获取 完善基本资料送的积分
        if ($customerEditCredit) {
            //记录积分流水
            $_requestData = [
                Constant::DB_TABLE_EXT_ID => $customerInfo->id,
                Constant::DB_TABLE_EXT_TYPE => 'customer_info',
            ];
            CreditService::handleVip($storeId, $customerId, 'complete_edit', null, $customerEditCredit, $_requestData);
        }
        /*         * ***************完善基本资料送 10 积分 end ************************** */

        //完善基本资料发送coupon
        $account = data_get($params, Constant::DB_TABLE_ACCOUNT, '');
        $createdAt = data_get($params, Constant::DB_TABLE_CREATED_AT, ''); //会员账号创建时间  现在没有使用了
        $ip = Arr::get($params, Constant::DB_TABLE_IP, ''); //ip
        $country = Arr::get($params, Constant::DB_TABLE_COUNTRY, ''); //会员国家
        $firstName = data_get($paramsEdit, Constant::DB_TABLE_FIRST_NAME, ''); //会员 first name
        $lastName = data_get($paramsEdit, Constant::DB_TABLE_LAST_NAME, ''); //会员 last name
        $group = Constant::CUSTOMER;
        $remark = '完善基本资料';
        $customerEditCoupon = data_get($customerEditConfig, 'coupon', 0); //完善资料是否发送coupon 1:发送 0:不发送

        if ($customerEditCoupon) {
            //发送优惠券邮件
            $requestData = [
                Constant::DB_TABLE_STORE_ID => $storeId,
                Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                Constant::DB_TABLE_ACCOUNT => $account,
                Constant::DB_TABLE_COUNTRY => $country,
                'group' => $group,
                Constant::DB_TABLE_FIRST_NAME => $firstName,
                Constant::DB_TABLE_LAST_NAME => $lastName,
                Constant::DB_TABLE_IP => $ip,
                'remark' => $remark,
                'ctime' => $createdAt,
                Constant::DB_TABLE_ACT_ID => data_get($params, Constant::DB_TABLE_ACT_ID, 0),
                Constant::DB_TABLE_SOURCE => data_get($params, Constant::DB_TABLE_SOURCE, 1),
            ];
            EmailService::sendCouponEmail($storeId, $requestData);
        }
        /*         * ***************完善基本资料发送coupon end ************************** */

        return $res;
    }

    /**
     * 获取单个会员
     * @param int $storeId
     * @param int $customerId
     * @param string $account
     * @param int $storeCustomerId
     * @return \yii\db\Command
     */
    public static function getCustomer($storeId = 0, $customerId = 0, $account = '', $storeCustomerId = 0) {

        $where = [];

        if ($customerId) {
            $where[Constant::DB_TABLE_CUSTOMER_PRIMARY] = intval($customerId);
        }

        if ($storeId) {
            $where[Constant::DB_TABLE_STORE_ID] = intval($storeId);
        }

        if ($account) {
            $where[Constant::DB_TABLE_ACCOUNT] = $account;
        }

        if ($storeCustomerId) {
            $where['store_customer_id'] = $storeCustomerId;
        }

        $dbExecutionPlan = [
            'with' => [
                'info' => [
                    Constant::DB_EXECUTION_PLAN_SETCONNECTION => false,
                    Constant::DB_EXECUTION_PLAN_STOREID => 0,
                    Constant::DB_EXECUTION_PLAN_RELATION => Constant::HAS_ONE,
                    Constant::DB_EXECUTION_PLAN_DEFAULT => [
                        Constant::DB_TABLE_CUSTOMER_PRIMARY => Constant::DB_TABLE_CUSTOMER_PRIMARY,
                    ],
                    Constant::DB_EXECUTION_PLAN_SELECT => CustomerInfo::getColumns(),
                    Constant::DB_EXECUTION_PLAN_WHERE => [],
                    Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                        'info.brithday' => [
                            Constant::DB_EXECUTION_PLAN_FIELD => 'info.brithday',
                            'data' => [],
                            Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_DATETIME,
                            Constant::DB_EXECUTION_PLAN_DATA_FORMAT => 'Y-m-d',
                            'glue' => '',
                            Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                        ],
                    ],
                    Constant::DB_EXECUTION_PLAN_UNSET => ['info'],
                ],
                Constant::ADDRESS_HOME => [
                    Constant::DB_EXECUTION_PLAN_SETCONNECTION => false,
                    Constant::DB_EXECUTION_PLAN_STOREID => 0,
                    Constant::DB_EXECUTION_PLAN_RELATION => Constant::HAS_ONE,
                    Constant::DB_EXECUTION_PLAN_DEFAULT => [
                        Constant::DB_TABLE_CUSTOMER_PRIMARY => Constant::DB_TABLE_CUSTOMER_PRIMARY,
                    ],
                    Constant::DB_EXECUTION_PLAN_SELECT => [
                        Constant::DB_TABLE_CUSTOMER_PRIMARY,
                        Constant::DB_TABLE_REGION,
                        'city',
                        'street',
                    ],
                    Constant::DB_EXECUTION_PLAN_WHERE => [],
                    Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                        'address_home.city' => [
                            Constant::DB_EXECUTION_PLAN_FIELD => 'address_home.city{or}address_home.region',
                            'data' => [],
                            Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                            Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                            'glue' => '',
                            Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                        ],
                    ],
                    Constant::DB_EXECUTION_PLAN_UNSET => [Constant::ADDRESS_HOME],
                ],
                Constant::DB_TABLE_INTERESTS => [
                    Constant::DB_EXECUTION_PLAN_SETCONNECTION => false,
                    Constant::DB_EXECUTION_PLAN_STOREID => 0,
                    Constant::DB_EXECUTION_PLAN_RELATION => 'hasMany',
                    Constant::DB_EXECUTION_PLAN_DEFAULT => [
                        Constant::DB_TABLE_CUSTOMER_PRIMARY => Constant::DB_TABLE_CUSTOMER_PRIMARY,
                    ],
                    Constant::DB_EXECUTION_PLAN_SELECT => [
                        Constant::DB_TABLE_CUSTOMER_PRIMARY,
                        Constant::DB_TABLE_INTEREST
                    ],
                    Constant::DB_EXECUTION_PLAN_WHERE => [],
                    Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [],
                //Constant::DB_EXECUTION_PLAN_UNSET => [Constant::DB_TABLE_INTERESTS],
                ],
            ]
        ];

        $customerSelect = Customer::getColumns();
        $customer = static::getCustomerData($storeId, $where, $customerSelect, $dbExecutionPlan, true);

        if ($customer) {
            $inviteCodeType = [ //老的邀请
                'invite_code_type' => 1,
                'store_id' => $storeId
            ];
            $inviteNewCodeType = [ //新的佣金邀请
                'invite_code_type' => 2,
                'store_id' => $storeId
            ];
            $inviteCodeData = InviteService::getInviteCodeData($customer[Constant::DB_TABLE_CUSTOMER_PRIMARY],$inviteCodeType);
            $inviteNewCodeData = InviteService::getInviteCodeData($customer[Constant::DB_TABLE_CUSTOMER_PRIMARY],$inviteNewCodeType);
            $customer[Constant::DB_TABLE_INVITE_CODE] = Arr::get($inviteCodeData, Constant::DB_TABLE_INVITE_CODE, '');
            $customer[Constant::DB_TABLE_NEW_INVITE_CODE] = Arr::get($inviteNewCodeData, Constant::DB_TABLE_INVITE_CODE, '');
            $customer[Constant::DB_TABLE_STORE_DICT_VALUE] = DictStoreService::getByTypeAndKey($storeId, 'invite_register', 'invite_register_url', true);

            $countryName = CountryService::countryOne($customer[Constant::DB_TABLE_COUNTRY], true);
            $customer[Constant::DB_TABLE_COUNTRY_CODE] = $customer[Constant::DB_TABLE_COUNTRY];
            $customer[Constant::DB_TABLE_COUNTRY] = $countryName;

            $customer['is_social_media_account'] = SocialMediaLoginService::existsOrFirst($storeId, '', [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId]);
        }

        return $customer;
    }

    /**
     * 获取单个地址
     * @param $storeId
     * @param $customer_id
     * @param string $type
     * @return obj|null
     */
    public static function getAddress($storeId, $customer_id, $type = '') {
        $where = [
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customer_id,
            Constant::DB_TABLE_STORE_ID => $storeId
        ];
        if ($type) {
            $where['type'] = $type;
        }
        return CustomerAddressService::getModel($storeId)->where($where)->orderBy('id', 'desc')->limit(1)->first();
    }

    /**
     * 获取VIP等级
     * @param $storeId
     * @param $credit
     * @return int|string
     */
    public static function getVipLv($storeId, $credit) {

        $vip = 1;
        $viplist = DictStoreService::getListByType($storeId, 'vip_lv', 'conf_key DESC', 'conf_key', 'conf_value');
        if ($viplist->isEmpty()) {
            return $vip;
        }

        foreach ($viplist as $lv => $value) {
            if ($credit >= $value) {
                $vip = $lv;
                break;
            }
        }

        return $vip;
    }

    /**
     * 判断邮箱是否有效 详情：https://api.hubuco.com/api/v3/?api=dUcaqvPVb6RN9Emx0jmLXSJuh&email=quella_xia@patazon.net&timeout=10
     * @param string $email 邮箱
     * @param string $requestMethod 请求方式 默认：GET
     * @param array $headers 请求头
     * @return int $resultcode 1 for ok, 2 for catch_all, 3 for unknown, 4 for error, 5 for disposable, 6 for invalid
     */
    public static function isEffectiveEmail($email = '', $requestMethod = 'GET', $headers = []) {
        $headers = $headers ? $headers : [
            'Content-Type: application/json; charset=utf-8', //设置请求内容为 json  这个时候post数据必须是json串 否则请求参数会解析失败
        ];

        $curlOptions = [
            CURLOPT_CONNECTTIMEOUT_MS => 1000 * 10,
            CURLOPT_TIMEOUT_MS => 1000 * 10,
        ];

        //https://api.hubuco.com/api/v3/?api=dUcaqvPVb6RN9Emx0jmLXSJuh&email=quella_xia@patazon.net&timeout=10
        $requestData = [
            'api' => 'dUcaqvPVb6RN9Emx0jmLXSJuh',
            Constant::DB_TABLE_EMAIL => $email,
            'timeout' => 10,
        ];
        $url = 'https://api.hubuco.com/api/v3/';

        $responseText = Curl::request($url, $headers, $curlOptions, $requestData, $requestMethod);
        $resultcode = data_get($responseText, 'responseText.resultcode', -1);

        if ($resultcode === -1) {//如果请求失败，就再请求一次
            $responseText = Curl::request($url, $headers, $curlOptions, $requestData, $requestMethod);
            $resultcode = data_get($responseText, 'responseText.resultcode', -1);
        }

        return $resultcode == 1 ? true : false;
    }

    /**
     * 获取db query
     * @param array $where
     * @return \Illuminate\Database\Query\Builder|static $query
     */
    public static function getQuery($storeId = 0, $country = '', $where = []) {
        return static::getModel($storeId, $country)->from('customer as a')
                        ->leftJoin('customer_info as b', 'a' . Constant::LINKER . Constant::DB_TABLE_CUSTOMER_PRIMARY, '=', 'b' . Constant::LINKER . Constant::DB_TABLE_CUSTOMER_PRIMARY)
                        ->buildWhere($where)
//                ->with(['source_data' => function($query) {
//                $query->select([Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE])->where('type', Constant::DB_TABLE_SOURCE);
//            }])
        ;
    }

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @return array
     */
    public static function getPublicData($params, $order = []) {
        $where = [];

        $params[Constant::DB_TABLE_STORE_ID] = $params[Constant::DB_TABLE_STORE_ID] ?? '';
        $params[Constant::DB_TABLE_COUNTRY] = $params[Constant::DB_TABLE_COUNTRY] ?? '';
        $params[Constant::DB_TABLE_ACCOUNT] = $params[Constant::DB_TABLE_ACCOUNT] ?? '';
        $params[Constant::DB_TABLE_VIP] = $params[Constant::DB_TABLE_VIP] ?? 0;
        $params[Constant::START_TIME] = $params[Constant::START_TIME] ?? '';
        $params[Constant::DB_TABLE_END_TIME] = $params[Constant::DB_TABLE_END_TIME] ?? '';

        if ($params[Constant::DB_TABLE_STORE_ID]) {
            $where[] = ['a.store_id', '=', intval($params[Constant::DB_TABLE_STORE_ID])];
        }

        if ($params[Constant::DB_TABLE_ACCOUNT]) {
            $where[] = ['a.account', '=', $params[Constant::DB_TABLE_ACCOUNT]];
        }

        if ($params[Constant::DB_TABLE_COUNTRY]) {
            $where[] = ['b' . Constant::LINKER . Constant::DB_TABLE_COUNTRY, '=', $params[Constant::DB_TABLE_COUNTRY]];
        }

        if ($params[Constant::DB_TABLE_VIP]) {
            $where[] = ['b.vip', '=', $params[Constant::DB_TABLE_VIP]];
        }

        if ($params[Constant::START_TIME]) {
            $where[] = ['a' . Constant::LINKER . Constant::DB_TABLE_OLD_CREATED_AT, '>=', $params[Constant::START_TIME]];
        }

        if ($params[Constant::DB_TABLE_END_TIME]) {
            $where[] = ['a' . Constant::LINKER . Constant::DB_TABLE_OLD_CREATED_AT, '<=', $params[Constant::DB_TABLE_END_TIME]];
        }

        $start_at = data_get($params, Constant::DB_TABLE_START_AT, Constant::PARAMETER_STRING_DEFAULT);
        $end_at = data_get($params, Constant::DB_TABLE_END_AT, Constant::PARAMETER_STRING_DEFAULT);
        if ($start_at) {
            $where[] = ['b' . Constant::LINKER . Constant::DB_TABLE_EDIT_AT, '>=', $start_at];
        }
        if ($end_at) {
            $where[] = ['b' . Constant::LINKER . Constant::DB_TABLE_EDIT_AT, '<=', $end_at];
        }

        $gender = Arr::get($params, Constant::DB_TABLE_GENDER, ''); //性别
        if ($gender !== '') {
            $where[] = ['b' . Constant::LINKER . Constant::DB_TABLE_GENDER, '=', $gender];
        }

        $isHasProfile = Arr::get($params, 'is_has_profile', ''); //个人资料网址
        if ($isHasProfile !== '') {
            switch ($isHasProfile) {
                case 0:
                    $where[] = ['b' . Constant::LINKER . Constant::DB_TABLE_PROFILE_URL, '=', ''];

                    break;

                case 1:
                    $where[] = ['b' . Constant::LINKER . Constant::DB_TABLE_PROFILE_URL, '!=', ''];

                    break;

                default:
                    break;
            }
        }

        $isActivate = Arr::get($params, Constant::DB_TABLE_IS_ACTIVATE, ''); //是否激活
        if ($isActivate !== '') {
            $where[] = ['b.isactivate', '=', $isActivate];
        }

        $order = $order ? $order : ['a' . Constant::LINKER . Constant::DB_TABLE_OLD_CREATED_AT, 'desc'];
        $_where = [];

        if (data_get($params, Constant::DB_TABLE_CUSTOMER_PRIMARY, 0)) {
            $_where['a' . Constant::LINKER . Constant::DB_TABLE_CUSTOMER_PRIMARY] = $params[Constant::DB_TABLE_CUSTOMER_PRIMARY];
        }
        $_where[] = $where;
        return Arr::collapse([parent::getPublicData($params, $order), [
                        Constant::DB_EXECUTION_PLAN_WHERE => $_where,
        ]]);
    }

    /**
     * 列表
     * @param array $params 请求参数
     * @param boolean $toArray 是否转化为数组 true:是 false:否 默认:true
     * @param boolean $isPage 是否分页 true:是 false:否 默认:true
     * @param array $select 查询字段
     * @param boolean $isRaw 是否原始 select true:是 false:否 默认:false
     * @param boolean $isGetQuery 是否获取 query
     * @param boolean $isOnlyGetCount 是否仅仅获取总记录数
     * @return array|\Hyperf\Database\Model\Builder 列表数据|Builder
     */
    public static function getShowList($params, $toArray = false, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {

        $_data = static::getPublicData($params);

        $where = $_data[Constant::DB_EXECUTION_PLAN_WHERE];
        $order = data_get($params, 'orderBy', $_data['order']);
        $pagination = $_data[Constant::DB_EXECUTION_PLAN_PAGINATION];
        $limit = $pagination['page_size'];

        $customerCount = true;
        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, 0); //商城id
        $country = '';
        $query = static::getQuery($storeId, $country, $where);

        $source = Arr::get($params, Constant::DB_TABLE_SOURCE, '');
        if ($source) {
            $query = $query->whereExists(function ($query) use ($source) {
                $query->select(DB::raw(1))
                        ->from('dict as d')
                        ->where('d.type', Constant::DB_TABLE_SOURCE)
                        ->whereColumn('d.dict_key', '=', 'a.source')
                        ->where('ext1', $source);
            });
        }

        if ($isPage || $isOnlyGetCount) {
            $customerCount = static::adminCount($params, $query);
            $pagination['total'] = $customerCount;
            $pagination['total_page'] = ceil($customerCount / $limit);

            if ($isOnlyGetCount) {
                return $pagination;
            }
        }

        if (empty($customerCount)) {
            $query = null;
            return [
                'data' => [],
                Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
            ];
        }

        if ($order) {
            $query = $query->orderBy($order[0], $order[1]);
        }

        $data = [
            'query' => $query,
            Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
        ];
        $infoSelect = [
            'b' . Constant::LINKER . Constant::DB_TABLE_FIRST_NAME,
            'b' . Constant::LINKER . Constant::DB_TABLE_LAST_NAME,
            'b' . Constant::LINKER . Constant::DB_TABLE_GENDER,
            'b' . Constant::LINKER . Constant::DB_TABLE_BRITHDAY,
            'b' . Constant::LINKER . Constant::DB_TABLE_COUNTRY,
            'b' . Constant::LINKER . Constant::DB_TABLE_CREDIT,
            'b' . Constant::LINKER . Constant::DB_TABLE_TOTAL_CREDIT,
            'b' . Constant::LINKER . Constant::DB_TABLE_EXP,
            'b' . Constant::LINKER . Constant::DB_TABLE_VIP,
            'b' . Constant::LINKER . Constant::DB_TABLE_LASTLOGIN,
            'b' . Constant::LINKER . Constant::DB_TABLE_IP,
            'b' . Constant::LINKER . Constant::DB_TABLE_IS_ACTIVATE,
        ];
        $select = $select ? $select : Customer::getColumns('a', $infoSelect); //, 'c.dict_value as source_value'
        $data = static::getList($data, $toArray, $isPage, $select, $isRaw, true);

        $genderData = DictService::getListByType(Constant::DB_TABLE_GENDER, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE);
        $isActivateData = DictService::getListByType('is_activate', Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE);
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => [
                Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                Constant::DB_EXECUTION_PLAN_STOREID => 0,
                Constant::DB_EXECUTION_PLAN_BUILDER => $data,
                'make' => '',
                'from' => '',
                Constant::DB_EXECUTION_PLAN_SELECT => $select,
                Constant::DB_EXECUTION_PLAN_WHERE => $where,
                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                    Constant::DB_TABLE_GENDER => [
                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_GENDER,
                        'data' => $genderData,
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => $genderData[0],
                    ],
                    Constant::DB_TABLE_BRITHDAY => [
                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_BRITHDAY,
                        'data' => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_DATETIME,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => 'Y-m-d',
                        'glue' => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                    'name' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'first_name{connection}last_name',
                        'data' => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_STRING,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => ' ',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                    Constant::DB_TABLE_IS_ACTIVATE => [
                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_IS_ACTIVATE,
                        'data' => $isActivateData,
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => $isActivateData[0],
                    ],
                ],
            //Constant::DB_EXECUTION_PLAN_UNSET => [Constant::DB_TABLE_CUSTOMER_PRIMARY],
            ],
            'with' => [
                'source_data' => [
                    Constant::DB_EXECUTION_PLAN_SETCONNECTION => false,
                    Constant::DB_EXECUTION_PLAN_STOREID => 0,
                    Constant::DB_EXECUTION_PLAN_RELATION => Constant::HAS_ONE,
                    Constant::DB_EXECUTION_PLAN_SELECT => [Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE],
                    Constant::DB_EXECUTION_PLAN_DEFAULT => [],
                    Constant::DB_EXECUTION_PLAN_WHERE => [
                        'type' => Constant::DB_TABLE_SOURCE,
                    ],
                    Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                        'source_value' => [
                            Constant::DB_EXECUTION_PLAN_FIELD => 'source_data.dict_value',
                            'data' => [],
                            Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                            'glue' => '',
                            Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                        ],
                    ],
                    Constant::DB_EXECUTION_PLAN_UNSET => ['source_data'],
                ],
            ],
                //'sqlDebug' => true,
        ];

        if (data_get($params, 'isOnlyGetPrimary', false)) {//如果仅仅获取主键id，就不需要处理数据，不关联
            data_set($dbExecutionPlan, 'parent.handleData', []);
            data_set($dbExecutionPlan, 'with', []);
        }

        $dataStructure = 'list';
        $flatten = false;
        $_data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);

        if ($isGetQuery) {
            return $_data;
        }

        return [
            'data' => $_data,
            Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
        ];
    }

    /**
     * 列表
     * @param array $params 请求参数
     * @param boolean $toArray 是否转化为数组 true:是 false:否 默认:true
     * @param boolean $isPage 是否分页 true:是 false:否 默认:true
     * @param array $select 查询字段
     * @param boolean $isRaw 是否原始 select true:是 false:否 默认:false
     * @param boolean $isGetQuery 是否获取 query
     * @param boolean $isOnlyGetCount 是否仅仅获取总记录数
     * @return array|\Hyperf\Database\Model\Builder 列表数据|Builder
     */
    public static function getDetailsList($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {

        $_data = static::getPublicData($params, ['b' . Constant::LINKER . Constant::DB_TABLE_EDIT_AT, 'desc']);

        $where = $_data[Constant::DB_EXECUTION_PLAN_WHERE];
        $order = data_get($params, 'orderBy', data_get($_data, 'order', []));
        $pagination = $_data[Constant::DB_EXECUTION_PLAN_PAGINATION];
        $limit = $pagination['page_size'];

        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, 0); //商城id
        $country = '';
        $query = static::getQuery($storeId, $country, $where);

        //兴趣查询
        $interests = isset($params[Constant::DB_TABLE_INTERESTS]) && $params[Constant::DB_TABLE_INTERESTS] ? $params[Constant::DB_TABLE_INTERESTS] : [];
        if ($interests) {
            $query = $query->whereExists(function ($query) use ($interests) {
                $query->select(DB::raw(1))
                        ->from('interests as i')
                        ->whereColumn('i.customer_id', '=', 'a' . Constant::LINKER . Constant::DB_TABLE_CUSTOMER_PRIMARY)
                        ->where(function ($query) use ($interests) {
                            foreach ($interests as $interest) {
                                $query->orWhere('i.interest', $interest);
                            }
                        });
            });
        }

        $customerCount = true;

        if ($isPage || $isOnlyGetCount) {
            $customerCount = static::adminCount($params, $query);
            $pagination['total'] = $customerCount;
            $pagination['total_page'] = ceil($customerCount / $limit);

            if ($isOnlyGetCount) {
                return $pagination;
            }
        }

        if (empty($customerCount)) {
            $query = null;
            return [
                'data' => [],
                Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
            ];
        }

        if ($order) {
            $query = $query->orderBy($order[0], $order[1]);
        }

        $data = [
            'query' => $query,
            Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
        ];

        $select = $select ? $select : Customer::getColumns('a', ['b' . Constant::LINKER . Constant::DB_TABLE_FIRST_NAME, 'b' . Constant::LINKER . Constant::DB_TABLE_LAST_NAME, 'b' . Constant::LINKER . Constant::DB_TABLE_GENDER, 'b' . Constant::LINKER . Constant::DB_TABLE_BRITHDAY, 'b' . Constant::LINKER . Constant::DB_TABLE_COUNTRY, 'b' . Constant::LINKER . Constant::DB_TABLE_OLD_UPDATED_AT, 'b' . Constant::LINKER . Constant::DB_TABLE_IP, 'b' . Constant::LINKER . Constant::DB_TABLE_PROFILE_URL, 'b' . Constant::LINKER . Constant::DB_TABLE_EDIT_AT]);
        $data = static::getList($data, $toArray, $isPage, $select, $isRaw, true);

        $genderData = DictService::getListByType(Constant::DB_TABLE_GENDER, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE);
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => [
                Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                Constant::DB_EXECUTION_PLAN_STOREID => 0,
                Constant::DB_EXECUTION_PLAN_BUILDER => $data,
                'make' => static::getModelAlias(),
                'from' => '',
                Constant::DB_EXECUTION_PLAN_SELECT => $select,
                Constant::DB_EXECUTION_PLAN_WHERE => $where,
                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                    'mtime' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_EDIT_AT,
                        'data' => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                    Constant::DB_TABLE_GENDER => [
                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_GENDER,
                        'data' => $genderData,
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => $genderData[0],
                    ],
                    Constant::DB_TABLE_BRITHDAY => [
                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_BRITHDAY,
                        'data' => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_DATETIME,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => 'Y-m-d',
                        'glue' => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                    'name' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'first_name{connection}last_name',
                        'data' => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_STRING,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => ' ',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                    Constant::DB_TABLE_REGION => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'address_home.region{or}address_home.city',
                        'data' => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                    Constant::DB_TABLE_INTEREST => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'interests.*.interest',
                        'data' => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_STRING,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => ',',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                ],
            //Constant::DB_EXECUTION_PLAN_UNSET => [Constant::DB_TABLE_CUSTOMER_PRIMARY],
            ],
            'with' => [
                Constant::ADDRESS_HOME => [
                    Constant::DB_EXECUTION_PLAN_SETCONNECTION => false,
                    Constant::DB_EXECUTION_PLAN_STOREID => 0,
                    Constant::DB_EXECUTION_PLAN_RELATION => Constant::HAS_ONE,
                    Constant::DB_EXECUTION_PLAN_DEFAULT => [
                        Constant::DB_TABLE_CUSTOMER_PRIMARY => Constant::DB_TABLE_CUSTOMER_PRIMARY,
                    ],
                    Constant::DB_EXECUTION_PLAN_SELECT => [
                        Constant::DB_TABLE_CUSTOMER_PRIMARY,
                        Constant::DB_TABLE_REGION,
                        'city',
                    ],
                    Constant::DB_EXECUTION_PLAN_WHERE => [],
                    Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [],
                    Constant::DB_EXECUTION_PLAN_UNSET => [Constant::ADDRESS_HOME],
                ],
            ],
                //'sqlDebug' => true,
        ];

        $storeId = Arr::get($params, Constant::DB_TABLE_STORE_ID, 0);
        if ($storeId != 1) {
            Arr::set($dbExecutionPlan, 'with.interests', [
                Constant::DB_EXECUTION_PLAN_SETCONNECTION => false,
                Constant::DB_EXECUTION_PLAN_STOREID => 0,
                Constant::DB_EXECUTION_PLAN_RELATION => 'hasMany',
                Constant::DB_EXECUTION_PLAN_SELECT => [Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::DB_TABLE_INTEREST, Constant::DB_TABLE_CREATED_AT],
                Constant::DB_EXECUTION_PLAN_DEFAULT => [],
                Constant::DB_EXECUTION_PLAN_WHERE => [],
                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [],
                Constant::DB_EXECUTION_PLAN_UNSET => [Constant::DB_TABLE_INTERESTS],
            ]);
        }

        if (data_get($params, 'isOnlyGetPrimary', false)) {//如果仅仅获取主键id，就不需要处理数据，不关联
            data_set($dbExecutionPlan, 'parent.handleData', []);
            data_set($dbExecutionPlan, 'with', []);
        }

        $dataStructure = 'list';
        $flatten = false;
        $_data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);

        if ($isGetQuery) {
            return $_data;
        }

        return [
            'data' => $_data,
            Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
        ];
    }

    /**
     * admin编辑会员信息
     * @param int $customerId
     * @param array $requestData
     * @return mixed
     */
    public static function adminEdit($customerId, $requestData) {

        $data = [];
        if (isset($requestData[Constant::DB_TABLE_FIRST_NAME]) && !empty($requestData[Constant::DB_TABLE_FIRST_NAME])) {
            $data[Constant::DB_TABLE_FIRST_NAME] = $requestData[Constant::DB_TABLE_FIRST_NAME];
        }
        if (isset($requestData[Constant::DB_TABLE_LAST_NAME]) && !empty($requestData[Constant::DB_TABLE_LAST_NAME])) {
            $data[Constant::DB_TABLE_LAST_NAME] = $requestData[Constant::DB_TABLE_LAST_NAME];
        }
        if (isset($requestData[Constant::DB_TABLE_COUNTRY]) && !empty($requestData[Constant::DB_TABLE_COUNTRY])) {
            $data[Constant::DB_TABLE_COUNTRY] = $requestData[Constant::DB_TABLE_COUNTRY];
        }
        if (isset($requestData[Constant::DB_TABLE_GENDER]) && !empty($requestData[Constant::DB_TABLE_GENDER])) {
            $data[Constant::DB_TABLE_GENDER] = $requestData[Constant::DB_TABLE_GENDER];
        }
        if (isset($requestData[Constant::DB_TABLE_BRITHDAY]) && !empty($requestData[Constant::DB_TABLE_BRITHDAY])) {
            $data[Constant::DB_TABLE_BRITHDAY] = $requestData[Constant::DB_TABLE_BRITHDAY];
        }

        $res = true;
        $storeId = data_get($requestData, Constant::DB_TABLE_STORE_ID, 0);
        if ($data) {
            $data[Constant::DB_TABLE_EDIT_AT] = Carbon::now()->toDateTimeString();
            if (isset($requestData['bk'])) {
                $data[Constant::DB_TABLE_EDIT_AT] = data_get($requestData, Constant::DB_TABLE_CREATED_AT, $data[Constant::DB_TABLE_EDIT_AT]);
            }

            $res = static::edit($storeId, $customerId, $data, $requestData);
        }

        InterestService::edit($storeId, $customerId, $requestData); //兴趣
        //地址
        if (isset($requestData[Constant::DB_TABLE_REGION]) || isset($requestData['city']) || isset($requestData['street'])) {
            CustomerAddressService::edit($storeId, $customerId, $requestData);
        }

        return $res;
    }

    /**
     * 检查同步频率
     * @param $storeId
     * @param $operator
     */
    public static function checkSyncFrequent($storeId, $operator) {
        $startTime = date('Y-m-d H:i:s', time() - 300);
        $where = [
            'level' => 'info',
            'type' => 'syncCustomer',
            'subtype' => 'store_' . $storeId,
            'keyinfo' => $operator,
            [[Constant::DB_TABLE_CREATED_AT, '>', $startTime]]
        ];
        return LogService::existsSystemLog($storeId, $where);
    }

    /**
     * 注册初始化 如：赠送注册积分和经验
     * @param int $storeId
     * @param int $customerId
     * @return boolean
     */
    public static function regInit($storeId, $customerId, $requestData = []) {

        //赠送注册积分和经验
        $action = Constant::SIGNUP_KEY;
        $type = Constant::SIGNUP_KEY;
        $confKey = Constant::DB_TABLE_CREDIT;
        $expType = Constant::SIGNUP_KEY;
        $expConfKey = Constant::DB_TABLE_EXP;
        $requestData[Constant::DB_TABLE_EXT_ID] = $customerId;
        $requestData[Constant::DB_TABLE_EXT_TYPE] = Constant::CUSTOMER;

        return CreditService::handleVip($storeId, $customerId, $action, $type, $confKey, $requestData, $expType, $expConfKey);
    }

    /**
     * 锁定注册任务
     * @param int $storeId 商城id
     * @param string $account 账号
     * @param array $actionData 操作数据
     * @return mix
     */
    public static function handleRegLimit($storeId, $account, $actionData = []) {
        //防止 高并发下 账号重复注册
        $key = 'handleRegLimit:' . $storeId . ':' . $account;
        $tags = config('cache.tags.customer');

        /*         * *$service = data_get($actionData, 'service', '');** */
        $method = data_get($actionData, 'method', '');
        $parameters = data_get($actionData, 'parameters', []);
        array_unshift($parameters, $key);

        return Cache::tags($tags)->{$method}(...$parameters);
    }

    /**
     * 会员注册
     * @param $params
     * @return int $customerId
     */
    public static function reg($storeId, $account, $storeCustomerId = 0, $createdAt = '', $source = 1, $country = '', $firstName = '', $lastName = '', $gender = 0, $brithday = '', $orderno = '', $lastlogin = '', $ip = '', $data = []) {

        $defaultRs = [
            Constant::CUSTOMER_ID => 0,
            'code' => '',
            Constant::RESPONSE_DATA => Response::getDefaultResponseData(10002),
        ];

        $logParameters = func_get_args();

        $rs = Cache::lock('reg:' . $storeId . ':' . $account)->get(function () use ($storeId, $account, $storeCustomerId, $createdAt, $source, $country, $firstName, $lastName, $gender, $brithday, $orderno, $lastlogin, $ip, $data, $logParameters) {
            // 获取无限期锁并自动释放...

            $customerId = 0; //会员id

            $_data = [
                Constant::CUSTOMER_ID => $customerId,
                'code' => '', //激活码
                Constant::RESPONSE_DATA => [
                    Constant::RESPONSE_CODE_KEY => 10016,
                    Constant::RESPONSE_MSG_KEY => 'reg fail',
                    Constant::RESPONSE_DATA_KEY => [],
                ]
            ];

            //判断账号是否已经注册
            $isExists = static::customerExists($storeId, 0, $account);
            if ($isExists) {
                data_set($_data, Constant::RESPONSE_DATA, [
                    Constant::RESPONSE_CODE_KEY => 10029,
                    Constant::RESPONSE_MSG_KEY => 'customer  exists',
                    Constant::RESPONSE_DATA_KEY => [],
                ]);
                return $_data;
            }

            $ctime = $createdAt ? Carbon::parse($createdAt)->toDateTimeString() : Carbon::now()->toDateTimeString();
            $source = $source ? $source : 5; //注册方式：1自然注册，2常规活动，3大型活动,4非官方页面 5:后台同步
            $code = FunctionHelper::randomStr(6); //激活码
            data_set($data, Constant::DB_TABLE_SOURCE, $source);
            $status = 1;
            DB::beginTransaction();
            try {

                $nowTime = Carbon::now()->toDateTimeString();
                $customerData = [
                    Constant::DB_TABLE_STORE_ID => $storeId,
                    Constant::DB_TABLE_ACCOUNT => $account,
                    Constant::DB_TABLE_STORE_CUSTOMER_ID => $storeCustomerId,
                    Constant::DB_TABLE_STATUS => $status,
                    Constant::DB_TABLE_OLD_CREATED_AT => $ctime,
                    Constant::DB_TABLE_SOURCE => in_array($source, [3201907, 320190719]) ? 3 : $source,
                    Constant::DB_TABLE_LAST_SYS_AT => $nowTime,
                    Constant::DB_TABLE_STATE => data_get($data, Constant::DB_TABLE_STATE, Constant::DB_TABLE_STATE_ENABLED), //账号状态 disabled/invited/enabled/declined
                    Constant::DB_TABLE_ACCEPTS_MARKETING => data_get($data, Constant::DB_TABLE_ACCEPTS_MARKETING, 0), //订阅状态 1:订阅 0:未订阅
                ];

                $password = data_get($data, Constant::DB_TABLE_PASSWORD, '');
                if ($password) {
                    $customerData[Constant::DB_TABLE_PASSWORD] = encrypt($password);
                }

                $platformCreatedAt = data_get($data, Constant::DB_TABLE_PLATFORM_CREATED_AT, '');
                IF ($platformCreatedAt) {
                    data_set($customerData, Constant::DB_TABLE_PLATFORM_CREATED_AT, $platformCreatedAt, false);
                }

                $platformUpdatedAt = data_get($data, Constant::DB_TABLE_PLATFORM_UPDATED_AT, '');
                IF ($platformUpdatedAt) {
                    data_set($customerData, Constant::DB_TABLE_PLATFORM_UPDATED_AT, $platformCreatedAt, false);
                }

                $acceptsMarketingUpdatedAt = data_get($data, Constant::DB_TABLE_ACCEPTS_MARKETING_UPDATED_AT, '');
                IF ($acceptsMarketingUpdatedAt) {
                    data_set($customerData, Constant::DB_TABLE_PLATFORM_ACCEPTS_MARKETING_UPDATED_AT, $acceptsMarketingUpdatedAt, false);
                }

//                if (!in_array($storeId, Constant::RULES_NOT_APPLY_STORE) && in_array($source, Constant::RULES_APPLY_SOURCE)) {//如果是定时任务同步的账号并且不是 holife和ikich，就根据shopify 账号状态 state 设置 status 的值
//                    $status = data_get($data, Constant::DB_TABLE_STATUS, 1);
//                    data_set($customerData, Constant::DB_TABLE_STATUS, $status);
//                    if ($status == 0) {
//                        data_set($customerData, Constant::DB_TABLE_DELETED_AT, data_get($data, Constant::DB_TABLE_OLD_UPDATED_AT, $nowTime));
//                    }
//                }
                data_set($_data, Constant::DB_TABLE_STATUS, $status);

                $customerId = static::getModel($storeId, $country)->insertGetId($customerData);
                if (empty($customerId)) {
                    //DB::rollBack();
                    LogService::addSystemLog('log', Constant::SIGNUP_KEY, 'signup_customer', '注册异常', $_data); //添加系统日志
                    return $_data;
                }

                //添加用户基本资料
                $ip = in_array($source, Constant::RULES_APPLY_SOURCE) ? '' : FunctionHelper::getClientIP($ip);
                $supportVip = DictStoreService::getByTypeAndKey($storeId, Constant::CUSTOMER, 'support_vip', true, true); //会员是否支持等级 1:支持 0:不支持
                $customerInfoData = [
                    Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                    Constant::DB_TABLE_FIRST_NAME => $firstName,
                    Constant::DB_TABLE_LAST_NAME => $lastName,
                    Constant::DB_TABLE_GENDER => $gender ? $gender : 0,
                    Constant::DB_TABLE_COUNTRY => $country ? $country : '',
                    Constant::DB_TABLE_BRITHDAY => $brithday ? $brithday : '',
                    Constant::DB_TABLE_IP => $ip,
                    Constant::DB_TABLE_IS_ORDER => $orderno ? 1 : 0,
                    Constant::DB_TABLE_LASTLOGIN => $lastlogin ? $lastlogin : $ctime,
                    Constant::DB_TABLE_VIP => $supportVip ? 1 : 0,
                    Constant::DB_TABLE_IS_ACTIVATE => 0,
                    'code' => $code, //激活码
                    Constant::DB_TABLE_OLD_UPDATED_AT => $ctime,
                    Constant::DB_TABLE_PROFILE_URL => Arr::get($data, Constant::DB_TABLE_PROFILE_URL, ''),
                    Constant::DB_TABLE_ACCOUNT => $account,
                    Constant::DB_TABLE_EDIT_AT => $ctime,
                    Constant::DB_TABLE_STORE_ID => $storeId,
                    Constant::DB_TABLE_STATUS => $status,
                ];

//                if (!in_array($storeId, Constant::RULES_NOT_APPLY_STORE) && in_array($source, Constant::RULES_APPLY_SOURCE)) {//如果是定时任务同步的账号并且不是 holife和ikich，就根据shopify 账号状态 state 设置 status 的值
//                    if ($status == 0) {
//                        data_set($customerInfoData, Constant::DB_TABLE_DELETED_AT, data_get($data, Constant::DB_TABLE_OLD_UPDATED_AT, $nowTime));
//                    }
//                }
                CustomerInfoService::getModel($storeId, $country)->insert($customerInfoData);
                unset($customerInfoData);

                //记录活动拉新的会员
                $actId = app('request')->input(Constant::DB_TABLE_ACT_ID, 0); //获取有效的活动id ActivityService::getValidActIds($storeId)
                if ($storeId && $actId && $status == 1) {
                    $activityCustomerData = [
                        Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId, //会员id
                        Constant::DB_TABLE_ACT_ID => $actId, //活动id
                        Constant::DB_TABLE_CREATED_AT => $ctime,
                        Constant::DB_TABLE_UPDATED_AT => $ctime,
                    ];
                    ActivityCustomerService::getModel($storeId, '')->insert($activityCustomerData);
                    unset($activityCustomerData);
                }

                DB::commit();
            } catch (\Exception $e) {
                // 出错回滚
                DB::rollBack();

                data_set($logParameters, 'exc', ExceptionHandler::getMessage($e));
                LogService::addSystemLog('log', Constant::SIGNUP_KEY, Constant::SIGNUP_KEY, '注册异常', $logParameters); //添加系统日志
                $customerId = 0;
            }

            data_set($_data, Constant::CUSTOMER_ID, $customerId);
            data_set($_data, 'code', $code);
            data_set($_data, 'status', $status);
            if (empty($customerId)) {
                return $_data;
            }

            try {

                //清空认证缓存
                $tags = config('cache.tags.auth', ['{auth}']);
                $cacheKey = $storeId . ':' . $customerId;
                Cache::tags($tags)->forget($cacheKey);
                Cache::tags($tags)->forget(($storeId . ':' . $account));

                //地址
                if (isset($data['address']) || (isset($data['city']) && isset($data[Constant::DB_TABLE_REGION]))) {
                    CustomerAddressService::edit($storeId, $customerId, $data);
                }

                if ($status == 0) {
                    data_set($_data, Constant::CUSTOMER_ID, 0);
                } else {
                    //分配邀请码
                    $inviteCodeData = [
                        Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                        Constant::DB_TABLE_INVITE_CODE => FunctionHelper::randomStr(8),
                        Constant::DB_TABLE_CREATED_AT => $ctime,
                        Constant::DB_TABLE_UPDATED_AT => $ctime,
                    ];
                    InviteCodeService::getModel($storeId, '')->insert($inviteCodeData);
                }
            } catch (\Exception $exc) {

            }

            data_set($_data, Constant::RESPONSE_DATA, [
                Constant::RESPONSE_CODE_KEY => 1,
                Constant::RESPONSE_MSG_KEY => '',
                Constant::RESPONSE_DATA_KEY => [
                    Constant::CUSTOMER_ID => $customerId
                ],
            ]);

            return $_data;
        });

        return $rs === false ? $defaultRs : $rs;
    }

    /**
     * 判断是否是有效账号
     * @param string $account 账号
     * @return boolean true:有效; false:无效; 默认:true
     */
    public static function isEffectiveAccount($account) {

        if (empty($account)) {//如果账号为空，返回账号无效
            return false;
        }

        $invalidAccount = ['@qq.com', '@163.com', '@patazon.net', '@chacuo.net']; //, '@chacuo.net'
        $isEffectiveAccount = true;
        foreach ($invalidAccount as $value) {
            if (false !== strpos($account, $value)) {
                $isEffectiveAccount = false;
                break;
            }
        }

        return $isEffectiveAccount;
    }

    /**
     * 批量添加会员
     * @param $storeId
     * @param $data
     * @return mixed
     */
    public static function addBatch($storeId, $data) {

        $retData = [
            'success' => [],
            Constant::SUCCESS_COUNT => 0,
            'exists' => [],
            Constant::EXISTS_COUNT => 0,
            'fail' => [],
            Constant::FAIL_COUNT => 0,
        ];
        $storeId += 0;
        //$customerModel = static::getModel($storeId, '');
        foreach ($data as $row) {

            $account = $row[Constant::DB_TABLE_ACCOUNT] ?? '';
            if (empty($account) || !(static::isEffectiveAccount($account))) {//如果 $account 不是有效账号，就不同步到会员系统
                continue;
            }

            $row[Constant::DB_TABLE_STORE_ID] = $storeId;
            $storeCustomerId = $row[Constant::DB_TABLE_STORE_CUSTOMER_ID] ?? '';
            $firstName = $row[Constant::DB_TABLE_FIRST_NAME] ?? '';
            $lastName = $row[Constant::DB_TABLE_LAST_NAME] ?? '';
            $country = $row[Constant::DB_TABLE_COUNTRY] ?? '';
            $gender = $row[Constant::DB_TABLE_GENDER] ?? 0;
            $brithday = $row[Constant::DB_TABLE_BRITHDAY] ?? '';
            $createdAt = $row[Constant::DB_TABLE_OLD_CREATED_AT] ?? '';
            $source = $row[Constant::DB_TABLE_SOURCE] ?? 1;
            $orderno = $row[Constant::DB_TABLE_ORDER_NO] ?? '';
            $lastlogin = $row[Constant::DB_TABLE_LASTLOGIN] ?? '';
            $ip = $row[Constant::DB_TABLE_IP] ?? '';
            $accepts_marketing = data_get($row, Constant::DB_TABLE_ACCEPTS_MARKETING, 0); //是否订阅 1：是  0：否
            $status = $row[Constant::DB_TABLE_STATUS] ?? 0;
            $nowTime = Carbon::now()->toDateTimeString();

            //处理订阅数据
            if (!in_array($storeId, Constant::RULES_NOT_APPLY_STORE)) {//如果不是  holife和ikich, 就执行订阅
                if ($accepts_marketing) {//如果订阅就添加/更新订阅，订阅备注使用  shopify同步
                    SubcribeService::addSubcribe($storeId, $account, '', '', 'shopify同步', '', $row);
                }
//                else {//如果未订阅，就设置订阅为无效
//                    $subcribeWhere = [
//                        Constant::DB_TABLE_EMAIL => $account,
//                        [[Constant::DB_TABLE_OLD_CREATED_AT, '>', Constant::TIME_FRAME_SHOPIFY_CUSTOMER]]
//                    ];
//                    SubcribeService::delete($storeId, $subcribeWhere);
//                }
            }

            $exists = static::customerExists($storeId, 0, $account, 0, true); //获取 $account 对应的有效账号
            if ($exists) {//如果 $account 是有效的账号，就更新账号数据
                $customerId = data_get($exists, Constant::DB_TABLE_CUSTOMER_PRIMARY, -1);
                $platformUpdatedAt = data_get($row, Constant::DB_TABLE_PLATFORM_UPDATED_AT, ''); //Carbon::now()->toDateTimeString(),
                $customerUpdateData = [
                    Constant::DB_TABLE_STORE_CUSTOMER_ID => $storeCustomerId, //平台账号id
                    Constant::DB_TABLE_OLD_CREATED_AT => DB::raw("IF(" . Constant::DB_TABLE_OLD_CREATED_AT . ">'$createdAt', '$createdAt', " . Constant::DB_TABLE_OLD_CREATED_AT . ")"), //账号创建时间 规则：那个小就用那个
                    Constant::DB_TABLE_LAST_SYS_AT => DB::raw("IF(" . Constant::DB_TABLE_LAST_SYS_AT . "<'$platformUpdatedAt', '$platformUpdatedAt', " . Constant::DB_TABLE_LAST_SYS_AT . ")"), //账号最后活跃时间 规则：那个大使用那个
                    Constant::DB_TABLE_STATE => data_get($row, Constant::DB_TABLE_STATE, Constant::DB_TABLE_STATE_ENABLED),
                    Constant::DB_TABLE_ACCEPTS_MARKETING => data_get($row, Constant::DB_TABLE_ACCEPTS_MARKETING, 0),
                    Constant::DB_TABLE_PLATFORM_ACCEPTS_MARKETING_UPDATED_AT => data_get($row, Constant::DB_TABLE_ACCEPTS_MARKETING_UPDATED_AT, ''),
                    Constant::DB_TABLE_PLATFORM_CREATED_AT => data_get($row, Constant::DB_TABLE_PLATFORM_CREATED_AT, ''),
                    Constant::DB_TABLE_PLATFORM_UPDATED_AT => data_get($row, Constant::DB_TABLE_PLATFORM_UPDATED_AT, ''),
                ];
                $where = [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId];
                static::update($storeId, $where, $customerUpdateData);

                $_customerInfo = CustomerInfoService::existsOrFirst($storeId, '', $where, true, [Constant::DB_TABLE_FIRST_NAME, Constant::DB_TABLE_LAST_NAME]); //获取 $account 对应的有效账号
                $replacePairs = [
                    "'" => "\'",
                    "?" => "\?",
                ];
                $firstName = strtr($firstName, $replacePairs);
                $lastName = strtr($lastName, $replacePairs);
                $country = strtr($country, $replacePairs);
                $brithday = strtr($brithday, $replacePairs);

//                $updateData = [
//                    Constant::DB_TABLE_FIRST_NAME => DB::raw("IF(" . Constant::DB_TABLE_FIRST_NAME . "='', '$firstName', " . Constant::DB_TABLE_FIRST_NAME . ")"),
//                    Constant::DB_TABLE_LAST_NAME => DB::raw("IF(" . Constant::DB_TABLE_LAST_NAME . "='', '$lastName', " . Constant::DB_TABLE_LAST_NAME . ")"),
////                    Constant::DB_TABLE_COUNTRY => DB::raw("IF(" . Constant::DB_TABLE_COUNTRY . "='', '$country', " . Constant::DB_TABLE_COUNTRY . ")"),
////                    Constant::DB_TABLE_GENDER => DB::raw("IF(" . Constant::DB_TABLE_GENDER . "=0, $gender, " . Constant::DB_TABLE_GENDER . ")"),
////                    Constant::DB_TABLE_BRITHDAY => DB::raw("IF(" . Constant::DB_TABLE_BRITHDAY . "='','$brithday', " . Constant::DB_TABLE_BRITHDAY . ")"),
//                ];

                $updateData = [];

                if (empty(data_get($_customerInfo, Constant::DB_TABLE_FIRST_NAME))) {
                    $updateData[Constant::DB_TABLE_FIRST_NAME] = $firstName;
                }

                if (empty(data_get($_customerInfo, Constant::DB_TABLE_LAST_NAME))) {
                    $updateData[Constant::DB_TABLE_LAST_NAME] = $lastName;
                }

                if ($updateData) {
                    CustomerInfoService::update($storeId, $where, $updateData);
                }

                //地址
                if ((!in_array($storeId, Constant::RULES_NOT_APPLY_STORE)) && (isset($row[Constant::DB_TABLE_ADDRESS]) || (isset($row[Constant::DB_TABLE_CITY]) && isset($row[Constant::DB_TABLE_REGION])))) {
                    CustomerAddressService::edit($storeId, $customerId, $row);
                }

//                if (
//                        !in_array($storeId, Constant::RULES_NOT_APPLY_STORE) && in_array($source, Constant::RULES_APPLY_SOURCE) && $status == 0 && data_get($exists, Constant::DB_TABLE_OLD_CREATED_AT, '') > Constant::TIME_FRAME_SHOPIFY_CUSTOMER
//                ) {//如果是定时任务同步的账号并且不是 holife和ikich 并且 shopify无效 账号创建时间大于 Constant::TIME_FRAME_SHOPIFY_CUSTOMER  的情况下 设置账号无效
//                    static::deleteCustomerData($storeId, [$account]);
//                }

                $retData[Constant::EXISTS_COUNT] ++;
                $retData['exists'][] = $account;
                continue;
            }

//            if (!in_array($storeId, Constant::RULES_NOT_APPLY_STORE)) {//如果不是  holife和ikich, 就执行账号更新
//                if ($status == 0) {//如果shopify账号状态为无效
//                    $where = [
//                        Constant::DB_TABLE_STORE_ID => $storeId,
//                        Constant::DB_TABLE_ACCOUNT => $account
//                    ];
//                    //$customerModel->enableQueryLog();
//                    $customerData = $customerModel->buildWhere($where)->onlyTrashed()->get();
//                    //dump($customerModel->getQueryLog());
//                    foreach ($customerData as $item) {
//                        $_customerId = data_get($item, Constant::DB_TABLE_CUSTOMER_PRIMARY, -1);
//                        $where = [
//                            Constant::DB_TABLE_CUSTOMER_PRIMARY => $_customerId,
//                        ];
//                        $updateData = [
//                            Constant::DB_TABLE_STORE_CUSTOMER_ID => $storeCustomerId,
//                            Constant::DB_TABLE_OLD_CREATED_AT => DB::raw("IF(" . Constant::DB_TABLE_OLD_CREATED_AT . ">'$createdAt', '$createdAt', " . Constant::DB_TABLE_OLD_CREATED_AT . ")"),
//                            Constant::DB_TABLE_LAST_SYS_AT => Carbon::now()->toDateTimeString(),
//                            Constant::DB_TABLE_DELETED_AT => data_get($row, Constant::DB_TABLE_OLD_UPDATED_AT, $nowTime),
//                            Constant::DB_TABLE_STATE => data_get($row, Constant::DB_TABLE_STATE, Constant::DB_TABLE_STATE_ENABLED),
//                            Constant::DB_TABLE_ACCEPTS_MARKETING => data_get($row, Constant::DB_TABLE_ACCEPTS_MARKETING, 0),
//                            Constant::DB_TABLE_PLATFORM_ACCEPTS_MARKETING_UPDATED_AT => data_get($row, Constant::DB_TABLE_ACCEPTS_MARKETING_UPDATED_AT, ''),
//                            Constant::DB_TABLE_PLATFORM_CREATED_AT => data_get($row, Constant::DB_TABLE_PLATFORM_CREATED_AT, ''),
//                            Constant::DB_TABLE_PLATFORM_UPDATED_AT => data_get($row, Constant::DB_TABLE_PLATFORM_UPDATED_AT, ''),
//                        ];
//                        $customerModel->withTrashed()->buildWhere($where)->update($updateData);
//
//                        $replacePairs = ["'" => "\'"];
//                        $firstName = strtr($firstName, $replacePairs);
//                        $lastName = strtr($lastName, $replacePairs);
//                        $country = strtr($country, $replacePairs);
//                        $brithday = strtr($brithday, $replacePairs);
//                        $updateData = [
//                            Constant::DB_TABLE_FIRST_NAME => DB::raw("IF(" . Constant::DB_TABLE_FIRST_NAME . "='', '$firstName', " . Constant::DB_TABLE_FIRST_NAME . ")"),
//                            Constant::DB_TABLE_LAST_NAME => DB::raw("IF(" . Constant::DB_TABLE_LAST_NAME . "='', '$lastName', " . Constant::DB_TABLE_LAST_NAME . ")"),
//                            Constant::DB_TABLE_COUNTRY => DB::raw("IF(" . Constant::DB_TABLE_COUNTRY . "='', '$country', " . Constant::DB_TABLE_COUNTRY . ")"),
//                            Constant::DB_TABLE_GENDER => DB::raw("IF(" . Constant::DB_TABLE_GENDER . "=0, $gender, " . Constant::DB_TABLE_GENDER . ")"),
//                            Constant::DB_TABLE_BRITHDAY => DB::raw("IF(" . Constant::DB_TABLE_BRITHDAY . "='','$brithday', " . Constant::DB_TABLE_BRITHDAY . ")"),
//                            Constant::DB_TABLE_DELETED_AT => data_get($row, Constant::DB_TABLE_OLD_UPDATED_AT, $nowTime),
//                        ];
//                        CustomerInfoService::getModel($storeId, '')->withTrashed()->where($where)->update($updateData);
//
//                        //地址
//                        if (isset($row[Constant::DB_TABLE_ADDRESS]) || (isset($row[Constant::DB_TABLE_CITY]) && isset($row[Constant::DB_TABLE_REGION]))) {
//                            CustomerAddressService::edit($storeId, $_customerId, $row);
//                        }
//                    }
//
//                    if ($customerData) {//如果会员系统有对应的账号，就更新会员系统的账号数据
//                        continue;
//                    }
//                }
//            }

            $customerId = 0;
            try {

                $customerData = static::reg($storeId, $account, $storeCustomerId, $createdAt, $source, $country, $firstName, $lastName, $gender, $brithday, $orderno, $lastlogin, $ip, $row);
                $customerId = $customerData[Constant::CUSTOMER_ID] ?? 0;
                if ($customerId) {
                    static::regInit($storeId, $customerId);

                    //判断shopify同步是否发送coupon
                    $isConsoleCoupon = DictStoreService::getByTypeAndKey($storeId, Constant::DB_TABLE_EMAIL, 'console_coupon', true);
                    if ($isConsoleCoupon) {//如果 shopify同步要发送coupon，就执行发送coupon
                        //新人订阅 发送新人优惠券
                        $group = Constant::CUSTOMER;
                        $remark = 'shopify同步';
                        $extData = [
                            'actId' => 0,
                            Constant::DB_TABLE_SOURCE => $source,
                            Constant::DB_TABLE_ACTION => Constant::SIGNUP_KEY, //会员行为
                        ];
                        SubcribeService::handle($storeId, $account, $country, $firstName, $lastName, $group, $ip, $remark, $createdAt, $extData);
                    }
                }
            } catch (Exception $exc) {
                $content = [
                    'data' => $row,
                    'exc' => $exc->getTraceAsString(),
                ];
                $subkeyinfo = '';
                $extData = [
                    Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                ];
                LogService::addSystemLog('error', 'customer_sync', $row[Constant::DB_TABLE_ACCOUNT], '同步会员数据异常', $content, $subkeyinfo, $extData);
            }

            if ($customerId) {
                $retData[Constant::SUCCESS_COUNT] ++;
                $retData['success'][] = $account;
            } else {
                $retData[Constant::FAIL_COUNT] ++;
                $retData['fail'][] = $account;
            }
        }

        return Response::getDefaultResponseData(($retData[Constant::FAIL_COUNT] <= 0 ? 1 : 0), ('success: ' . $retData[Constant::SUCCESS_COUNT] . '个 exists: ' . $retData[Constant::EXISTS_COUNT] . '个 fail:' . $retData[Constant::FAIL_COUNT]), $retData);
    }

    /**
     * 同步会员
     * @param int $storeId 商城id
     * @param string $createdAtMin 最小创建时间
     * @param string $createdAtMax 最大创建时间
     * @param array $ids shopify会员id
     * @param string $sinceId shopify会员id
     * @param int $limit 记录条数
     * @param int $source 会员来源
     * @param string $operator 同步人员
     * @param array $extData 扩展数据
     * @return array
     */
    public static function sync($storeId = 1, $createdAtMin = '', $createdAtMax = '', $ids = [], $sinceId = '', $limit = 1000, $source = 5, $operator = '', $extData = []) {
        $parameters = [$storeId, $createdAtMin, $createdAtMax, $ids, $sinceId, $limit, $source, $extData];
        return static::handlePull($storeId, Constant::PLATFORM_SERVICE_SHOPIFY, $parameters);
    }

    /**
     * 获取会员基本资料
     * @param int $storeId
     * @param int $customerId
     * @param string $account
     * @param int $storeCustomerId
     * @return \yii\db\Command
     */
    public static function getCustomerData($storeId = 0, $customerId = 0, $customerSelect = [], $dbExecutionPlan = [], $flatten = false, $isGetQuery = false) {

        $customerSelect = $customerSelect ? $customerSelect : Customer::getColumns();
        $where = [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId];
        if (is_array($customerId)) {
            $where = $customerId;
        }

        if (empty(Arr::exists($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_PARENT))) {
            $parent = [
                Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                Constant::DB_EXECUTION_PLAN_STOREID => 0,
                Constant::DB_EXECUTION_PLAN_BUILDER => null,
                'make' => static::getModelAlias(),
                'from' => '',
                Constant::DB_EXECUTION_PLAN_SELECT => $customerSelect,
                Constant::DB_EXECUTION_PLAN_WHERE => $where,
                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [],
                    //Constant::DB_EXECUTION_PLAN_UNSET => ['store_customer_id'],
            ];
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_PARENT, $parent);
        }

        $dataStructure = 'one';
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    /**
     * 获取会员激活数据
     * @param int $storeId
     * @param int $customerId
     * @return array
     */
    public static function getCustomerActivateData($storeId = 0, $customerId = 0) {

        //获取会员激活状态数据，并保存到缓存中
        $tags = config('cache.tags.customer', ['{customer}']);
        $ttl = config('cache.ttl', 86400); //认证缓存时间 单位秒
        $cacheKey = 'activate:' . $customerId;
        return Cache::tags($tags)->remember($cacheKey, $ttl, function () use ($storeId, $customerId) {
                    $dbExecutionPlan = [
                        'with' => [
                            'info' => [
                                Constant::DB_EXECUTION_PLAN_SETCONNECTION => false,
                                Constant::DB_EXECUTION_PLAN_STOREID => 0,
                                Constant::DB_EXECUTION_PLAN_RELATION => Constant::HAS_ONE,
                                Constant::DB_EXECUTION_PLAN_SELECT => [Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::DB_TABLE_IS_ACTIVATE, 'code', Constant::DB_TABLE_COUNTRY],
                                Constant::DB_EXECUTION_PLAN_WHERE => [],
                                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [],
                            //Constant::DB_EXECUTION_PLAN_UNSET => ['info'],
                            ],
                        ],
                    ];
                    return static::getCustomerData($storeId, $customerId, [Constant::DB_TABLE_CUSTOMER_PRIMARY], $dbExecutionPlan);
                });
    }

    /**
     * 删除账号以及账号相关的数据
     * @param int $storeId 商城id
     * @param array $accountData 账号数据
     * @return boolean
     */
    public static function deleteCustomerData($storeId, $accountData = []) {

        $rs = Response::getDefaultResponseData(1);

        if (empty($accountData)) {
            return $rs;
        }

        if (!is_array($accountData)) {
            $accountData = array_unique(array_filter(explode(',', $accountData)));
            if (empty($accountData)) {
                return $rs;
            }
        }

        $count = count($accountData);
        if ($count > 10) {
            data_set($rs, 'code', 0);
            data_set($rs, 'msg', '删除的账号超过10个，删除失败');
            return $rs;
        }

        $accounts = implode("','", $accountData);
        if (empty($accounts)) {
            return $rs;
        }

        $sql = "select
c.customer_id,c.account,c.store_id,c.store_customer_id,c1.customer_id as invite_customer_id,c1.account as invite_account,c1.store_id as invite_store_id,c1.store_customer_id as invite_store_customer_id
from crm_customer c
left join crm_invite_historys ih on c.customer_id=ih.customer_id and ih.status=1 and ih.store_id={$storeId}
left join crm_customer c1 on c1.customer_id=ih.invite_customer_id and c1.status=1 and c1.store_id={$storeId}
where c.store_id={$storeId} and c.account in ('" . $accounts . "') and c.status=1";

        $data = DB::select($sql);

        $_data = [];
        $_storeData = [];
        $platformData = []; //平台数据
        foreach ($data as $item) {
            if ($item->customer_id) {
                $_data[$item->customer_id] = $item->account;
                $_storeData[$item->store_id][$item->customer_id] = $item->account;
                $platformData[$item->store_id][] = $item->store_customer_id;
            }

            if ($item->invite_customer_id) {
                $_data[$item->invite_customer_id] = $item->invite_account;
                $_storeData[$item->invite_store_id][$item->invite_customer_id] = $item->invite_account;
                $platformData[$item->invite_store_id][] = $item->invite_store_customer_id;
            }
        }

        if ($_data) {
            foreach ($_storeData as $_storeId => $item) {

                //设置时区
                FunctionHelper::setTimezone($storeId);
                $nowTime = Carbon::now()->toDateTimeString();

                $_customerIds = array_keys($item);
                $_accounts = array_values($item);

                $_whereCustomerId = [
                    Constant::DB_TABLE_CUSTOMER_PRIMARY => $_customerIds,
                ];

                $_whereAccount = [
                    Constant::DB_TABLE_ACCOUNT => $_accounts,
                ];

                OrderWarrantyService::delete($_storeId, $_whereCustomerId); //延保订单
                SubcribeService::delete($_storeId, [Constant::DB_TABLE_EMAIL => $_accounts]); //订阅
                EmailService::delete($_storeId, [Constant::DB_TABLE_STORE_ID => $_storeId, 'to_email' => $_accounts]); //邮件流水

                CreditService::delete($_storeId, $_whereCustomerId); //积分流水
                ExpService::delete($_storeId, $_whereCustomerId); //经验流水

                ActivityCustomerService::delete($_storeId, $_whereCustomerId); //活动用户流水

                $voteLogData = VoteLogService::getModel($_storeId, '')->buildWhere($_whereAccount)->select([Constant::DB_TABLE_ACT_ID, Constant::DB_TABLE_VOTE_ITEM_ID])->get();
                foreach ($voteLogData as $voteLogTtem) {
                    $voteWhere = [
                        Constant::DB_TABLE_ACT_ID => data_get($voteLogTtem, Constant::DB_TABLE_ACT_ID, 0),
                        Constant::DB_TABLE_VOTE_ITEM_ID => data_get($voteLogTtem, Constant::DB_TABLE_VOTE_ITEM_ID, 0),
                    ];
                    $voteData = [
                        'score' => DB::raw('score-1'),
                        Constant::DB_TABLE_UPDATED_AT => $nowTime,
                    ];
                    VoteService::getVoteModel($storeId, '')->where($voteWhere)->update($voteData);
                }
                VoteLogService::delete($_storeId, $_whereAccount); //投票流水

                RankService::delete($_storeId, $_whereCustomerId); //排行榜
                RankDayService::delete($_storeId, $_whereCustomerId); //日榜

                ActivityApplyService::delete($_storeId, $_whereCustomerId); //申请众测活动
                ActivityApplyInfoService::delete($_storeId, $_whereCustomerId); //申请众测活动

                $activityWinningData = ActivityWinningService::getModel($_storeId, '')->buildWhere($_whereCustomerId)->select(['prize_id', 'prize_item_id', Constant::DB_TABLE_ACT_ID, 'quantity'])->get();
                foreach ($activityWinningData as $activityWinningTtem) {
                    $activityPrizeItemWhere = [
                        'id' => data_get($activityWinningTtem, 'prize_item_id', 0),
                    ];
                    $activityPrizeUpdata = [
                        'qty_receive' => DB::raw('qty_receive-' . data_get($activityWinningTtem, 'quantity', 0)),
                        Constant::DB_TABLE_UPDATED_AT => $nowTime,
                    ];
                    ActivityPrizeItemService::update($_storeId, $activityPrizeItemWhere, $activityPrizeUpdata);

                    $activityPrizeWhere = [
                        'id' => data_get($activityWinningTtem, 'prize_id', 0),
                    ];
                    ActivityPrizeService::update($_storeId, $activityPrizeWhere, $activityPrizeUpdata);
                }
                ActivityWinningService::delete($_storeId, $_whereCustomerId); //中奖流水
                ActivityShareService::delete($_storeId, $_whereCustomerId); //分享流水

                ActivityAddressService::delete($_storeId, $_whereCustomerId); //活动收件地址表

                OrderReviewService::delete($_storeId, $_whereCustomerId); //用户订单 review
            }

            $customerIds = array_keys($_data);
            $accounts = array_values($_data);

            $whereCustomerId = [
                Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerIds,
            ];

            $whereAccount = [
                Constant::DB_TABLE_ACCOUNT => $accounts,
            ];

            InterestService::delete($storeId, $whereCustomerId); //兴趣

            InviteCodeService::delete($storeId, $whereCustomerId); //邀请码
            InviteService::delete($storeId, $whereCustomerId); //邀请流水

            ShareService::delete($storeId, $whereCustomerId); //分享

            CustomerSyncService::delete($storeId, Arr::collapse([$whereAccount, [Constant::DB_TABLE_STORE_ID => $storeId]])); //同步用户
            CustomerGuideService::delete($storeId, $whereCustomerId); //用户引导

            CustomerAddressService::delete($storeId, $whereCustomerId); //用户地址

            CustomerInfoService::delete($storeId, Arr::collapse([$whereCustomerId, [Constant::DB_TABLE_STORE_ID => $storeId]])); //用户详情
            static::delete($storeId, Arr::collapse([$whereCustomerId, [Constant::DB_TABLE_STORE_ID => $storeId]])); //用户基本信息

            SocialMediaLoginService::delete($storeId, $whereCustomerId); //社媒信息
        }

        //清空缓存
        $headers = [
            'Content-Type: application/json; charset=utf-8', //设置请求内容为 json  这个时候post数据必须是json串 否则请求参数会解析失败
            'X-Requested-With: XMLHttpRequest', //告诉服务器，当前请求是 ajax 请求
        ];

        $curlOptions = [
            CURLOPT_CONNECTTIMEOUT_MS => 1000 * 100,
            CURLOPT_TIMEOUT_MS => 1000 * 100,
        ];
        $url = ('production' == config('app.env', 'production')) ? 'https://brand-api.patozon.net/api/shop/clear' : 'http://127.0.0.1:8006/api/shop/clear';
        \App\Utils\Curl::request($url, $headers, $curlOptions, [], 'GET');

        data_set($rs, 'data', $data);

        return $rs;
    }

    /**
     * 记录物流订单数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 多条用户数据
     * @return bool
     */
    public static function handle($storeId, $platform, $data, $source = 6) {

        $customerData = PlatformServiceManager::handle($platform, 'Customer', 'getCustomerData', [$data, $storeId, $source]);

        return static::addBatch($storeId, $customerData); //订单购买 06-29/30
    }

    /**
     * 拉取订单
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $parameters 拉取平台订单参数
     * @return type
     */
    public static function handlePull($storeId, $platform, $parameters) {
        $customerData = PlatformServiceManager::handle($platform, 'Customer', 'getCustomer', $parameters);

        if (empty($customerData)) {
            unset($customerData);
            return Response::getDefaultResponseData(0, 'data is empty', []);
        }

        $source = data_get($parameters, 6, 6);
        return static::handle($storeId, $platform, $customerData, $source);
    }

    /**
     * 创建平台会员账号
     * @param string $platform 平台
     * @param int $storeId 商城id
     * @param string $account 会员账号
     * @param string $password 会员密码
     * @param string $action 行为：register：注册  order_bind：订单绑定  login：登录
     * @param boolean $acceptsMarketing 是否接收营销邮件
     * @param string $firstName The customer’s first name.
     * @param string $lastName
     * @param string $phone
     * @return array $rs
     */
    public static function createCustomer($platform = Constant::PLATFORM_SERVICE_SHOPIFY, $storeId = 1, $account = '', $password = '', $action = 'login', $acceptsMarketing = true, $firstName = '', $lastName = '', $phone = '', $extData = []) {

        $rs = Response::getDefaultResponseData(1);

        //获取账号的 first_name  last_name  phone
        if (empty($firstName) || empty($lastName) || empty($phone)) {
            $customerWhere = [
                'account' => $account,
                'store_id' => $storeId,
            ];

            $customerData = CustomerInfoService::existsOrFirst($storeId, '', $customerWhere, true, ['first_name', 'last_name', 'phone']);
            if ($customerData) {
                $firstName = $firstName ? $firstName : data_get($customerData, 'first_name', $firstName);
                $lastName = $lastName ? $lastName : data_get($customerData, 'last_name', $lastName);
                $phone = $phone ? $phone : data_get($customerData, 'phone', $phone);
            }
        }

        //判断 $platform 平台 是否存在 $account 账号
        $parameters = [$storeId, $account, $password, $acceptsMarketing, $firstName, $lastName, $phone];
        $data = PlatformServiceManager::handle($platform, 'Customer', 'createCustomer', $parameters);
        $rs[Constant::RESPONSE_DATA_KEY] = $data;
        if (empty($data)) {//注册失败
            switch ($action) {
                case 'register':
                    $rs[Constant::RESPONSE_CODE_KEY] = 10016;
                    $rs[Constant::RESPONSE_MSG_KEY] = 'register failed, please register again later';
                    break;

                default:
                    break;
            }

            return $rs;
        }

        $errors = data_get($data, 'errors', false);
        $messageData = data_get($data, 'data.customerCreate.userErrors.*.message', []);
        switch ($action) {
            case 'register':
                if ($errors !== false) {//如果请求接口被限制或者其他原因导致，接口返回异常，就提示前端直接使用shopify的注册流程
                    $rs[Constant::RESPONSE_CODE_KEY] = 9800000000;
                    $rs[Constant::RESPONSE_MSG_KEY] = implode(', ', data_get($errors, '*.message', []));
                    return $rs;
                }

                if ($messageData) {
                    $message = implode(', ', $messageData);
                    $rs[Constant::RESPONSE_CODE_KEY] = 10001;
                    $rs[Constant::RESPONSE_MSG_KEY] = $message;

                    if (false !== strpos($message, 'Email has already been taken')) {//如果 $platform 平台存在 $account 账号，就提示用户
                        $rs[Constant::RESPONSE_CODE_KEY] = 10029;
                        $rs[Constant::RESPONSE_MSG_KEY] = 'Account already exists.';
                    }

                    $inviteCode = data_get($extData, Constant::DB_TABLE_INVITE_CODE, '');
                    if (strlen($inviteCode) == 10){
                        $rs[Constant::RESPONSE_CODE_KEY] = 10029;
                        $rs[Constant::RESPONSE_MSG_KEY] = 'This user has signed up and cannot be invited.';
                    }

                    return $rs;
                }
                break;

            default:

                if ($errors !== false || $messageData) {//如果导入账号失败，直接使用shopify的登录
                    return $rs;
                }

                break;
        }

        /*         * *******************记录账号同步流水************************** */
        $service = CustomerSyncService::getNamespaceClass();
        $method = 'updateOrCreate'; //记录请求日志 updateOrCreate($storeId, $where, $data, $country = '')
        $where = [
            'account' => $account,
            'platform' => $platform,
            'store_id' => $storeId,
        ];
        $nowTime = Carbon::now()->toDateTimeString();
        $_data = [
            'password' => $password,
            'responseData' => json_encode($rs[Constant::RESPONSE_DATA_KEY], JSON_UNESCAPED_UNICODE),
            'created_at' => $nowTime,
            'updated_at' => $nowTime,
        ];
        $parameters = [$storeId, $where, $_data, ''];

        FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters)); //记录账号同步流水

        return $rs;
    }

    /**
     * 账号编辑
     * @param int $storeId 官网id
     * @param string $account 原账号
     * @param string $newAccount 新账号
     * @param string $platform 平台标识
     * @return array
     */
    public static function editAccount($storeId, $account, $newAccount, $platform) {
        $rs = Response::getDefaultResponseData(1);

        //账号一样，不用修改 || 新账号为空，不用修改
        if ($account == $newAccount || empty($newAccount)) {
            return $rs;
        }

        //获取用户信息
        $customer = CustomerService::existsOrFirst($storeId, '', [Constant::DB_TABLE_STORE_ID => $storeId, Constant::DB_TABLE_ACCOUNT => $account], true);
        $customerId = data_get($customer, Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::PARAMETER_INT_DEFAULT);

        $key = "e_a_{$storeId}_{$customerId}";
        $keyAcc = "e_a_{$storeId}_{$account}";
        if (Redis::exists($key) || Redis::exists($keyAcc)) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 10106);
            data_set($rs, Constant::RESPONSE_MSG_KEY, 'It can only be modified once in 24 hours.');
            return $rs;
        }

        //社媒登陆的账号不允许修改
        $exists = SocialMediaLoginService::existsOrFirst($storeId, '', [Constant::DB_TABLE_STORE_ID => $storeId, Constant::DB_TABLE_ACCOUNT => $account]);
        if ($exists) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 10100);
            data_set($rs, Constant::RESPONSE_MSG_KEY, 'The account number logged in by social media is not allowed to be modified.');
            return $rs;
        }

        //判断要修改的新账号是否存在
        $exists = CustomerService::existsOrFirst($storeId, '', [Constant::DB_TABLE_STORE_ID => $storeId, Constant::DB_TABLE_ACCOUNT => $newAccount], true);
        //新账号在会员系统存在，不能编辑
        if ($exists) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 10101);
            data_set($rs, Constant::RESPONSE_MSG_KEY, 'Email already exists, change another one.');
            return $rs;
        }

        //获取storeCustomerId
        $storeCustomerId = data_get($customer, Constant::DB_TABLE_STORE_CUSTOMER_ID, Constant::PARAMETER_INT_DEFAULT);
        if (empty($storeCustomerId)) {
            //获取storeCustomerId
            $platformCustomer = PlatformServiceManager::handle($platform, 'Customer', 'customerQuery', [$storeId, '', $account]);
            $storeCustomerId = data_get($platformCustomer, "0.store_customer_id", Constant::PARAMETER_INT_DEFAULT);
            //原账号不存在于shopify
            if (empty($storeCustomerId)) {
                data_set($rs, Constant::RESPONSE_CODE_KEY, 10103);
                data_set($rs, Constant::RESPONSE_MSG_KEY, 'System error. Try again later.');
                return $rs;
            }
            //$storeCustomerId更新到customer表
            static::update($storeId, [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId], [Constant::DB_TABLE_STORE_CUSTOMER_ID => $storeCustomerId]);
        }

        //修改shopify账号
        $note = "用户账号修改";
        $data = PlatformServiceManager::handle($platform, 'Customer', 'updateCustomerDetails', [$storeId, $storeCustomerId, $newAccount, '', '', '', $note]);
        //shopify账号修改失败
        $emailErrorMsg = data_get($data, 'responseText.errors.email.0', Constant::PARAMETER_STRING_DEFAULT);
        if ($emailErrorMsg == 'has already been taken') {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 10102);
            data_set($rs, Constant::RESPONSE_MSG_KEY, 'Email already exists, change another one.');
            return $rs;
        } else if (!isset($data['responseText'][Constant::CUSTOMER]) || empty($data['responseText'][Constant::CUSTOMER])) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 10104);
            data_set($rs, Constant::RESPONSE_MSG_KEY, 'System error. Try again later.');
            return $rs;
        }

        //修改会员系统数据
        $modifyRet = static::modifyTableData($storeId, $platform, $customerId, $storeCustomerId, $account, $newAccount);
        //账号数据修改失败
        if (empty($modifyRet)) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 10105);
            data_set($rs, Constant::RESPONSE_MSG_KEY, 'System Error,Please Contact: support@xmpow.com.');
            return $rs;
        }

        //expireTime时间内只能编辑一次
        $expireTime = DictService::getByTypeAndKey('edit_account', 'time', true);
        empty($expireTime) && $expireTime = 24 * 60 * 60;
        Redis::setex($key, $expireTime, '1');
        Redis::setex($keyAcc, $expireTime, '1');

        //删除用户数据缓存
        $tags = config('cache.tags.auth', ['{auth}']);
        $keyCustomerId = $storeId . ':' . $customerId;
        $keyAccount = $storeId . ':' . $account;
        Cache::tags($tags)->forget($keyCustomerId);
        Cache::tags($tags)->forget($keyAccount);

        data_set($rs, Constant::RESPONSE_DATA_KEY, $data);
        return $rs;
    }

    /**
     * 修改会员系统数据
     * @param int $storeId 官网id
     * @param string $platform 平台标识
     * @param int $customerId 会员id
     * @param int $storeCustomerId 平台会员id
     * @param string $account 现账号
     * @param string $newAccount 新账号
     * @return bool
     */
    public static function modifyTableData($storeId, $platform, $customerId, $storeCustomerId, $account, $newAccount) {
        static::getModel($storeId)->getConnection()->beginTransaction();

        try {
            //更新customer表数据
            static::update($storeId, [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId], [Constant::DB_TABLE_ACCOUNT => $newAccount]);

            //更新customerInfo表数据
            CustomerInfoService::update($storeId, [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId], [Constant::DB_TABLE_ACCOUNT => $newAccount]);

            static::getModel($storeId)->getConnection()->commit();
        } catch (\Exception $exc) {

            // 出错回滚
            static::getModel($storeId)->getConnection()->rollBack();

            //数据修改出错，改回原账号
            PlatformServiceManager::handle($platform, 'Customer', 'updateCustomerDetails', [$storeId, $storeCustomerId, $account, '', '', '', '']);

            //添加系统日志
            LogService::addSystemLog('error', 'edit', 'account', '账号编辑失败', ['data' => func_get_args(), 'exception' => $exc->getTraceAsString()]);

            $exceptionName = '用户修改账号失败 : ';
            $messageData = [('store: ' . $storeId . ' customerId:' . $customerId . ' storeCustomerId:' . $storeCustomerId . ' account:' . $account . ' newAccount:' . $newAccount . ' errorMsg::' . $exc->getMessage())];
            $message = implode(',', $messageData);
            $parameters = [$exceptionName, $message, ''];
            MonitorServiceManager::handle('Ali', 'Ding', 'report', $parameters);

            return false;
        }

        //修改其他关联数据
        $service = static::getNamespaceClass();
        $method = '_modifyTableData';
        $parameters = [$storeId, $platform, $customerId, $storeCustomerId, $account, $newAccount];
        FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters));

        //添加系统日志
        LogService::addSystemLog('info', 'edit', 'account', '账号编辑成功', ['data' => func_get_args()]);

        return true;
    }

    /**
     * 修改会员系统数据
     * @param int $storeId 官网id
     * @param string $platform 平台标识
     * @param int $customerId 会员id
     * @param int $storeCustomerId 平台会员id
     * @param string $account 现账号
     * @param string $newAccount 新账号
     */
    public static function _modifyTableData($storeId, $platform, $customerId, $storeCustomerId, $account, $newAccount) {
        //添加系统日志
        LogService::addSystemLog('info', 'edit', 'account', '账号编辑，修改关联数据', ['data' => func_get_args()]);

        static::getModel($storeId)->getConnection()->beginTransaction();

        try {
            //更新crm_customer_syncs表数据
            CustomerSyncService::update($storeId, [Constant::DB_TABLE_ACCOUNT => $account, Constant::DB_TABLE_PLATFORM => Constant::PLATFORM_SERVICE_SHOPIFY, Constant::DB_TABLE_STORE_ID => $storeId], [Constant::DB_TABLE_ACCOUNT => $newAccount]);

            //邮件流水
            EmailService::update($storeId, [Constant::TO_EMAIL => $account, Constant::DB_TABLE_STORE_ID => $storeId], [Constant::TO_EMAIL => $newAccount]);

            //邀请流水
            InviteService::update($storeId, [Constant::DB_TABLE_ACCOUNT => $account, Constant::DB_TABLE_STORE_ID => $storeId], [Constant::DB_TABLE_ACCOUNT => $newAccount]);

            //邀请流水
            InviteService::update($storeId, [Constant::DB_TABLE_INVITE_ACCOUNT => $account, Constant::DB_TABLE_STORE_ID => $storeId], [Constant::DB_TABLE_INVITE_ACCOUNT => $newAccount]);

            //活动地址
            ActivityAddressService::update($storeId, [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId], [Constant::DB_TABLE_ACCOUNT => $newAccount]);

            //活动申请表
            ActivityApplyService::update($storeId, [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId], [Constant::DB_TABLE_ACCOUNT => $newAccount]);

            //邀请解锁表
            ActivityHelpedLogService::update($storeId, [Constant::DB_TABLE_ACCOUNT => $account], [Constant::DB_TABLE_ACCOUNT => $newAccount]);

            //邀请解锁表
            ActivityHelpedLogService::update($storeId, [Constant::HELP_ACCOUNT => $account], [Constant::HELP_ACCOUNT => $newAccount]);

            //活动分享表
            ActivityShareService::update($storeId, [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId], [Constant::DB_TABLE_ACCOUNT => $newAccount]);

            //中奖表
            ActivityWinningService::update($storeId, [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId], [Constant::DB_TABLE_ACCOUNT => $newAccount]);

            //联系表
            ContactUsService::update($storeId, [Constant::DB_TABLE_ACCOUNT => $account], [Constant::DB_TABLE_ACCOUNT => $newAccount]);

            //优惠券表
            CouponService::update($storeId, [Constant::DB_TABLE_ACCOUNT => $account], [Constant::DB_TABLE_ACCOUNT => $newAccount]);

            //订单延保表
            OrderWarrantyService::update($storeId, [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId], [Constant::DB_TABLE_ACCOUNT => $newAccount]);

            //订单索评表
            OrderReviewService::update($storeId, [Constant::DB_TABLE_ACCOUNT => $account], [Constant::DB_TABLE_ACCOUNT => $newAccount]);

            //订阅表
            SubcribeService::update($storeId, [Constant::DB_TABLE_EMAIL => $account], [Constant::DB_TABLE_EMAIL => $newAccount]);

            //投票表
            VoteService::getVoteModel($storeId)->buildWhere([Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId])->update([Constant::DB_TABLE_ACCOUNT => $newAccount]);

            //投票流水表
            VoteLogService::update($storeId, [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId], [Constant::DB_TABLE_ACCOUNT => $newAccount]);

//        //日志表
//        LogService::update($storeId, [Constant::DB_TABLE_STORE_ID => $storeId, Constant::DB_TABLE_ACCOUNT], [Constant::DB_TABLE_ACCOUNT => $newAccount]);
//
//        //response日志表
//        ResponseLogService::update($storeId, [Constant::DB_TABLE_STORE_ID => $storeId, Constant::DB_TABLE_ACCOUNT], [Constant::DB_TABLE_ACCOUNT => $newAccount]);
            static::getModel($storeId)->getConnection()->commit();

            //添加系统日志
            LogService::addSystemLog('info', 'edit', 'account', '账号编辑，修改关联数据成功', ['data' => func_get_args()]);
        } catch (\Exception $exc) {
            // 出错回滚
            static::getModel($storeId)->getConnection()->rollBack();

            $exceptionName = '用户修改账号，关联数据表修改失败 : ';
            $messageData = [('store: ' . $storeId . ' customerId:' . $customerId . ' storeCustomerId:' . $storeCustomerId . ' account:' . $account . ' newAccount:' . $newAccount . ' errorMsg:' . $exc->getMessage())];
            $message = implode(',', $messageData);
            $parameters = [$exceptionName, $message, ''];
            MonitorServiceManager::handle('Ali', 'Ding', 'report', $parameters);

            //添加系统日志
            LogService::addSystemLog('error', 'edit', 'account', '账号编辑，修改关联数据失败', ['data' => func_get_args(), 'exception' => $exc->getTraceAsString()]);
        }
    }

}
