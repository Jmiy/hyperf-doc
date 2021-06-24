<?php

/**
 * 排行榜服务
 * User: Jmiy
 * Date: 2019-07-01
 * Time: 11:15
 */

namespace App\Services;

use Hyperf\DbConnection\Db as DB;
use Hyperf\Utils\Arr;
use App\Utils\Support\Facades\Redis;
use Carbon\Carbon;
use App\Utils\Support\Facades\Cache;
use App\Utils\FunctionHelper;
use App\Constants\Constant;

class RankService extends BaseService {

    /**
     * 获取db query
     * @param array $where
     * @return \Illuminate\Database\Query\Builder|static $query
     */
    public static function getQuery($storeId, $where = []) {
        $query = static::getModel($storeId, '')
                ->where($where)
                ->with(['customer' => function($query) {
                $query->select(['account', 'customer_id']);
            }]); //::leftJoin('customer_info as a', 'a.customer_id', '=', 'ranks.customer_id')
        return $query;
    }

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @return array
     */
    public static function getPublicData($params, $order = []) {

        $where = [];

        $params['customer_id'] = $params['customer_id'] ?? 0; //会员ID
        $params['act_id'] = $params['act_id'] ?? 0; //活动id
        $params['type'] = $params['type'] ?? 0; //榜单类型 1:分享 2:邀请

        if ($params['customer_id']) {//会员ID
            $where[] = ['ranks.customer_id', '=', $params['customer_id']];
        }

        if ($params['act_id'] !== null) {//活动id
            $where[] = ['ranks.act_id', '=', $params['act_id']];
        }

        if ($params['type']) {//榜单类型 1:分享 2:邀请
            $where[] = ['ranks.type', '=', $params['type']];
        }

        $order = $order ? $order : ['ranks.id', 'desc'];
        return Arr::collapse([parent::getPublicData($params, $order), [
                        'where' => $where,
        ]]);
    }

    /**
     * 获取展示格式的数据
     * @param array $data  源数据
     * @return array $data 展示格式的数据
     */
    public static function getShowData($data) {

        $typeData = Rank::$typeData;
        foreach ($data[Constant::RESPONSE_DATA_KEY] as $key => $item) {
            $data[Constant::RESPONSE_DATA_KEY][$key]['type'] = $typeData[$data[Constant::RESPONSE_DATA_KEY][$key]['type']];
        }

        return $data;
    }

    /**
     * 列表
     * @param $params
     * @param bool $toArray
     * @param bool $isPage
     * @return array
     */
    public static function getListData($params, $toArray = true, $isPage = true, $select = [], $isRaw = false) {

        $_data = static::getPublicData($params);

        $where = $_data['where'];
        $order = $_data['order'];
        $pagination = $_data['pagination'];
        $limit = $pagination['page_size'];

        $customerCount = true;
        $storeId = data_get($params, 'store_id', 0);
        $query = static::getQuery($storeId, $where);
        if ($isPage) {
            //\Illuminate\Support\Facades\DB::enableQueryLog();
            //var_dump(\Illuminate\Support\Facades\DB::getQueryLog());
            //exit;
            $customerCount = $query->count();
            $pagination['total'] = $customerCount;
            $pagination['total_page'] = ceil($customerCount / $limit);
        }

        if (empty($customerCount)) {
            $query = null;
            return [
                Constant::RESPONSE_DATA_KEY => [],
                'pagination' => $pagination,
            ];
        }

        $query = $query->orderBy($order[0], $order[1]);
        $data = [
            'query' => $query,
            'pagination' => $pagination,
        ];

        $select = $select ? $select : ['customer_id', 'type', 'score'];

        $data = static::getList($data, $toArray, $isPage, $select, $isRaw);
        $data = static::getShowData($data);

        return $data;
    }

    /**
     * 添加记录
     * @param int $storeId 商城id
     * @param array $where where条件
     * @param array $data 数据
     * @return bool
     */
    public static function insert($storeId, $where, $data) {
        return static::updateOrCreate($storeId, $where, $data); //updateOrCreate：不可以添加主键id的值  updateOrInsert：可以添加主键id的值
    }

    /**
     * 获取会员数据
     * @param int $customerId 会员id
     * @return array $customerInfoData 会员数据
     */
    public static function getCustomerData($customerId = 0, $data = []) {

        if ($data) {
            return [
                'customer_id' => data_get($data, 'customer_id', 0),
                'first_name' => data_get($data, 'customer.account', ''),
                'last_name' => '',
            ];
        }

        if (empty($customerId)) {
            return [];
        }

        $data = CustomerService::customerExists(0, $customerId, '', 0, true);
        if (empty($data)) {
            return [];
        }

        return [
            'customer_id' => data_get($data, 'customer_id', 0),
            'first_name' => data_get($data, 'account', ''),
            'last_name' => '',
        ];
    }

