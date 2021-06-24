<?php

/**
 * 活动服务
 * User: Jmiy
 * Date: 2019-06-14
 * Time: 16:50
 */

namespace App\Services;

use Hyperf\Utils\Arr;
use App\Utils\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\Activity;
use App\Utils\FunctionHelper;
use App\Constants\Constant;
use App\Utils\Support\Facades\Redis;
use App\Utils\Response;

class ActivityService extends BaseService {

    /**
     * 检查记录是否存在
     * @param int $storeId
     * @param string $account
     * @param boolean $getData true:获取数据  false:获取是否存在标识
     * @return bool|object|null $rs
     */
    public static function exists($storeId = 0, $name = '', $getData = false) {

        $where = [];

        if ($name) {
            $where[Constant::DB_TABLE_NAME] = $name;
        }

        return static::existsOrFirst($storeId, '', $where, $getData);
    }

    /**
     * 添加积分记录
     * @param $storeId
     * @param $data
     * @return bool
     */
    public static function insert($storeId, $data) {
        return static::getModel($storeId)->insertGetId($data);
    }

    /**
     * 获取db query
     * @param array $where
     * @return \Illuminate\Database\Query\Builder|static $query
     */
    public static function getQuery($storeId, $where = []) {
        return static::getModel($storeId)->buildWhere($where);
    }

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @return array
     */
    public static function getPublicData($params, $order = []) {

        $where = [];

        $type = Arr::get($params, 'type', 0); //活动类型 1:日常活动 2:注册拉新活动 3:大型活动
        if ($type) {
            $where[] = ['type', '=', $type];
        }

        $name = Arr::get($params, Constant::DB_TABLE_NAME, '');
        if ($name) {//活动名称
            $where[] = [Constant::DB_TABLE_NAME, '=', $name];
        }

        $actType = Arr::get($params, Constant::DB_TABLE_ACT_TYPE, '');
        if ($actType) {//活动类型
            $where[] = [Constant::DB_TABLE_ACT_TYPE, '=', $actType];
        }

        $start_time = Arr::get($params, 'start_time', '');
        if ($start_time) {//开始时间
            $where[] = [Constant::DB_TABLE_START_AT, '<=', $start_time];
        }

        $end_time = Arr::get($params, 'end_time', '');
        if ($end_time) {//结束时间
            $where[] = [Constant::DB_TABLE_END_AT, '>=', $end_time];
        }

        $start_at = Arr::get($params, 'start_at', '');
        if ($start_at) {//开始时间
            $where[] = [Constant::DB_TABLE_START_AT, '>=', $start_at];
        }

        $end_at = Arr::get($params, 'end_at', '');
        if ($end_at) {//结束时间
            $where[] = [Constant::DB_TABLE_END_AT, '<=', $end_at];
        }

        $_where = [];
        if (data_get($params, Constant::DB_TABLE_PRIMARY, 0)) {
            $_where[Constant::DB_TABLE_PRIMARY] = $params[Constant::DB_TABLE_PRIMARY];
        }

        if ($where) {
            $_where[] = $where;
        }


        $order = $order ? $order : [Constant::DB_TABLE_PRIMARY, 'desc'];
        return Arr::collapse([parent::getPublicData($params, $order), [
                        Constant::DB_EXECUTION_PLAN_WHERE => $_where,
        ]]);
    }

    /**
     * 获取有效的活动数据
     * @param int $storeId  商城id
     * @param int $type 活动类型 1:日常活动 2:注册拉新活动 3:大型活动
     * @param boolean $isPage 是否分页 true:是  false:否
     * @param int $page 分页页码
     * @param int $pageSize 每个分页记录条数
     * @param array $select 要查询的字段
     * @param boolean $isRaw 是否原始查询语句 true:是 false:否 默认:false
     * @return array|null $data
     */
    public static function getValidData($storeId = 0, $type = 3, $isPage = false, $page = 1, $pageSize = 1, $select = [], $isRaw = false, $isGetQuery = false) {

        if (empty($storeId)) {
            return [];
        }

        $ttl = 2 * 60 * 60; //缓存2小时 单位秒
        $tags = config('cache.tags.activity');
        $key = md5(json_encode(func_get_args()));
        return Cache::tags($tags)->remember($key, $ttl, function () use($storeId, $type, $isPage, $page, $pageSize, $select, $isRaw, $isGetQuery) {
                    $params = [
                        'type' => $type,
                        'page' => $page,
                        'page_size' => $pageSize,
                    ];

                    $_data = static::getPublicData($params);

                    $where = $_data['where'];
                    $order = $_data['order'];
                    $pagination = $_data[Constant::DB_EXECUTION_PLAN_PAGINATION];
                    $limit = $pagination['page_size'];

                    $query = static::getQuery($storeId, $where);
                    $nowTime = Carbon::now()->toDateTimeString();
                    $query = $query->where(function ($query) use($nowTime) {
                                $query->whereNull(Constant::DB_TABLE_START_AT)->orWhere(Constant::DB_TABLE_START_AT, '<=', $nowTime);
                            })
                            ->where(function ($query) use($nowTime) {
                        $query->whereNull(Constant::DB_TABLE_END_AT)->orWhere(Constant::DB_TABLE_END_AT, '>=', $nowTime);
                    });

                    $customerCount = true;
                    if ($isPage) {
                        $customerCount = $query->count();
                        $pagination['total'] = $customerCount;
                        $pagination['total_page'] = ceil($customerCount / $limit);
                    }

                    if (empty($customerCount)) {
                        $query = null;
                        return [
                            'data' => [],
                            Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
                        ];
                    }

                    $query = $query->orderBy($order[0], $order[1]);
                    $data = [
                        'query' => $query,
                        Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
                    ];

                    $select = $select ? $select : ['*'];

                    return static::getList($data, true, $isPage, $select, $isRaw, $isGetQuery);
                });
    }

