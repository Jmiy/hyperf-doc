<?php

/**
 * 会员基本资料服务
 * User: Jmiy
 * Date: 2019-11-13
 * Time: 16:50
 */

namespace App\Services;

use App\Constants\Constant;
use Hyperf\Utils\Arr;
use App\Models\CustomerInfo;
use App\Utils\FunctionHelper;
use App\Services\Traits\GetDefaultConnectionModel;
use Hyperf\DbConnection\Db as DB;

class CustomerInfoService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 检查会员是否存在
     * @author harry
     * @param int $storeId
     * @param int $customerId
     * @param string $account
     * @return bool
     */
    public static function exists($storeId = 0, $customerId = 0, $account = '', $getData = false, $select = null) {
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

        return static::existsOrFirst($storeId, '', $where, $getData, $select);
    }

    /**
     * 获取会员基本资料
     * @param int $storeId
     * @param int $customerId
     * @param string $account
     * @param int $storeCustomerId
     * @return array
     */
    public static function getData($storeId = 0, $customerId = 0, $customerSelect = [], $dbExecutionPlan = [], $flatten = false, $isGetQuery = false) {

        $customerSelect = $customerSelect ? $customerSelect : CustomerInfo::getColumns();
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
            ];
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_PARENT, $parent);
        }

        $dataStructure = 'one';
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @return array
     */
    public static function getPublicData($params, $order = []) {
        $where = [];

        $storeId = $params[Constant::DB_TABLE_STORE_ID] ?? Constant::PARAMETER_STRING_DEFAULT; //官网id
        $country = $params[Constant::DB_TABLE_COUNTRY] ?? Constant::PARAMETER_STRING_DEFAULT; //国家
        $account = $params[Constant::DB_TABLE_ACCOUNT] ?? Constant::PARAMETER_STRING_DEFAULT; //邮箱
        $params['vip'] = $params['vip'] ?? 0;
        $startAt = $params[Constant::DB_TABLE_START_AT] ?? Constant::PARAMETER_STRING_DEFAULT;
        $endAt = $params[Constant::DB_TABLE_END_AT] ?? Constant::PARAMETER_STRING_DEFAULT;
        $gender = Arr::get($params, Constant::DB_TABLE_GENDER, Constant::PARAMETER_STRING_DEFAULT); //性别
        $isHasProfile = Arr::get($params, 'is_has_profile', Constant::PARAMETER_STRING_DEFAULT); //个人资料网址
        $isActivate = Arr::get($params, 'isactivate', ''); //是否激活

        if ($storeId) {
            $where[] = [Constant::DB_TABLE_STORE_ID, '=', intval($params[Constant::DB_TABLE_STORE_ID])];
        }

        if ($account) {
            $where[] = [Constant::DB_TABLE_ACCOUNT, '=', $params[Constant::DB_TABLE_ACCOUNT]];
        }

        if ($country) {
            $where[] = [Constant::DB_TABLE_COUNTRY, '=', $params[Constant::DB_TABLE_COUNTRY]];
        }

        if ($params['vip']) {
            $where[] = ['vip', '=', $params['vip']];
        }

        if ($startAt) {
            $where[] = [Constant::DB_TABLE_EDIT_AT, '>=', $startAt];
        }

        if ($endAt) {
            $where[] = [Constant::DB_TABLE_EDIT_AT, '<=', $endAt];
        }

        if ($gender !== '') {
            $where[] = [Constant::DB_TABLE_GENDER, '=', $gender];
        }

        if ($isHasProfile !== '') {
            switch ($isHasProfile) {
                case 0:
                    $where[] = [Constant::DB_TABLE_PROFILE_URL, '=', ''];

                    break;

                case 1:
                    $where[] = [Constant::DB_TABLE_PROFILE_URL, '!=', ''];

                    break;

                default:
                    break;
            }
        }

        if ($isActivate !== '') {
            $where[] = ['isactivate', '=', $isActivate];
        }

        $order = $order ? $order : [Constant::DB_TABLE_EDIT_AT, 'desc'];
        $_where = [];

        if (data_get($params, Constant::DB_TABLE_CUSTOMER_PRIMARY, 0)) {
            $_where[Constant::DB_TABLE_CUSTOMER_PRIMARY] = $params[Constant::DB_TABLE_CUSTOMER_PRIMARY];
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
     * @param boolean $isPage  是否分页 true:是 false:否 默认:true
     * @param array $select  查询字段
     * @param boolean $isRaw 是否原始 select true:是 false:否 默认:false
     * @param boolean $isGetQuery 是否获取 query
     * @param boolean $isOnlyGetCount 是否仅仅获取总记录数
     * @return array|\Hyperf\Database\Model\Builder 列表数据|Builder
     */
    public static function getDetailsListNew($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {

        $_data = CustomerInfoService::getPublicData($params, [Constant::DB_TABLE_EDIT_AT, Constant::ORDER_DESC]);

        $where = $_data[Constant::DB_EXECUTION_PLAN_WHERE];
        $order = data_get($params, 'orderBy', data_get($_data, 'order', []));
        $pagination = $_data[Constant::DB_EXECUTION_PLAN_PAGINATION];
        $limit = $pagination[Constant::REQUEST_PAGE_SIZE];

        $country = '';
        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, 0); //商城id
        $query = CustomerInfoService::getModel($storeId, $country)->from('customer_info as ci')->buildWhere($where);

        //兴趣查询
        $interests = isset($params[Constant::DB_TABLE_INTERESTS]) && $params[Constant::DB_TABLE_INTERESTS] ? $params[Constant::DB_TABLE_INTERESTS] : [];
        if ($interests) {
            $query = $query->whereExists(function ($query) use($interests) {
                $query->select(DB::raw(1))
                        ->from('interests as i')
                        ->whereColumn('i.customer_id', '=', 'ci.customer_id')
                        ->where(function ($query) use($interests) {
                            foreach ($interests as $interest) {
                                $query->orWhere('i.interest', $interest);
                            }
                        })
                ;
            });
        }

        $customerCount = true;

        if ($isPage || $isOnlyGetCount) {

            $customerCount = static::adminCount($params, $query);
            $pagination[Constant::TOTAL] = $customerCount;
            $pagination[Constant::TOTAL_PAGE] = ceil($customerCount / $limit);

            if ($isOnlyGetCount) {
                return $pagination;
            }
        }

        if (empty($customerCount)) {
            $query = null;
            return [
                Constant::RESPONSE_DATA_KEY => [],
                Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
            ];
        }

        if ($order) {
            $query = $query->orderBy($order[0], $order[1]);
        }

        $data = [
            Constant::QUERY => $query,
            Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
        ];

        $select = $select ? $select : ['ci.' . Constant::DB_TABLE_ACCOUNT, 'ci.' . Constant::DB_TABLE_FIRST_NAME, 'ci.' . Constant::DB_TABLE_LAST_NAME, 'ci.' . Constant::DB_TABLE_GENDER, 'ci.' . Constant::DB_TABLE_BRITHDAY,
            'ci.' . Constant::DB_TABLE_COUNTRY, 'ci.' . 'mtime', 'ci.' . Constant::DB_TABLE_IP, 'ci.' . Constant::DB_TABLE_PROFILE_URL, 'ci.' . Constant::DB_TABLE_EDIT_AT, 'ci.' . Constant::DB_TABLE_CUSTOMER_PRIMARY];
        $data = static::getList($data, $toArray, $isPage, $select, $isRaw, true);

        $genderData = DictService::getListByType(Constant::DB_TABLE_GENDER, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE);
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => [
                Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                Constant::DB_EXECUTION_PLAN_STOREID => 0,
                Constant::DB_EXECUTION_PLAN_BUILDER => $data,
                'make' => CustomerInfoService::getModelAlias(),
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
                    'brithday' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'brithday',
                        'data' => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => 'datetime',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => 'Y-m-d',
                        'glue' => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                    'name' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'first_name{connection}last_name',
                        'data' => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => 'string',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => ' ',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                    'region' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'address_home.region{or}address_home.city',
                        'data' => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                    'interest' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'interests.*.interest',
                        'data' => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => 'string',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => ',',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                ],
            ],
            'with' => [
                'address_home' => [
                    Constant::DB_EXECUTION_PLAN_SETCONNECTION => false,
                    Constant::DB_EXECUTION_PLAN_STOREID => 0,
                    'relation' => 'hasOne',
                    Constant::DB_EXECUTION_PLAN_DEFAULT => [
                        Constant::DB_TABLE_CUSTOMER_PRIMARY => Constant::DB_TABLE_CUSTOMER_PRIMARY,
                    ],
                    Constant::DB_EXECUTION_PLAN_SELECT => [
                        Constant::DB_TABLE_CUSTOMER_PRIMARY,
                        'region',
                        'city',
                    ],
                    Constant::DB_EXECUTION_PLAN_WHERE => [],
                    Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [],
                    'unset' => ['address_home'],
                ],
            ],
                //'sqlDebug' => true,
        ];

        $storeId = Arr::get($params, 'store_id', 0);
        if ($storeId != 1) {
            Arr::set($dbExecutionPlan, 'with.interests', [
                Constant::DB_EXECUTION_PLAN_SETCONNECTION => false,
                Constant::DB_EXECUTION_PLAN_STOREID => 0,
                'relation' => 'hasMany',
                Constant::DB_EXECUTION_PLAN_SELECT => [Constant::DB_TABLE_CUSTOMER_PRIMARY, 'interest', 'created_at'],
                Constant::DB_EXECUTION_PLAN_DEFAULT => [],
                Constant::DB_EXECUTION_PLAN_WHERE => [],
                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [],
                'unset' => [Constant::DB_TABLE_INTERESTS],
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
            Constant::RESPONSE_DATA_KEY => $_data,
            Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
        ];
    }

    /**
     * 更新用户最近活跃时间
     * @param array $data 请求数据
     * @return bool
     */
    public static function updateLastlogin($data = [])
    {

        $apiUrl = data_get($data, 'apiUrl', ''); //接口地址
        $storeId = data_get($data, 'storeId', 0); //商城id
        $account = data_get($data, 'account', ''); //会员账号
        $createdAt = data_get($data, 'createdAt', ''); //访问时间

        if (false === stripos($apiUrl, '/api/shop/')) {//如果不是会员行为，就直接返回
            return false;
        }

        if (empty($account)) {//如果账号未空，就直接返回
            return false;
        }

        //判断账号是否存在
        $customer = CustomerService::customerExists($storeId, 0, $account, 0, true, [Constant::DB_TABLE_CUSTOMER_PRIMARY]);
        $customerId = data_get($customer, Constant::DB_TABLE_CUSTOMER_PRIMARY);
        if (empty($customerId)) {//如果账号不存在，就直接返回
            return false;
        }

        //更新会员的lastlogin
        $createdAt = $createdAt ? $createdAt : Carbon::now()->toDateTimeString();

        return static::update($storeId, [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId], ['lastlogin' => $createdAt]);
    }

}