    /**
     * 获取排行榜的key
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $rankType 榜单种类 1：总榜 2：日榜
     * @param int $type 榜单类型 0:综合榜 1:分享 2:邀请 3:签到
     * @return string 排行榜的key
     */
    public static function getRankKey($storeId = 0, $actId = 0, $rankType = 1, $type = 1) {
        $zsetKey = implode(':', func_get_args());
        return $zsetKey . ($rankType == 2 ? (':' . static::getDayTimestamp($storeId)) : '');
    }

    /**
     * 获取走马灯的key
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $type 榜单类型 1:分享 2:邀请
     * @return string 排行榜的key
     */
    public static function getLanternRankKey($storeId = 0, $actId = 0, $type = 1) {
        $zsetKey = static::getRankKey($storeId, $actId, 1, $type);
        return $zsetKey . ':lantern';
    }

    /**
     * 更新排行榜初始化数据
     * @param int $storeId  商城id
     * @param int $id 榜单初始化id
     * @param int $score 榜单id对应的积分
     * @return init $score
     */
    public static function updateRandInit($storeId = 0, $id = 0, $score = 0) {
        //更新排行榜初始化数据
        $_score = mt_rand(1, 2);
        switch ($storeId) {
            case 1://mpow
                $_score = $_score * 50;

                break;

            default:
                break;
        }
        $nowTime = Carbon::now()->toDateTimeString();
        $updateData = [
            'score' => DB::raw('score+' . $_score),
            'updated_at' => $nowTime,
        ];
        $where = [
            'id' => $id,
        ];
        static::createModel($storeId, 'RankInit')->where($where)->update($updateData);

        return $score + $_score;
    }

    /**
     * 初始化 日榜  综合排名
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $rankType 榜单种类 1：总榜 2：日榜
     * @param int $type 榜单类型 0:综合榜 1:分享 2:邀请 3:签到
     * @return boolean true:初始化成功
     */
    public static function initDayRank($storeId = 0, $actId = 0, $rankType = 2, $type = 0) {
        $zsetDayKey = static::getRankKey($storeId, $actId, $rankType, $type);
        $isExists = Redis::exists($zsetDayKey);
        if ($isExists) {
            return true;
        }

        /*         * ***********************初始化日榜 综合排名 start ********************** */
        $dayType = static::getDayTimestamp($storeId);
        $dayWhere = [
            'act_id' => $actId,
            'day' => $dayType,
        ];

        if (!empty($type)) {
            data_set($dayWhere, 'type', $type);
        }

        $query = static::getDayQuery($storeId, $dayWhere);

        $select = ['customer_id'];
        if (empty($type)) {
            $query = $query->groupBy('customer_id');
            $select[] = DB::raw('sum(score) as score');
        } else {
            $select[] = 'score';
        }

        $query->select($select)
                ->chunk(50, function ($data) use($zsetDayKey) {
                    foreach ($data as $item) {
                        $score = $item->score;
                        $member = static::getCustomerData(0, $item);
                        Redis::zadd($zsetDayKey, $score, static::getZsetMember($member));
                    }
                });

        $dayCount = Redis::zcard($zsetDayKey);
        if ($dayCount < 10) {
            $dayWhere = [
                'act_id' => $actId,
            ];
            $count = 10 - $dayCount;
            static::createModel($storeId, 'RankInit')->where($dayWhere)
                    ->select(['id', 'score', 'first_name', 'last_name'])
                    ->inRandomOrder()
                    ->chunk($count, function ($data) use($zsetDayKey) {
                        foreach ($data as $item) {
                            $score = mt_rand(1, 2) * 50;
                            $member = [
                                'customer_id' => '00' . $item->id,
                                'first_name' => $item->first_name . $item->last_name,
                                'last_name' => '',
                            ];
                            Redis::zadd($zsetDayKey, $score, static::getZsetMember($member));
                        }
                        return false;
                    });
        }

        //初始化日榜排行数据
        $isExists = Redis::exists($zsetDayKey);
        if (empty($isExists)) {//如果数据为空，就构造假数据
            $member = [
                'customer_id' => 0,
                'first_name' => 'Robert day',
                'last_name' => '',
            ];
            Redis::zadd($zsetDayKey, 0, static::getZsetMember($member));
        }
        $ttl = $dayType - (Carbon::now()->timestamp); //缓存时间 单位秒
        Redis::expire($zsetDayKey, $ttl);
        /*         * ***********************初始化日榜 综合排名 end ********************** */

        return true;
    }

