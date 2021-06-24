<?php

/**
 * 抽奖服务
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services;

use Hyperf\DbConnection\Db as DB;
use Hyperf\Utils\Arr;
use App\Utils\Support\Facades\Redis;
use Carbon\Carbon;
use App\Utils\Support\Facades\Cache;
use App\Utils\FunctionHelper;
use App\Utils\Arrays\MyArr;
use App\Constants\Constant;
use App\Utils\Response;
use App\Services\Store\PlatformServiceManager;
use App\Services\Platform\ProductService;
use App\Services\Platform\OrderService;
use App\Services\Platform\OrderItemService;

class ActivityWinningService extends BaseService {

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'ActivityWinningLog';
    }

    /**
     * 检查是否存在
     * @param int $id 角色id
     * @param bool $getData 是否获取数据 true:是 false:否 默认:false
     * @return mixed|static
     */
    public static function exists($storeId = 0, $country = '', $actId = 0, $customerId = 0, $where = [], $getData = false) {

        $_where = [];
        if ($actId) {
            data_set($_where, Constant::DB_TABLE_ACT_ID, $actId);
        }

        if ($customerId) {
            data_set($_where, Constant::DB_TABLE_CUSTOMER_PRIMARY, $customerId);
        }

        $where = Arr::collapse([$_where, $where]);
        if (empty($where)) {
            return $getData ? [] : true;
        }

        $query = static::getModel($storeId, $country)->buildWhere($where);

        if ($getData) {
            $rs = $query->first();
        } else {
            $rs = $query->count();
        }

        return $rs;
    }

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @param array $order 排序控制
     * @return array
     */
    public static function getPublicData($params, $order = []) {

        $where = [];

        $actId = data_get($params, Constant::DB_TABLE_ACT_ID, 0); //活动id
        $name = data_get($params, 'name', 0); //奖品名称
        $type = data_get($params, 'type', 0); //奖品类型
        $customerId = data_get($params, Constant::DB_TABLE_CUSTOMER_PRIMARY, 0); //会员id
        $account = data_get($params, Constant::DB_TABLE_ACCOUNT, ''); //会员id
        $country = data_get($params, Constant::DB_TABLE_COUNTRY, ''); //会员id
        $actName = data_get($params, 'act_name', ''); //活动名称
        $isParticipationAward = data_get($params, Constant::DB_TABLE_IS_PARTICIPATION_AWARD, null); //是否安慰奖 1:是 0:否 默认:0

        if ($actId) {//活动id
            $where[] = ['w.act_id', '=', $actId];
        }

        if ($name) {//奖品名称
            $where[] = [('p.' . Constant::DB_TABLE_NAME), 'like', "%$name%"];
        }

        if ($type) {//奖品类型
            $where[] = [('p.' . Constant::DB_TABLE_TYPE), '=', $type];
        }

        if ($customerId) {//奖品类型
            $where[] = [('w.' . Constant::DB_TABLE_CUSTOMER_PRIMARY), '=', $customerId];
        }
        if ($account) {
            $where[] = ['w.' . Constant::DB_TABLE_ACCOUNT, '=', $account];
        }
        if ($country) {
            $where[] = ['w.' . Constant::DB_TABLE_COUNTRY, 'like', "%$country%"];
        }

        if ($isParticipationAward !== null) {//是否安慰奖 1:是 0:否 默认:0
            $where[] = ['w.' . Constant::DB_TABLE_IS_PARTICIPATION_AWARD, '=', $isParticipationAward];
        }

        if ($actName) {//活动名称
            $where[] = ['activities.' . Constant::DB_TABLE_NAME, 'like', "%$actName%"];
        }

        $_where = [];
        if (data_get($params, 'id', 0)) {
            $_where['w.id'] = $params['id'];
        }

        if ($where) {
            $_where[] = $where;
        }

        $order = $order ? $order : [['w.id', 'DESC']];
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
     * @param boolean $isRaw  是否是原生sql true:是 false:否 默认:false
     * @return array 列表数据
     */
    public static function getListData($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {

        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, 0);
        $_data = static::getPublicData($params, data_get($params, 'orderby', []));

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, []);
        $order = data_get($_data, Constant::ORDER, []);
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10);
        $offset = data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0);

        $select = $select ? $select : ['id', 'name', 'img_url', 'mb_img_url', 'url']; //
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => [
                Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                Constant::DB_EXECUTION_PLAN_BUILDER => null,
                'make' => static::getModelAlias(),
                'from' => '',
                Constant::DB_EXECUTION_PLAN_JOIN_DATA => [
                ],
                Constant::DB_OPERATION_SELECT => $select,
                Constant::DB_EXECUTION_PLAN_WHERE => $where,
                Constant::DB_EXECUTION_PLAN_ORDERS => $order,
                Constant::DB_EXECUTION_PLAN_OFFSET => $offset,
                Constant::DB_EXECUTION_PLAN_LIMIT => $limit,
                Constant::DB_EXECUTION_PLAN_IS_PAGE => $isPage,
                Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_COUNT => $isOnlyGetCount,
                Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [],
            //Constant::DB_EXECUTION_PLAN_UNSET => [Constant::DB_TABLE_CUSTOMER_PRIMARY],
            ],
            'with' => [
            ],
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => [
            ],
                //'sqlDebug' => true,
        ];

        $dataStructure = 'list';
        $flatten = false;
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    public static function getWinningPrizeDbExecutionPlan($storeId = 0, $where = [], $order = [], $offset = null, $limit = null, $isPage = false, $pagination = []) {

        $select = [
            'w.id',
            'w.' . Constant::DB_TABLE_ACCOUNT,
            'w.' . Constant::DB_TABLE_COUNTRY,
            ('w.' . Constant::DB_TABLE_PRIZE_ID),
            'w.' . Constant::DB_TABLE_PRIZE_ITEM_ID,
            'w.' . Constant::DB_TABLE_UPDATED_AT,
            'w.first_name',
            'w.last_name',
            ('p.' . Constant::DB_TABLE_NAME),
            'p.asin',
            'pi.asin as item_asin'
        ]; //
        $amazonHostData = DictService::getListByType('amazon_host', Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE);
        return [
            Constant::DB_EXECUTION_PLAN_PARENT => [
                Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                Constant::DB_EXECUTION_PLAN_BUILDER => null,
                'make' => static::getModelAlias(),
                'from' => 'activity_winning_logs as w',
                Constant::DB_EXECUTION_PLAN_JOIN_DATA => [
                    [
                        Constant::DB_EXECUTION_PLAN_TABLE => 'activity_prizes as p',
                        Constant::DB_EXECUTION_PLAN_FIRST => 'p.id',
                        Constant::DB_TABLE_OPERATOR => '=',
                        Constant::DB_EXECUTION_PLAN_SECOND => ('w.' . Constant::DB_TABLE_PRIZE_ID),
                        'type' => 'left',
                    ],
                    [
                        Constant::DB_EXECUTION_PLAN_TABLE => 'activity_prize_items as pi',
                        Constant::DB_EXECUTION_PLAN_FIRST => function ($join) {
                            $join->on([[('pi.' . Constant::DB_TABLE_PRIMARY), '=', 'w.' . Constant::DB_TABLE_PRIZE_ITEM_ID], [('pi.' . Constant::DB_TABLE_PRIZE_ID), '=', ('w.' . Constant::DB_TABLE_PRIZE_ID)]]); //->where('b.status', '=', 1);
                        },
                        Constant::DB_TABLE_OPERATOR => null,
                        Constant::DB_EXECUTION_PLAN_SECOND => null,
                        'type' => 'left',
                    ],
                ],
                Constant::DB_OPERATION_SELECT => $select,
                Constant::DB_EXECUTION_PLAN_WHERE => $where,
                Constant::DB_EXECUTION_PLAN_ORDERS => $order,
                Constant::DB_EXECUTION_PLAN_OFFSET => $offset,
                Constant::DB_EXECUTION_PLAN_LIMIT => $limit,
                Constant::DB_EXECUTION_PLAN_IS_PAGE => $isPage,
                Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                    Constant::PLATFORM_AMAZON => [
                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_COUNTRY,
                        'data' => $amazonHostData,
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => data_get($amazonHostData, 'US', ''),
                    ],
                    'asin' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'item_asin{or}asin',
                        'data' => '',
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                    'url' => [//亚马逊链接
                        Constant::DB_EXECUTION_PLAN_FIELD => 'amazon{connection}asin',
                        'data' => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_STRING,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => '/dp/',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                ],
                Constant::DB_EXECUTION_PLAN_UNSET => [Constant::PLATFORM_AMAZON, 'asin', 'item_asin'],
            ],
            'with' => [
            ],
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => [
            ],
                //'sqlDebug' => true,
        ];
    }

    /**
     * 获取中奖数据
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $prizeId  奖品id
     * @param int $customerId 会员id
     * @param int $prizeItemId 奖品item id
     * @return type
     */
    public static function getItem($storeId, $actId = 0, $prizeId = 0, $customerId = 0, $prizeItemId = 0) {

        $where = [
            ('w.' . Constant::DB_TABLE_PRIZE_ID) => $prizeId,
            'w.' . Constant::DB_TABLE_PRIZE_ITEM_ID => $prizeItemId,
            'w.act_id' => $actId,
            ('w.' . Constant::DB_TABLE_CUSTOMER_PRIMARY) => $customerId,
        ];
        $dbExecutionPlan = static::getWinningPrizeDbExecutionPlan($storeId, $where, [['w.' . Constant::DB_TABLE_UPDATED_AT, Constant::DB_EXECUTION_PLAN_ORDER_DESC]]);

        $dataStructure = 'one';
        $flatten = false;
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);
    }

    /**
     * 添加记录
     * @param int $storeId 商城id
     * @param array $where where条件
     * @param array $data  数据
     * @return int
     */
    public static function insert($storeId, $where, $data) {
//        $nowTime = Carbon::now()->toDateTimeString();
//        $data['created_at'] = DB::raw("IF(created_at='2019-01-01 00:00:00','$nowTime',created_at)");
//        $data[Constant::DB_TABLE_UPDATED_AT] = $nowTime;

        return static::getModel($storeId, '')->updateOrCreate($where, $data); //updateOrCreate：不可以添加主键id的值  updateOrInsert：可以添加主键id的值
    }

    /**
     * 获取排行榜的key
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @return string 排行榜的key
     */
    public static function getRankKey($storeId = 0, $actId = 0) {
        return 'winningRank:' . $storeId . ':' . $actId;
    }

    /**
     * 获取排行榜的key
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @return string 排行榜的key
     */
    public static function delRankCache($storeId = 0, $actId = 0) {
        $zsetKey = static::getRankKey($storeId, $actId);
        return static::del($zsetKey);
    }

    /**
     * 初始化排行榜
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $type 榜单类型 1:分享 2:邀请
     * @return boolean true:初始化成功
     */
    public static function initRank($storeId = 0, $actId = 0) {
        $zsetKey = static::getRankKey($storeId, $actId);
        $isExists = Redis::exists($zsetKey);
        if ($isExists) {
            return true;
        }

        $publicData = [
            Constant::DB_TABLE_ACT_ID => $actId,
            'page' => 1,
            Constant::REQUEST_PAGE_SIZE => 10,
        ];
        $_data = static::getPublicData($publicData, [['w.' . Constant::DB_TABLE_UPDATED_AT, 'desc']]);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, []);
        $where[('w.' . Constant::DB_TABLE_IS_PARTICIPATION_AWARD)] = 0; //排行榜只获取非参与奖的奖项
        $order = data_get($_data, Constant::ORDER, []);
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10);
        $offset = data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0);
        $dbExecutionPlan = static::getWinningPrizeDbExecutionPlan($storeId, $where, $order, null, $limit);

        $dataStructure = 'list';
        $flatten = false;
        $data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);

        foreach ($data as $member) {
            $score = Carbon::parse(data_get($member, Constant::DB_TABLE_UPDATED_AT, Carbon::now()->toDateTimeString()))->timestamp; //当前时间戳
            unset($member[Constant::DB_TABLE_UPDATED_AT]);
            Redis::zadd($zsetKey, $score, static::getZsetMember($member));
        }
        $ttl = 30 * 24 * 60 * 60;
        Redis::expire($zsetKey, $ttl);

        return false;
    }

    /**
     * 获取会员中实物缓存 tag
     * @return array
     */
    public static function getWinRealThingCacheTag() {
        return ['{winRealThing}'];
    }

    /**
     * 获取缓存tags
     * @return string
     */
    public static function getCacheTags() {
        return 'winningLock';
    }

    /**
     * 获取要清空的tags
     * @return array
     */
    public static function getClearTags() {
        return [static::getCacheTags(), 'winRealThingCredit'];
    }

    /**
     * 处理积分抽奖
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param string $account 账号
     * @param array $extData  扩展参数
     * @return int
     */
    public static function handle($storeId = 0, $actId = 0, $customerId = 0, $account = '', $extData = []) {

        $defaultRs = Response::getDefaultResponseData(62007);

        $tag = static::getCacheTags();
        $cacheKey = $tag . ':' . $storeId . '：' . $actId . ':' . $customerId;
        $handleCacheData = [
            Constant::SERVICE_KEY => static::getNamespaceClass(),
            Constant::METHOD_KEY => 'lock',
            Constant::PARAMETERS_KEY => [
                $cacheKey,
            ],
            'serialHandle' => [
                [
                    Constant::SERVICE_KEY => static::getNamespaceClass(),
                    Constant::METHOD_KEY => 'get',
                    Constant::PARAMETERS_KEY => [
                        function () use($storeId, $actId, $customerId, $account, $extData) {

                            $retult = Response::getDefaultResponseData(1);

                            //IP限制抽奖用户数，holife翻牌需求
                            if (!static::flopIpLimit($storeId, $actId, $customerId, $account)) {
                                return Response::getDefaultResponseData(62009);
                            }

                            $inviteCode = data_get($extData, 'invite_code', null);
                            if (!empty($inviteCode)) {//如果是被邀请者
                                $isValidInviteCode = InviteCodeService::exists(0, $inviteCode);
                                if (!$isValidInviteCode) {//如果邀请码无效，就直接提示用户
                                    return Response::getDefaultResponseData(62006);
                                }
                            }

                            $isHandleAct = ActivityService::isHandle($storeId, $actId, $customerId, $extData);
                            if (data_get($isHandleAct, Constant::RESPONSE_CODE_KEY, 0) != 1) {
                                return $isHandleAct;
                            }

                            $isWin = 0; //是否中奖 1：中奖  0:不中奖
                            $customer = CustomerService::getCustomerActivateData($storeId, $customerId);
                            $prizeCountry = data_get($customer, 'info.country', '');
                            $prizeCountry = $prizeCountry ? $prizeCountry : data_get($extData, Constant::DB_TABLE_COUNTRY, '');

                            //获取参与奖
                            $prizeWhere = ['p.is_participation_award' => 1];
                            $prizeItem = ActivityPrizeService::getData($storeId, $actId, $customerId, $prizeCountry, $prizeWhere, 1);

                            data_set($prizeItem, Constant::ACTIVITY_WINNING_ID, 0); //设置中奖流水id
                            data_set($prizeItem, Constant::DB_TABLE_COUNTRY, $prizeCountry); //设置中奖流水国家
                            data_set($retult, 'data.is_win', $isWin); //设置是否中奖
                            data_set($retult, 'data.prizeData', $prizeItem);

                            //通过活动配置 获取禁止抽奖的国家
                            $activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, 'winning');
                            $banCountryData = data_get($activityConfigData, 'winning_ban_country.value', '');
                            if (!empty($banCountryData)) {
                                $banCountryData = explode(',', $banCountryData);
                                if (in_array($prizeCountry, $banCountryData)) {//如果当前用户来自 禁止抽奖的国家，就直接返回参与奖即可
                                    data_set($retult, Constant::RESPONSE_CODE_KEY, 62005);
                                    return $retult;
                                }
                            }

                            //5.所有抽中实物奖励的账号，后续参与活动不能在中奖励
                            //判断是否中过实物
                            $winRealThingCacheTags = static::getWinRealThingCacheTag();
                            $winRealThingCacheKey = $storeId . ':' . $actId . ':' . $customerId;
                            $isWinRealThing = Cache::tags($winRealThingCacheTags)->get($winRealThingCacheKey);
                            if ($isWinRealThing) {//如果已经中过实物奖，就不可以中其他奖品了
                                data_set($retult, Constant::RESPONSE_CODE_KEY, 62004);
                                return $retult;
                            }

                            //根据配置判断是否中了礼品卡就不能在中其他奖励,holife翻牌需求
                            $giftCard = data_get($activityConfigData, 'winning_gift_card.value', '');
                            if ($giftCard) {
                                $winGiftCardCacheKey = 'gift_card:' . $storeId . ':' . $actId . ':' . $customerId;
                                $isWinGiftCard = Cache::tags($winRealThingCacheTags)->get($winGiftCardCacheKey);
                                if ($isWinGiftCard) {//如果已经中过礼品卡奖，就不可以中其他奖品了
                                    data_set($retult, Constant::RESPONSE_CODE_KEY, 62004);
                                    return $retult;
                                }
                            }

                            //获取当前用户已经中奖的奖品类型
                            $winPrizeTypeCacheKey = 'prizeType:' . $storeId . ':' . $actId . ':' . $customerId;
                            $winPrizeTypeData = Cache::tags($winRealThingCacheTags)->get($winPrizeTypeCacheKey); //已经中奖的奖品类型
                            $winPrizeTypeData = is_array($winPrizeTypeData) ? $winPrizeTypeData : [];

                            //获取中奖互斥配置 如1{@#$}3;2{@#$}3;3{@#$}1,2 表示：3和(1或者2)互斥
                            $mutuallyExclusiveWhere = []; //中奖互斥限制条件
                            $mutuallyExclusiveData = data_get($activityConfigData, 'winning_mutually_exclusive.value', ''); //1{@#$}3;2{@#$}3;3{@#$}1,2
                            if ($mutuallyExclusiveData) {

                                //获取中奖互斥配置数据
                                $mutuallyExclusiveConfig = [];
                                $mutuallyExclusiveData = explode(';', $mutuallyExclusiveData);
                                foreach ($mutuallyExclusiveData as $mutuallyExclusiveItem) {
                                    $meTtem = explode('{@#$}', $mutuallyExclusiveItem);
                                    $mutuallyExclusiveConfig[data_get($meTtem, 0, -1)] = data_get($meTtem, 1, '');
                                }

                                //获取当前用户的中奖互斥配置数据
                                $customerMutuallyExclusiveData = [];
                                foreach ($winPrizeTypeData as $winPrizeType) {
                                    $winPrizeTypeMutuallyExclusive = explode(',', data_get($mutuallyExclusiveConfig, $winPrizeType, -1));
                                    $customerMutuallyExclusiveData = Arr::collapse([$customerMutuallyExclusiveData, $winPrizeTypeMutuallyExclusive]);
                                }

                                //获取中奖互斥限制条件
                                if ($customerMutuallyExclusiveData) {
                                    $customerMutuallyExclusiveData = MyArr::handle($customerMutuallyExclusiveData);
                                    if ($customerMutuallyExclusiveData) {

                                        $mutuallyExclusiveWhere = [
                                            '{customizeWhere}' => [
                                                [
                                                    Constant::METHOD_KEY => 'whereNotIn',
                                                    Constant::PARAMETERS_KEY => [('p.' . Constant::DB_TABLE_TYPE), $customerMutuallyExclusiveData, 'and'],
                                                ],
                                            ]
                                        ];
                                    }
                                }
                            }

                            //holife实物奖品6个和Holife 40% off code 10个奖品价值高，抽奖期间控制平均一天出1个。
                            $dayPrizes = explode(',', data_get($activityConfigData, 'winning_day_prizes.value', ''));
                            $dayPrizes = array_unique(array_filter($dayPrizes));
                            $isWinDayPrize = false; //是否已经中了每日必出的奖品
                            if (!empty($dayPrizes)) {

                                //获取每日必出的奖品
                                $dayDate = Carbon::now()->rawFormat('Y-m-d 00:00:00');
                                $where = [
                                    'prize_id' => $dayPrizes,
                                    "(created_at >= '{$dayDate}')"
                                ];
                                $isWinDayPrize = static::exists($storeId, '', $actId, 0, $where);
                                if ($isWinDayPrize > 0) {//如果每日必出奖品，已经有用户中奖了，别的用户就不可以再中 每日必出奖品
                                    $mutuallyExclusiveWhere = [
                                        '{customizeWhere}' => [
                                            [
                                                Constant::METHOD_KEY => 'whereNotIn',
                                                Constant::PARAMETERS_KEY => ['p.id', $dayPrizes, 'and'],
                                            ],
                                        ]
                                    ];
                                }
                            }

                            //查询奖品
                            $where = [
                                [[Constant::DB_TABLE_IS_PARTICIPATION_AWARD, '=', 0]]
                            ];
                            $winningLimit = data_get($activityConfigData, 'winning_limit.value', 1);
                            $winningNum = static::exists($storeId, '', $actId, $customerId, $where);
                            if ($winningNum >= $winningLimit) {
                                data_set($retult, Constant::RESPONSE_CODE_KEY, 62001);
                                return $retult;
                            }

                            //获取当前用户未中过的奖品
                            $prizeData = ActivityPrizeService::getPrizeData($storeId, $actId, $customerId, $prizeCountry, $mutuallyExclusiveWhere, $activityConfigData);
                            if (empty($prizeData)) {
                                data_set($retult, Constant::RESPONSE_CODE_KEY, 62002);
                                return $retult;
                            }

                            /*                             * ***************更新奖品领取数量以便 提前占有 这些奖品，防止高并发多人领取没有库存的奖品 ************************ */
                            $prizeIds = array_unique(array_filter(data_get($prizeData, '*.id', []))); //奖品id
                            $prizeItemIds = array_unique(array_filter(data_get($prizeData, '*.item_id', []))); //奖品 item id
                            if ($prizeItemIds) {
                                $where = [
                                    'id' => $prizeItemIds,
                                ];
                                $data = [
                                    Constant::DB_TABLE_QTY_RECEIVE => DB::raw(Constant::DB_TABLE_QTY_RECEIVE . '+1'),
                                ];
                                ActivityPrizeItemService::update($storeId, $where, $data);
                            }

                            if ($prizeIds) {
                                $where = [
                                    'id' => $prizeIds,
                                ];
                                $data = [
                                    Constant::DB_TABLE_QTY_RECEIVE => DB::raw(Constant::DB_TABLE_QTY_RECEIVE . '+1'),
                                ];
                                ActivityPrizeService::update($storeId, $where, $data);
                            }

                            foreach ($prizeData as $item) {

                                if (data_get($item, Constant::DB_TABLE_IS_PARTICIPATION_AWARD, 0) == 1) {
                                    continue;
                                }

                                $num = mt_rand(1, data_get($item, 'max', 1));
                                if ($num <= data_get($item, 'winning_value', 1)) {//如果中奖了，就获取奖品
                                    $prizeItem = $item;
                                    $isWin = 1;
                                    break;
                                }
                            }

                            data_set($retult, 'data.is_win', $isWin); //设置是否中奖

                            if (empty($prizeItem)) {//如果没有中奖，就获取参与奖
                                $prizeData = collect($prizeData);
                                $prizeItem = $prizeData->where(Constant::DB_TABLE_IS_PARTICIPATION_AWARD, 1)->first();
                            }

                            $prizeId = data_get($prizeItem, 'id', 0); //奖品id
                            $prizeItemId = data_get($prizeItem, Constant::DB_TABLE_ITEM_ID, 0); //奖品 item id

                            /*                             * ***************更新奖品领取数量以便 释放 提前占有的奖品，防止高并发多人领取没有库存的奖品 ************************ */
                            $prizeItemIds = array_diff($prizeItemIds, [$prizeItemId]);
                            $prizeIds = array_diff($prizeIds, [$prizeId]);
                            if ($prizeItemIds) {
                                $where = [
                                    'id' => $prizeItemIds,
                                ];
                                $data = [
                                    Constant::DB_TABLE_QTY_RECEIVE => DB::raw('qty_receive-1'),
                                ];
                                ActivityPrizeItemService::update($storeId, $where, $data);
                            }

                            if ($prizeIds) {
                                $where = [
                                    'id' => $prizeIds,
                                ];
                                $data = [
                                    Constant::DB_TABLE_QTY_RECEIVE => DB::raw('qty_receive-1'),
                                ];
                                ActivityPrizeService::update($storeId, $where, $data);
                            }

                            $isParticipationAward = data_get($prizeItem, Constant::DB_TABLE_IS_PARTICIPATION_AWARD, 0); //是否是参与奖
                            $participationAwardInWinningLog = data_get($activityConfigData, 'winning_participation_award_in_winning_log.value', 0); //参与奖是否放入到中奖列表中 1：是  0：否
                            if ($isParticipationAward == 1 && $participationAwardInWinningLog == 1 && $prizeItem) {//如果是参与奖，并且参与奖要放入到中奖列表中，就把参与奖也当做一个奖项，加入到当前用户的奖品流水中
                                $where = [
                                    Constant::DB_TABLE_IS_PARTICIPATION_AWARD => 1,
                                ];
                                $isWinParticipationAward = static::exists($storeId, '', $actId, $customerId, $where); //是否已经中过参与奖
                                if (!$isWinParticipationAward) {//如果没有中过参与奖  就把参与奖放到用户的中奖列表中
                                    $isWin = 1;
                                } else {
                                    data_set($retult, Constant::RESPONSE_CODE_KEY, 62008); //已经中过安慰奖，不能再中安慰奖
                                }
                            }

                            if ($isWin == 0) {//如果没有中奖，就判断是否有每日必出的奖品，优先发给不中奖的用户，返回参与奖
                                if (empty($dayPrizes) || $isWinDayPrize) {//如果没有每日必出的奖品或者当天已经有用户中了每日必出的奖品，就直接返回
                                    return $retult;
                                }

                                //holife实物奖品6个和Holife 40% off code 10个奖品价值高，抽奖期间控制平均一天出1个。
                                //获取每日必出的奖品
                                shuffle($dayPrizes);
                                $dayPrizesWhere = ['p.id' => $dayPrizes];
                                $prefix = DB::getConfig('prefix');
                                $dayPrizesWhere[] = "({$prefix}p.qty_receive < {$prefix}p.qty)";
                                $dayPrizesWhere[] = "({$prefix}pi.qty_receive < {$prefix}pi.qty)";
                                $prizeItem = ActivityPrizeService::getData($storeId, $actId, $customerId, $prizeCountry, $dayPrizesWhere, 1);
                                if (empty($prizeItem)) {//如果没有中奖，就直接返回
                                    return $retult;
                                }

                                /*                                 * ***************更新奖品领取数量以便 提前占有 这些奖品，防止高并发多人领取没有库存的奖品 ************************ */
                                $prizeIds = data_get($prizeItem, 'id', 0); //奖品id
                                $prizeItemIds = data_get($prizeItem, Constant::DB_TABLE_ITEM_ID, 0); //奖品 item id
                                if ($prizeItemIds) {
                                    $where = [
                                        'id' => $prizeItemIds,
                                    ];
                                    $data = [
                                        Constant::DB_TABLE_QTY_RECEIVE => DB::raw(Constant::DB_TABLE_QTY_RECEIVE . '+1'),
                                    ];
                                    ActivityPrizeItemService::update($storeId, $where, $data);
                                }

                                if ($prizeIds) {
                                    $where = [
                                        'id' => $prizeIds,
                                    ];
                                    $data = [
                                        Constant::DB_TABLE_QTY_RECEIVE => DB::raw(Constant::DB_TABLE_QTY_RECEIVE . '+1'),
                                    ];
                                    ActivityPrizeService::update($storeId, $where, $data);
                                }
                            }

                            $prizeId = data_get($prizeItem, 'id', 0); //奖品id
                            $prizeItemId = data_get($prizeItem, Constant::DB_TABLE_ITEM_ID, 0); //奖品 item id
                            if (empty($prizeId)) {
                                return $retult;
                            }

                            /*                             * ***************添加中奖数据************************ */
                            $prizeType = data_get($prizeItem, 'type', 0); //奖品类型
                            //获取用户基本资料
                            $customerInfoData = CustomerInfoService::getData($storeId, $customerId);

                            $addPrizeMulti = data_get($activityConfigData, 'winning_add_prize_multi.value', 0);
                            if ($addPrizeMulti) {
                                $data = [
                                    Constant::DB_TABLE_ACCOUNT => $account,
                                    'ip' => data_get($extData, 'ip', ''),
                                    'quantity' => DB::raw('quantity+1'),
                                    Constant::DB_TABLE_IS_PARTICIPATION_AWARD => $isParticipationAward,
                                    Constant::DB_TABLE_COUNTRY => $prizeCountry,
                                    'prize_type' => $prizeType,
                                    Constant::DB_TABLE_FIRST_NAME => data_get($customerInfoData, Constant::DB_TABLE_FIRST_NAME, ''),
                                    Constant::DB_TABLE_LAST_NAME => data_get($customerInfoData, Constant::DB_TABLE_LAST_NAME, ''),
                                    'prize_id' => $prizeId,
                                    'prize_item_id' => $prizeItemId,
                                    Constant::DB_TABLE_ACT_ID => $actId,
                                    Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                                ];
                                static::getModel($storeId)->insert($data);
                            } else {
                                $data = [
                                    Constant::DB_TABLE_ACCOUNT => $account,
                                    'ip' => data_get($extData, 'ip', ''),
                                    'quantity' => DB::raw('quantity+1'),
                                    Constant::DB_TABLE_IS_PARTICIPATION_AWARD => $isParticipationAward,
                                    Constant::DB_TABLE_COUNTRY => $prizeCountry,
                                    'prize_type' => $prizeType,
                                    Constant::DB_TABLE_FIRST_NAME => data_get($customerInfoData, Constant::DB_TABLE_FIRST_NAME, ''),
                                    Constant::DB_TABLE_LAST_NAME => data_get($customerInfoData, Constant::DB_TABLE_LAST_NAME, ''),
                                ];
                                $where = [
                                    'prize_id' => $prizeId,
                                    'prize_item_id' => $prizeItemId,
                                    Constant::DB_TABLE_ACT_ID => $actId,
                                    Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                                ];
                                static::insert($storeId, $where, $data);
                            }

                            $ttl = 30 * 24 * 60 * 60;
                            //更新当前用户已经中奖的奖品类型
                            $winPrizeTypeData = array_filter(array_unique(Arr::collapse([$winPrizeTypeData, [$prizeType]])));
                            Cache::tags($winRealThingCacheTags)->put($winPrizeTypeCacheKey, $winPrizeTypeData, $ttl);

                            if ($isParticipationAward != 1 && $prizeType == 3) {//如果是实物，就标识用户已经中过实物
                                Cache::tags($winRealThingCacheTags)->put($winRealThingCacheKey, 1, $ttl);
                            }

                            //holife翻牌需求，礼品卡/实物，中了就不再中奖
                            if ($giftCard && $prizeType == 1) { //根据配置，如果是礼品卡，就标识用户已经中过礼品卡
                                Cache::tags($winRealThingCacheTags)->put($winGiftCardCacheKey, 1, $ttl);
                            }

                            //更新会员中奖列表缓存
                            $tags = static::getCustomerWinCacheTag();
                            Cache::tags($tags)->flush();

                            //获取中奖流水数据
                            $member = static::getItem($storeId, $actId, $prizeId, $customerId, $prizeItemId);
                            //        if (empty($member)) {
                            //            data_set($retult, Constant::RESPONSE_CODE_KEY, 62003);
                            //            $retult['msg'] = ''; //中奖数据获取失败
                            //            return $retult;
                            //        }
                            $activityWinningId = data_get($member, 'id', 0);
                            if ($prizeType == 5) {//如果是积分，就添加积分到当前用户
                                $credit = data_get($prizeItem, 'type_value', 0); //类型数据(即积分)
                                $creditData = FunctionHelper::getHistoryData([
                                            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                                            'value' => $credit,
                                            'add_type' => 1,
                                            'action' => 'lottery',
                                            'ext_id' => $activityWinningId,
                                            'ext_type' => 'ActivityWinningLog',
                                                ], [Constant::DB_TABLE_STORE_ID => $storeId]);

                                CreditService::handle($creditData); //记录积分流水
                            }

                            data_set($prizeItem, Constant::ACTIVITY_WINNING_ID, $activityWinningId); //设置中奖流水id
                            data_set($prizeItem, Constant::DB_TABLE_COUNTRY, $prizeCountry); //设置中奖流水国家
                            data_set($retult, 'data.prizeData', $prizeItem); //设置中奖奖品

                            $isInited = static::initRank($storeId, $actId);
                            if ($isInited == false) {
                                return $retult;
                            }

                            if ($isParticipationAward == 1) {//如果是参与奖，就不要放到中奖排行榜中
                                return $retult;
                            }

                            //更新中奖排行榜数据
                            $zsetKey = static::getRankKey($storeId, $actId);
                            $score = Carbon::parse(data_get($member, Constant::DB_TABLE_UPDATED_AT, Carbon::now()->toDateTimeString()))->timestamp; //当前时间戳
                            unset($member[Constant::DB_TABLE_UPDATED_AT]);
                            Redis::zadd($zsetKey, $score, static::getZsetMember($member));
                            $ttl = 30 * 24 * 60 * 60;
                            Redis::expire($zsetKey, $ttl);

                            return $retult;
                        }
                    ],
                ]
            ]
        ];

        $rs = static::handleCache($tag, $handleCacheData);

        return $rs === false ? $defaultRs : $rs;
    }

    /**
     * 获取排行榜数据
     * @param int $storeId    商城id
     * @param int $actId      活动id
     * @param string $account 会员账号
     * @param int $page       当前页码
     * @param int $pageSize   每页记录条数
     * @return array
     */
    public static function getRankData($storeId = 0, $actId = 0, $account = '', $page = 1, $pageSize = 10) {

        $publicData = [
            'page' => $page,
            Constant::REQUEST_PAGE_SIZE => $pageSize,
        ];
        $_data = parent::getPublicData($publicData);

        $pagination = $_data[Constant::DB_EXECUTION_PLAN_PAGINATION];
        $offset = $pagination[Constant::DB_EXECUTION_PLAN_OFFSET];
        $count = $pagination[Constant::REQUEST_PAGE_SIZE];

        static::initRank($storeId, $actId); //初始化排行榜

        $zsetKey = static::getRankKey($storeId, $actId);

        //获取分页数据
        $customerCount = Redis::zcard($zsetKey);
        $pagination['total'] = $customerCount;
        $pagination['total_page'] = ceil($customerCount / $count);
        $rankData = [];
        if ($customerCount <= 0) {
            return [
                Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
                'data' => $rankData,
            ];
        }

        /*         * **************获取排行榜数据*************** */
        $options = [
            'withscores' => true,
            Constant::DB_EXECUTION_PLAN_LIMIT => [
                Constant::DB_EXECUTION_PLAN_OFFSET => $offset,
                'count' => $count,
            ]
        ];
        $data = Redis::zrevrangebyscore($zsetKey, '+inf', '-inf', $options);
        if (empty($data)) {
            return [
                Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
                'data' => $rankData,
            ];
        }

        //排行榜数据
        foreach ($data as $member => $score) {
            //$no = Redis::zrevrank($zsetKey, $member);
            //$member['no'] = $no + 1;
            $member = static::getSrcMember($member);

            data_set($member, Constant::DB_TABLE_ACCOUNT, FunctionHelper::handleAccount(data_get($member, Constant::DB_TABLE_ACCOUNT, '')));

//            if ($storeId == 1) {
//                $firstName = data_get($member, Constant::DB_TABLE_FIRST_NAME, '');
//                $lastName = data_get($member, Constant::DB_TABLE_LAST_NAME, '');
//                if ($firstName || $lastName) {
//                    data_set($member, Constant::DB_TABLE_ACCOUNT, ($firstName . ' ' . $lastName));
//                } else {
//                    data_set($member, Constant::DB_TABLE_ACCOUNT, FunctionHelper::handleAccount(data_get($member, Constant::DB_TABLE_ACCOUNT, '')));
//                }
//            }

            data_set($member, 'rank_at', Carbon::createFromTimestamp($score)->toDateTimeString()); //->rawFormat($dateFormat)
            $rankData[] = $member;
        }

        return [
            Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
            'data' => $rankData,
        ];
    }

    /**
     * 获取会员中奖列表缓存 tag
     * @return array
     */
    public static function getCustomerWinCacheTag() {
        return ['{customerWin}'];
    }

    /**
     * 获取会员中奖数据
     * @param int $storeId    商城id
     * @param int $actId      活动id
     * @param int $customerId 会员id
     * @param int $page       当前页码
     * @param int $pageSize   每页记录条数
     * @return array
     */
    public static function getItemData($storeId = 0, $actId = 0, $customerId = 0, $page = 1, $pageSize = 10) {

        //获取活动配置数据，并保存到缓存中
        $tags = Arr::collapse([config('cache.tags.activity', ['{activity}']), static::getCustomerWinCacheTag()]);
        $ttl = config('cache.ttl', 86400); //认证缓存时间 单位秒
        $cacheKey = md5(json_encode(func_get_args()));
        return Cache::tags($tags)->remember($cacheKey, $ttl, function () use($storeId, $actId, $customerId, $page, $pageSize) {
                    $publicData = [
                        Constant::DB_TABLE_STORE_ID => $storeId,
                        Constant::DB_TABLE_ACT_ID => $actId,
                        Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                        'page' => $page,
                        Constant::REQUEST_PAGE_SIZE => $pageSize,
                    ];
                    $_data = static::getPublicData($publicData, [['w.deleted_at', 'desc']]);

                    $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, []);
                    $order = data_get($_data, Constant::ORDER, []);
                    $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
                    $limit = data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10);
                    $offset = data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0);

                    $select = [
                        'p.id', ('p.' . Constant::DB_TABLE_NAME), 'p.img_url', 'p.mb_img_url', 'p.url',
                        ('p.' . Constant::DB_TABLE_TYPE), 'p.type_value',
                        'pi.type as item_type', 'pi.type_value as item_type_value',
                        'p.asin', 'pi.asin as item_asin',
                        'pi.id as item_id',
                        'w.' . Constant::DB_TABLE_COUNTRY,
                        'w.id as activity_winning_id',
                        'p.is_participation_award'
                    ]; //

                    $amazonHostData = DictService::getListByType('amazon_host', Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE);
                    $dbExecutionPlan = [
                        Constant::DB_EXECUTION_PLAN_PARENT => [
                            Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                            Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                            Constant::DB_EXECUTION_PLAN_BUILDER => null,
                            'make' => static::getModelAlias(),
                            'from' => 'activity_winning_logs as w',
                            Constant::DB_EXECUTION_PLAN_JOIN_DATA => [
                                [
                                    Constant::DB_EXECUTION_PLAN_TABLE => 'activity_prizes as p',
                                    Constant::DB_EXECUTION_PLAN_FIRST => 'p.id',
                                    Constant::DB_TABLE_OPERATOR => '=',
                                    Constant::DB_EXECUTION_PLAN_SECOND => ('w.' . Constant::DB_TABLE_PRIZE_ID),
                                    'type' => 'left',
                                ],
                                [
                                    Constant::DB_EXECUTION_PLAN_TABLE => 'activity_prize_items as pi',
                                    Constant::DB_EXECUTION_PLAN_FIRST => function ($join) {
                                        $join->on([[('pi.' . Constant::DB_TABLE_PRIMARY), '=', 'w.' . Constant::DB_TABLE_PRIZE_ITEM_ID], [('pi.' . Constant::DB_TABLE_PRIZE_ID), '=', ('w.' . Constant::DB_TABLE_PRIZE_ID)]]); //->where('b.status', '=', 1);
                                    },
                                    Constant::DB_TABLE_OPERATOR => null,
                                    Constant::DB_EXECUTION_PLAN_SECOND => null,
                                    'type' => 'left',
                                ],
                            ],
                            Constant::DB_OPERATION_SELECT => $select,
                            Constant::DB_EXECUTION_PLAN_WHERE => $where,
                            Constant::DB_EXECUTION_PLAN_ORDERS => $order,
                            Constant::DB_EXECUTION_PLAN_OFFSET => $offset,
                            Constant::DB_EXECUTION_PLAN_LIMIT => $limit,
                            Constant::DB_EXECUTION_PLAN_IS_PAGE => true,
                            Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_COUNT => false,
                            Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
                            Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                                'type' => [
                                    Constant::DB_EXECUTION_PLAN_FIELD => 'item_type{or}type',
                                    'data' => '',
                                    Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                                    Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                                    'glue' => '',
                                    Constant::DB_EXECUTION_PLAN_DEFAULT => 0,
                                ],
                                'type_value' => [
                                    Constant::DB_EXECUTION_PLAN_FIELD => 'item_type_value{or}type_value',
                                    'data' => '',
                                    Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                                    Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                                    'glue' => '',
                                    Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                                ],
                                Constant::PLATFORM_AMAZON => [
                                    Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_COUNTRY,
                                    'data' => $amazonHostData,
                                    Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                                    Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                                    'glue' => '',
                                    Constant::DB_EXECUTION_PLAN_DEFAULT => data_get($amazonHostData, 'US', ''),
                                ],
                                'asin' => [
                                    Constant::DB_EXECUTION_PLAN_FIELD => 'item_asin{or}asin',
                                    'data' => '',
                                    Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                                    Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                                    'glue' => '',
                                    Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                                ],
                                'amazon_url' => [//亚马逊链接 asin
                                    Constant::DB_EXECUTION_PLAN_FIELD => 'amazon{connection}asin',
                                    'data' => [],
                                    Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_STRING,
                                    Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                                    'glue' => '/dp/',
                                    Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                                ],
                                'url' => [//亚马逊链接
                                    Constant::DB_EXECUTION_PLAN_FIELD => 'url{or}amazon_url',
                                    'data' => [],
                                    Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_STRING,
                                    Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                                    'glue' => '',
                                    Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                                ],
                            ],
                            Constant::DB_EXECUTION_PLAN_UNSET => [Constant::PLATFORM_AMAZON, 'asin', 'item_asin', 'item_type', 'item_type_value',],
                        ],
                        'with' => [
                        ],
                        Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => [
                        ],
                            //'sqlDebug' => true,
                    ];

                    $dataStructure = 'list';
                    $flatten = false;
                    return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);
                });
    }

    /**
     * 后台列表
     * @param array $params 请求参数
     * @param boolean $toArray 是否转化为数组 true:是 false:否 默认:true
     * @param boolean $isPage  是否分页 true:是 false:否 默认:true
     * @param array $select  查询字段
     * @param boolean $isRaw  是否是原生sql true:是 false:否 默认:false
     * @return array 列表数据
     */
    public static function getAdminList($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {

        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, 0);
        $_data = static::getPublicData($params, [['w.id', 'desc']]);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, []);
        $order = data_get($params, 'orderBy', data_get($_data, Constant::ORDER, []));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, Constant::DB_EXECUTION_PLAN_LIMIT, data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0));
        $shareMode = static::createModel($storeId, 'ActivityShareLog');
        $address = DictStoreService::getByTypeAndKey($storeId, 'sweepstakes', 'address', true); //是否配置中奖收货地址

        $select = $select ? $select : ['w.id', ('p.' . Constant::DB_TABLE_NAME), ('p.' . Constant::DB_TABLE_TYPE), 'pi.type_value', 'pi.asin as item_asin', ('w.' . Constant::DB_TABLE_CUSTOMER_PRIMARY), 'w.ip', 'w.' . Constant::DB_TABLE_ACCOUNT, 'w.' . Constant::DB_TABLE_COUNTRY, ('w.' . Constant::DB_TABLE_IS_PARTICIPATION_AWARD), 'w.' . Constant::DB_TABLE_UPDATED_AT, 'activities.name as act_name']; //

        $generalType = [
            0 => '否',
            1 => '是'
        ];
        $type = DictService::getListByType('prize_type', Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE); //获取奖品类型配置
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => [
                Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                Constant::DB_EXECUTION_PLAN_BUILDER => null,
                'make' => static::getModelAlias(),
                'from' => 'activity_winning_logs as w',
                Constant::DB_EXECUTION_PLAN_JOIN_DATA => [
                    [
                        Constant::DB_EXECUTION_PLAN_TABLE => 'activity_prizes as p',
                        Constant::DB_EXECUTION_PLAN_FIRST => 'p.id',
                        Constant::DB_TABLE_OPERATOR => '=',
                        Constant::DB_EXECUTION_PLAN_SECOND => ('w.' . Constant::DB_TABLE_PRIZE_ID),
                        'type' => 'left',
                    ],
                    [
                        Constant::DB_EXECUTION_PLAN_TABLE => 'activity_prize_items as pi',
                        Constant::DB_EXECUTION_PLAN_FIRST => function ($join) {
                            $join->on([[('pi.' . Constant::DB_TABLE_PRIZE_ID), '=', ('w.' . Constant::DB_TABLE_PRIZE_ID)], [('pi.' . Constant::DB_TABLE_PRIMARY), '=', 'w.' . Constant::DB_TABLE_PRIZE_ITEM_ID]]); //->where('b.status', '=', 1);
                        },
                        Constant::DB_TABLE_OPERATOR => null,
                        Constant::DB_EXECUTION_PLAN_SECOND => null,
                        'type' => 'left',
                    ],
                    [
                        Constant::DB_EXECUTION_PLAN_TABLE => 'activities',
                        Constant::DB_EXECUTION_PLAN_FIRST => 'activities.id',
                        Constant::DB_TABLE_OPERATOR => '=',
                        Constant::DB_EXECUTION_PLAN_SECOND => ('w.' . Constant::DB_TABLE_ACT_ID),
                        'type' => 'left',
                    ],
                ],
                Constant::DB_OPERATION_SELECT => $select,
                Constant::DB_EXECUTION_PLAN_WHERE => $where,
                Constant::DB_EXECUTION_PLAN_ORDERS => $order,
                Constant::DB_EXECUTION_PLAN_OFFSET => $offset,
                Constant::DB_EXECUTION_PLAN_LIMIT => $limit,
                Constant::DB_EXECUTION_PLAN_IS_PAGE => $isPage,
                Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_COUNT => $isOnlyGetCount,
                Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                    'type' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'type',
                        'data' => $type,
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                    Constant::DB_TABLE_IS_PARTICIPATION_AWARD => [
                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_IS_PARTICIPATION_AWARD,
                        'data' => $generalType,
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                ],
            //Constant::DB_EXECUTION_PLAN_UNSET => [Constant::DB_TABLE_CUSTOMER_PRIMARY],
            ],
            'with' => [
                'customer_info' => [//关联用户详情表，获取中奖用户是否激活，最后登录时间
                    Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                    Constant::DB_EXECUTION_PLAN_STOREID => 0,
                    'relation' => 'hasOne',
                    Constant::DB_OPERATION_SELECT => [Constant::DB_TABLE_CUSTOMER_PRIMARY, 'isactivate'],
                    Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                        'isactivate' => [
                            Constant::DB_EXECUTION_PLAN_FIELD => 'customer_info.isactivate',
                            'data' => $generalType,
                            Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                            Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                            'glue' => '',
                            Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                        ],
                    ],
                    Constant::DB_EXECUTION_PLAN_UNSET => ['customer_info'],
                ],
                'customer' => [//关联会员表，获取中奖用户注册时间
                    Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                    Constant::DB_EXECUTION_PLAN_STOREID => 0,
                    'relation' => 'hasOne',
                    Constant::DB_OPERATION_SELECT => [Constant::DB_TABLE_CUSTOMER_PRIMARY, 'ctime'],
                    Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                        'created_at' => [
                            Constant::DB_EXECUTION_PLAN_FIELD => 'customer.ctime',
                            'data' => [],
                            Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                            Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                            'glue' => '',
                            Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                        ],
                    ],
                    Constant::DB_EXECUTION_PLAN_UNSET => ['customer'],
                ],
            ],
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => [
            ],
                //'sqlDebug' => true,
        ];

        if ($address) {//中奖实物时需要填写收货地址
            data_set($dbExecutionPlan, 'parent.joinData.3', [
                Constant::DB_EXECUTION_PLAN_TABLE => 'activity_addresses as a',
                Constant::DB_EXECUTION_PLAN_FIRST => function ($join) {
                    $join->on([['a.activity_winning_id', '=', 'w.id'], ['a.customer_id', '=', ('w.' . Constant::DB_TABLE_CUSTOMER_PRIMARY)]]); //->where('b.status', '=', 1);
                },
                Constant::DB_TABLE_OPERATOR => null,
                Constant::DB_EXECUTION_PLAN_SECOND => null,
                'type' => 'left',
            ]);
            data_set($dbExecutionPlan, 'parent.select', ['w.id', ('p.' . Constant::DB_TABLE_NAME), ('p.' . Constant::DB_TABLE_TYPE), 'pi.type_value', 'pi.asin as item_asin', ('w.' . Constant::DB_TABLE_CUSTOMER_PRIMARY), 'w.ip', 'w.' . Constant::DB_TABLE_ACCOUNT, 'w.' . Constant::DB_TABLE_COUNTRY, ('w.' . Constant::DB_TABLE_IS_PARTICIPATION_AWARD), 'w.' . Constant::DB_TABLE_UPDATED_AT, 'a.country as usercountry', 'a.full_name', 'a.street', 'a.apartment', 'a.city', 'a.state', 'a.zip_code', 'a.phone', 'a.account_link', 'activities.name as act_name']);
        }

        if (data_get($params, 'isOnlyGetPrimary', false)) {//如果仅仅获取主键id，就不需要处理数据，不关联
            data_set($dbExecutionPlan, 'parent.handleData', []);
            data_set($dbExecutionPlan, 'with', []);
        }

        $dataStructure = 'list';
        $flatten = false;
        $data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);

        if ($isGetQuery) {
            return $data;
        }
        foreach ($data as $key => $value) {
            if (!empty($value[Constant::DB_TABLE_CUSTOMER_PRIMARY])) {
                $share = $shareMode->where(Constant::DB_TABLE_CUSTOMER_PRIMARY, $value[Constant::DB_TABLE_CUSTOMER_PRIMARY])->count(); //导出获取分享次数
                data_set($data, $key . '.share_total', $share);
            }
        }
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $keys => $item) {
                    if (!empty($item[Constant::DB_TABLE_CUSTOMER_PRIMARY])) {
                        $share = $shareMode->where(Constant::DB_TABLE_CUSTOMER_PRIMARY, $item[Constant::DB_TABLE_CUSTOMER_PRIMARY])->count(); //列表获取分享次数
                        data_set($data, $key . '.' . $keys . '.share_times', $share);
                    }
                }
            }
        }
        return $data;
    }

    /**
     * 实物中奖收货地址
     * @param int $storeId 商店ID
     * @param int $winningId 产品ID
     * @param array $data 数据
     * @return array
     */
    public static function shippingAddress($storeId, $winningId) {
        return ActivityAddressService::existsOrFirst($storeId, '', [Constant::ACTIVITY_WINNING_ID => $winningId], true);
    }

    /**
     * IP限制抽奖用户数
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 用户id
     * @param string $account 账号
     * @return bool
     */
    public static function flopIpLimit($storeId, $actId, $customerId, $account) {
        $activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, 'winning', 'ip_limit_customer');
        $ipLimitCustomers = data_get($activityConfigData, 'winning_ip_limit_customer.value', 0);
        if (empty($ipLimitCustomers)) {
            return true;
        }

        $customerInfo = CustomerInfoService::existsOrFirst($storeId, '', [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId], true, [Constant::DB_TABLE_IP]);
        $ip = data_get($customerInfo, Constant::DB_TABLE_IP, Constant::PARAMETER_STRING_DEFAULT);
        $ipLimitKey = "$storeId:$actId:$ip";
        if (Redis::SISMEMBER($ipLimitKey, $account)) {
            return true;
        }

        $nums = Redis::SCARD($ipLimitKey);
        if ($nums < $ipLimitCustomers) {
            Redis::SADD($ipLimitKey, $account);
            Redis::EXPIRE($ipLimitKey, 86400);
            return true;
        }

        return false;
    }

    /**
     * 更新汇总数据
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param string $account 会员账号
     * @param array $extData 积分
     * @return int
     */
    public static function handleCreditLottery($storeId = 0, $actId = 0, $customerId = 0, $account = '', $extData = []) {

        $tag = static::getCacheTags();
        $cacheKey = $tag . ':' . $storeId . '：' . $actId . ':' . $customerId;

        $lockParameters = [
            function () use($storeId, $actId, $customerId, $account, $extData) {

                $retult = Response::getDefaultResponseData(1);

                $actionData = FunctionHelper::getJobData(ActivityService::getNamespaceClass(), 'get', [], $extData);
                $lotteryData = ActivityService::handleLimit($storeId, $actId, $customerId, $actionData);
                $lotteryNum = data_get($lotteryData, Constant::LOTTERY_NUM, 0);
                if ($lotteryNum <= 0) {
                    return Response::getDefaultResponseData(62000);
                }

                //扣除积分
                $actForm = data_get($extData, 'act_form', 'lottery'); //积分中奖的最大账号积分
                $_activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, $actForm, [
                            'deduct_credit',
                            'win_credit_max_account_credit'
                ]);
                $deductCredit = data_get($_activityConfigData, $actForm . '_' . 'deduct_credit' . Constant::LINKER . Constant::DB_TABLE_VALUE); //每次参与活动扣除积分
                $winCreditMaxAccountCredit = data_get($_activityConfigData, $actForm . '_' . 'win_credit_max_account_credit' . Constant::LINKER . Constant::DB_TABLE_VALUE); //积分中奖的最大账号积分
                $creditLogId = 0;
                if ($deductCredit) {
                    $creditData = FunctionHelper::getHistoryData([
                                Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                                'value' => $deductCredit,
                                'add_type' => 2,
                                'action' => 'lottery',
                                Constant::DB_TABLE_EXT_ID => $actId,
                                Constant::DB_TABLE_EXT_TYPE => ActivityService::getModelAlias(),
                                Constant::DB_TABLE_ACT_ID => $actId,
                                    ], [Constant::DB_TABLE_STORE_ID => $storeId]);

                    $creditData = CreditService::handle($creditData); //记录积分流水
                    $creditLogId = data_get($creditData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . 'creditLogId', 0);
                }

                $isWin = 0; //是否中奖 1：中奖  0:不中奖
                $prizeItem = [];
                data_set($retult, 'data.is_win', $isWin); //设置是否中奖
                data_set($retult, 'data.prizeData', $prizeItem);

                //获取用户基本资料
                $customerInfoData = CustomerInfoService::getData($storeId, $customerId);
                $customerCredit = data_get($customerInfoData, Constant::DB_TABLE_CREDIT, 0);
                $prizeCountry = data_get($customerInfoData, Constant::DB_TABLE_COUNTRY, '');
                $prizeCountry = $prizeCountry ? $prizeCountry : data_get($extData, Constant::DB_TABLE_COUNTRY, '');

                //获取抽奖活动配置数据
                $activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, 'winning');
                //获取奖品
                $prizeData = ActivityPrizeService::getCreditPrizeData($storeId, $actId, $customerId, $prizeCountry, [], $activityConfigData);
                if (empty($prizeData)) {
                    data_set($retult, Constant::RESPONSE_CODE_KEY, 62002);
                    return $retult;
                }

                $time = data_get($activityConfigData, 'winning_win_realthing_ttl.value', '+1 month');
                $dateFormat = data_get($activityConfigData, 'winning_win_realthing_date_format.value', 'Y-m-d H:i:s');
                $nowTime = Carbon::now()->toDateTimeString();
                $ttl = Carbon::parse(FunctionHelper::handleTime($nowTime, $time, $dateFormat))->timestamp - Carbon::now()->timestamp;

                //判断是否中过实物
                $winRealThingCacheTags = ['{winRealThingCredit}'];
                $winRealThingCacheKey = $storeId . ':' . $actId . ':' . $customerId;
                $isWinRealThing = Cache::tags($winRealThingCacheTags)->get($winRealThingCacheKey);
                if ($isWinRealThing === null) {
                    $realThingWhere = [
                        Constant::DB_TABLE_ACT_ID => $actId, //活动id
                        Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId, //账号id
                        'is_participation_award' => 0, //非参与奖
                        'prize_type' => 3, //奖品类型 0:其他 1:礼品卡 2:coupon 3:实物 5:活动积分
                        [[Constant::DB_TABLE_CREATED_AT, '>', Carbon::parse(FunctionHelper::handleTime($nowTime, str_replace('+', '-', $time), $dateFormat))->toDateTimeString()]]
                    ];
                    $isWinRealThing = static::existsOrFirst($storeId, '', $realThingWhere);
                    Cache::tags($winRealThingCacheTags)->put($winRealThingCacheKey, $isWinRealThing, $ttl);
                }

                /*                 * ***************更新奖品领取数量以便 提前占有 这些奖品，防止高并发多人领取没有库存的奖品 ************************ */
                $prizeIds = array_unique(array_filter(data_get($prizeData, '*.id', []))); //奖品id
                $prizeItemIds = array_unique(array_filter(data_get($prizeData, '*.item_id', []))); //奖品 item id
                if ($prizeItemIds) {
                    $where = [
                        'id' => $prizeItemIds,
                    ];
                    $data = [
                        Constant::DB_TABLE_QTY_RECEIVE => DB::raw(Constant::DB_TABLE_QTY_RECEIVE . '+1'),
                    ];
                    ActivityPrizeItemService::update($storeId, $where, $data);
                }

                if ($prizeIds) {
                    $where = [
                        'id' => $prizeIds,
                    ];
                    $data = [
                        Constant::DB_TABLE_QTY_RECEIVE => DB::raw(Constant::DB_TABLE_QTY_RECEIVE . '+1'),
                    ];
                    ActivityPrizeService::update($storeId, $where, $data);
                }

                foreach ($prizeData as $item) {

                    if (data_get($item, Constant::DB_TABLE_IS_PARTICIPATION_AWARD, 0) == 1) {
                        continue;
                    }

                    $num = mt_rand(1, data_get($item, 'max', 1));
                    if ($num <= data_get($item, 'winning_value', 1)) {//如果中奖了，就获取奖品
                        $prizeType = data_get($item, 'type', 0); //奖品类型 1:礼品卡 2:coupon 3:实物 5:活动积分 10000:其他

                        if ($prizeType == 5 && $winCreditMaxAccountCredit) {
                            if ($customerCredit < $winCreditMaxAccountCredit) {
                                $isWin = 1;
                            }
                        } else {
                            if ($prizeType == 3) {//如果是实物，并且在指定的日期内没有中过实物，就可以再次中实物
                                if (!$isWinRealThing) {
                                    $isWin = 1;
                                }
                            } else {
                                $isWin = 1;
                            }
                        }

                        if ($isWin == 1) {//如果中奖了，就把奖项给用户
                            $prizeItem = $item;
                            break;
                        }
                    }
                }

                data_set($retult, 'data.is_win', $isWin); //设置是否中奖

                if (empty($prizeItem)) {//如果没有中奖，就获取参与奖
                    $prizeData = collect($prizeData);
                    $prizeItem = $prizeData->where(Constant::DB_TABLE_IS_PARTICIPATION_AWARD, 1)->first();

                    $prizeType = data_get($prizeItem, 'type', 0); //奖品类型 1:礼品卡 2:coupon 3:实物 5:活动积分 10000:其他
                    if ($prizeType == 5 && $winCreditMaxAccountCredit) {
                        if ($customerCredit >= $winCreditMaxAccountCredit) {
                            $prizeItem = [];
                        }
                    } else {
                        if ($prizeType == 3 && $isWinRealThing) {//如果是实物，并且在指定的日期内中过实物，就不可以再次中实物
                            $prizeItem = [];
                        }
                    }
                }

                $prizeId = data_get($prizeItem, Constant::DB_TABLE_PRIMARY, 0); //奖品id
                $prizeItemId = data_get($prizeItem, Constant::DB_TABLE_ITEM_ID, 0); //奖品 item id

                /*                 * ***************更新奖品领取数量以便 释放 提前占有的奖品，防止高并发多人领取没有库存的奖品 ************************ */
                $prizeItemIds = array_diff($prizeItemIds, [$prizeItemId]);
                $prizeIds = array_diff($prizeIds, [$prizeId]);
                if ($prizeItemIds) {
                    $where = [
                        'id' => $prizeItemIds,
                    ];
                    $data = [
                        Constant::DB_TABLE_QTY_RECEIVE => DB::raw('qty_receive-1'),
                    ];
                    ActivityPrizeItemService::update($storeId, $where, $data);
                }

                if ($prizeIds) {
                    $where = [
                        'id' => $prizeIds,
                    ];
                    $data = [
                        Constant::DB_TABLE_QTY_RECEIVE => DB::raw('qty_receive-1'),
                    ];
                    ActivityPrizeService::update($storeId, $where, $data);
                }

                if (empty($prizeId)) {
                    return $retult;
                }

                $isParticipationAward = data_get($prizeItem, Constant::DB_TABLE_IS_PARTICIPATION_AWARD, 0); //是否是参与奖
                /*                 * ***************添加中奖数据************************ */
                $prizeType = data_get($prizeItem, Constant::DB_TABLE_TYPE, 0); //奖品类型
                $firstName = data_get($customerInfoData, Constant::DB_TABLE_FIRST_NAME, '');
                $lastName = data_get($customerInfoData, Constant::DB_TABLE_LAST_NAME, '');
                $data = [
                    Constant::DB_TABLE_ACCOUNT => $account,
                    Constant::DB_TABLE_IP => data_get($extData, Constant::DB_TABLE_IP, ''),
                    Constant::DB_TABLE_QUANTITY => 1,
                    Constant::DB_TABLE_IS_PARTICIPATION_AWARD => $isParticipationAward,
                    Constant::DB_TABLE_COUNTRY => $prizeCountry,
                    'prize_type' => $prizeType,
                    Constant::DB_TABLE_FIRST_NAME => $firstName,
                    Constant::DB_TABLE_LAST_NAME => $lastName,
                    'prize_id' => $prizeId,
                    'prize_item_id' => $prizeItemId,
                    Constant::DB_TABLE_ACT_ID => $actId,
                    Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                ];
                $activityWinningId = static::getModel($storeId)->insertGetId($data);

                //处理本地订单
                $nowTime = Carbon::now()->toDateTimeString();
                $platform = Constant::PLATFORM_SERVICE_LOCALHOST;
                $asin = implode('-', [$storeId, $prizeId, ActivityPrizeService::getModelAlias()]);
                $sku = implode('-', [$storeId, $prizeItemId, ActivityPrizeItemService::getModelAlias()]);
                $orderno = FunctionHelper::getUniqueId(FunctionHelper::randomStr(10));

//                $productData = static::getProduct($storeId, $platform, $prizeItem);
//                $productData = ProductService::handlePull($storeId, $platform, [$storeId, [Constant::DB_TABLE_PLATFORM => $platform]], $productData);
//                dump($productData);

                $orderCountry = strtoupper($prizeCountry);
                $orderItemData = [
                    [
                        Constant::DB_TABLE_PRIMARY => $orderno, //订单国家 item id
                        'auth_id' => 0,
                        Constant::DB_TABLE_AMAZON_ORDER_ID => $orderno, //订单no
                        Constant::DB_TABLE_ORDER_STATUS => 'Shipped', //订单状态 Pending Shipped Canceled
                        Constant::DB_TABLE_SHIP_SERVICE_LEVEL => '', //发货优先级
                        Constant::DB_TABLE_AMOUNT => 0, //订单金额
                        Constant::DB_TABLE_CURRENCY_CODE => '', //订单结算的货币
                        Constant::DB_TABLE_COUNTRY_CODE => '', //寄送地址 国家代码
                        Constant::DB_TABLE_PURCHASE_DATE => $nowTime, //下单日期(当前国家对应的时间)
                        Constant::DB_TABLE_PURCHASE_DATE_ORIGIN => $nowTime, //MWS接口下单时间 2017-05-01T00:01:05Z
                        Constant::DB_TABLE_RATE => 0, //汇率
                        Constant::DB_TABLE_RATE_AMOUNT => 0, //折算汇率金额
                        Constant::DB_TABLE_IS_REPLACEMENT_ORDER => 0, //是否替换订单 0 false | 1 true
                        Constant::DB_TABLE_IS_PREMIUM_ORDER => 0, //是否重要订单 0 false | 1 true
                        Constant::DB_TABLE_SHIPMENT_SERVICE_LEVEL_CATEGORY => '', //装运服务等级类别
                        Constant::DB_TABLE_LATEST_SHIP_DATE => $nowTime, //最新发货日期
                        Constant::DB_TABLE_EARLIEST_SHIP_DATE => $nowTime, //最早的发货日期
                        Constant::DB_TABLE_SALES_CHANNEL => '', //销售渠道
                        Constant::DB_TABLE_IS_BUSINESS_ORDER => 0, //是否B2B订单 0:否;1是
                        Constant::DB_TABLE_FULFILLMENT_CHANNEL => '', //发货渠道
                        Constant::DB_TABLE_PAYMENT_METHOD => '', //支付方式
                        Constant::DB_TABLE_IS_HAND => 1, //是否手工单 0:否;1是
                        Constant::DB_TABLE_ORDER_TYPE => 2, //订单类型,1:正常购买订单,2积分兑换订单,3秒杀订单,其他值待定义
                        Constant::DB_TABLE_LAST_UPDATE_DATE => $nowTime, //订单总表更新时间
                        Constant::DB_TABLE_MODFIY_AT_TIME => $nowTime, //订单item更新时间
                        Constant::DB_TABLE_ORDER_ITEM_ID => $nowTime, //订单item id
                        Constant::DB_TABLE_ASIN => $asin, //asin
                        'seller_sku' => $sku, //产品店铺sku
                        Constant::DB_TABLE_SKU => $sku, //产品店铺sku
                        Constant::DB_TABLE_LISITING_PRICE => 0, //sku的售价
                        Constant::DB_TABLE_PROMOTION_DISCOUNT_AMOUNT => 0, //促销所产生的折扣金额
                        Constant::DB_TABLE_TTEM_PRICE_AMOUNT => 0, //订单中sku的金额
                        Constant::DB_TABLE_QUANTITY_ORDERED => 1, //订单中的sku件数
                        Constant::DB_TABLE_QUANTITY_SHIPPED => 1, //订单中sku发货的件数
                        Constant::DB_TABLE_IS_GIFT => 0, //是否赠品 0 false | 1 true
                        Constant::DB_TABLE_SERIAL_NUMBER_REQUIRED => 0, //是否赠品 0 false | 1 true
                        Constant::DB_TABLE_IS_TRANSPARENCY => 0, //是否赠品 0 false | 1 true
                        Constant::DB_TABLE_IS_PRIME => 0, //是否会员 0:否;1是
                        Constant::DB_TABLE_BUYER_EMAIL => $account, //买家邮箱
                        Constant::DB_TABLE_BUYER_NAME => implode(' ', array_filter([$firstName, $lastName])), //买家名字
                        Constant::DB_TABLE_FIRST_NAME => $firstName,
                        Constant::DB_TABLE_LAST_NAME => $lastName,
                        Constant::DB_TABLE_SHIPPING_ADDRESS_NAME => '', //买家详细地址
                        Constant::DB_TABLE_STATE_OR_REGION => '', //收件地址 州/省
                        Constant::DB_TABLE_CITY => '', //城市
                        Constant::DB_TABLE_POSTAL_CODE => '', //邮编
                        Constant::DB_TABLE_ADDRESS_LINE_1 => '', //详细地址
                        Constant::DB_TABLE_ADDRESS_LINE_2 => '', //详细地址
                        Constant::DB_TABLE_ADDRESS_LINE_3 => '', //详细地址
                        Constant::DB_TABLE_ORDER_COUNTRY => $orderCountry,
                        Constant::DB_TABLE_PLATFORM_CUSTOMER_ID => $customerId,
                        Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                        Constant::DB_TABLE_IMG => data_get($prizeItem, Constant::DB_TABLE_IMG_URL, '') ?? '', //产品图片
                        Constant::FILE_TITLE => data_get($prizeItem, Constant::DB_TABLE_NAME) ?? '', //订单编号
                    ]
                ];
                $orderData = OrderService::handlePull($storeId, $platform, [$storeId, [Constant::DB_TABLE_PLATFORM => $platform], $orderItemData]);

                //添加订单积分属性
                $ownerResource = OrderService::getModelAlias();
                $orderId = data_get($orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . '0' . Constant::LINKER . Constant::DB_TABLE_UNIQUE_ID, 0); //订单唯一id

                OrderService::update($storeId, [Constant::DB_TABLE_UNIQUE_ID => $orderId], [Constant::DB_TABLE_IS_PARTICIPATION_AWARD => $isParticipationAward]); //设置积分抽奖订单是否安慰奖

                MetafieldService::insert($storeId, $platform, 0, $ownerResource, $orderId, Constant::POINT_STORE_NAME_SPACE, Constant::DB_TABLE_ACT_ID, $actId);
                MetafieldService::insert($storeId, $platform, 0, $ownerResource, $orderId, Constant::POINT_STORE_NAME_SPACE, Constant::DB_TABLE_CREDIT_LOG_ID, $creditLogId);
                MetafieldService::insert($storeId, $platform, 0, $ownerResource, $orderId, Constant::POINT_STORE_NAME_SPACE, Constant::ACTIVITY_WINNING_ID, $activityWinningId);
                MetafieldService::insert($storeId, $platform, 0, $ownerResource, $orderId, Constant::POINT_STORE_NAME_SPACE, Constant::DB_TABLE_EXT_TYPE, ActivityWinningService::getModelAlias());
                MetafieldService::insert($storeId, $platform, 0, $ownerResource, $orderId, Constant::POINT_STORE_NAME_SPACE, Constant::DB_TABLE_PLATFORM, $platform);

                $orderItemOwnerResource = OrderItemService::getModelAlias();
                $orderItemUniqueId = data_get($orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . '0.line_items.0' . Constant::LINKER . Constant::DB_TABLE_UNIQUE_ID);
                MetafieldService::insert($storeId, $platform, 0, $orderItemOwnerResource, $orderItemUniqueId, Constant::POINT_STORE_NAME_SPACE, Constant::PRODUCT_TYPE, $prizeType);
                MetafieldService::insert($storeId, $platform, 0, $orderItemOwnerResource, $orderItemUniqueId, Constant::POINT_STORE_NAME_SPACE, Constant::DB_TABLE_PRODUCT_URL, data_get($prizeItem, Constant::FILE_URL, Constant::PARAMETER_STRING_DEFAULT));
                MetafieldService::insert($storeId, $platform, 0, $orderItemOwnerResource, $orderItemUniqueId, Constant::POINT_STORE_NAME_SPACE, Constant::DB_TABLE_PRODUCT_CODE, data_get($prizeItem, 'type_value', Constant::PARAMETER_STRING_DEFAULT));
                MetafieldService::insert($storeId, $platform, 0, $orderItemOwnerResource, $orderItemUniqueId, Constant::POINT_STORE_NAME_SPACE, Constant::DB_TABLE_PRODUCT_COUNTRY, $orderCountry);
                MetafieldService::insert($storeId, $platform, 0, $orderItemOwnerResource, $orderItemUniqueId, Constant::POINT_STORE_NAME_SPACE, Constant::DB_TABLE_PLATFORM, $platform);

                if ($deductCredit) {
                    MetafieldService::insert($storeId, $platform, 0, $ownerResource, $orderId, Constant::POINT_STORE_NAME_SPACE, Constant::DB_TABLE_CREDIT, $deductCredit);
                    MetafieldService::insert($storeId, $platform, 0, $orderItemOwnerResource, $orderItemUniqueId, Constant::POINT_STORE_NAME_SPACE, Constant::DB_TABLE_CREDIT, $deductCredit);
                }

                if ($creditLogId) {

                    $where = [
                        Constant::DB_TABLE_UNIQUE_ID => $orderItemUniqueId, //订单item 唯一id
                    ];
                    $orderItemData = OrderItemService::existsOrFirst($storeId, '', $where, true, [Constant::DB_TABLE_PRIMARY]);
                    $orderItemId = data_get($orderItemData, Constant::DB_TABLE_PRIMARY, 0); //订单item id
                    $creditLog = [
                        Constant::DB_TABLE_EXT_ID => $orderItemId,
                        Constant::DB_TABLE_EXT_TYPE => $orderItemOwnerResource,
                    ];
                    CreditService::update($storeId, [Constant::DB_TABLE_PRIMARY => $creditLogId], $creditLog);
                }

                if ($isParticipationAward != 1 && $prizeType == 3) {//如果是实物，就标识用户已经中过实物
                    Cache::tags($winRealThingCacheTags)->put($winRealThingCacheKey, 1, $ttl);
                }

                //更新会员中奖列表缓存
                $tags = static::getCustomerWinCacheTag();
                Cache::tags($tags)->flush();

                //获取中奖流水数据
                $member = static::getItem($storeId, $actId, $prizeId, $customerId, $prizeItemId);
                $activityWinningId = data_get($member, 'id', 0);
                if ($prizeType == 5) {//如果是积分，就添加积分到当前用户
                    $credit = data_get($prizeItem, 'type_value', 0); //类型数据(即积分)
                    $creditData = FunctionHelper::getHistoryData([
                                Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                                'value' => $credit,
                                'add_type' => 1,
                                'action' => 'lottery',
                                'ext_id' => $activityWinningId,
                                'ext_type' => 'ActivityWinningLog',
                                Constant::DB_TABLE_ACT_ID => $actId,
                                    ], [Constant::DB_TABLE_STORE_ID => $storeId]);

                    CreditService::handle($creditData); //记录积分流水
                }

                data_set($prizeItem, Constant::ACTIVITY_WINNING_ID, $activityWinningId); //设置中奖流水id
                data_set($prizeItem, Constant::DB_TABLE_COUNTRY, $prizeCountry); //设置中奖流水国家
                data_set($prizeItem, Constant::DB_TABLE_ORDER_UNIQUE_ID, $orderId); //订单 唯一 id
                data_set($retult, 'data.prizeData', $prizeItem); //设置中奖奖品

                $isInited = static::initRank($storeId, $actId);
                if ($isInited == false) {
                    return $retult;
                }

                if ($isParticipationAward == 1) {//如果是参与奖，就不要放到中奖排行榜中
                    return $retult;
                }

                //更新中奖排行榜数据
                $zsetKey = static::getRankKey($storeId, $actId);
                $score = Carbon::parse(data_get($member, Constant::DB_TABLE_UPDATED_AT, Carbon::now()->toDateTimeString()))->timestamp; //当前时间戳
                unset($member[Constant::DB_TABLE_UPDATED_AT]);
                Redis::zadd($zsetKey, $score, static::getZsetMember($member));
                Redis::expire($zsetKey, $ttl);

                return $retult;
            }
        ];
        $rs = static::handleLock([$cacheKey], $lockParameters);

        return $rs === false ? Response::getDefaultResponseData(62007) : $rs;
    }

    /**
     * 产品获取
     * @param int $storeId 商城id
     * @param string $createdAtMin    最小创建时间
     * @param string $createdAtMax    最大创建时间
     * @param array $ids              shopify会员id
     * @param string $sinceId shopify 会员id
     * @param string $publishedAtMin  最小发布时间
     * @param string $publishedAtMax  最大发布时间
     * @param string $publishedStatus 发布状态
     * @param array $fields 字段数据
     * @param int $limit 记录条数
     * @param int $source 数据获取方式 1:定时任务拉取
     * @return array
     */
    public static function getProduct($storeId = 0, $platform = Constant::PLATFORM_SERVICE_AMAZON, $prizeItem = []) {
        $storeId = static::castToString($storeId);

        $prizeId = data_get($prizeItem, Constant::DB_TABLE_PRIMARY, 0); //奖品id
        $prizeItemId = data_get($prizeItem, Constant::DB_TABLE_ITEM_ID, 0); //奖品 item id

        $country = strtoupper(data_get($prizeItem, Constant::DB_TABLE_COUNTRY) ?? '');

        $asin = implode('-', [$storeId, $prizeId, ActivityPrizeService::getModelAlias()]);
        $sku = implode('-', [$storeId, $prizeItemId, ActivityPrizeItemService::getModelAlias()]);

        $productUniqueId = PlatformServiceManager::handle($platform, 'Product', 'getProductUniqueId', [$storeId, $platform, $country, $asin]); //平台产品唯一id

        $createdAt = data_get($prizeItem, Constant::DB_TABLE_CREATED_AT); //创建时间
        $updatedAt = data_get($prizeItem, Constant::DB_TABLE_UPDATED_AT); //更新时间

        $imageSrc = data_get($prizeItem, Constant::DB_TABLE_IMG_URL, '') ?? ''; //产品图片
        $productId = $productUniqueId; //平台产品 主键id
        $productItem = [
            Constant::DB_TABLE_PRIMARY => $productId, //平台产品 主键id
            Constant::DB_TABLE_UNIQUE_ID => $productUniqueId, //产品唯一id
            Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform), //平台
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_PLATFORM_CREATED_AT => $createdAt, //创建时间
            Constant::DB_TABLE_PLATFORM_UPDATED_AT => $updatedAt, //更新时间
            Constant::DB_TABLE_PRODUCT_ID => $productId, //平台产品 主键id
            Constant::FILE_TITLE => data_get($prizeItem, Constant::DB_TABLE_NAME) ?? '', //订单编号
            'body_html' => '', //订单编号
            'vendor' => '',
            'product_type' => '',
            'handle' => '',
            'platform_published_at' => FunctionHelper::handleTime(data_get($prizeItem, Constant::DB_TABLE_UPDATED_AT)),
            'template_suffix' => '',
            'published_scope' => '',
            'tags' => '',
            'admin_graphql_api_id' => '',
            'image_src' => $imageSrc,
            Constant::DB_TABLE_ASIN => $asin,
            Constant::DB_TABLE_COUNTRY => $country,
            Constant::DB_TABLE_STATUS => 1,
        ];

        $images = [];
        $imageId = PlatformServiceManager::handle($platform, 'Product', 'getImageUniqueId', [$storeId, $productUniqueId, $imageSrc]);
        $_images = [$imageId];
        $images[] = [
            Constant::DB_TABLE_UNIQUE_ID => $imageId, //唯一id
            Constant::DB_TABLE_PRODUCT_UNIQUE_ID => $productUniqueId, //产品 唯一id
            Constant::DB_TABLE_PLATFORM_CREATED_AT => $createdAt, //创建时间
            Constant::DB_TABLE_PLATFORM_UPDATED_AT => $updatedAt, //更新时间
            'image_id' => $imageId,
            Constant::DB_TABLE_PRODUCT_ID => $productId, //平台产品 主键id
            'position' => '',
            'alt' => '',
            'width' => 0,
            'height' => 0,
            'src' => $imageSrc,
            'admin_graphql_api_id' => '',
        ];

        $productItem['images'] = $images;

        return [$productItem];
    }

}
