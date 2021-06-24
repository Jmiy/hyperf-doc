<?php

/**
 * 邀请流水服务
 * User: Jmiy
 * Date: 2019-12-17
 * Time: 14:17
 */

namespace App\Services;

use App\Services\Platform\OrderService;
use Hyperf\DbConnection\Db as DB;
use Hyperf\Utils\Arr;
use App\Utils\Support\Facades\Cache;
use App\Services\Traits\GetDefaultConnectionModel;
use App\Constants\Constant;
use App\Utils\FunctionHelper;
use Carbon\Carbon;

class InviteService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 检查是否存在
     * @param int $customerId 会员id
     * @param string $inviteCode 邀请码
     * @param boolean $getData 是否获取数据 true:是 false:否 默认:false
     * @return boolean|array
     */
    public static function exists($customerId = 0, $inviteCode = '', $getData = false) {
        return InviteCodeService::exists($customerId, $inviteCode, $getData);
    }

    /**
     * 获取邀请cache
     * @param int $storeId 商店id
     * @param int $customerId 会员id
     * @param int $actId 活动id
     * @param mix $keyType key类型
     * @return array cache
     */
    public static function getInviteStatisticsCache($storeId = 0, $customerId = 0, $actId = 0, $keyType = null) {
        $where = [
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::DB_TABLE_ACT_ID => $actId,
        ];
        $key = $storeId . ':' . md5(json_encode($where)) . ($keyType !== null ? (':' . $keyType) : '');
        $tags = config('cache.tags.inviteStatistics');

        return [
            Constant::RESPONSE_CACHE => Cache::tags($tags),
            'key' => $key,
        ];
    }

    /**
     * 初始化邀请统计
     * @param int $storeId 商店id
     * @param int $customerId 会员id
     * @param int $actId 活动id
     * @param mix $keyType key类型
     * @return array cache
     */
    public static function initInviteStatistics($storeId = 0, $customerId = 0, $actId = 0, $keyType = null) {

        if (!($actId && in_array($storeId, [1, 8]))) {
            return [];
        }

        $inviteStatisticsCache = static::getInviteStatisticsCache($storeId, $customerId, $actId, $keyType);
        $cache = $inviteStatisticsCache[Constant::RESPONSE_CACHE];
        $key = $inviteStatisticsCache['key'];

        $isHas = $cache->has($key);
        if (!$isHas) {
            $inviteHistoryWhere = [
                Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                Constant::DB_TABLE_STORE_ID => $storeId,
                Constant::DB_TABLE_ACT_ID => $actId,
            ];

            $ttl = config('auth.auth_ttl', 86400); //认证缓存时间 单位秒
            if ($keyType == 'day') {
                $ttl = (Carbon::parse(Carbon::now()->rawFormat('Y-m-d 23:59:59'))->timestamp) - (Carbon::now()->timestamp); //缓存时间 单位秒
                $nowTime = Carbon::now()->rawFormat('Y-m-d 00:00:00');
                $inviteHistoryWhere[] = [
                    [Constant::DB_TABLE_CREATED_AT, '>=', $nowTime]
                ];
            }

            $count = InviteService::getModel($storeId)->buildWhere($inviteHistoryWhere)->count();
            $cache->put($key, $count, $ttl); //缓存24小时
        } else {
            $count = $cache->get($key);
        }

        $inviteStatisticsCache[Constant::RESPONSE_COUNT] = $count;
        $inviteStatisticsCache['isHas'] = $isHas;

        return $inviteStatisticsCache;
    }

    /**
     * 清空统计缓存
     * @param int $storeId 商店id
     * @param int $customerId 会员id
     * @param int $actId 活动id
     * @return boolean
     */
    public static function delInviteStatisticsCache($storeId = 0, $customerId = 0, $actId = 0, $keyType = null) {

        $inviteStatisticsCache = static::getInviteStatisticsCache($storeId, $customerId, $actId, $keyType);
        $cache = $inviteStatisticsCache[Constant::RESPONSE_CACHE];
        $key = $inviteStatisticsCache['key'];

        if (empty($storeId) && empty($customerId) && empty($actId)) {
            $cache->flush();
        } else {
            $cache->forget($key);
        }

        return true;
    }

    /**
     * 添加邀请流水
     * @param int $storeId 商店id
     * @param int $actId   活动id
     * @param string $account 邀请者账号
     * @param string $inviteAccount 被邀请者账号
     * @param int $customerId 邀请者id
     * @param int $inviteCustomerId 被邀请者id
     * @param int $verifiedEmail 邮箱是否验证 1：验证 0：未验证
     * @param string $createdAt 创建时间
     * @param string $updatedAt 更新时间
     * @param string $inviteCode 邀请码
     * @param array $extData 扩展数据
     * @return array 添加结果
     */
    public static function addInviteLogs($storeId = 0, $actId = 0, $account = '', $inviteAccount = '', $customerId = 0, $inviteCustomerId = 0, $verifiedEmail = 0, $createdAt = '', $updatedAt = '', $inviteCode = '', $extData = []) {

        $tags = config('cache.tags.invite', ['{invite}']);

        $defaultRs = [
            'dbOperation' => 'no',
            'data' => [],
        ];
        $rs = Cache::tags($tags)->lock('add:' . $storeId . ':' . $account . ':' . $inviteAccount)->get(function () use($storeId, $actId, $account, $inviteAccount, $customerId, $inviteCustomerId, $verifiedEmail, $createdAt, $updatedAt, $inviteCode, $extData) {
            $socialMedia = data_get($extData, 'social_media', '');
            $data = [
                Constant::DB_TABLE_CUSTOMER_PRIMARY => DB::raw("IF(customer_id!=0,customer_id," . $customerId . ")"), //邀请者id
                'invite_customer_id' => DB::raw("IF(invite_customer_id!=0,invite_customer_id," . $inviteCustomerId . ")"), //被邀请者id
                Constant::DB_TABLE_ACT_ID => DB::raw("IF(act_id=-1,$actId,act_id)"), //活动id
                'verified_invite_email' => DB::raw("IF(verified_invite_email=1,verified_invite_email," . $verifiedEmail . ")"),
                Constant::DB_TABLE_INVITE_CODE => DB::raw("IF(invite_code='','$inviteCode',invite_code)"), //邀请码
                'social_media' => DB::raw("IF(social_media='','$socialMedia',social_media)"), //社媒平台 FB TW copy_link
            ];

            if ($createdAt) {
                data_set($data, Constant::DB_TABLE_CREATED_AT, $createdAt);
            }

            if ($updatedAt) {
                data_set($data, 'updated_at', $updatedAt);
            }

            $where = [
                Constant::DB_TABLE_STORE_ID => $storeId, //商城id
                Constant::DB_TABLE_ACCOUNT => $account, //邀请者账号
                'invite_account' => $inviteAccount, //被邀请者账号
            ];

            //10位邀请码，holife_邀请注册功能

            if (strlen($inviteCode) == 10 && in_array($storeId, [3])) {

                $data[Constant::DB_TABLE_INVITE_COMMISSION] = DictStoreService::getByTypeAndKey($storeId, 'invite_register', 'commission', true);
                $data[Constant::DB_TABLE_ACT_ID] = DictStoreService::getByTypeAndKey($storeId, 'invite_register', 'act_id', true);

                data_set($data, Constant::DB_TABLE_ACCOUNT, $account);

                $where = [
                    Constant::DB_TABLE_STORE_ID => $storeId, //商城id
                    Constant::DB_TABLE_INVITE_CODE => $inviteCode, //邀请者邀请码
                    'invite_account' => $inviteAccount, //被邀请者账号
                ];
            }

            return static::updateOrCreate($storeId, $where, $data);
        });

        return $rs === false ? $defaultRs : $rs;
    }

    /**
     * 获取邀请码
     * @param int $storeId 商城id
     * @param string $inviteCode 邀请码
     * @return string $inviteCode 邀请码
     */
    public static function getInviteCode($storeId, $inviteCode = '', $actId = 0) {

        if (!(in_array($storeId, [8]) && $actId == 4)) {//如果不是  ilitom并且2020复活节  就直接返回
            return $inviteCode;
        }

        if (empty($inviteCode) || strripos($inviteCode, '?') === false) {//如果邀请码为空  或者  不包含 ? 就直接返回
            return $inviteCode;
        }

        if (strripos($inviteCode, '=') !== false) {//KnUh0d2j?invite_code=KnUh0d2j?invite_code=3cQVpRpi?invite_code=3cQVpRpi?invite_code=pdaTSY9Z
            $index = strripos($inviteCode, '=') + 1;
            $inviteCode = substr($inviteCode, $index);

            if (strripos($inviteCode, '?') !== false) {//KnUh0d2j?invite_code=KnUh0d2j?invite_code=3cQVpRpi?invite_code=3cQVpRpi?invite_code=3cQVpRpi?invite_code
                $inviteCodeData = explode('?', $inviteCode);
                $inviteCode = data_get($inviteCodeData, 0, null);
            }
        } else if (strripos($inviteCode, '?') !== false) {//KnUh0d2j?invite_code
            $inviteCodeData = explode('?', $inviteCode);
            $inviteCode = data_get($inviteCodeData, 0, null);
        }

        return $inviteCode;
    }

    /**
     * 处理邀请
     * @param string $inviteCode 邀请码
     * @param int $inviteCustomerId 被邀请的会员id
     * @param int $storeId 商城id
     * @param string $createdAt 创建时间
     * @param string $updatedAt 更新时间
     * @param int $actId 活动id
     * @param array $extData 扩展数据
     * @return boolean
     */
    public static function handle($inviteCode = '', $inviteCustomerId = 0, $storeId = 0, $createdAt = '', $updatedAt = '', $actId = 0, $extData = []) {

        if (empty($inviteCode) || empty($inviteCustomerId) || empty($storeId)) {
            return false;
        }

        $inviteCode = static::getInviteCode($storeId, $inviteCode, $actId); //获取邀请码

        $_customer_id = InviteCodeService::getModel($storeId, '')->buildWhere([Constant::DB_TABLE_INVITE_CODE => $inviteCode])->limit(1)->value(Constant::DB_TABLE_CUSTOMER_PRIMARY); //获取拥有 $invite_code 的客户id
        if (empty($_customer_id)) {
            return false;
        }

        $accountData = CustomerService::customerExists($storeId, $_customer_id, '', 0, true); //邀请者数据
        $inviteAccountData = CustomerService::customerExists($storeId, $inviteCustomerId, '', 0, true); //被邀请者数据
        $account = data_get($accountData, Constant::DB_TABLE_ACCOUNT, ''); //邀请者账号
        $inviteAccount = data_get($inviteAccountData, Constant::DB_TABLE_ACCOUNT, ''); //被邀请者账号
        if (empty($account) || empty($inviteAccount)) {//如果邀请者账号或者被邀请者账号不存在，就直接返回 false
            return false;
        }

        //添加邀请流水
        $inviteData = static::addInviteLogs($storeId, $actId, $account, $inviteAccount, $_customer_id, $inviteCustomerId, 0, $createdAt, $updatedAt, $inviteCode, $extData);
        $dbOperation = data_get($inviteData, 'dbOperation', 'no');
        if ($dbOperation == 'no') {
            return false;
        }

        if ($dbOperation != 'insert') {//如果已经有邀请流水，就直接返回
            return true;
        }

        $ext_id = data_get($inviteData, (Constant::RESPONSE_DATA_KEY . '.' . Constant::DB_TABLE_PRIMARY), Constant::PARAMETER_INT_DEFAULT);
        if (empty($ext_id)) {
            return false;
        }

        //邀请用户成功注册，游戏次数更新
        $actId && GameService::updatePlayNums($storeId, $actId, $_customer_id, 'add_nums', 'invite', $extData);

        //更新邀请汇总数据
        $inviteStatisticsCache = [];
        if ($actId && in_array($storeId, [1, 8])) {

            $keyType = null;
            if ($storeId == 8) {
                $keyType = 'day';
            }

            $inviteStatisticsCache = static::initInviteStatistics($storeId, $_customer_id, $actId, $keyType);
            if ($inviteStatisticsCache) {
                $cache = $inviteStatisticsCache[Constant::RESPONSE_CACHE];
                $key = $inviteStatisticsCache['key'];
                if ($inviteStatisticsCache['isHas']) {
                    $cache->increment($key);
                    data_set($inviteStatisticsCache, Constant::RESPONSE_COUNT, (data_get($inviteStatisticsCache, Constant::RESPONSE_COUNT, 0) + 1));
                }
            }
        }

        /*         * *********更新邀请汇总数据************ */

        //添加邀请者排行榜积分
        $activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, ['rank', 'invite', 'register'], ['rank_type', 'rank_score', 'day_rank_quantity']);
        $rankTypeData = data_get($activityConfigData, 'rank_rank_type.value', Constant::PARAMETER_INT_DEFAULT);
        if ($rankTypeData) {
            $rankTypeData = explode(',', $rankTypeData);

            $isAddInviteRankScore = true; //是否添加邀请者的排行榜积分 true:是 false:否
            $dayRankQuantity = data_get($activityConfigData, 'invite_day_rank_quantity.value', null); //获取每天算入邀请排行榜的人数
            if ($dayRankQuantity !== null) {
                $dayInviteQuantity = data_get($inviteStatisticsCache, Constant::RESPONSE_COUNT, 0); //获取 $_customer_id 对应用户 每天邀请的人数
                if ($dayInviteQuantity > $dayRankQuantity) {//如果 $_customer_id 对应用户每天邀请的人数 大于 每天算入邀请排行榜的人数，就不给邀请者添加排行榜积分
                    $isAddInviteRankScore = false;
                }
            }

            if ($isAddInviteRankScore) {//如果可以添加邀请者的排行榜积分，就执行添加操作
                //添加邀请者的排行榜积分
                $score = data_get($activityConfigData, 'invite_rank_score.value', Constant::PARAMETER_INT_DEFAULT);
                RankService::handle($storeId, $_customer_id, $actId, $rankTypeData, 2, $score); // 榜单类型 1:分享 2:邀请
            }

            //添加被邀请者的排行榜积分
            $score = data_get($activityConfigData, 'register_rank_score.value', Constant::PARAMETER_INT_DEFAULT);
            RankService::handle($storeId, $inviteCustomerId, $actId, $rankTypeData, 2, $score); // 榜单类型 1:分享 2:邀请
        }

        return [
            Constant::DB_TABLE_EXT_ID => $ext_id,
            Constant::DB_TABLE_EXT_TYPE => 'invite_historys',
            'inviteData' => $inviteData,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $_customer_id, //邀请者id
            'inviteCustomerId' => $inviteCustomerId, //被邀请者id
            Constant::DB_TABLE_ACT_ID => $actId, //活动id
        ];
    }

    /**
     * 获取邀会员邀请码数据
     * @param int $customerId 会员id
     * @param array $requestData 请求参数
     * @return 会员邀请码数据
     */
    public static function getInviteCodeData($customerId, $requestData) {
        return InviteCodeService::getInviteCodeData($customerId, $requestData);
    }

    /**
     * 通过邀请码获取邀请者数据
     * @param int $inviteCode 邀请码
     * @return array|obj 邀请者数据
     */
    public static function getCustomerData($inviteCode) {
        return InviteCodeService::getCustomerData($inviteCode);
    }

    /**
     * 获取db query
     * @param array $where
     * @return \Illuminate\Database\Query\Builder|static $query
     */
    public static function getQuery($storeId, $select, $where = [], $order = [], $limit = null, $offset = null, $isPage = true, $pagination = [], $isOnlyGetCount = false) {

        $field = Constant::DB_TABLE_FIRST_NAME . '{connection}' . Constant::DB_TABLE_LAST_NAME;
        $data = Constant::PARAMETER_ARRAY_DEFAULT;
        $dataType = Constant::DB_EXECUTION_PLAN_DATATYPE_STRING;
        $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
        $time = Constant::PARAMETER_STRING_DEFAULT;
        $glue = ' ';
        $isAllowEmpty = true;
        $default = Constant::PARAMETER_STRING_DEFAULT;
        $callback = Constant::PARAMETER_ARRAY_DEFAULT;
        $only = Constant::PARAMETER_ARRAY_DEFAULT;

        $dbConf = DictStoreService::getByTypeAndKey($storeId, 'db', 'database');
        $dbName = data_get($dbConf, 'conf_value');

        $parameters = [$field, $default, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only];
        $handleData = [
            'use_name' => FunctionHelper::getExePlanHandleData(...$parameters),
        ];
        $joinData = [
            FunctionHelper::getExePlanJoinData('customer as c', function ($join) use($storeId) {
                        $join->on([['c.' . Constant::DB_TABLE_ACCOUNT, '=', 'ih.' . Constant::DB_TABLE_INVITE_ACCOUNT]])
                                ->where('c.' . Constant::DB_TABLE_STORE_ID, '=', $storeId)
                                ->where('c.' . Constant::DB_TABLE_STATUS, '=', 1)
                        ;
                    }),
            FunctionHelper::getExePlanJoinData('customer_info as b', function ($join) {//
                        $join->on([['b.' . Constant::DB_TABLE_CUSTOMER_PRIMARY, '=', 'c.' . Constant::DB_TABLE_CUSTOMER_PRIMARY]]);
                    }),
            FunctionHelper::getExePlanJoinData('dict as d', function ($join) {
                        $join->on([['d.dict_key', '=', 'c.' . Constant::DB_TABLE_SOURCE]])
                                ->where('d.' . Constant::DB_TABLE_TYPE, '=', 'source');
                    }),
            FunctionHelper::getExePlanJoinData(DB::raw("`$dbName`.crm_activities"), function ($join) {
                $join->on([['activities.id', '=', 'ih.' . Constant::DB_TABLE_ACT_ID]]);
            }),
        ];
        $with = [];
        $unset = [
            Constant::DB_TABLE_FIRST_NAME,
            Constant::DB_TABLE_LAST_NAME,
        ];
        $exePlan = FunctionHelper::getExePlan('default_connection_' . $storeId, null, static::getModelAlias(), 'invite_historys as ih', $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, $with, $handleData, $unset);
        return [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
        ];
    }

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @return array
     */
    public static function getPublicData($params, $order = []) {

        $where = [];
        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, Constant::PARAMETER_INT_DEFAULT);
        $country = data_get($params, Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT); //被邀请者国家
        $startAt = data_get($params, Constant::DB_TABLE_START_AT, Constant::PARAMETER_STRING_DEFAULT); //被邀请者注册时间
        $endAt = data_get($params, Constant::DB_TABLE_END_AT, Constant::PARAMETER_STRING_DEFAULT); //被邀请者注册时间
        $source = data_get($params, Constant::DB_TABLE_SOURCE, Constant::PARAMETER_STRING_DEFAULT); //被邀请者来源
        $inviteCode = data_get($params, Constant::DB_TABLE_INVITE_CODE, Constant::PARAMETER_STRING_DEFAULT); //邀请者邀请码
        $actName = data_get($params, 'act_name', Constant::PARAMETER_STRING_DEFAULT); //活动名称

        if ($storeId) {//商店id
            $where[] = ['ih.' . Constant::DB_TABLE_STORE_ID, '=', $storeId];
        }

        $account = data_get($params, Constant::DB_TABLE_ACCOUNT, Constant::PARAMETER_STRING_DEFAULT);
        if ($account) {
            $where[] = ['ih.' . Constant::DB_TABLE_ACCOUNT, '=', $account];
        }

        if ($inviteCode) {
            $where[] = ['ih.' . Constant::DB_TABLE_INVITE_CODE, '=', $inviteCode];
        }

        if ($country) {
            $where[] = ['b.' . Constant::DB_TABLE_COUNTRY, '=', $country];
        }

        if ($startAt) {
            $where[] = ['c.' . Constant::DB_TABLE_OLD_CREATED_AT, '>=', $startAt];
        }

        if ($endAt) {
            $where[] = ['c.' . Constant::DB_TABLE_OLD_CREATED_AT, '<=', $endAt];
        }

        if ($source) {
            $where[] = ['d.ext1', '=', $source];
        }

        if ($actName) {
            $where[] = ['activities.name', '=', $actName];
        }

        $_where = [];

        if (data_get($params, 'id', 0)) {
            $_where['ih.id'] = $params['id'];
        }

        if ($where) {
            $_where[] = $where;
        }

        $order = $order ? $order : [['ih.' . Constant::DB_TABLE_CREATED_AT, 'desc']];

        return Arr::collapse([parent::getPublicData($params, $order), [
                        'where' => $_where,
        ]]);
    }

    /**
     * 展示列表
     * @param $params
     * @param bool $toArray
     * @param bool $isPage
     * @return array
     */
    public static function getListData($params, $toArray = false, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {

        $orderByData = data_get($params, 'order_by_data', []);
        $_data = static::getPublicData($params, $orderByData);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, null);
        $order = data_get($params, Constant::DB_EXECUTION_PLAN_ORDER_BY, data_get($_data, Constant::ORDER, []));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, Constant::DB_EXECUTION_PLAN_LIMIT, data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0));
        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, 0);

        $select = $select ? $select : [
            'ih.' . Constant::DB_TABLE_PRIMARY,
            'ih.' . Constant::DB_TABLE_ACCOUNT,
            'ih.' . Constant::DB_TABLE_INVITE_CODE,
            'b.' . Constant::DB_TABLE_FIRST_NAME,
            'b.' . Constant::DB_TABLE_LAST_NAME,
            'b.' . Constant::DB_TABLE_COUNTRY,
            'b.' . Constant::DB_TABLE_IP,
            'ih.' . Constant::DB_TABLE_INVITE_ACCOUNT,
            'ih.' . Constant::DB_TABLE_REMARKS,
            'c.' . Constant::DB_TABLE_OLD_CREATED_AT,
            'd.dict_value as ' . Constant::DB_TABLE_SOURCE . '_show',
            'activities.name as act_name',
        ];
        $dbExecutionPlan = static::getQuery($storeId, $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount);

        if (data_get($params, Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_PRIMARY, false)) {//如果仅仅获取主键id，就不需要处理数据，不关联
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_PARENT . Constant::LINKER . Constant::DB_EXECUTION_PLAN_HANDLE_DATA, Constant::PARAMETER_ARRAY_DEFAULT);
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_WITH, Constant::PARAMETER_ARRAY_DEFAULT);
        }

        $dataStructure = 'list';
        $flatten = false;
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    /**
     * 修复邀请流水
     * @param int $storeId 商城id
     * @param int $actId 活动id
     */
    public static function hotFix($storeId, $actId) {

        static::delInviteStatisticsCache(0, 0, 0); //清空邀请统计限制

        $sql = 'SELECT request_data,created_at FROM `ptx_statistical_analysis`.`crm_access_logs` where store_id=' . $storeId . ' and act_id=' . $actId . ' and api_url=\'/api/shop/customer/createCustomer\' and  request_data like \'%"invite_code":%\' and request_data not like \'%"invite_code":""%\' and REPLACE (
		JSON_EXTRACT (request_data, \'$.invite_code\'),
		\'"\',
		\'\'
	) like \'%?%\'';
        $connectionName = LogService::getModel($storeId)->getConnectionName();
        $data = \Illuminate\Support\Facades\DB::connection($connectionName)->select($sql);
        foreach ($data as $item) {
            $requestData = json_decode(data_get($item, 'request_data', ''), true);
            $invite_code_str = data_get($requestData, Constant::DB_TABLE_INVITE_CODE, '');

            $storeId = data_get($requestData, Constant::DB_TABLE_STORE_ID, 0);
            $account = data_get($requestData, 'account', '');
            $createdAt = data_get($item, Constant::DB_TABLE_CREATED_AT, '');
            $updatedAt = data_get($item, Constant::DB_TABLE_CREATED_AT, '');
            $actId = data_get($requestData, Constant::DB_TABLE_ACT_ID, 0);

            $inviteCode = static::getInviteCode($storeId, $invite_code_str, $actId);
            if ($inviteCode) {
                $accountData = CustomerService::customerExists($storeId, 0, $account, 0, true); //被邀请者数据
                $inviteCustomerId = data_get($accountData, Constant::DB_TABLE_CUSTOMER_PRIMARY, 0);
                \App\Services\InviteService::handle($inviteCode, $inviteCustomerId, $storeId, $createdAt, $updatedAt, $actId, $requestData);
            }
        }

        return [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => 'hotfix ok',
            Constant::RESPONSE_DATA_KEY => []
        ];
    }

    /**
     * 邀请记录列表
     * @param array $params 请求参数
     * @param boolean $toArray 是否转化为数组 true:是 false:否 默认:true
     * @param boolean $isPage  是否分页 true:是 false:否 默认:true
     * @param array $select  查询字段
     * @param boolean $isRaw  是否是原生sql true:是 false:否 默认:false
     * @return array 列表数据
     */
    public static function getInviteHistoryData($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {

        $storeId = data_get($params, 'store_id', 0);
        $_data = static::getInvitePublicData($params);

        $where = data_get($_data, 'where', []);
        $order = data_get($params, 'orderBy', data_get($_data, 'order', []));
        $pagination = data_get($_data, 'pagination', []);
        $limit = data_get($params, 'limit', data_get($pagination, 'page_size', 10));
        $offset = data_get($params, 'offset', data_get($pagination, 'offset', 0));

        $select = $select ? $select : [
            'ih.' . Constant::DB_TABLE_PRIMARY,
            'ih.' . Constant::DB_TABLE_INVITE_ACCOUNT,
            'ih.' . Constant::DB_TABLE_REMARKS,
            'ih.' . Constant::DB_TABLE_INVITE_COMMISSION,
            'b.' . Constant::DB_TABLE_FIRST_NAME,
            'b.' . Constant::DB_TABLE_LAST_NAME,
            'c.' . Constant::DB_TABLE_OLD_CREATED_AT,
        ];

        $dbExecutionPlan = static::getInviteQuery($storeId, $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount);
        $dataStructure = 'list';
        $flatten = false;
        $data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);

        foreach($data['data'] as &$val){
            if (empty($val[Constant::DB_TABLE_REMARKS])){
                $val['check_status'] = 'Wait';
                $val['commission'] = 'Wait';
            } else {
                if (empty(OrderService::isExists($storeId, str_replace(" ",'',$val[Constant::DB_TABLE_REMARKS]))) || !FunctionHelper::checkOrderNo(str_replace(" ",'',$val[Constant::DB_TABLE_REMARKS]))){ //判断订单是否存在以及格式是否正确
                    $val['check_status'] = 'Failure';
                    $val['commission'] = 'Failure';
                } else {
                    $val['check_status'] = 'Sign up';
                }
            }
            unset($val[Constant::DB_TABLE_REMARKS]);
        }

        return $data;
    }

    /**
     * 格式化邀请记录参数
     * @param array $params 请求参数
     * @return array
     */
    public static function getInvitePublicData($params, $order = []) {

        $where = [];
        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, Constant::PARAMETER_INT_DEFAULT);
        $invite_code_type = data_get($params, Constant::DB_TABLE_INVITE_TYPE, 2);

        if ($storeId) {
            $where[] = ['ih.' . Constant::DB_TABLE_STORE_ID, '=', $storeId];
        }

        $account = data_get($params, Constant::DB_TABLE_ACCOUNT, Constant::PARAMETER_STRING_DEFAULT);
        if ($account) {
            $where[] = ['ih.' . Constant::DB_TABLE_ACCOUNT, '=', $account];
        }

        if ($invite_code_type) {
            $where[] = ['d.' . Constant::DB_TABLE_INVITE_TYPE, '=', $invite_code_type];
        }

        $_where = [];

        if ($where) {
            $_where[] = $where;
        }

        $order = $order ? $order : [['ih.' . Constant::DB_TABLE_CREATED_AT, 'desc']];

        return Arr::collapse([parent::getPublicData($params, $order), [
            'where' => $_where,
        ]]);
    }

    /**
     * 获取邀请记录的db query
     * @param array $where
     * @return \Illuminate\Database\Query\Builder|static $query
     */
    public static function getInviteQuery($storeId, $select, $where = [], $order = [], $limit = null, $offset = null, $isPage = true, $pagination = [], $isOnlyGetCount = false) {

        $field = Constant::DB_TABLE_FIRST_NAME . '{connection}' . Constant::DB_TABLE_LAST_NAME;
        $data = Constant::PARAMETER_ARRAY_DEFAULT;
        $dataType = Constant::DB_EXECUTION_PLAN_DATATYPE_STRING;
        $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
        $time = Constant::PARAMETER_STRING_DEFAULT;
        $glue = ' ';
        $isAllowEmpty = true;
        $default = Constant::PARAMETER_STRING_DEFAULT;
        $callback = Constant::PARAMETER_ARRAY_DEFAULT;
        $only = Constant::PARAMETER_ARRAY_DEFAULT;

        $parameters = [$field, $default, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only];
        $handleData = [
            'use_name' => FunctionHelper::getExePlanHandleData(...$parameters),
        ];
        $joinData = [
            FunctionHelper::getExePlanJoinData('customer as c', function ($join) use($storeId) {
                $join->on([['c.' . Constant::DB_TABLE_ACCOUNT, '=', 'ih.' . Constant::DB_TABLE_INVITE_ACCOUNT]])
                    ->where('c.' . Constant::DB_TABLE_STORE_ID, '=', $storeId)
                    ->where('c.' . Constant::DB_TABLE_STATUS, '=', 1)
                ;
            }),
            FunctionHelper::getExePlanJoinData('customer_info as b', function ($join) {//
                $join->on([['b.' . Constant::DB_TABLE_ACCOUNT, '=', 'ih.' . Constant::DB_TABLE_INVITE_ACCOUNT]])
                    ->where('b.' . Constant::DB_TABLE_STATUS, '=', 1);
            }),
            FunctionHelper::getExePlanJoinData('invite_codes as d', function ($join) {//
                $join->on([['d.' . Constant::DB_TABLE_CUSTOMER_PRIMARY, '=', 'ih.' . Constant::DB_TABLE_CUSTOMER_PRIMARY]])
                    ->on([['d.' . Constant::DB_TABLE_INVITE_CODE, '=', 'ih.' . Constant::DB_TABLE_INVITE_CODE]])
                    ->where('d.' . Constant::DB_TABLE_STATUS, '=', 1);
            }),
        ];
        $with = [];
        $unset = [
            Constant::DB_TABLE_FIRST_NAME,
            Constant::DB_TABLE_LAST_NAME,
        ];
        $exePlan = FunctionHelper::getExePlan('default_connection_' . $storeId, null, static::getModelAlias(), 'invite_historys as ih', $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, $with, $handleData, $unset);
        return [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
        ];
    }

    /**
     * 编辑邀请关系列表的备注
     * @param int $storeId 官网id
     * @param int $Id 邀请关系列表主键id
     * @param array $data 修改的数据
     * @return \Illuminate\Database\Query\Builder|static $query
     */
    public static function getInviteEdit($storeId, $Id, $data) {

        $where = [
            'id' => $Id,
        ];
        $updateData = [
            'remarks' => data_get($data, 'remarks', ''), //签约备注
            'updated_at' => Carbon::now()->toDateTimeString(),
        ];
        $update = static::update($storeId, $where, $updateData);

        return $update;
    }
}