    /**
     * 初始化 总榜 $type 排行榜 走马灯 数据
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $rankType 榜单种类 1：总榜 2：日榜
     * @param int $type 榜单类型 0:综合榜 1:分享 2:邀请 3:签到
     * @return boolean true:初始化成功
     */
    public static function initRank($storeId = 0, $actId = 0, $rankType = 1, $type = 1) {
        $zsetKey = static::getRankKey($storeId, $actId, $rankType, $type);
        $zsetKeyLantern = static::getLanternRankKey($storeId, $actId, $type);
        $isExists = Redis::exists($zsetKey);
        if ($isExists) {
            return true;
        }

        /*         * **********************初始化 总榜 $type 对应的榜单 start ******************************* */
        $where = [
            'act_id' => $actId,
        ];
        if ($type) {
            data_set($where, 'type', $type);
        }
        $query = static::getQuery($storeId, $where);

        $select = ['customer_id', 'updated_at'];
        if (empty($type)) {
            $query = $query->groupBy('customer_id');
            $select[] = DB::raw('sum(score) as score');
        } else {
            $select[] = 'score';
        }

        $query->select($select)
                ->chunk(100, function ($data) use($zsetKey, $type, $zsetKeyLantern) {
                    foreach ($data as $item) {
                        $score = $item->score;
                        $member = static::getCustomerData(0, $item);
                        Redis::zadd($zsetKey, $score, static::getZsetMember($member));

                        if ($type == 1) {//1:分享
                            $member = [
                                'account' => data_get($item, 'customer.account', ''),
                            ];
                            Redis::zadd($zsetKeyLantern, Carbon::parse($item->updated_at)->timestamp, static::getZsetMember($member));
                        }
                    }
                });

        $customerCount = Redis::zcard($zsetKey);
        if ($customerCount < 100) {
            $where = [
                'act_id' => $actId,
            ];

            if ($type) {
                data_set($where, 'type', $type);
            }

            $count = 100 - $customerCount;
            static::createModel($storeId, 'RankInit')
                    ->where($where)
                    ->select(['score', 'id', 'first_name', 'last_name', 'updated_at'])
                    ->chunk($count, function ($data) use($storeId, $zsetKey, $type, $zsetKeyLantern) {
                        foreach ($data as $item) {

                            //更新排行榜初始化数据
                            $score = static::updateRandInit($storeId, $item->id, $item->score);
                            $member = [
                                'customer_id' => '00' . $item->id,
                                'first_name' => $item->first_name . $item->last_name,
                                'last_name' => '',
                            ];
                            Redis::zadd($zsetKey, $score, static::getZsetMember($member));

                            if ($type == 1) {//1:分享
                                $member = [
                                    'account' => $item->first_name . $item->last_name,
                                ];
                                Redis::zadd($zsetKeyLantern, Carbon::parse($item->updated_at)->timestamp, static::getZsetMember($member));
                            }
                        }

                        return false;
                    });
        }

        $ttl = 30 * 24 * 60 * 60;
        $isExists = Redis::exists($zsetKey);
        if (empty($isExists)) {//如果排行榜数据为空，就构造假数据
            $member = [
                'customer_id' => 0,
                'first_name' => $type == 1 ? 'Alexis share' : 'Robert invite',
                'last_name' => '',
            ];
            Redis::zadd($zsetKey, 0, static::getZsetMember($member));
        }
        Redis::expire($zsetKey, $ttl);

        if ($type == 1) {
            $isExists = Redis::exists($zsetKeyLantern);
            if (empty($isExists)) {//如果走马灯数据为空，就构造假数据
                $member = [
                    'account' => 'Alexis@gmail.com',
                ];
                Redis::zadd($zsetKeyLantern, Carbon::now()->timestamp, static::getZsetMember($member));
            }
            Redis::expire($zsetKeyLantern, $ttl);
        }
        /*         * **********************初始化 总榜 $type 对应的榜单 end ******************************* */

        return false;
    }