    /**
     * 获取有效的活动id
     * @param int $storeId  商城id
     * @param int $type 活动类型 1:日常活动 2:注册拉新活动 3:大型活动
     * @param boolean $isPage 是否分页 true:是  false:否
     * @param int $page 分页页码
     * @param int $pageSize 每个分页记录条数
     * @param array $select 要查询的字段
     * @param boolean $isRaw 是否原始查询语句 true:是 false:否 默认:false
     * @return array|null $data
     */
    public static function getValidActIds($storeId = 0, $type = 3, $isPage = false, $page = 1, $pageSize = 1, $select = [], $isRaw = false, $isGetQuery = false) {

        $activityData = static::getValidData($storeId, $type, $isPage, $page, $pageSize, $select, $isRaw, $isGetQuery);

        return $page == 1 && $pageSize == 1 ? Arr::get($activityData, 'data.0.id', 0) : Arr::pluck(Arr::get($activityData, 'data', []), Constant::DB_TABLE_PRIMARY);
    }

    /**
     * 活动类型 1:九宫格 2:转盘 3:砸金蛋 4:翻牌 5:邀请好友注册 6:上传图片投票
     * @return array
     */
    public static function getActType($key = null, $default = null) {
        $data = [
            '九宫格' => 1,
            '转盘' => 2,
            '砸金蛋' => 3,
            '翻牌' => 4,
            '邀请好友注册' => 5,
            '上传图片投票' => 6,
            1 => '九宫格',
            2 => '转盘',
            3 => '砸金蛋',
            4 => '翻牌',
            5 => '邀请好友注册',
            6 => '上传图片投票',
        ];
        return data_get($data, $key, $default);
    }

