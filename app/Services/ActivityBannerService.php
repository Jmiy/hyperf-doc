<?php

/**
 * 活动banner管理
 * User: Bo
 * Date: 2019-12-31
 * Time: 17:32
 */

namespace App\Services;

use App\Utils\Support\Facades\Cache;
use Hyperf\Utils\Arr;
use App\Utils\FunctionHelper;
use App\Constants\Constant;

class ActivityBannerService extends BaseService {

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'ActivityBanner';
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
        $name = data_get($params, Constant::BANNER_NAME, ''); //banner名称
        if ($actId) {//活动id
            $where[] = [Constant::DB_TABLE_ACT_ID, '=', $actId];
        }

        if ($name) {//banner名称
            $where[] = [Constant::BANNER_NAME, '=', $name];
        }

        $order = $order ? $order : [['sort', 'DESC']];

        $_where = [];
        if (data_get($params, 'id', 0)) {
            $_where['id'] = $params['id'];
        }
        if ($where) {
            $_where[] = $where;
        }

        return Arr::collapse([parent::getPublicData($params, $order), [
                        Constant::DB_EXECUTION_PLAN_WHERE => $_where,
        ]]);
    }

    /**
     * 获取选项数据
     * @param int $storeId    商城id
     * @param int $actId      活动id
     * @return array 
     */
    public static function getItemData($name, $storeId = 0, $actId = 0) {

        //获取活动配置数据，并保存到缓存中
        $tags = config('cache.tags.banner', ['{banner}']);

        $ttl = config('cache.ttl', 86400); //认证缓存时间 单位秒
        $cacheKey = 'banner:' . md5(json_encode(func_get_args()));
        return Cache::tags($tags)->remember($cacheKey, $ttl, function () use($name, $storeId, $actId) {

                    $publicData = [
                        'store_id' => $storeId,
                        Constant::DB_TABLE_ACT_ID => $actId,
                        Constant::BANNER_NAME => $name,
                        'orderby' => [['sort', 'ASC']],
                    ];
                    return static::getListData($publicData);
                });
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

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, []);
        $order = data_get($_data, 'order', []);
        $pagination = data_get($_data, 'pagination', []);
        $limit = data_get($pagination, 'page_size', 10);
        $offset = data_get($pagination, 'offset', 0);

        $select = $select ? $select : ['id', 'sort', Constant::DB_TABLE_ACT_ID, Constant::BANNER_NAME, 'img_url', 'mb_img_url', 'jump_link']; //
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
                Constant::DB_EXECUTION_PLAN_WHERE => $where,
                'orders' => $order,
                'offset' => $offset,
                'limit' => $limit,
                'isPage' => $isPage,
                'isOnlyGetCount' => $isOnlyGetCount,
                'pagination' => $pagination,
                'handleData' => [],
            //'unset' => ['customer_id'],
            ],
            'with' => [
            ],
            'itemHandleData' => [
            ],
                //'sqlDebug' => true,
        ];

        $dataStructure = 'list';
        $flatten = false;
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

}