    /**
     * 获取db query
     * @param int $storeId  商城id
     * @param array $where
     * @return \Illuminate\Database\Query\Builder|static $query
     */
    public static function getDayQuery($storeId, $where = []) {
        $query = RankDayService::getModel($storeId)
                ->with(['customer' => function($query) {
                        $query->select(['account', 'customer_id']);
                    }])
                ->where($where)
        ;
        return $query;
    }

    /**
     * 获取当天时间戳
     */
    public static function getDayTimestamp($storeId, $timestamp = null) {
        FunctionHelper::setTimezone($storeId); //设置时区
        return $timestamp !== null ? $timestamp : Carbon::parse(Carbon::now()->rawFormat('Y-m-d 23:59:59'))->timestamp;
    }

    /**
     * 初始化 排行榜 走马灯 数据
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $rankTypeData 榜单种类 1：总榜 2：日榜
     * @param int $type 榜单类型 0:综合榜 1:分享 2:邀请 3:签到
     * @return boolean true:初始化成功
     */
    public static function initAllRank($storeId = Constant::PARAMETER_INT_DEFAULT, $actId = Constant::PARAMETER_INT_DEFAULT, $rankTypeData = [1], $type = Constant::PARAMETER_INT_DEFAULT) {

        if (is_array($rankTypeData)) {
            foreach ($rankTypeData as $rankType) {
                static::initAllRank($storeId, $actId, $rankType, $type);
            }
            return true;
        }

        switch ($rankTypeData) {
            case 1:
                //初始化 总榜 综合排名 数据
                static::initRank($storeId, $actId, $rankTypeData, Constant::PARAMETER_INT_DEFAULT);
                if ($type) {
                    //初始化 总榜 $type 排行榜 走马灯 数据
                    static::initRank($storeId, $actId, $rankTypeData, $type);
                }

                break;

            case 2:
                //初始化 日榜  综合排名
                static::initDayRank($storeId, $actId, $rankTypeData, Constant::PARAMETER_INT_DEFAULT);

                if ($type) {
                    //初始化 日榜  $type 排名
                    static::initDayRank($storeId, $actId, $rankTypeData, $type);
                }

                break;

            default:
                break;
        }

        return false;
    }

