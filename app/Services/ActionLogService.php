<?php

/**
 * 行为服务
 * User: Jmiy
 * Date: 2019-11-13
 * Time: 16:50
 */

namespace App\Services;

use Carbon\Carbon;
use App\Constants\Constant;
use App\Utils\Support\Facades\Cache;
use App\Utils\FunctionHelper;
use App\Utils\Response;

class ActionLogService extends BaseService {

    public static $typeData = [
        1 => 'Sharing',
        2 => 'Invitation',
        3 => 'Sign in',
    ];

    /**
     * 获取要清空的tags
     * @return array
     */
    public static function getClearTags() {
        return ['actionLock', 'signInLimit'];
    }

    public static function getCacheTags() {
        return 'actionLock';
    }

    /**
     * 签到
     * @param int $storeId 品牌商店id
     * @param int $customerId 会员id
     * @param int $actId 活动id
     * @param int $type 榜单类型 0:综合榜 1:分享 2:邀请 3:签到 4:关注社媒
     * @param array $extData 请求数据
     * @return int 流水id
     */
    public static function add($storeId, $customerId = 0, $actId = 0, $type = 0, $extData = []) {
        $country = data_get($extData, Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT);
        $data = [
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_TYPE => $type,
            Constant::DB_TABLE_IP => data_get($extData, Constant::DB_TABLE_IP, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_COUNTRY => $country,
            Constant::DB_TABLE_EXT_ID => data_get($extData, Constant::RELATED_DATA . Constant::LINKER . Constant::DB_TABLE_EXT_ID, Constant::PARAMETER_INT_DEFAULT),
            Constant::DB_TABLE_EXT_TYPE => data_get($extData, Constant::RELATED_DATA . Constant::LINKER . Constant::DB_TABLE_EXT_TYPE, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_EXT_DATA => data_get($extData, Constant::RELATED_DATA . Constant::LINKER . Constant::DB_TABLE_EXT_DATA, Constant::PARAMETER_STRING_DEFAULT),
        ];

        return static::getModel($storeId, $country)->insertGetId($data);
    }

    /**
     * 签到
     * @param int $storeId 商城id
     * @param int $customerId 会员id
     * @param int $actId 活动id
     * @param int $type 榜单类型 0:综合榜 1:分享 2:邀请 3:签到 4:关注社媒
     * @param array $extData 请求数据
     * @return int 流水id
     */
    public static function handle($storeId, $customerId = 0, $actId = 0, $type = 0, $extData = []) {

        $tag = static::getCacheTags();
        $cacheKeyData = [__FUNCTION__, $storeId, $actId, $customerId, $type];
        $service = static::getNamespaceClass();
        $parameters = [
            function () use($storeId, $customerId, $actId, $type, $extData) {
                $activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, ['rank', 'sign_in'], ['rank_type', 'rank_score', 'day_limit', 'expire', 'expire_format']);
                if ($type == 3) {//如果是签到，就判断当天是否已经签到
                    //一天只能签到一次
                    $key = 'actionlog:' . $storeId . ':' . $customerId . ':' . $actId . ':' . $type;
                    $tags = config('cache.tags.signInLimit');
                    $limit = Cache::tags($tags)->get($key);
                    if ($limit >= data_get($activityConfigData, 'sign_in_day_limit.value', 1)) {
                        return Response::getDefaultResponseData(110002);
                    }

                    if (Cache::tags($tags)->has($key)) {
                        Cache::tags($tags)->increment($key);
                    } else {

                        $nowTimestamp = Carbon::now()->timestamp;

                        $signInExpire = data_get($activityConfigData, 'sign_in_expire.value', '+1 day');
                        $time = strtotime($signInExpire, $nowTimestamp);

                        $signInExpireFormat = data_get($activityConfigData, 'sign_in_expire_format.value', 'Y-m-d H:i:s');
                        $signInExpireDate = Carbon::createFromTimestamp($time)->rawFormat($signInExpireFormat);

                        $ttl = (Carbon::parse($signInExpireDate)->timestamp) - $nowTimestamp; //缓存时间 单位秒
                        Cache::tags($tags)->put($key, 1, $ttl);
                    }
                }

                $rankTypeData = data_get($activityConfigData, 'rank_rank_type.value', Constant::PARAMETER_INT_DEFAULT);
                $rankData = [];
                if ($rankTypeData) {
                    $rankTypeData = explode(',', $rankTypeData);
                    //添加邀请者的排行榜积分
                    $score = data_get($activityConfigData, 'sign_in_rank_score.value', Constant::PARAMETER_INT_DEFAULT);
                    $rankData = RankService::handle($storeId, $customerId, $actId, $rankTypeData, $type, $score); // 榜单类型 1:分享 2:邀请 3:签到
                }

                data_set($extData, Constant::RELATED_DATA, $rankData);

                return Response::getDefaultResponseData(1, null, [Constant::DB_TABLE_PRIMARY => static::add($storeId, $customerId, $actId, $type, $extData)]);
            }
        ];

        $rs = static::handleLock($cacheKeyData, $parameters);

        return $rs === false ? Response::getDefaultResponseData(110001) : $rs;
    }

    public static function getConfigData($storeId, $configType, $orderby = null, $country = null, $extWhere = [], $select = []) {
        return DictService::getDistData($storeId, $configType, Constant::DB_TABLE_STORE_DICT_KEY, Constant::DB_TABLE_STORE_DICT_VALUE, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE, $orderby, $country, $extWhere, $select); //关注配置数据
    }

    /**
     * 关注
     * @param int $storeId 商城id
     * @param int $customerId 会员id
     * @param int $actId 活动id
     * @param int $type 类型 0:综合榜 1:分享 2:邀请 3:签到 5:关注社媒 6:登录
     * @param array $extData 请求数据
     * @return int 流水id
     */
    public static function follow($storeId, $customerId = 0, $actId = 0, $type = 5, $extData = []) {

        $cacheKeyData = [__FUNCTION__, $storeId, $actId, $customerId, $type];
        $tag = static::getCacheTags();
        $service = static::getNamespaceClass();
        $parameters = [
            function () use($storeId, $customerId, $actId, $type, $extData, $tag, $service) {

                $select = [
                    Constant::DICT_STORE => [Constant::DB_TABLE_STORE_DICT_KEY, Constant::DB_TABLE_STORE_DICT_VALUE],
                ];
                $configData = static::getConfigData($storeId, Constant::ACTION_FOLLOW, null, null, [], $select); //关注配置数据

                $socialMedia = data_get($extData, Constant::SOCIAL_MEDIA);
                $credit = data_get($configData, 'credit_' . strtolower($socialMedia), 0);
                if (empty($credit)) {
                    return Response::getDefaultResponseData(110005);
                }

                $key = implode(':', [__FUNCTION__, $storeId, $customerId, $actId, $type, $socialMedia]);
                $ttl = static::getTtl();
                $limit = static::handleCache($tag, FunctionHelper::getJobData($service, 'get', [$key]));
                if ($limit === null) {
                    $limitHandleCacheData = FunctionHelper::getJobData($service, 'remember', [$key, $ttl, function () use($storeId, $customerId, $actId, $type, $extData, $tag, $service, $socialMedia) {
                                    $where = [
                                        Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                                        Constant::DB_TABLE_TYPE => $type,
                                        Constant::DB_TABLE_EXT_DATA => $socialMedia,
                                    ];
                                    return static::existsOrFirst($storeId, Constant::PARAMETER_STRING_DEFAULT, $where);
                                }]);
                    $limit = static::handleCache($tag, $limitHandleCacheData);
                }

                if ($limit >= data_get($configData, 'limit', 1)) {//每种社媒只能关注一次
                    return Response::getDefaultResponseData(110004);
                }

                static::handleCache($tag, FunctionHelper::getJobData($service, 'put', [$key, 1, $ttl])); //设置当前用户已经关注过

                $relatedData = [
                    Constant::DB_TABLE_EXT_ID => $credit,
                    Constant::DB_TABLE_EXT_DATA => $socialMedia,
                ];
                data_set($extData, Constant::RELATED_DATA, $relatedData);

                //添加行为流水
                $extId = static::add($storeId, $customerId, $actId, $type, $extData);

                //处理积分
                $requestData = [
                    Constant::DB_TABLE_EXT_ID => $extId,
                    Constant::DB_TABLE_EXT_TYPE => static::getModelAlias(),
                ];
                CreditService::handleVip($storeId, $customerId, Constant::ACTION_FOLLOW, null, $credit, $requestData);

                return Response::getDefaultResponseData(110003, null, [Constant::DB_TABLE_PRIMARY => $extId]);
            }
        ];

        $rs = static::handleLock($cacheKeyData, $parameters);

        return $rs === false ? Response::getDefaultResponseData(110001) : $rs;
    }

    /**
     * 登录
     * @param int $storeId 商城id
     * @param int $customerId 会员id
     * @param int $actId 活动id
     * @param int $type 类型 0:综合榜 1:分享 2:邀请 3:签到 5:关注社媒 6:登录
     * @param array $extData 请求数据
     * @return int 流水id
     */
    public static function login($storeId, $customerId = 0, $actId = 0, $type = 6, $extData = []) {

        $cacheKeyData = [__FUNCTION__, $storeId, $actId, $customerId, $type];
        $tag = static::getCacheTags();
        $service = static::getNamespaceClass();
        $parameters = [
            function () use($storeId, $customerId, $actId, $type, $extData, $tag, $service) {

                $select = [
                    Constant::DICT_STORE => [Constant::DB_TABLE_STORE_DICT_KEY, Constant::DB_TABLE_STORE_DICT_VALUE],
                ];
                $configData = static::getConfigData($storeId, Constant::ACTION_LOGIN, null, null, [], $select); //关注配置数据

                $credit = data_get($configData, 'credit', 0);
                if (empty($credit)) {
                    return Response::getDefaultResponseData(110006);
                }

                $key = implode(':', [__FUNCTION__, $storeId, $customerId, $type]);
                $ttl = static::getTtl();
                $limit = static::handleCache($tag, FunctionHelper::getJobData($service, 'get', [$key]));
                if ($limit === null) {
                    $limitHandleCacheData = FunctionHelper::getJobData($service, 'remember', [$key, $ttl, function () use($storeId, $customerId, $actId, $type, $extData, $tag, $service) {
                                    $where = [
                                        Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                                        Constant::DB_TABLE_TYPE => $type,
                                        [[Constant::DB_TABLE_CREATED_AT, '>=', Carbon::now()->rawFormat('Y-m-d 00:00:00')]],
                                    ];
                                    return static::existsOrFirst($storeId, Constant::PARAMETER_STRING_DEFAULT, $where);
                                }]);
                    $limit = static::handleCache($tag, $limitHandleCacheData);
                }

                if ($limit >= data_get($configData, 'day_limit', 1)) {//每天登录获得积分次数
                    return Response::getDefaultResponseData(110007);
                }

                static::handleCache($tag, FunctionHelper::getJobData($service, 'put', [$key, 1, $ttl])); //设置当前用户当天已经登录过

                $relatedData = [
                    Constant::DB_TABLE_EXT_ID => $credit,
                ];
                data_set($extData, Constant::RELATED_DATA, $relatedData);

                //添加行为流水
                $extId = static::add($storeId, $customerId, $actId, $type, $extData);

                //处理积分
                $requestData = [
                    Constant::DB_TABLE_EXT_ID => $extId,
                    Constant::DB_TABLE_EXT_TYPE => static::getModelAlias(),
                ];
                CreditService::handleVip($storeId, $customerId, Constant::ACTION_LOGIN, null, $credit, $requestData);

                return Response::getDefaultResponseData(110008, null, [Constant::DB_TABLE_PRIMARY => $extId]);
            }
        ];

        $rs = static::handleLock($cacheKeyData, $parameters);

        return $rs === false ? Response::getDefaultResponseData(110001) : $rs;
    }

}
