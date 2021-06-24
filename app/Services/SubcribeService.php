<?php

/**
 * 订阅服务
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services;

use Hyperf\DbConnection\Db as DB;
use Carbon\Carbon;
use Hyperf\Utils\Arr;
use App\Utils\Support\Facades\Cache;
use App\Utils\FunctionHelper;
use App\Constants\Constant;
use App\Services\Monitor\MonitorServiceManager;

class SubcribeService extends BaseService {

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'Subscribe';
    }

    /**
     * 检查记录是否存在
     * @param int $storeId
     * @param string $account
     * @param boolean $getData true:获取数据  false:获取是否存在标识
     * @return bool|object|null $rs
     */
    public static function exists($storeId = 0, $account = '', $getData = false) {

        $where = [];
        if ($account) {
            $where['email'] = $account;
        }

        return static::existsOrFirst($storeId, '', $where, $getData);
    }

    /**
     * 添加
     * @param $storeId
     * @param $data
     * @return bool
     */
    public static function insert($storeId, $data) {

        $data['ctime'] = (isset($data['ctime']) && $data['ctime']) ? Carbon::parse($data['ctime'])->toDateTimeString() : Carbon::now()->toDateTimeString();
        $data['ip'] = FunctionHelper::getClientIP(data_get($data, 'ip'));
        $id = static::getModel($storeId)->insertGetId($data);
        if (!$id) {
            return false;
        }

        return $id;
    }

    /**
     * 添加订阅
     * @param int $storeId 商城id
     * @param string $account  邮箱
     * @param string $country  国家简称
     * @param string $ip  ip
     * @param string $remark  备注
     * @param string $createdAt 记录创建时间
     * @param array $extData  扩展数据
     * @return boolean 是否添加成功  false：添加失败  
     */
    public static function addSubcribe($storeId = 0, $account = '', $country = '', $ip = '', $remark = '', $createdAt = '', $extData = []) {

        $defaultRs = [
            'dbOperation' => 'no',
            'data' => [],
        ];
        $tags = config('cache.tags.subcribe', ['{subcribe}']);
        $rs = Cache::tags($tags)->lock('add:' . $storeId . ':' . $account)->get(function () use($defaultRs, $storeId, $account, $country, $ip, $remark, $createdAt, $extData) {

            if (data_get($extData, 'accepts_marketing', 0) != 1) {
                return $defaultRs;
            }

            //添加订阅流水
            $actId = data_get($extData, 'actId', 0);
            $verifiedEmail = data_get($extData, 'verifiedEmail', 0);
            $data = [
                'country' => DB::raw("IF(country!='',country,'" . ($country ? $country : '') . "')"),
                'ip' => DB::raw("IF(ip!='',ip,'" . $ip . "')"),
                'remark' => DB::raw("IF(remark!='',remark,'" . $remark . "')"),
                'act_id' => DB::raw("IF(act_id=-1,$actId,act_id)"), //活动id
                'verified_email' => DB::raw("IF(verified_email=1,verified_email," . $verifiedEmail . ")"),
            ];

            if ($createdAt) {
                data_set($data, 'ctime', $createdAt, false);
            }

            $acceptsMarketing = data_get($extData, Constant::DB_TABLE_ACCEPTS_MARKETING, -1);
            if ($acceptsMarketing != -1) {
                data_set($data, Constant::DB_TABLE_ACCEPTS_MARKETING, $acceptsMarketing, false);
            }

            //shopify订阅时间
            $acceptsMarketingUpdatedAt = data_get($extData, Constant::DB_TABLE_ACCEPTS_MARKETING_UPDATED_AT, '');
            if ($acceptsMarketingUpdatedAt) {
                data_set($data, Constant::DB_TABLE_PLATFORM_UPDATED_AT, $acceptsMarketingUpdatedAt, false);
                if ($remark === 'shopify同步') {//如果 订阅方式 是 shopify同步，订阅时间就使用shopify订阅时间
                    data_set($data, Constant::DB_TABLE_OLD_CREATED_AT, $acceptsMarketingUpdatedAt, false);
                }
            }

            return static::updateOrCreate($storeId, ['email' => $account], $data);
        });

        if ($remark !== 'shopify同步' && empty($country)) {//如果非shopify同步，并且获取国家失败，就发出钉钉预警
            $exceptionName = '订阅国家为空的邮箱如下：';
            $messageData = [('store: ' . $storeId . ' email:' . $account . ' ip:' . $ip)];
            $message = implode(',', $messageData);
            $parameters = [$exceptionName, $message, ''];
            MonitorServiceManager::handle('Ali', 'Ding', 'report', $parameters);
        }

        return $rs === false ? $defaultRs : $rs;
    }

    /**
     * 订阅
     * @param int $storeId 商城id
     * @param string $account 账号
     * @param string $country 国家
     * @param string $firstName firstName
     * @param string $lastName  lastName
     * @param string $group group
     * @param string $ip ip
     * @param string $remark 备注
     * @param string $createdAt 操作时间
     * @param array $extData 扩展数据
     * @return array ['code' => 1, 'msg' => '', 'data' => []]
     */
    public static function handle($storeId = 0, $account = '', $country = '', $firstName = '', $lastName = '', $group = 'subcribe', $ip = '', $remark = '', $createdAt = '', $extData = []) {

        $result = ['code' => 1, 'msg' => '', 'data' => []];

        $actId = data_get($extData, 'actId', 0); //活动id
        $source = data_get($extData, 'source', 0); //会员来源
        $action = data_get($extData, 'action', ''); //会员行为
        //判断是否发送coupon邮件
        $isEmailCoupon = DictStoreService::getByTypeAndKey($storeId, $action, 'coupon', true);
        if ($isEmailCoupon) {
            //发送优惠券邮件
            $requestData = [
                'store_id' => $storeId,
                'customer_id' => '',
                'account' => $account,
                'country' => $country,
                'group' => $group,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'ip' => $ip,
                'remark' => $remark,
                'ctime' => $createdAt,
                'act_id' => $actId,
                'source' => $source,
            ];
            $result = EmailService::sendCouponEmail($storeId, $requestData);
        }

        if (data_get($extData, 'accepts_marketing', 0) != 1) {
            return $result;
        }

        $isSupportSubcribe = DictStoreService::getByTypeAndKey($storeId, 'subcribe', 'support', true);
        if (empty($isSupportSubcribe)) {
            return [
                'code' => '70000',
                'msg' => 'No Support Subcribe',
            ];
        }

        $isExists = static::exists($storeId, $account);
        if ($isExists) {

            if (isset($extData['bk'])) {//修复数据
                $updateData = [];
                if (isset($extData['created_at'])) {
                    data_set($updateData, 'ctime', $extData['created_at']);
                }

                if ($updateData) {
                    $updateWhere = [
                        'email' => $account,
                    ];
                    static::update($storeId, $updateWhere, $updateData);
                }
            }

            return [
                'code' => '70001',
                'msg' => 'this email is subcribed',
            ];
        }

        data_set($extData, 'actId', $actId);
        data_set($extData, 'verifiedEmail', 0);
        $subcribeData = static::addSubcribe($storeId, $account, $country, $ip, $remark, $createdAt, $extData);

        $dbOperation = data_get($subcribeData, 'dbOperation', 'no');
        if ($dbOperation == 'no') {
            return [
                'code' => '70002',
                'msg' => 'subcribed fail',
            ];
        }

        return $result;
    }

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @param array $order 排序控制
     * @return array
     */
    public static function getPublicData($params, $order = []) {

        $where = [];

        $params['act_id'] = $params['act_id'] ?? 0; //活动id
        $params['email'] = $params['email'] ?? ''; //邮箱
        $params['country'] = $params['country'] ?? '';
        $params['start_time'] = $params['start_time'] ?? ''; //开始时间
        $params['end_time'] = $params['end_time'] ?? ''; //结束时间

        if ($params['act_id']) {//活动id
            $where[] = ['act_id', '=', $params['act_id']];
        }

        if ($params['email']) {//邮箱
            $where[] = ['email', '=', $params['email']];
        }

        if ($params['country']) {//国家
            $where[] = ['country', '=', $params['country']];
        }

        if ($params['start_time']) {//开始时间
            $where[] = ['ctime', '>=', $params['start_time']];
        }

        if ($params['end_time']) {//结束时间
            $where[] = ['ctime', '<=', $params['end_time']];
        }

        $order = $order ? $order : ['id', 'asc'];

        $_where = [];

        if (data_get($params, 'id', 0)) {
            $_where['id'] = $params['id'];
        }
        $_where[] = $where;

        return Arr::collapse([parent::getPublicData($params, $order), [
                        'where' => $_where,
        ]]);
    }

    /**
     * 后台订阅列表
     * @param array $params 请求参数
     * @param boolean $toArray 是否转化为数组 true:是 false:否 默认:true
     * @param boolean $isPage  是否分页 true:是 false:否 默认:true
     * @param array $select  查询字段
     * @param boolean $isRaw  是否是原生sql true:是 false:否 默认:false
     * @return array 列表数据
     */
    public static function getItemData($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {

        $storeId = data_get($params, 'store_id', 0);
        $_data = static::getPublicData($params);

        $where = data_get($_data, 'where', []);
        $order = data_get($params, 'orderBy', data_get($_data, 'order', []));
        $pagination = data_get($_data, 'pagination', []);
        $limit = data_get($params, 'limit', data_get($pagination, 'page_size', 50));
        $offset = data_get($params, 'offset', data_get($pagination, 'offset', 0));

        $select = $select ? $select : ['*']; //

        $dbExecutionPlan = [
            'parent' => [
                'setConnection' => true,
                'storeId' => $storeId,
                'builder' => null,
                'make' => static::getModelAlias(),
                'from' => '',
                'joinData' => [
                ],
                'select' => $select,
                'where' => $where,
                'orders' => [
                    $order
                ],
                'offset' => $offset,
                'limit' => $limit,
                'isPage' => $isPage,
                'isOnlyGetCount' => $isOnlyGetCount,
                'pagination' => $pagination,
                'handleData' => [
                ],
            //'unset' => ['customer_id'],
            ],
            'with' => [
            ],
            'itemHandleData' => [
            ],
                //'sqlDebug' => true,
        ];

        if (data_get($params, 'isOnlyGetPrimary', false)) {//如果仅仅获取主键id，就不需要处理数据，不关联
            data_set($dbExecutionPlan, 'parent.handleData', []);
            data_set($dbExecutionPlan, 'with', []);
        }

        $dataStructure = 'list';
        $flatten = false;
        $data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);

        return $data;
    }

}