    /**
     * 更新汇总数据
     * @param int $storeId 商城id
     * @param int $customerId 会员id
     * @param int $actId 活动id
     * @param int $rankTypeData 榜单种类 1：总榜 2：日榜
     * @param int $type 榜单类型 0:综合榜 1:分享 2:邀请 3:签到
     * @param int $score 积分
     * @return int
     */
    public static function handle($storeId = 0, $customerId = 0, $actId = 0, $rankTypeData = [1], $type = 1, $score = 1) {

        if (empty($storeId)) {
            return [Constant::RESPONSE_CODE_KEY => 2, Constant::RESPONSE_MSG_KEY => 'storeId is required.', Constant::RESPONSE_DATA_KEY => []];
        }

        if (empty($score)) {
            return [Constant::RESPONSE_CODE_KEY => 1, Constant::RESPONSE_MSG_KEY => '', Constant::RESPONSE_DATA_KEY => []];
        }

//        if (empty($actId)) {
//            //获取有效的活动id
//            $actId = ActivityService::getValidActIds($storeId);
//        }

        if (empty($actId)) {//如果活动已经结束，就直接返回
            return [Constant::RESPONSE_CODE_KEY => 1, Constant::RESPONSE_MSG_KEY => '', Constant::RESPONSE_DATA_KEY => []];
        }

        $member = static::getCustomerData($customerId);
        if (empty($member)) {
            return [Constant::RESPONSE_CODE_KEY => 3, Constant::RESPONSE_MSG_KEY => 'customer not exists.', Constant::RESPONSE_DATA_KEY => []];
        }
        $member = static::getZsetMember($member);

        //初始化总排行榜
        static::initAllRank($storeId, $actId, $rankTypeData, $type);

        $where = [
            'customer_id' => $customerId,
            'act_id' => $actId,
            'type' => $type,
        ];

        if ($type == 1) {

            //更新会员参与活动的时间，用于更新走马灯数据
            $lanternMember = [
                'account' => $member['first_name'],
            ];
            $zsetKeyLantern = static::getLanternRankKey($storeId, $actId, $type);
            Redis::zadd($zsetKeyLantern, Carbon::now()->timestamp, static::getZsetMember($lanternMember));

            //一天只能累计分享10次
            $key = $storeId . ':' . md5(json_encode($where));
            $tags = config('cache.tags.shareLimt');
            $shareLimt = Cache::tags($tags)->get($key);
            if ($shareLimt > 9) {
                return [Constant::RESPONSE_CODE_KEY => 5, Constant::RESPONSE_MSG_KEY => 'Share over limit.', Constant::RESPONSE_DATA_KEY => []];
            }

            if (Cache::tags($tags)->has($key)) {
                Cache::tags($tags)->increment($key);
            } else {
                $ttl = (Carbon::parse(Carbon::now()->rawFormat('Y-m-d 23:59:59'))->timestamp) - (Carbon::now()->timestamp); //缓存时间 单位秒
                Cache::tags($tags)->put($key, 1, $ttl);
            }
        }

        $data = [
            'score' => DB::raw('score+' . $score),
        ];

        $typeData = [
            Constant::PARAMETER_INT_DEFAULT,
            $type,
        ];

        $updateOrCreateData = [];

        $extType = static::getModelAlias();
        foreach ($rankTypeData as $rankType) {

            switch ($rankType) {
                case 1:
                    /*                     * *************更新总榜数据 start ********** */

                    $updateOrCreateData = static::updateOrCreate($storeId, $where, $data);
                    if (data_get($updateOrCreateData, Constant::DB_OPERATION, Constant::DB_OPERATION_DEFAULT) != Constant::DB_OPERATION_DEFAULT) {
                        foreach ($typeData as $_type) {
                            $zsetKey = static::getRankKey($storeId, $actId, $rankType, $_type);
                            Redis::zincrby($zsetKey, $score, $member);
                        }
                    }
                    /*                     * *************更新总榜数据 end ********** */
                    break;

                case 2:
                    /*                     * *************更新日榜数据 start ********** */
                    $where['day'] = static::getDayTimestamp($storeId);
                    $updateOrCreateData = RankDayService::updateOrCreate($storeId, $where, $data);
                    if (data_get($updateOrCreateData, Constant::DB_OPERATION, Constant::DB_OPERATION_DEFAULT) != Constant::DB_OPERATION_DEFAULT) {
                        foreach ($typeData as $_type) {
                            $zsetDayKey = static::getRankKey($storeId, $actId, $rankType, $_type);
                            Redis::zincrby($zsetDayKey, $score, $member);
                        }
                    }

                    $extType = RankDayService::getModelAlias();
                    /*                     * *************更新日榜数据 end ********** */
                    break;

                default:
                    break;
            }
        }

        return [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => '',
            Constant::RESPONSE_DATA_KEY => $updateOrCreateData,
            Constant::DB_TABLE_EXT_ID => data_get($updateOrCreateData, (Constant::RESPONSE_DATA_KEY . '.' . Constant::DB_TABLE_PRIMARY), Constant::PARAMETER_INT_DEFAULT),
            Constant::DB_TABLE_EXT_TYPE => $extType,
            Constant::DB_TABLE_EXT_DATA => $score,
        ];
    }

    /**
     * 获取排行榜数据
     * @param int $customerId 账号id
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $rankType 榜单种类 1：总榜 2：日榜
     * @param int $type 榜单类型 0:综合榜 1:分享 2:邀请 3:签到
     * @param int $page
     * @param int $pageSize
     * @return type
     */
    public static function getRankData($customerId = 0, $storeId = 0, $actId = 0, $rankType = 1, $type = 1, $page = 1, $pageSize = 10) {

        $publicData = [
            'page' => $page,
            'page_size' => $pageSize,
        ];
        $_data = parent::getPublicData($publicData);

        $pagination = $_data['pagination'];
        $offset = $pagination['offset'];
        $count = $pagination['page_size'];

        if (empty($actId)) {//如果活动结束，就直接返回
            $pagination['total'] = 0;
            $pagination['total_page'] = 0;
            return [
                'pagination' => $pagination,
                Constant::RESPONSE_DATA_KEY => [],
                'customerRankData' => [],
            ];
        }

        //初始化总排行榜
        static::initAllRank($storeId, $actId, $rankType, $type);
        $zsetKey = static::getRankKey($storeId, $actId, $rankType, $type);

        //获取分页数据
        $rankTotalKey = 'total_' . $rankType;
        $activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, ['rank'], [$rankTotalKey]);
        $rankTotal = data_get($activityConfigData, 'rank_' . $rankTotalKey . '.value', 100);

        $customerCount = Redis::zcard($zsetKey);
        $customerCount = $customerCount > $rankTotal ? $rankTotal : $customerCount;
        $pagination['total'] = $customerCount;
        $pagination['total_page'] = ceil($customerCount / $count);
        $rankData = [];
        $customerRankData = [];