    /**
     * 活动列表
     * @param array $params 请求参数
     * @param boolean $toArray 是否转化为数组 true:是 false:否 默认:true
     * @param boolean $isPage  是否分页 true:是 false:否 默认:true
     * @param array $select  查询字段
     * @param boolean $isRaw 是否原始 select true:是 false:否 默认:false
     * @param boolean $isGetQuery 是否获取 query
     * @param boolean $isOnlyGetCount 是否仅仅获取总记录数
     * @return array|\Hyperf\Database\Model\Builder 列表数据|Builder
     */
    public static function getListData($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {

        $_data = static::getPublicData($params);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, null);

        if (empty(data_get($params, Constant::DB_TABLE_PRIMARY, 0))) {
            $where[Constant::DB_TABLE_ACT_TYPE] = array_keys(static::getActType());
        }

        $order = data_get($params, 'orderBy', data_get($_data, 'order', []));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, 'limit', data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0));
        $storeId = Arr::get($params, Constant::DB_TABLE_STORE_ID, 0);

        $select = $select ? $select : [
            Constant::DB_TABLE_PRIMARY,
            Constant::DB_TABLE_NAME,
            Constant::DB_TABLE_START_AT,
            Constant::DB_TABLE_END_AT,
            Constant::DB_TABLE_CREATED_AT,
            Constant::DB_TABLE_UPDATED_AT,
            Constant::DB_TABLE_ACT_TYPE,
            Constant::DB_TABLE_MARK,
            Constant::FILE_URL,
        ];

        $actTypeData = static::getActType();
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => [
                Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                Constant::DB_EXECUTION_PLAN_BUILDER => null,
                Constant::DB_EXECUTION_PLAN_MAKE => static::getModelAlias(),
                Constant::DB_EXECUTION_PLAN_FROM => Constant::PARAMETER_STRING_DEFAULT,
                Constant::DB_EXECUTION_PLAN_SELECT => $select,
                Constant::DB_EXECUTION_PLAN_WHERE => $where,
                Constant::DB_EXECUTION_PLAN_ORDERS => [$order],
                Constant::DB_EXECUTION_PLAN_LIMIT => $limit,
                Constant::DB_EXECUTION_PLAN_OFFSET => $offset,
                Constant::DB_EXECUTION_PLAN_IS_PAGE => $isPage,
                Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
                Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_COUNT => $isOnlyGetCount,
                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                    'act_type_show' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_ACT_TYPE,
                        Constant::RESPONSE_DATA_KEY => $actTypeData,
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_STRING,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DEFAULT => Constant::PARAMETER_STRING_DEFAULT,
                    ],
                ],
                Constant::DB_EXECUTION_PLAN_UNSET => [],
            ],
            Constant::DB_EXECUTION_PLAN_WITH => [
            ],
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => [
                Constant::DB_EXECUTION_PLAN_FIELD => null, //数据字段
                Constant::RESPONSE_DATA_KEY => [], //数据映射map
                Constant::DB_EXECUTION_PLAN_DATATYPE => '', //数据类型
                Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '', //数据格式
                Constant::DB_EXECUTION_PLAN_TIME => '', //时间处理句柄
                Constant::DB_EXECUTION_PLAN_GLUE => '', //分隔符或者连接符
                Constant::DB_EXECUTION_PLAN_IS_ALLOW_EMPTY => true, //是否允许为空 true：是  false：否
                Constant::DB_EXECUTION_PLAN_DEFAULT => '', //默认值$default
                Constant::DB_EXECUTION_PLAN_CALLBACK => [
                    Constant::DB_TABLE_START_AT => function ($item) {
                        return FunctionHelper::getShowTime(data_get($item, Constant::DB_TABLE_START_AT, 'null'));
                    },
                    Constant::DB_TABLE_END_AT => function ($item) {
                        return FunctionHelper::getShowTime(data_get($item, Constant::DB_TABLE_END_AT, 'null'));
                    },
                    'act_time' => function ($item) {
                        $field = [
                            Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_START_AT . Constant::DB_EXECUTION_PLAN_CONNECTION . Constant::DB_TABLE_END_AT,
                            Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
                            Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_STRING,
                            Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
                            Constant::DB_EXECUTION_PLAN_GLUE => '-',
                            Constant::DB_EXECUTION_PLAN_DEFAULT => Constant::PARAMETER_STRING_DEFAULT,
                        ];
                        return FunctionHelper::handleData($item, $field);
                    },
                ],
                'only' => [
                ],
            ],
                //Constant::DB_EXECUTION_PLAN_DEBUG => true,
        ];

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

        return $data;
    }

    /**
     * 获取活动数据
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param array $select 查询的字段
     * @param array $dbExecutionPlan sql执行计划
     * @param boolean $flatten 是否合并
     * @param boolean $isGetQuery 是否获取查询句柄
     * @param array $where where条件
     * @return array 活动数据
     */
    public static function getActivityData($storeId = 0, $actId = 0, $select = [], $dbExecutionPlan = [], $flatten = false, $isGetQuery = false, $where = null, $order = [], $limit = null, $offset = null) {

        $select = $select ? $select : Activity::getColumns();
        if (empty(Arr::exists($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_PARENT))) {
            $where = $where === null ? [Constant::DB_TABLE_PRIMARY => $actId] : $where;
            $parent = FunctionHelper::getExePlan($storeId, null, static::getModelAlias(), '', $select, $where, $order, $limit, $offset);
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_PARENT, $parent);
        }

        $dataStructure = 'one';
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    /**
     * 获取活动配置数据
     * @param int $storeId 商城id
     * @param int $actId  活动id
     * @param string|array $type 配置类型
     * @param string $key  配置项key
     * @return array $data ['registered_is_need_activate' => [
      'type'=>'registered',
      'key'=>'is_need_activate',
      'value'=>1,
      Constant::RESPONSE_MSG_KEY=>'A verification email is sent to your inbox, please verify account before applying Free Product Testi',
      'landing_url'=>'https://www.xmpow.com/pages/product-activity',
      ]]
     */
    public static function getActivityConfigData($storeId = 0, $actId = 0, $type = '', $key = '', $orderBy = []) {

        if (empty($actId)) {
            return [];
        }

        //获取活动配置数据，并保存到缓存中
        $tags = config('cache.tags.activity', ['{activity}']);
        $ttl = config('cache.ttl', 86400); //认证缓存时间 单位秒
        $cacheKey = 'configs:' . md5(json_encode(func_get_args()));
        return Cache::tags($tags)->remember($cacheKey, $ttl, function () use($storeId, $actId, $type, $key, $orderBy) {

                    $where = ['activity_id' => $actId];
                    if ($type) {
                        data_set($where, 'type', $type);
                    }

                    if ($key) {
                        data_set($where, 'key', $key);
                    }

                    //获取活动配置数据
                    $dbExecutionPlan = [
                        Constant::DB_EXECUTION_PLAN_PARENT => [
                            'setConnection' => true,
                            'storeId' => $storeId,
                            'builder' => null,
                            'relation' => 'hasMany',
                            'make' => 'ActivityConfig',
                            'from' => '',
                            'select' => [
                                'activity_id',
                                'type',
                                'key',
                                'value',
                                Constant::RESPONSE_MSG_KEY,
                                'landing_url',
                            ],
                            'where' => $where,
                            Constant::DB_EXECUTION_PLAN_ORDERS => $orderBy,
                            'handleData' => [
                                'key' => [
                                    'field' => 'type{connection}key',
                                    'data' => [],
                                    'dataType' => Constant::DB_EXECUTION_PLAN_DATATYPE_STRING,
                                    'dateFormat' => '',
                                    'glue' => '_',
                                    'default' => '',
                                ],
                            ],
                        //'unset' => ['configs'],
                        ],
                            //'sqlDebug' => true,
                    ];

                    $dataStructure = 'list';
                    $flatten = false;
                    $data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);
                    return Arr::pluck($data, null, 'key');
                });
    }

    /**
     * 获取要清空的tags
     * @return array
     */
    public static function getClearTags() {
        return ['activity'];
    }

    /**
     * 处理活动各种限制
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $customerId 账号id
     * @param array $actionData 缓存操作数据
     * @return mix
     */
    public static function handleLimit($storeId = 0, $actId = 0, $customerId = 0, $actionData = []) {

        $tag = 'lotteryLimit';
        $key = $tag . ':' . $storeId . ':' . $actId . ':' . $customerId; //记录用户剩余的次数
        $statisticsKey = 'lotteryStatistics:' . $storeId . ':' . $actId . ':' . $customerId; //记录用户添加的次数
        $lotteryTotalKey = 'lotteryTotal:' . $storeId . ':' . $actId . ':' . $customerId; //记录用户总次数

        $actForm = data_get($actionData, 'requestData.act_form', 'lottery');
        $limitKey = Constant::ACT_DAY_LIMIT_KEY;
        $timestamp = Carbon::now()->timestamp;
        $ttl = (Carbon::parse(Carbon::now()->rawFormat('Y-m-d 23:59:59'))->timestamp) - $timestamp; //缓存时间 单位秒

        if ($actForm && $actForm != 'lottery') {
            $key .= ':' . $actForm;
            $statisticsKey .= ':' . $actForm;
            $lotteryTotalKey .= ':' . $actForm;
        }

        $handleCacheData = FunctionHelper::getJobData(static::getNamespaceClass(), 'has', [$key]);

        $service = data_get($actionData, Constant::SERVICE_KEY, '');
        $method = data_get($actionData, Constant::METHOD_KEY, '');
        $parameters = data_get($actionData, Constant::PARAMETERS_KEY, []);

        $actData = static::getModel($storeId)->where([Constant::DB_TABLE_PRIMARY => $actId])->select([Constant::DB_TABLE_END_AT])->first();
        if ($actData === null) {//如果活动不存在,并且是活动不存在就不可以执行的请求就直接提示
            if ($method == 'get') {
                return [
                    Constant::LOTTERY_NUM => 0, //剩余次数
                    Constant::LOTTERY_TOTAL => 0, //已经获得的次数
                    Constant::ACT_TOTAL => 0, //总次数
                    'lotteryUsedNum' => 0, //已经参与活动次数
                ];
            }

            return -1;
        }

        $nowTime = Carbon::now()->toDateTimeString();
        $endAt = data_get($actData, Constant::DB_TABLE_END_AT, null);
        if ($endAt !== null && $nowTime > $endAt) {
            if ($method == 'get') {
                return [
                    Constant::LOTTERY_NUM => 0, //剩余次数
                    Constant::LOTTERY_TOTAL => 0, //已经获得的次数
                    Constant::ACT_TOTAL => 0, //总次数
                    'lotteryUsedNum' => 0, //已经参与活动次数
                ];
            }
            return -1;
        }

        $actEndAt = null;

        if ($endAt !== null) {
            $actEndAt = Carbon::parse($endAt)->timestamp;
        }

        $activityConfigData = static::getActivityConfigData($storeId, $actId, $actForm, [
                    Constant::ACT_LIMIT_KEY,
                    Constant::ACT_MONTH_LIMIT_KEY,
                    Constant::ACT_WEEK_LIMIT_KEY,
                    Constant::ACT_DAY_LIMIT_KEY,
                    'is_credit_count',
                    'deduct_credit',
                    'day_max_play_nums'
        ]);
        $limit = data_get($activityConfigData, $actForm . '_' . Constant::ACT_LIMIT_KEY . Constant::LINKER . Constant::DB_TABLE_VALUE, null);
        $isCreditCount = data_get($activityConfigData, $actForm . '_' . 'is_credit_count' . Constant::LINKER . Constant::DB_TABLE_VALUE); //活动是否使用积分计算参与机会
        $deductCredit = data_get($activityConfigData, $actForm . '_' . 'deduct_credit' . Constant::LINKER . Constant::DB_TABLE_VALUE); //每次参与活动扣除积分
        $dayMaxPlayNums = data_get($activityConfigData, $actForm . '_' . 'day_max_play_nums' . Constant::LINKER . Constant::DB_TABLE_VALUE); //每天最多可以参与活动的次数
        if ($limit !== null) {
            $limitKey = Constant::ACT_LIMIT_KEY;
            $ttl = $actEndAt === null ? (30 * 24 * 60 * 60) : ($actEndAt - $timestamp); //缓存时间 单位秒
        } else if (data_get($activityConfigData, $actForm . '_' . Constant::ACT_MONTH_LIMIT_KEY . Constant::LINKER . Constant::DB_TABLE_VALUE, null) !== null) {
            $limitKey = Constant::ACT_MONTH_LIMIT_KEY;
            $time = strtotime('+1month');
            $ttl = $actEndAt === null ? ($time - $timestamp) : ($time > $actEndAt ? ($actEndAt - $timestamp) : ($time - $timestamp)); //缓存时间 单位秒
        } else if (data_get($activityConfigData, $actForm . '_' . Constant::ACT_WEEK_LIMIT_KEY . Constant::LINKER . Constant::DB_TABLE_VALUE, null) !== null) {
            $limitKey = Constant::ACT_WEEK_LIMIT_KEY;
            $time = strtotime('+1week');
            $ttl = $actEndAt === null ? ($time - $timestamp) : ($time > $actEndAt ? ($actEndAt - $timestamp) : ($time - $timestamp)); //缓存时间 单位秒
        }

        if ($limitKey && $limitKey != Constant::ACT_DAY_LIMIT_KEY) {
            $key .= ':' . $limitKey;
            $statisticsKey .= ':' . $limitKey;
            $lotteryTotalKey .= ':' . $limitKey;
        }

        data_set($handleCacheData, Constant::PARAMETERS_KEY, [$key]);
        $isHas = static::handleCache($tag, $handleCacheData);
        //初始化活动次数
        if (!$isHas) {
            $parametersData = [
                [$key, 1, $ttl], //记录用户剩余的抽奖次数
                [$lotteryTotalKey, 1, $ttl], //记录用户 总抽奖次数
                [$statisticsKey, 0, $ttl], //记录用户添加的抽奖次数
            ];
            //老虎机游戏初始化次数及设置dateKey
            if ($actForm == Constant::ACT_FORM_SLOT_MACHINE) {
                $initPlayNums = GameService::getPlayNums($storeId, $actId, 'add_nums', 'init');
                $parametersData = [
                    [$key, $initPlayNums, $ttl], //记录用户剩余的抽奖次数
                    [$lotteryTotalKey, $initPlayNums, $ttl], //记录用户总抽奖次数
                    [$statisticsKey, 0, $ttl], //记录用户添加的抽奖次数
                ];
            }

            //dateKey控制后续参与游戏赠送次数，后续参与，一天送一次
            $dateKey = "every_{$storeId}_{$actId}_{$customerId}_" . date("Ymd");
            //过期时间至当天末尾
            $expireTime = strtotime(date("Y-m-d 23:59:59")) - time();
            Redis::setex($dateKey, $expireTime, 1);

            foreach ($parametersData as $_parameters) {
                data_set($handleCacheData, Constant::METHOD_KEY, 'put');
                data_set($handleCacheData, Constant::PARAMETERS_KEY, $_parameters);
                static::handleCache($tag, $handleCacheData);
            }

            if ($storeId == 1) {//如果是mpow，就根据参加投票情况，添加抽奖次数
                //默认一个自然日内登录账号可以玩1次，分享活动到社媒平台可以再额外获得1次抽奖的机会，分享即可不限制必邀请注册，总共2次机会；
                //参与过投票环节的用户再次参与抽奖活动，除默认的一个自然日内可以玩1次以后，自动获得3次抽奖机会（鼓励参与投票竞猜），
                //另外分享社媒也可以再获得一次抽奖（分享即可不限制必邀请注册），总共5次机会
                $isVote = VoteLogService::exists($storeId, '', $actId, $customerId);
                if ($isVote) {//如果已经参加了投票，就多添加 2 次抽奖机会
                    data_set($handleCacheData, Constant::METHOD_KEY, 'increment');
                    data_set($handleCacheData, Constant::PARAMETERS_KEY, [$key, 3]);
                    static::handleCache($tag, $handleCacheData);

                    data_set($handleCacheData, Constant::PARAMETERS_KEY, [$lotteryTotalKey, 3]);
                    static::handleCache($tag, $handleCacheData);
                }
            }
        }
        array_unshift($parameters, $key);

        //老虎机游戏非首次参与次数增加
        GameService::updatePlayNums($storeId, $actId, $customerId, 'add_nums', 'every');
        $dayAddNums = data_get($actionData, 'requestData.day_add_nums', 0);
        if ($dayAddNums) {
            data_set($handleCacheData, Constant::METHOD_KEY, 'increment');
            data_set($handleCacheData, Constant::PARAMETERS_KEY, [$key, 1]);
            return static::handleCache($tag, $handleCacheData);
        }

        $data = '';
        switch ($method) {
            case 'increment':

                data_set($handleCacheData, Constant::METHOD_KEY, 'get');
                data_set($handleCacheData, Constant::PARAMETERS_KEY, [$statisticsKey]);
                $statisticsNum = static::handleCache($tag, $handleCacheData); //获取用户已添加的次数

                $actTotal = data_get($activityConfigData, $actForm . '_' . $limitKey . Constant::LINKER . Constant::DB_TABLE_VALUE, 1); //获取可以添加的次数

                if ($statisticsNum < $actTotal) {

                    data_set($handleCacheData, Constant::METHOD_KEY, $method);
                    data_set($handleCacheData, Constant::PARAMETERS_KEY, $parameters);
                    $data = static::handleCache($tag, $handleCacheData); //更新用户剩余的抽奖次数

                    data_set($parameters, '0', $statisticsKey);
                    data_set($handleCacheData, Constant::METHOD_KEY, $method);
                    data_set($handleCacheData, Constant::PARAMETERS_KEY, $parameters);
                    static::handleCache($tag, $handleCacheData); //更新用户添加的次数


                    data_set($handleCacheData, Constant::PARAMETERS_KEY, [$lotteryTotalKey, 1]);
                    static::handleCache($tag, $handleCacheData); //更新用户总次数

                    data_set($handleCacheData, Constant::METHOD_KEY, 'get');
                    data_set($handleCacheData, Constant::PARAMETERS_KEY, [$key]);
                    $lotteryNum = static::handleCache($tag, $handleCacheData); //获取用户剩余次数
                    if ($lotteryNum < 0) {//如果用户剩余次数小于0，就设置用户剩余次数为：1
                        data_set($handleCacheData, Constant::METHOD_KEY, 'put');
                        data_set($handleCacheData, Constant::PARAMETERS_KEY, [$key, 1, $ttl]);
                        static::handleCache($tag, $handleCacheData); //设置当天用户剩余的抽奖次数
                    }
                }

                break;

            case 'get':

                if ($isCreditCount && $deductCredit) {
                    $customerInfo = CustomerInfoService::existsOrFirst($storeId, '', [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId], true, [Constant::DB_TABLE_CREDIT]);
                    $initPlayNums = floor(data_get($customerInfo, Constant::DB_TABLE_CREDIT, 0) / $deductCredit);

                    data_set($handleCacheData, Constant::METHOD_KEY, 'put');
                    data_set($handleCacheData, Constant::PARAMETERS_KEY, [$lotteryTotalKey, $initPlayNums, $ttl]); //记录用户总抽奖次数
                    static::handleCache($tag, $handleCacheData);

                    //剩余活动次数
                    //$lotteryNum = ($dayMaxPlayNums - $lotteryUsedNum) < $initPlayNums ? ($dayMaxPlayNums - $lotteryUsedNum) : $initPlayNums;
                    data_set($handleCacheData, Constant::METHOD_KEY, 'put');
                    data_set($handleCacheData, Constant::PARAMETERS_KEY, [$key, $initPlayNums, $ttl]); //记录用户剩余的抽奖次数
                    static::handleCache($tag, $handleCacheData);
                }

                //剩余活动次数
                data_set($handleCacheData, Constant::METHOD_KEY, $method);
                data_set($handleCacheData, Constant::PARAMETERS_KEY, $parameters);
                $lotteryNum = static::handleCache($tag, $handleCacheData);

                //用户总抽奖次数
                data_set($handleCacheData, Constant::PARAMETERS_KEY, [$lotteryTotalKey]);
                $lotteryTotal = static::handleCache($tag, $handleCacheData);

                $actTotal = data_get($activityConfigData, $actForm . '_' . $limitKey . Constant::LINKER . Constant::DB_TABLE_VALUE, 1); //获取可以添加的次数
                $data = [
                    Constant::LOTTERY_NUM => $lotteryNum, //剩余次数
                    Constant::LOTTERY_TOTAL => $lotteryTotal, //用户总抽奖次数
                    Constant::ACT_TOTAL => $actTotal + 1, //总次数
                ];
                break;

            default:
                data_set($handleCacheData, Constant::METHOD_KEY, $method);
                data_set($handleCacheData, Constant::PARAMETERS_KEY, $parameters);
                $data = static::handleCache($tag, $handleCacheData);

                break;
        }

        return $data;
    }

    /**
     * 生成活动
     * @param int $storeId
     * @param string $activityName 活动名称
     * @return bool|object|null $rs
     */
    public static function addActivity($storeId, $activityName, $data = []) {

        if (empty($activityName)) {
            return Constant::PARAMETER_ARRAY_DEFAULT;
        }

        $where = [];
        if ($activityName) {
            $where[Constant::DB_TABLE_NAME] = $activityName;
        }

        data_set($data, Constant::DB_TABLE_ACT_UNIQUE, static::getActUnique($storeId, 'g/' . $activityName), false);
        data_set($data, Constant::FILE_URL, static::getActUrl($storeId, 'g/' . $activityName), false);

        $actData = static::updateOrCreate($storeId, $where, $data);

        static::clear(); //清空活动缓存

        return data_get($actData, Constant::RESPONSE_DATA_KEY, Constant::PARAMETER_ARRAY_DEFAULT);
    }

    /**
     * 返回活动id列表
     * @param int $storeId
     * @return bool|object|null $rs
     */
    public static function getActivityList($storeId) {
        return static::getModel($storeId)->select(Constant::DB_TABLE_PRIMARY, Constant::DB_TABLE_NAME, Constant::DB_TABLE_ACT_TYPE)->orderBy(Constant::DB_TABLE_PRIMARY, 'desc')->get();
    }

    /**
     * 判断是否可以继续活动
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $customerId 账号id
     * @return mix
     */
    public static function isHandle($storeId, $actId, $customerId, $requestData) {

        $actionData = FunctionHelper::getJobData(static::getNamespaceClass(), 'decrement', [], $requestData);
        static::handleLimit($storeId, $actId, $customerId, $actionData);

        data_set($actionData, Constant::METHOD_KEY, 'get');
        $lotteryData = static::handleLimit($storeId, $actId, $customerId, $actionData);
        $lotteryNum = data_get($lotteryData, Constant::LOTTERY_NUM, 0);
        if ($lotteryNum < 0) {
            return Response::getDefaultResponseData(62000);
        }

        return Response::getDefaultResponseData(1);
    }

    /**
     * 获取活动hash标识
     * @param int $storeId 活动id
     * @param string $actUnique 活动标识
     * @param int $length 标识长度
     * @return string 唯一活动标识
     */
    public static function getActUnique($storeId, $actUnique = null, $length = 2) {

        if ($actUnique !== null) {
            return FunctionHelper::getUniqueId(FunctionHelper::getShopifyUri($actUnique));
        }

        $isDo = true;
        $actUnique = '';
        while ($isDo) {
            $actUnique = FunctionHelper::randomStr($length);
            $where = [
                Constant::DB_TABLE_ACT_UNIQUE => $actUnique,
            ];
            $isDo = static::existsOrFirst($storeId, '', $where);
        }

        return FunctionHelper::getUniqueId(FunctionHelper::getShopifyUri($actUnique));
    }

    /**
     * 获取活动链接
     * @param int $storeId 商城id
     * @param string $mark 活动标识
     * @param string $host host
     * @param string $actUnique 活动hash
     * @param int $length  字符串长度
     * @return string 活动链接
     */
    public static function getActUrl($storeId, $mark, $host = null, $actUnique = null, $length = 2) {
        return implode('/', ['https:/', FunctionHelper::getShopifyHost($storeId, $host),]) . FunctionHelper::getShopifyUri($mark);
    }

    /**
     * 是否能够切换 活动类型 1:九宫格 2:转盘 3:砸金蛋 4:翻牌 5:邀请好友注册 6:上传图片投票 7:免费评测活动 8:会员deal 9:通用deal
     * @param int $srcActType 源活动类型
     * @param int $distActType 目标活动类型
     * @return boolean true:是 false:否
     */
    public static function isCanSwitchActType($srcActType, $distActType) {
        $data = [
            1 => [1, 2, 3, 4],
            2 => [1, 2, 3, 4],
            3 => [1, 2, 3, 4],
            4 => [1, 2, 3, 4],
        ];
        return in_array($distActType, data_get($data, $srcActType, [$srcActType]));
    }

    /**
     * 判断活动是否存在 规则：活动名称+活动类型确认活动唯一性
     * @param int $storeId 商城id
     * @param string $name 活动名称
     * @param int $actType 活动类型
     * @param int $id 活动id
     * @return array $rs 活动是否存在结果
     */
    public static function isExists($storeId, $name, $actType, $id = 0) {

        $rs = [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => 'ok',
            Constant::RESPONSE_DATA_KEY => []
        ];

        $where = [
            Constant::DB_TABLE_NAME => $name, //活动名字
            Constant::DB_TABLE_ACT_TYPE => $actType, //活动类型
        ];

        if ($id) {
            $where[] = [[Constant::DB_TABLE_PRIMARY, '!=', $id]];
        }

        $actData = static::existsOrFirst($storeId, '', $where);
        if ($actData) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_INT_DEFAULT);
            data_set($rs, Constant::RESPONSE_MSG_KEY, '活动名称重复');
            return $rs;
        }

        return $rs;
    }

    /**
     * 添加活动
     * @param int $storeId 商城id
     * @param array $data 活动数据
     */
    public static function input($storeId, $data) {

        $mark = data_get($data, Constant::DB_TABLE_MARK, '');
        $id = data_get($data, Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT);

        $actType = data_get($data, Constant::DB_TABLE_ACT_TYPE, -1); //活动类型 1:九宫格 2:转盘 3:砸金蛋 4:翻牌 5:邀请好友注册 6:上传图片投票 7:免费评测活动 8:会员deal 9:通用deal
        $name = data_get($data, Constant::DB_TABLE_NAME, ''); //活动名字
        //活动名称+活动类型确认活动唯一性
        $rs = static::isExists($storeId, $name, $actType, $id);
        if (data_get($rs, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_INT_DEFAULT) != 1) {
            return $rs;
        }

        $actData = [
            Constant::DB_TABLE_NAME => $name, //活动名字
            Constant::DB_TABLE_START_AT => data_get($data, Constant::DB_TABLE_START_AT, null), //活动开始时间
            Constant::DB_TABLE_END_AT => data_get($data, Constant::DB_TABLE_END_AT, null), //活动结束时间
            Constant::DB_TABLE_ACT_TYPE => $actType, //活动类型
            Constant::DB_TABLE_MARK => $mark, //活动标识
            Constant::DB_TABLE_ACT_UNIQUE => static::getActUnique($storeId, $mark), //活动hash标识
            Constant::FILE_URL => static::getActUrl($storeId, $mark), //活动链接
        ];

        if (empty($id)) {
            $id = static::insert($storeId, $actData);
            data_set($rs, Constant::RESPONSE_DATA_KEY, [Constant::DB_TABLE_PRIMARY => $id]);
            return $rs;
        }

        $where = [
            Constant::DB_TABLE_PRIMARY => $id,
        ];
        $_actData = static::existsOrFirst($storeId, '', $where, true, [Constant::DB_TABLE_ACT_UNIQUE, Constant::DB_TABLE_ACT_TYPE]);
        $isCanSwitchActType = static::isCanSwitchActType(data_get($_actData, Constant::DB_TABLE_ACT_TYPE, -1), $actType);
        if (!$isCanSwitchActType) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 0);
            data_set($rs, Constant::RESPONSE_MSG_KEY, '活动类型不可以更改');
            return $rs;
        }

        $offset = static::update($storeId, $where, $actData);
        data_set($rs, Constant::RESPONSE_DATA_KEY, [Constant::DB_EXECUTION_PLAN_OFFSET => $offset]);

        static::clear(); //清空活动缓存

        return $rs;
    }

    /**
     * 删除活动
     * @param int $storeId 商城id
     * @param array $ids 活动id
     * @return array 处理结果
     */
    public static function delAct($storeId, $ids) {

        $rs = [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => 'ok',
            Constant::RESPONSE_DATA_KEY => []
        ];

        if (empty($ids)) {
            return $rs;
        }

        $where = [
            Constant::DB_TABLE_PRIMARY => $ids,
        ];
        $offset = static::delete($storeId, $where);
        data_set($rs, Constant::RESPONSE_DATA_KEY, [Constant::DB_EXECUTION_PLAN_OFFSET => $offset]);

        static::clear(); //清空活动缓存

        return $rs;
    }

    /**
     * 获取活动数据
     * @param int $storeId 品牌商店id
     * @param int $actId 活动id
     * @return boolean
     */
    public static function getActData($storeId, $actId) {

        $actData = static::existsOrFirst($storeId, '', ['id' => $actId], true, ['start_at', 'end_at']);

        $startAt = data_get($actData, 'start_at');
        $endAt = data_get($actData, 'end_at');
        $rs = [
            'actData' => $actData,
            'startAt' => $startAt,
            'endAt' => $endAt,
            'isStart' => null,
            'isEnd' => null,
            'isValid' => false,
        ];

        if ($actData === null) {
            return $rs;
        }

        $nowTime = Carbon::now()->toDateTimeString();

        $isStart = $startAt === null ? true : ($startAt <= $nowTime ? true : false);
        $isEnd = $endAt === null ? false : ($endAt >= $nowTime ? false : true);

        data_set($rs, 'isStart', $isStart);
        data_set($rs, 'isEnd', $isEnd);
        data_set($rs, 'isValid', ($isStart && !$isEnd));

        return $rs;
    }

}
