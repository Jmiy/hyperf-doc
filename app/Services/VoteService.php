<?php

/**
 * 积分服务
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
use App\Constants\Constant;
use App\Jobs\PublicJob;
use App\Utils\Support\Facades\Queue;
use App\Utils\Cdn\CdnManager;
use App\Utils\Response;

class VoteService extends BaseService {

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'VoteItem';
    }

    /**
     * 获取Vote模型
     * @param int $storeId 店铺id
     * @param string $country 国家缩写
     * @return string
     */
    public static function getVoteModel($storeId = 1, $country = '') {
        return static::createModel($storeId, 'Vote', [], $country);
    }

    /**
     * 检查投票项是否存在
     * @param int $storeId 商城id
     * @param string $country 国家
     * @param int $actId 活动id
     * @param array $where where条件
     * @param array $getData 是否获取记录  true:是  false:否
     * @return int|object
     */
    public static function exists($storeId = 0, $country = '', $actId = 0, $where = [], $getData = false) {

        $_where = [];
        if ($actId) {
            data_set($_where, 'act_id', $actId);
        }

        $where = Arr::collapse([$_where, $where]);
        return static::existsOrFirst($storeId, $country, $where, $getData);
    }

    /**
     * 获取db query
     * @param int $storeId 商城id
     * @param array $where
     * @return \Illuminate\Database\Query\Builder|static $query
     */
    public static function getQuery($storeId, $where = []) {
        $query = static::getModel($storeId, '')
                ->leftJoin('votes as v', 'v.vote_item_id', '=', 'vote_items.id')
                ->buildWhere($where);
        return $query;
    }

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @param array $order 排序控制
     * @return array
     */
    public static function getPublicData($params, $order = []) {

        $where = [];

        $actId = data_get($params, 'act_id', null); //活动id
        $name = data_get($params, 'name', ''); //名称
        $remarksFilter = data_get($params, 'remarks_filter', 0); //是否过滤remarks
        $searchText = data_get($params, 'search_text', '');

        if ($actId !== null) {//活动id
            $where[] = ['vote_items.act_id', '=', $actId];
        }

        if ($name) {//投票项名称
            $where[] = ['vote_items.name', '=', $name];
        }

        if ($remarksFilter) {
            $where[] = ['vote_items.remarks', '!=', ''];
        }

        if ($searchText) {
            $where[] = ['vote_items.search_text', 'like', "%$searchText%"];
        }

        $_where = [];
        if (data_get($params, 'id', 0)) {
            $_where['vote_items.id'] = $params['id'];
        }

        if ($where) {
            $_where[] = $where;
        }

        $order = $order ? $order : ['vote_items.id', 'asc'];
        return Arr::collapse([parent::getPublicData($params, $order), [
                        'where' => $_where,
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

        $storeId = data_get($params, 'store_id', 0);
        $_data = static::getPublicData($params, data_get($params, 'orderby', []));

        $where = data_get($_data, 'where', []);
        $order = data_get($params, 'orderBy', data_get($_data, 'order', []));
        $pagination = data_get($_data, 'pagination', []);
        $limit = data_get($params, 'limit', data_get($pagination, 'page_size', 10));
        $offset = data_get($params, 'offset', data_get($pagination, 'offset', 0));
        $select = $select ? $select : [
            'vote_items.id',
            'vote_items.name',
            'vote_items.url',
            'vote_items.img_url',
            'vote_items.mb_img_url',
            'v.score',
            'vote_items.type',
            'vote_items.figures_img_url',
            'vote_items.amount',
            'vote_items.winners',
            'vote_items.ext_id',
            'vote_items.ext_type',
            'p.name as p_name',
            'p.img_url as p_img_url',
            'p.mb_img_url as p_mb_img_url',
            'p.listing_price',
        ]; //

        $joinData = [
            FunctionHelper::getExePlanJoinData('votes as v', function ($join) {
                        $join->on([['v.vote_item_id', '=', 'vote_items.' . Constant::DB_TABLE_PRIMARY]]);
                    }),
            FunctionHelper::getExePlanJoinData('activity_products as p', function ($join) {
                        $join->on([['p.' . Constant::DB_TABLE_PRIMARY, '=', 'vote_items.' . Constant::DB_TABLE_EXT_ID]])->where('vote_items.' . Constant::DB_TABLE_EXT_TYPE, '=', ActivityProductService::getModelAlias());
                    }),
        ];

        $dbExecutionPlan = [
            'parent' => [
                'setConnection' => true,
                'storeId' => $storeId,
                'builder' => null,
                'make' => static::getModelAlias(),
                'from' => '',
                'joinData' => $joinData,
                'select' => $select,
                'where' => $where,
                'orders' => !empty($order) ? (count($order) == count($order, 1) ? [$order] : $order) : [],
                'offset' => $offset,
                'limit' => $limit,
                'isPage' => $isPage,
                'isOnlyGetCount' => $isOnlyGetCount,
                'pagination' => $pagination,
                'handleData' => [
                    'score' => [
                        'field' => 'score',
                        'data' => [],
                        'dataType' => '',
                        'dateFormat' => '',
                        'glue' => '',
                        'is_allow_empty' => false, //是否允许为空 true：是  false：否
                        'default' => 0,
                    ],
                    'amount' => [
                        'field' => 'amount{or}listing_price',
                        'data' => [],
                        'dataType' => 'price',
                        'dateFormat' => [2, ".", ''],
                        'glue' => '',
                        'is_allow_empty' => true, //是否允许为空 true：是  false：否
                        'default' => 0.00,
                    ],
                ],
            ],
            'with' => [
//                'ext' => [
//                    'setConnection' => true,
//                    'storeId' => $storeId,
//                    'relation' => 'hasOne',
//                    'morphToConnection' => [
//                        'ActivityProduct' => 'parent',
//                    ],
//                    'select' => [
//                        '*',
//                    ],
//                    'default' => [
//                    //'customer_id' => 'customer_id',
//                    ],
//                    'where' => [],
//                    'handleData' => [
////                        'info.brithday' => [
////                            'field' => 'info.brithday',
////                            'data' => [],
////                            'dataType' => 'datetime',
////                            'dateFormat' => 'Y-m-d',
////                            'glue' => '',
////                            'default' => '',
////                        ],
//                    ],
//                //'unset' => ['info'],
//                ],
            ],
            'itemHandleData' => [
            ],
        ];

        $dataStructure = 'list';
        $flatten = false;
        $data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);

        return $data;
    }

    /**
     * 获取选项数据
     * @param int $storeId 商城id
     * @param int $voteItemId 选项id
     * @param array $data 选项数据
     * @return array
     */
    public static function getVoteItem($storeId, $voteItemId = 0, $data = []) {

        if ($data) {
            return [
                'id' => data_get($data, 'id', 0),
                'name' => data_get($data, 'name', ''),
            ];
        }

        if (empty($voteItemId)) {
            return $data;
        }

        $data = static::getModel($storeId, '')->select(['id', 'name'])->where('id', $voteItemId)->first(); //, 'url'
        if (empty($data)) {
            return [];
        }

        return [
            'id' => data_get($data, 'id', 0),
            'name' => data_get($data, 'name', ''),
        ];
    }

    /**
     * 添加记录
     * @param int $storeId 商城id
     * @param array $where where条件
     * @param array $data  数据
     * @return int
     */
    public static function insert($storeId, $where, $data) {
        return static::createModel($storeId, 'Vote')->updateOrCreate($where, $data); //updateOrCreate：不可以添加主键id的值  updateOrInsert：可以添加主键id的值
    }

    /**
     * 获取排行榜的key
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @return string 排行榜的key
     */
    public static function getRankKey($storeId = 0, $actId = 0) {
        $zsetKey = 'vote:' . $storeId . ':' . $actId;
        return $zsetKey;
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

        $where = [
            'act_id' => $actId,
        ];
        $_data = static::getPublicData($where);
        $where = $_data['where'];
        $query = static::getQuery($storeId, $where);
        $query->select(['vote_items.id', 'vote_items.name', 'v.score'])//'vote_items.url',
                ->chunk(100, function ($data) use($zsetKey, $storeId) {
                    foreach ($data as $item) {
                        $score = $item->score ? $item->score : 0;
                        $member = static::getVoteItem($storeId, 0, $item);
                        Redis::zadd($zsetKey, $score, static::getZsetMember($member));
                    }
                });
        $ttl = 30 * 24 * 60 * 60;
        Redis::expire($zsetKey, $ttl);

        return false;
    }

    /**
     * 缓存处理
     * @param int $storeId 商城id
     * @param int $actId  活动id
     * @param string $account  账号
     * @param int $voteItemId 投票项id
     * @return array
     */
    public static function voteLimtCache($storeId = 0, $actId = 0, $account = '', $voteItemId = 0) {
        $key = $storeId . ':' . $actId . ':' . $account . ':' . $voteItemId;
        $tags = config('cache.tags.voteLimt');
        $cache = Cache::tags($tags);
        $value = $cache->get($key);
        return [
            'cache' => $cache,
            'key' => $key,
            'value' => $value
        ];
    }

    /**
     * 获取缓存tag
     * @return string
     */
    public static function getCacheTags() {
        return 'voteLock';
    }

    /**
     * 更新汇总数据
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param string $account 会员账号
     * @param int $voteItemId 选项id
     * @param int $score 积分
     * @return int
     */
    public static function handle($storeId = 0, $actId = 0, $account = '', $voteItemId = 0, $score = 1, $extData = []) {

        if (empty($storeId)) {
            return Response::getDefaultResponseData(9999999999);
        }

        $defaultRs = Response::getDefaultResponseData(61004);

        $tag = static::getCacheTags();
        $cacheKey = ':' . __FUNCTION__ . '：' . md5(implode(':', [$storeId, $actId, $account, $voteItemId]));
        $actionData = [
            'service' => static::getNamespaceClass(),
            'method' => 'lock',
            'parameters' => [
                $cacheKey,
            ],
            'serialHandle' => [
                [
                    'service' => static::getNamespaceClass(),
                    'method' => 'get',
                    'parameters' => [
                        function () use($storeId, $actId, $account, $voteItemId, $score, $extData) {

                            $retult = Response::getDefaultResponseData(1);

                            $isHandleAct = ActivityService::isHandle($storeId, $actId, $account, $extData);
                            if (data_get($isHandleAct, 'code', 0) != 1) {
                                return $isHandleAct;
                            }

                            $voteLimtCache = static::voteLimtCache($storeId, $actId, $account, $voteItemId);
                            $voteLimt = data_get($voteLimtCache, 'value', 0);
                            if ($voteLimt > 0) {//如果用户已经投票，就提示用户
                                return Response::getDefaultResponseData(61000);
                            }

                            $member = static::getVoteItem($storeId, $voteItemId);
                            if (empty($member)) {
                                return Response::getDefaultResponseData(61001);
                            }

                            $voteLog = VoteLogService::getModel($storeId, '');
                            $data = [
                                'act_id' => $actId,
                                'customer_id' => data_get($extData, 'customer_id', 0),
                                'vote_item_id' => $voteItemId,
                                'account' => $account,
                                'ip' => data_get($extData, 'ip', ''),
                                'country' => data_get($extData, 'country', ''),
                            ];
                            $voteLog->insertGetId($data);

                            /*                             * ***************更新选项的投票汇总数据************************ */
                            $where = [
                                'vote_item_id' => $voteItemId,
                            ];
                            $data = [
                                'act_id' => $actId,
                                'score' => DB::raw('score+' . $score),
                            ];
                            $id = static::insert($storeId, $where, $data);

                            if (empty($id)) {
                                return Response::getDefaultResponseData(61002);
                            }

                            //记录用户本次活动已经投票
                            $key = data_get($voteLimtCache, 'key', '');
                            if ($key) {
                                $ttl = 30 * 24 * 60 * 60;
                                $cache = data_get($voteLimtCache, 'cache', null);
                                if ($cache) {
                                    $cache->put($key, $voteItemId, $ttl);
                                }
                            }

                            $isInited = static::initRank($storeId, $actId);
                            if ($isInited == false) {
                                return $retult;
                            }

                            $zsetKey = static::getRankKey($storeId, $actId);
                            $isIncred = Redis::zincrby($zsetKey, $score, static::getZsetMember($member));
                            if (empty($isIncred)) {
                                return Response::getDefaultResponseData(61003);
                            }

                            return $retult;
                        }
                    ],
                ]
            ]
        ];

        $rs = static::handleCache($tag, $actionData);

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
            'page_size' => $pageSize,
        ];
        $_data = parent::getPublicData($publicData);

        $pagination = $_data['pagination'];
        $offset = $pagination['offset'];
        $count = $pagination['page_size'];

        static::initRank($storeId, $actId);
        $zsetKey = static::getRankKey($storeId, $actId);

        //获取分页数据
        $customerCount = Redis::zcard($zsetKey);
        $pagination['total'] = $customerCount;
        $pagination['total_page'] = ceil($customerCount / $count);
        $rankData = [];
        $customerRankData = [];
        if ($customerCount <= 0) {
            return [
                'pagination' => $pagination,
                'data' => $rankData,
                'customerRankData' => $customerRankData,
            ];
        }

        /*         * **************获取排行榜数据*************** */
        $options = [
            'withscores' => true,
            'limit' => [
                'offset' => $offset,
                'count' => $count,
            ]
        ];
        $data = Redis::zrevrangebyscore($zsetKey, '+inf', '-inf', $options);
        if (empty($data)) {
            return [
                'pagination' => $pagination,
                'data' => $rankData,
                'customerRankData' => $customerRankData,
            ];
        }

        //排行榜数据
        foreach ($data as $member => $score) {
            $no = Redis::zrevrank($zsetKey, $member);
            $member = static::getSrcMember($member);
            $member['no'] = $no + 1;
            $member['score'] = $score;
            $rankData[] = $member;
        }

        /*         * **************获取当前会员最近一次的投票数据*************** */
        $where = [
            'account' => $account,
            'act_id' => $actId,
        ];
        $voteItemId = static::createModel($storeId, 'VoteLog')->where($where)->orderBy('id', 'DESC')->value('vote_item_id');
        if (empty($voteItemId)) {
            return [
                'pagination' => $pagination,
                'data' => $rankData,
                'customerRankData' => $customerRankData,
            ];
        }

        $member = static::getVoteItem($storeId, $voteItemId);
        if (empty($member)) {
            return [
                'pagination' => $pagination,
                'data' => $rankData,
                'customerRankData' => $customerRankData,
            ];
        }

        $_member = static::getZsetMember($member);
        $no = Redis::zrevrank($zsetKey, $_member);
        if ($no === false) {
            return [
                'pagination' => $pagination,
                'data' => $rankData,
                'customerRankData' => $customerRankData,
            ];
        }

        $score = Redis::zscore($zsetKey, $_member);
        $member['no'] = $no + 1;
        $member['score'] = $score;
        $customerRankData = $member;

        return [
            'pagination' => $pagination,
            'data' => $rankData,
            'customerRankData' => $customerRankData,
        ];
    }

    /**
     * 获取选项数据
     * @param int $storeId    商城id
     * @param int $actId      活动id
     * @param string $account 会员账号
     * @param int $page       当前页码
     * @param int $pageSize   每页记录条数
     * @return array
     */
    public static function getItemData($storeId = 0, $actId = 0, $account = '', $page = 1, $pageSize = 10) {

        $publicData = [
            'store_id' => $storeId,
            'act_id' => $actId,
            'page' => $page,
            'page_size' => $pageSize,
            'orderby' => ['vote_items.id', 'asc'],
        ];
        $data = static::getListData($publicData);

        foreach ($data['data'] as $key => $item) {
            $itemId = data_get($item, 'id', null);
            $voteLimtCache = static::voteLimtCache($storeId, $actId, $account, $itemId);
            $voteItemId = data_get($voteLimtCache, 'value', 0);
            $data['data'][$key]['has_voted'] = $itemId == $voteItemId ? 1 : 0;
        }

        data_set($data, 'act_id', $actId);
        data_set($data, 'act_period', static::whichActPeriod());

        return $data;
    }

    public static function whichActPeriod() {
        $nowTime = Carbon::now()->toDateTimeString();
        $actPeriod = 0;
        switch (true) {
            case $nowTime < '2019-11-01 00:00:00':
                $actPeriod = 0;
                break;

            case $nowTime < '2019-11-18 00:00:00':
                $actPeriod = 1;
                break;

            default:
                $actPeriod = 2;
                break;
        }

        return $actPeriod;
    }

    /**
     * 投票后台产品列表
     * @param array $params 请求参数
     * @param boolean $toArray 是否转化为数组 true:是 false:否 默认:true
     * @param boolean $isPage  是否分页 true:是 false:否 默认:true
     * @param array $select  查询字段
     * @param boolean $isRaw  是否是原生sql true:是 false:否 默认:false
     * @return array 列表数据
     */
    public static function getProductList($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {

        $storeId = data_get($params, 'store_id', 0);
        $_data = static::getPublicData($params);

        $where = data_get($_data, 'where', []);
        $order = data_get($params, 'orderBy', data_get($_data, 'order', []));
        $pagination = data_get($_data, 'pagination', []);
        $limit = data_get($params, 'limit', data_get($pagination, 'page_size', 10));
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
            ],
            'with' => [
            ],
            'itemHandleData' => [
            ],
        ];

        $dataStructure = 'list';
        $flatten = false;
        $data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);

        return $data;
    }

    public static function getVoteEdit($storeId, $Id, $data) {

        $where = [
            'id' => $Id,
        ];
        $updateData = [
            'name' => data_get($data, 'name', ''), //产品名称
            'url' => data_get($data, 'url', ''), //产品链接
            'type' => data_get($data, 'type', 0), //产品类型
            'img_url' => data_get($data, 'img_url', ''), //pc主图
            'mb_img_url' => data_get($data, 'mb_img_url', ''), //移动主图
            'updated_at' => Carbon::now()->toDateTimeString(),
        ];
        $update = static::update($storeId, $where, $updateData);

        return ['code' => 1, 'msg' => 'ok', 'data' => $update];
    }

    /**
     * 用户上传投票项
     * 1. 记录上传信息
     * 2. 发邮件
     * 3. 发图片描述到队列
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param string $account 账号
     * @param array $requestData 请求参数
     * @return array
     */
    public static function addVote($storeId, $actId, $customerId, $account, $requestData) {
        $voteItemId = data_get($requestData, Constant::DB_TABLE_VOTE_ITEM_ID);

        //用户是否添加了投票内容
        $where = [
            [Constant::DB_TABLE_PRIMARY, '=', $voteItemId],
            [Constant::DB_TABLE_REMARKS, '<>', ''],
        ];
        $isCan = VoteService::isAddVoteItem($storeId, $where);
        if ($isCan[Constant::RESPONSE_CODE_KEY] != 1) {
            return $isCan;
        }

        static::getModel($storeId)->getConnection()->beginTransaction();
        try {

            //更新投票内容
            $where = [
                Constant::DB_TABLE_PRIMARY => $voteItemId
            ];
            $voteItem = [
                Constant::DB_TABLE_REMARKS => data_get($requestData, Constant::DB_TABLE_REMARKS, Constant::PARAMETER_STRING_DEFAULT),
                'search_text' => static::getText($storeId, $customerId, $requestData)
            ];
            $isUpdatedVoteItem = static::getModel($storeId)->buildWhere($where)->update($voteItem);
            if (empty($isUpdatedVoteItem)) {
                //更新记录失败
                throw new \Exception(null, 9900000003);
            }

            $ip = data_get($requestData, Constant::DB_TABLE_IP, Constant::PARAMETER_STRING_DEFAULT);
            static::emailToQueue($storeId, $actId, $account, $customerId, $ip, $voteItemId, "VoteItems", 'email', 'vote_food');

            static::getModel($storeId, '')->getConnection()->commit();
        } catch (\Exception $exception) {
            static::getModel($storeId, '')->getConnection()->rollBack();
            return Response::getDefaultResponseData($exception->getCode(), $exception->getMessage());
        }

        return Response::getDefaultResponseData(1);
    }

    /**
     * 获取待处理的字符串
     * @param int $storeId  官网id
     * @param int $customerId 会员id
     * @param array $requestData 请求参数
     * @return string
     */
    public static function getText($storeId, $customerId, $requestData) {
        //获取用户信息
        $customerInfo = CustomerInfoService::exists($storeId, $customerId, '', true);
        $account = data_get($customerInfo, Constant::DB_TABLE_ACCOUNT, Constant::PARAMETER_STRING_DEFAULT);
        $firstName = data_get($customerInfo, Constant::DB_TABLE_FIRST_NAME, Constant::PARAMETER_STRING_DEFAULT);
        $lastName = data_get($customerInfo, Constant::DB_TABLE_LAST_NAME, Constant::PARAMETER_STRING_DEFAULT);

        //图片描述
        $remarks = data_get($requestData, Constant::DB_TABLE_REMARKS, Constant::PARAMETER_STRING_DEFAULT);

        return $account . " " . $firstName . " " . $lastName . " " . $remarks;
    }

    /**
     * 待分割字符串发送至队列
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param int $extId 关联id
     * @param string $extType 关联类型
     * @param array $requestData 请求参数
     * @return bool
     */
    public static function searchDataToQueue($storeId, $actId, $customerId, $extId, $extType, $requestData) {
        $text = static::getText($storeId, $customerId, $requestData);

        $service = ActivitySearchService::getNamespaceClass();
        $method = 'generateData';
        $parameters = [$storeId, $actId, $extId, $extType, $text];
        $data = [
            Constant::SERVICE_KEY => $service,
            Constant::METHOD_KEY => $method,
            Constant::PARAMETERS_KEY => $parameters,
        ];
        Queue::push(new PublicJob($data));

        return true;
    }

    /**
     * 是否添加了投票内容
     * @param int $storeId 官网id
     * @param array $where
     * @return array
     */
    public static function isAddVoteItem($storeId, $where) {
        $count = static::getModel($storeId)->where($where)->count();
        if ($count) {
            return [
                Constant::RESPONSE_CODE_KEY => '61116',
                Constant::RESPONSE_MSG_KEY => "Already added",
                Constant::RESPONSE_DATA_KEY => []
            ];
        }

        return [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => '',
            Constant::RESPONSE_DATA_KEY => []
        ];
    }

    /**
     * 是否添加了投票项
     * @param int $storeId 官网id
     * @param array $where
     * @return array
     */
    public static function isAddVote($storeId, $where) {
        $count = static::getVoteModel($storeId)->buildWhere($where)->count();
        if ($count) {
            return [
                Constant::RESPONSE_CODE_KEY => '61116',
                Constant::RESPONSE_MSG_KEY => "Already added",
                Constant::RESPONSE_DATA_KEY => []
            ];
        }

        return [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => '',
            Constant::RESPONSE_DATA_KEY => []
        ];
    }

    /**
     * 文件上传
     * @param \Illuminate\Http\UploadedFile $files 文件上传对象或者是文件上传对象数组
     * @param array $requestData
     * @return array
     */
    public static function upload($files, $requestData) {
//        ($files instanceof \Illuminate\Http\UploadedFile) && $files = [$files];
//        $isOriginImage = data_get($requestData, 'is_origin_image', 0);
//        $storeId = data_get($requestData, 'store_id', 0);

        $aws = [];
        $server = [];
        $_files = CdnManager::upload(null, $files, '', 'AwsS3Cdn', false, false, '', 1, $requestData);
        $aws = collect($_files)->pluck(Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::FILE_URL)->toArray();

//        if ($isOriginImage != 1) {
//            $_files = CdnManager::upload(null, $files, "/upload/img/$storeId/");
//            $server = collect($_files)->pluck(Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::FILE_URL)->toArray();
//        }

        return [
            'aws' => $aws,
            'server' => $server
        ];
    }

    /**
     * 获取参赛图片列表
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param string $account 账号
     * @param int $page 页码
     * @param int $pageSize 页大小
     * @param array $requestData 请求参数
     * @param array $extData 扩展参数
     * @return array
     */
    public static function normalList($storeId = 0, $actId = 0, $account = '', $page = 1, $pageSize = 10, $requestData = [], $extData = []) {
        //排序参数
        $order = data_get($requestData, 'order', 'time');
        $sort = data_get($requestData, 'sort', 'desc');
        if ($order == 'score' && $sort == 'desc') {
            $orderBy[] = ['v.score', 'desc'];
            $orderBy[] = ['v.id', 'desc'];
        } elseif ($order == 'score' && 'asc') {
            $orderBy[] = ['v.score', 'asc'];
            $orderBy[] = ['v.id', 'desc'];
        } elseif ($order == 'time' && $sort == 'desc') {
            $orderBy[] = ['v.id', 'desc'];
        } else {
            $orderBy[] = ['v.id', 'asc'];
        }

        //获取列表
        $publicData = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::REQUEST_PAGE => $page,
            Constant::REQUEST_PAGE_SIZE => $pageSize,
            'orderby' => $orderBy,
            'remarks_filter' => data_get($requestData, 'remarks_filter', 0),
            'search_text' => data_get($requestData, 'text', ''),
        ];
        !empty(data_get($extData, 'ext_ids', [])) && $publicData[Constant::DB_TABLE_PRIMARY] = data_get($extData, 'ext_ids');
        $select = ['v.id as vote_id', 'v.vote_item_id', 'v.act_id', 'v.score', 'v.created_at', 'v.customer_id', 'vote_items.mb_img_url', 'vote_items.img_url', 'vote_items.remarks', 'vote_items.origin_img_url', 'vote_items.url'];
        $voteList = static::getListData($publicData, true, true, $select);

        //数据为空
        $data = data_get($voteList, Constant::RESPONSE_DATA_KEY, []);
        if (empty($data)) {
            return $voteList;
        }

        //获取该请求IP下，点赞的图片
        $where = [
            Constant::DB_TABLE_IP => data_get($requestData, Constant::DB_TABLE_IP, ''),
            'op_type' => 1
        ];
        $vote = VoteLogService::getModel($storeId)->buildWhere($where)->orderBy(Constant::DB_TABLE_PRIMARY, 'desc')->first();

        //获取用户名
        $customerIds = array_column($data, Constant::DB_TABLE_CUSTOMER_PRIMARY);
        $select = [Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::DB_TABLE_FIRST_NAME, Constant::DB_TABLE_LAST_NAME];
        $customerInfos = CustomerInfoService::getModel($storeId)->buildWhere([Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerIds])->select($select)->get();
        $customerInfos = !$customerInfos->isEmpty() ? array_column($customerInfos->toArray(), NULL, Constant::DB_TABLE_CUSTOMER_PRIMARY) : [];

        //添加是否点过赞标识及用户名
        foreach (data_get($voteList, Constant::RESPONSE_DATA_KEY, []) as $key => $item) {
            data_set($voteList, "data.$key.is_vote", 0);
            if (data_get($item, 'vote_item_id', 0) == data_get($vote, 'vote_item_id', 0)) {
                data_set($voteList, "data.$key.is_vote", 1);
            }
            $customerId = data_get($item, Constant::DB_TABLE_CUSTOMER_PRIMARY, 0);
            if (isset($customerInfos[$customerId])) {
                data_set($voteList, "data.$key.first_name", data_get($customerInfos, "$customerId.first_name", ''));
                data_set($voteList, "data.$key.last_name", data_get($customerInfos, "$customerId.last_name", ''));
            }
        }

        data_set($voteList, 'is_vote', !empty(data_get($vote, 'vote_item_id', 0)));

        return $voteList;
    }

    /**
     * 用户点赞
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param string $ip ip
     * @param int $voteId
     * @param int $voteItemId
     * @param array $requestData 请求参数
     * @param int $score
     * @return array
     */
    public static function userVote($storeId, $actId, $ip, $voteId, $voteItemId, $requestData, $score = 1) {
        if (!static::ipCheck($ip)) {
            return [
                Constant::RESPONSE_CODE_KEY => '61117',
                Constant::RESPONSE_MSG_KEY => "Illegal IP address",
                Constant::RESPONSE_DATA_KEY => []
            ];
        }

        //判断IP是否点过赞
        $where = [
            Constant::DB_TABLE_IP => $ip,
            'op_type' => 1
        ];
        $vote = VoteLogService::getModel($storeId)->buildWhere($where)->orderBy(Constant::DB_TABLE_PRIMARY, 'desc')->first();
        //投过票
        if (!empty($vote)) {
            //对上一次的点赞score减掉1
            $lastVoteId = static::getVoteModel($storeId)->buildWhere([Constant::DB_TABLE_VOTE_ITEM_ID => data_get($vote, Constant::DB_TABLE_VOTE_ITEM_ID)])->value(Constant::DB_TABLE_PRIMARY);
            $where = [
                Constant::DB_TABLE_PRIMARY => $lastVoteId
            ];
            $update = [
                'score' => DB::Raw("score - 1"),
            ];
            static::getVoteModel($storeId)->buildWhere($where)->update($update);

            //新增取消点赞流水
            $voteLog = [
                Constant::DB_TABLE_ACT_ID => $actId,
                Constant::DB_TABLE_CUSTOMER_PRIMARY => data_get($requestData, Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::PARAMETER_INT_DEFAULT),
                Constant::DB_TABLE_VOTE_ITEM_ID => data_get($vote, Constant::DB_TABLE_VOTE_ITEM_ID),
                Constant::DB_TABLE_ACCOUNT => data_get($requestData, Constant::DB_TABLE_ACCOUNT, Constant::PARAMETER_STRING_DEFAULT),
                Constant::DB_TABLE_IP => $ip,
                Constant::DB_TABLE_COUNTRY => data_get($requestData, Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT),
                'op_type' => 2
            ];
            VoteLogService::getModel($storeId)->insert($voteLog);
        }

        //对本次的点赞加1
        $where = [
            Constant::DB_TABLE_PRIMARY => $voteId,
        ];
        $update = [
            'score' => DB::Raw("score + 1"),
        ];
        static::getVoteModel($storeId)->buildWhere($where)->update($update);

        //新增点赞流水
        $voteLog = [
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => data_get($requestData, Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::PARAMETER_INT_DEFAULT),
            Constant::DB_TABLE_VOTE_ITEM_ID => $voteItemId,
            Constant::DB_TABLE_ACCOUNT => data_get($requestData, Constant::DB_TABLE_ACCOUNT, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_IP => $ip,
            Constant::DB_TABLE_COUNTRY => data_get($requestData, Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT),
            'op_type' => 1
        ];
        VoteLogService::getModel($storeId)->insert($voteLog);

        return [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => '',
            Constant::RESPONSE_DATA_KEY => []
        ];
    }

    /**
     * 判断ip是否有效
     * @param string $ip ip
     * @return bool
     */
    public static function ipCheck($ip) {
        //局域网ip段
        $privateIp = [
            [
                ip2long('10.0.0.0'),
                ip2long('10.255.255.255')
            ],
            [
                ip2long('172.16.0.0'),
                ip2long('172.31.255.255')
            ],
            [
                ip2long('192.168.0.0'),
                ip2long('192.168.255.255')
            ],
        ];

        if (!is_string($ip)) {
            return false;
        }

        $parts = explode('.', $ip);
        if (count($parts) != 4) {
            return false;
        }

        foreach ($parts as $part) {
            if (!ctype_digit($part)) {
                return false;
            }
            if ($part < 0 || $part > 255) {
                return false;
            }
        }

        //判断是否是局域网ip
        $ip2long = ip2long($ip);
        foreach ($privateIp as $item) {
            if ($ip2long >= $item[0] && $ip2long <= $item[1]) {
                //return false;
            }
        }

        return true;
    }

    /**
     * 文件检查
     * @param $files \Illuminate\Http\UploadedFile 文件上传对象或者是文件上传对象数组
     * @return bool
     */
    public static function fileCheck($files) {
        ($files instanceof \Illuminate\Http\UploadedFile) && $files = [$files];

        foreach ($files as $file) {
            //是否上传文件对象
            if (!($file instanceof \Illuminate\Http\UploadedFile)) {
                return false;
            }

            //是否上传成功
            if (!$file->isValid()) {
                return false;
            }

            //是否文件
            if (!$file->isFile()) {
                return false;
            }

            //是否符合文件类型getClientOriginalExtension获得文件后缀名
            $fileExtension = strtolower($file->getClientOriginalExtension());
            if (!in_array($fileExtension, ['jpeg', 'bmp', 'jpg', 'png', 'tif', 'gif', 'pcx', 'tga', 'exif', 'fpx', 'svg', 'psd', 'cdr', 'pcd', 'dxf', 'ufo', 'eps', 'ai', 'raw', 'wmf', 'webp'])) {
                return false;
            }

            //判断大小是否符合10M
            if ($file->getSize() >= 10240000) {
                return false;
            }

            //是否是通过http请求表单提交的文件
            if (!is_uploaded_file($file->getRealPath())) {
                return false;
            }
        }

        return true;
    }

    /**
     * 上传投票图片
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param string $account 账号
     * @param string $uniqueStr 唯一字符串
     * @param array $requestData 请求参数
     * @return array
     */
    public static function uploadVote($storeId, $actId, $customerId, $account, $uniqueStr, $requestData) {
        $isOriginImage = data_get($requestData, 'is_origin_image', 0);

        //判断用户能否上传
        $where = [
            'v.act_id' => $actId,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
        ];
        $data = static::getVoteModel($storeId)
                        ->from('votes as v')
                        ->leftJoin('vote_items as vi', function ($join) {
                            $join->on('v.vote_item_id', '=', 'vi.id');
                        })
                        ->buildWhere($where)->select(['v.id as vote_id', 'v.vote_item_id', 'vi.remarks'])->first();
        //文件描述不为空，不能再次上传
        if (!empty(data_get($data, 'remarks'))) {
            return Response::getDefaultResponseData(61116);
        }

        //上传图片
        $uploadRet = VoteService::upload(data_get($requestData, 'file'), $requestData);

        static::getModel($storeId)->getConnection()->beginTransaction();
        try {

            //上传的是原图
            if ($isOriginImage == 1) {
                $voteItem = [
                    Constant::DB_TABLE_ACT_ID => $actId,
                    'origin_img_url' => data_get($uploadRet, 'aws.0', Constant::PARAMETER_STRING_DEFAULT),
                ];
            } else {
                //上传的是裁剪后的图
                $voteItem = [
                    Constant::DB_TABLE_ACT_ID => $actId,
                    Constant::DB_TABLE_MB_IMG_URL => data_get($uploadRet, 'aws.0', Constant::PARAMETER_STRING_DEFAULT),
                    Constant::DB_TABLE_IMG_URL => data_get($uploadRet, 'aws.0', Constant::PARAMETER_STRING_DEFAULT),
                ];
            }

            $where = [
                Constant::DB_TABLE_UNIQUE_STR => $uniqueStr
            ];
            $VoteItemRet = static::getModel($storeId)->updateOrCreate($where, $voteItem);
            if (empty($VoteItemRet)) {
                //添加记录失败
                throw new \Exception(null, 9900000002);
            }

            //记录图片
            $where = [
                Constant::DB_TABLE_ACT_ID => $actId,
                Constant::DB_TABLE_ACCOUNT => $account,
                Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId
            ];
            $vote = [
                Constant::DB_TABLE_VOTE_ITEM_ID => data_get($VoteItemRet, Constant::DB_TABLE_PRIMARY)
            ];
            $VoteRet = static::getVoteModel($storeId)->updateOrCreate($where, $vote);
            if (empty($VoteRet)) {
                //添加记录失败
                throw new \Exception(null, 9900000002);
            }

            $data = [
                Constant::DB_TABLE_VOTE_ID => data_get($VoteRet, Constant::DB_TABLE_PRIMARY),
                Constant::DB_TABLE_VOTE_ITEM_ID => data_get($VoteItemRet, Constant::DB_TABLE_PRIMARY),
            ];
            static::getModel($storeId, '')->getConnection()->commit();
        } catch (\Exception $exception) {
            static::getModel($storeId, '')->getConnection()->rollBack();
            return Response::getDefaultResponseData($exception->getCode(), $exception->getMessage());
        }

        return Response::getDefaultResponseData(1, null, [
                    array_merge($data, $uploadRet)
        ]);
    }

    /**
     * 用户添加的投票内容
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @return mixed
     */
    public static function voteInfo($storeId, $actId, $customerId) {
        $where = [
            'v.act_id' => $actId,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
        ];
        $select = ['vi.img_url', 'vi.mb_img_url', 'vi.remarks', 'v.id as vote_id', 'v.vote_item_id', 'vi.url'];
        $data = static::getVoteModel($storeId)
                        ->from('votes as v')
                        ->leftJoin('vote_items as vi', 'v.vote_item_id', '=', 'vi.id')
                        ->buildWhere($where)->select($select)->first();

        if (!empty($data)) {
            $profileUrl = CustomerInfoService::getModel($storeId)->buildWhere([Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId])->value('profile_url');
            data_set($data, 'profile_url', $profileUrl);
        }

        return $data;
    }

    /**
     * 根据用户输入词搜索参赛图片列表
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param string $text 输入词
     * @param string $extType 关联类型
     * @param int $page 页码
     * @param int $pageSize 页大小
     * @param array $requestData 请求参数
     * @return array
     */
    public static function searchList($storeId, $actId, $text, $extType, $page, $pageSize, $requestData = []) {
//        $words = ActivitySearchService::wordSegment($text);
//        $words = ActivitySearchService::removeInvalidWords($words);
//        if (empty($words)) {
//            return [
//                Constant::RESPONSE_DATA_KEY => [],
//                'pagination' => []
//            ];
//        }
//
//        $where = [
//            Constant::DB_TABLE_ACT_ID => $actId,
//            'word' => $words
//        ];
//        !empty($extType) && $where[Constant::DB_TABLE_EXT_TYPE] = $extType;
//        $select = [Constant::DB_TABLE_EXT_ID, Constant::DB_TABLE_EXT_TYPE];
//        $list = ActivitySearchService::getModel($storeId)->buildWhere($where)->select($select)->get();
//        if ($list->isEmpty()) {
//            return [
//                Constant::RESPONSE_DATA_KEY => [],
//                'pagination' => []
//            ];
//        }
//        $extIds = array_column($list->toArray(), NULL, Constant::DB_TABLE_EXT_ID);
//        $extData = [
//            'ext_ids' => array_keys($extIds)
//        ];
        $extData = [];

        return VoteService::normalList($storeId, $actId, '', $page, $pageSize, $requestData, $extData);
    }

    /**
     * 参赛图片列表
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param string $account 账号
     * @param int $page 页码
     * @param int $pageSize 页大小
     * @param array $requestData 请求参数
     * @return array
     */
    public static function voteList($storeId, $actId, $account, $page, $pageSize, $requestData) {
        return VoteService::normalList($storeId, $actId, $account, $page, $pageSize, $requestData);
    }

    /**
     * 获取月度美食活动配置
     * @param int $storeId 官网id
     * @param array $requestData 请求参数
     * @return mixed
     */
    public static function getActivity($storeId, $requestData) {
        $select = ['id as act_id', Constant::DB_TABLE_NAME, Constant::DB_TABLE_START_AT, Constant::DB_TABLE_END_AT, Constant::DB_TABLE_MARK];
        $mark = data_get($requestData, Constant::DB_TABLE_MARK, Constant::PARAMETER_STRING_DEFAULT);
        if (!empty($mark)) {
            $activity = ActivityService::getModel($storeId)->buildWhere([Constant::DB_TABLE_MARK => $mark])->select($select)->first();
        } else {
            $actId = data_get($requestData, Constant::DB_TABLE_ACT_ID, Constant::PARAMETER_INT_DEFAULT);
            $activity = ActivityService::getModel($storeId)->buildWhere([Constant::DB_TABLE_PRIMARY => $actId])->select($select)->first();
        }

        $where = [
            Constant::DB_TABLE_ACTIVITY_ID => data_get($activity, Constant::DB_TABLE_ACT_ID),
            Constant::DB_TABLE_TYPE => [
                'fb_post',
                'fb_vote',
                'vote',
                'submit_picture',
                'fb_iframe',
                'banner',
                'share'
            ]
        ];
        $select = [Constant::DB_TABLE_TYPE, Constant::DB_TABLE_KEY, Constant::DB_TABLE_VALUE];
        $times = ActivityService::getModel($storeId, '', [], 'ActivityConfig')->buildWhere($where)->select($select)->get();
        $ret = [];
        foreach ($times as $time) {
            data_set($ret, data_get($time, Constant::DB_TABLE_TYPE) . '_' . data_get($time, Constant::DB_TABLE_KEY), data_get($time, Constant::DB_TABLE_VALUE));
        }
        data_set($ret, 'now_time', Carbon::now()->toDateTimeString());

        data_set($activity, 'mid_time', data_get($ret, 'vote_end_at'));
        data_set($activity, 'act_conf', $ret);

        return $activity;
    }

    /**
     * 判断当前时候是否在配置时间段内
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param string $type 配置类型
     * @return bool
     */
    public static function isWithinTime($storeId, $actId, $type) {
        $nowTime = Carbon::now()->toDateTimeString();

        $times = ActivityService::getActivityConfigData($storeId, $actId, $type);
        $startTime = data_get($times, $type . '_start_at.value', '');
        $endTime = data_get($times, $type . '_end_at.value', '');
        if (empty($startTime) || empty($endTime)) {
            return false;
        }

        return $startTime <= $nowTime && $nowTime <= $endTime;
    }

    /**
     * 邮件发送
     * @param int $storeId
     * @param int $actId
     * @param string $toEmail
     * @param int $customerId
     * @param string $ip
     * @param int $extId
     * @param string $extType
     * @param string $type
     * @param string $key
     * @param string $remark
     */
    public static function emailToQueue($storeId, $actId, $toEmail, $customerId, $ip, $extId, $extType, $type = 'email', $key = 'vote_food', $remark = 'ikich food') {
        $service = static::getNamespaceClass();
        $method = 'emailQueue';
        $parameters = [$storeId, $actId, $toEmail, $customerId, $ip, $extId, $extType, $type, $key, $remark];
        $data = [
            Constant::SERVICE_KEY => $service,
            Constant::METHOD_KEY => $method,
            Constant::PARAMETERS_KEY => $parameters,
        ];
        Queue::push(new PublicJob($data));
    }

    /**
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param string $toEmail 接收邮件
     * @param int $customerId 会员id
     * @param string $ip ip
     * @param int $extId 关联id
     * @param string $extType 关联模型
     * @param string $type 类型
     * @param string $key key
     * @param string $remark 备注
     * @return bool
     */
    public static function emailQueue($storeId, $actId, $toEmail, $customerId, $ip, $extId, $extType, $type = 'email', $key = 'vote_food', $remark = 'ikich food') {
        $group = 'vote';
        $emailType = $key;

        //判断邮件是否已经发送
        $where = [
            'store_id' => $storeId,
            'group' => $group,
            'type' => $emailType,
            'to_email' => $toEmail,
            'ext_id' => $extId,
            'ext_type' => $extType,
            'act_id' => $actId,
        ];
        $isExists = EmailService::exists($storeId, '', $where);
        if ($isExists) {
            return false;
        }

        $extService = static::getNamespaceClass();
        $extMethod = 'getEmailData';
        $extParameters = [$storeId, $actId, $toEmail, $customerId, $ip, $extId, $extType, $type, $key];

        $extData = [
            'actId' => $actId,
            'service' => $extService,
            'method' => $extMethod,
            'parameters' => $extParameters,
            'callBack' => [
            ]
        ];

        $service = EmailService::getNamespaceClass();
        $method = 'handle'; //邮件处理
        $parameters = [$storeId, $toEmail, $group, $emailType, $remark, $extId, $extType, $extData];

        $data = [
            'service' => $service,
            'method' => $method,
            'parameters' => $parameters,
            'extData' => [
                'service' => $service,
                'method' => $method,
                'parameters' => $parameters,
            ],
        ];

        Queue::push(new PublicJob($data));

        return true;
    }

    /**
     * @param int $storeId
     * @param int $actId
     * @param str $toEmail
     * @param int $customerId
     * @param string $ip
     * @param int $extId
     * @param string $extType
     * @param string $type
     * @param string $key
     * @return array
     */
    public static function getEmailData($storeId, $actId, $toEmail, $customerId, $ip, $extId, $extType, $type = 'email', $key = 'vote_food') {

        $rs = [
            'code' => 1,
            'storeId' => $storeId, //商城id
            'actId' => $actId, //活动id
            'content' => '', //邮件内容
            'subject' => '',
            'country' => '',
            'ip' => $ip,
            'extId' => $extId,
            'extType' => $extType,
        ];

        $activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, $type, [$key, ($key . '_subject'), 'code', 'code_url']);
        $emailView = Arr::get($activityConfigData, $type . '_' . $key . '.value', '');  //邮件模板
        $subject = Arr::get($activityConfigData, $type . '_' . $key . '_subject' . '.value', '');  //邮件主题
        data_set($rs, 'subject', $subject);

        //获取邮件模板
        $data = CustomerInfoService::exists($storeId, $customerId, '', true);
        $firstName = data_get($data, 'first_name', '');
        $account = $firstName ? $firstName : FunctionHelper::handleAccount(data_get($data, 'account', ''));

        $replacePairs = [
            '{{$first_name}}' => $account,
            '{{$code}}' => Arr::get($activityConfigData, $type . '_' . 'code' . '.value', ''),
            '{{$code_url}}' => Arr::get($activityConfigData, $type . '_' . 'code_url' . '.value', '')
        ];
        data_set($rs, 'content', strtr($emailView, $replacePairs));

        unset($data);
        unset($replacePairs);

        return $rs;
    }

    /**
     * 同步投票活动产品到投票表
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $extId 关联id
     * @param string $extType 关联模型
     * @param string $name 产品名字
     * @param string $imgUrl 产品图片
     * @param string $des 产品描述
     * @return array 同步结果
     */
    public static function sysVoteDataFromActivityProduct($storeId, $actId, $extId, $extType, $name, $imgUrl, $des) {
        //投票产品同步到投票item中
        $where = [
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_EXT_ID => $extId, //关联id
            Constant::DB_TABLE_EXT_TYPE => $extType, //关联模型
        ];
        $voteItemData = [
            Constant::DB_TABLE_NAME => $name,
            Constant::DB_TABLE_IMG_URL => $imgUrl, //商品主图
            Constant::DB_TABLE_MB_IMG_URL => $imgUrl, //移动端商品主图
            Constant::DB_TABLE_REMARKS => $des, //产品描述
        ];
        return static::updateOrCreate($storeId, $where, $voteItemData);
    }

    /**
     * 用户上传视频投票项
     * 1. 记录上传信息
     * 2. 发邮件
     * 3. 发图片描述到队列
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param string $account 账号
     * @param array $requestData 请求参数
     * @return array
     */
    public static function addVideoVote($storeId, $actId, $customerId, $account, $requestData) {
        //用户是否添加了投票内容
        $where = [
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::DB_TABLE_ACT_ID => $actId,
        ];
        $cnt = static::getVoteModel($storeId)->buildWhere($where)->count();
        if ($cnt) {
            return Response::getDefaultResponseData(Constant::PARAMETER_INT_DEFAULT);
        }

        static::getModel($storeId)->getConnection()->beginTransaction();
        try {
            $uniqueStr = data_get($requestData, Constant::DB_TABLE_UNIQUE_STR, Constant::PARAMETER_STRING_DEFAULT);
            empty($uniqueStr) && $uniqueStr = $customerId . '_' . $account;

            $voteItem = [
                Constant::DB_TABLE_ACT_ID => $actId,
                'search_text' => static::getText($storeId, $customerId, $requestData),
                Constant::DB_TABLE_REMARKS => data_get($requestData, Constant::DB_TABLE_REMARKS, Constant::PARAMETER_STRING_DEFAULT),
                Constant::FILE_URL => data_get($requestData, 'video_url', Constant::PARAMETER_STRING_DEFAULT),
            ];

            $where = [
                Constant::DB_TABLE_UNIQUE_STR => $uniqueStr
            ];
            $VoteItemRet = static::getModel($storeId)->updateOrCreate($where, $voteItem);
            if (empty($VoteItemRet)) {
                //添加记录失败
                throw new \Exception(null, 9900000002);
            }

            $voteItemId = data_get($VoteItemRet, Constant::DB_TABLE_PRIMARY);

            //记录图片
            $where = [
                Constant::DB_TABLE_ACT_ID => $actId,
                Constant::DB_TABLE_ACCOUNT => $account,
                Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId
            ];
            $vote = [
                Constant::DB_TABLE_VOTE_ITEM_ID => $voteItemId
            ];
            $VoteRet = static::getVoteModel($storeId)->updateOrCreate($where, $vote);
            if (empty($VoteRet)) {
                //添加记录失败
                throw new \Exception(null, 9900000002);
            }

            $profileUrl = data_get($requestData, 'profile_url', Constant::PARAMETER_STRING_DEFAULT);
            if (!empty($profileUrl)) {
                $update = [
                    'profile_url' => $profileUrl
                ];
                CustomerInfoService::getModel($storeId)->buildWhere([Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId])->update($update);
            }

            $ip = data_get($requestData, Constant::DB_TABLE_IP, Constant::PARAMETER_STRING_DEFAULT);
            static::emailToQueue($storeId, $actId, $account, $customerId, $ip, $voteItemId, "VoteItems", 'email', 'vote_video', 'video vote');

            static::getModel($storeId, '')->getConnection()->commit();
        } catch (\Exception $exception) {
            static::getModel($storeId, '')->getConnection()->rollBack();
            return Response::getDefaultResponseData($exception->getCode(), $exception->getMessage());
        }

        return Response::getDefaultResponseData(1);
    }

    /**
     * 获取视频地址
     * @param string $url 视频播放页地址
     * @return bool|string
     */
    public static function getVideoUrl($url) {
        $host = 'host';
        $result = parse_url($url);
        if (empty($result)) {
            return false;
        }

        if (!isset($result[$host])) {
            return false;
        }

        if ($result[$host] != 'youtu.be' && $result[$host] != 'www.youtube.com') {
            return false;
        }

        $videoId = '';
        if ($result[$host] == 'youtu.be') {
            if (!isset($result['path'])) {
                return false;
            }

            $videoId = substr($result['path'], 1, 11);
        }

        if ($result[$host] == 'www.youtube.com') {
            if (!isset($result['query'])) {
                return false;
            }
            $query = explode("&", $result['query']);
            foreach ($query as $item) {
                $params = explode("=", $item);
                if ($params[0] == 'v') {
                    $videoId = $params[1];
                }
            }
        }

        if (empty($videoId) || strlen($videoId) != 11) {
            return false;
        }

        return "https://www.youtube.com/embed/$videoId";
    }

}