        if ($customerCount <= 0) {
            return [
                'pagination' => $pagination,
                Constant::RESPONSE_DATA_KEY => $rankData,
                'customerRankData' => $customerRankData,
            ];
        }

        /*         * **************获取排行榜数据*************** */
        $_count = $customerCount - $offset;
        $options = [
            'withscores' => true,
            'limit' => [
                'offset' => $offset,
                'count' => $_count > $count ? $count : $_count,
            ]
        ];
        $data = Redis::zrevrangebyscore($zsetKey, '+inf', '-inf', $options); //降序排序
        //$data = Redis::zrangebyscore($zsetKey, '-inf', '+inf', $options); //升序排序
        if (empty($data)) {
            return [
                'pagination' => $pagination,
                Constant::RESPONSE_DATA_KEY => $rankData,
                'customerRankData' => $customerRankData,
            ];
        }

        //排行榜数据
        foreach ($data as $member => $score) {
            $no = Redis::zrevrank($zsetKey, $member);
            $member = static::getSrcMember($member);
            $member['no'] = $no + 1;
            $member['score'] = $score;
            $member['first_name'] = FunctionHelper::handleAccount($member['first_name']);
            $rankData[] = $member;
        }

        /*         * **************获取当前会员的排名数据*************** */
        $member = static::getCustomerData($customerId);
        if (empty($member)) {
            return [
                'pagination' => $pagination,
                Constant::RESPONSE_DATA_KEY => $rankData,
                'customerRankData' => $customerRankData,
            ];
        }

        $_member = static::getZsetMember($member);
        $no = Redis::zrevrank($zsetKey, $_member);
        $score = Redis::zscore($zsetKey, $_member);
        $member['no'] = $no !== false ? ($no + 1) : 999;
        $member['score'] = $score !== false ? $score : 0;
        $member['first_name'] = FunctionHelper::handleAccount($member['first_name']);
        $member['invite'] = data_get(InviteService::initInviteStatistics($storeId, $customerId, $actId), 'count', 0);
        $customerRankData = $member;

        return [
            'pagination' => $pagination,
            Constant::RESPONSE_DATA_KEY => $rankData,
            'customerRankData' => $customerRankData,
        ];
    }

    /**
     * 获取活动首页走马灯数据
     * @param int $storeId 商城id
     * @param int $actId  活动id
     * @param int $type 榜单类型 0:综合榜 1:分享 2:邀请 3:签到
     * @return type
     */
    public static function getLanternData($storeId = 0, $actId = 0, $type = 1) {

        $lanternData = [];

        //初始化走马灯数据
        static::initRank($storeId, $actId, 1, $type);

        $options = [
            'withscores' => true,
            'limit' => [
                'offset' => 0,
                'count' => 10,
            ]
        ];
        $zsetKeyLantern = static::getLanternRankKey($storeId, $actId, $type);
        $data = Redis::zrevrangebyscore($zsetKeyLantern, '+inf', '-inf', $options);
        if (empty($data)) {
            return $lanternData;
        }

        //走马灯数据
        foreach ($data as $member => $score) {
            $member = static::getSrcMember($member);
            $lanternData[] = FunctionHelper::handleAccount($member['account']);
        }

        return $lanternData;
    }

    /**
     * 清空排行榜数据
     * @param array $storeIds
     * @return type
     */
    public static function clearRankCache($storeIds = []) {

        $key = [];
        $typeData = [0, 1, 2, 3];
        $rankTypeData = [1, 2];

        $storeIds = $storeIds ? $storeIds : (StoreService::getModel(0)->pluck(Constant::DB_TABLE_PRIMARY));

        foreach ($storeIds as $storeId) {

            $actIds = ActivityService::getModel($storeId)->pluck(Constant::DB_TABLE_PRIMARY);

            foreach ($actIds as $actId) {
                $_key = [
                    static::getLanternRankKey($storeId, $actId, 1), //走马灯
                    VoteService::getRankKey($storeId, $actId), //投票排行榜
                ];

                foreach ($rankTypeData as $rankType) {
                    foreach ($typeData as $type) {
                        $_key[] = static::getRankKey($storeId, $actId, $rankType, $type); //榜单key
                    }
                }

                $key = Arr::collapse([$key, $_key]);
            }
        }

        if (empty($key)) {
            return -1;
        }

        return static::del($key);
    }

    /**
     * 清空缓存
     */
    public static function clear() {
        static::clearRankCache();
    }

}
