<?php

namespace App\Services;

use App\Constants\Constant;
use App\Utils\FunctionHelper;
use Hyperf\Utils\Arr;

class ActivityCategoryService extends BaseService {

    /**
     * 获取类目列表
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $page 页码
     * @param int $pageSize 页大小
     * @return array
     */
    public static function getCategoryList($storeId, $actId, $page, $pageSize) {

        $params = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::REQUEST_PAGE => $page,
            Constant::REQUEST_PAGE_SIZE => $pageSize,
        ];

        $_data = static::getPublicData($params);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, []);
        $order = data_get($params, 'orderBy', ['sort', 'ASC']);
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, Constant::DB_EXECUTION_PLAN_LIMIT, data_get($pagination, 'page_size', 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0));

        $select = [Constant::DB_TABLE_PRIMARY, 'name', 'des', 'img_url', 'mb_img_url', 'url'];
        $dbExecutionPlan = [
            'parent' => [
                Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                'builder' => null,
                'make' => static::getModelAlias(),
                'from' => '',
                Constant::DB_EXECUTION_PLAN_SELECT => $select,
                Constant::DB_EXECUTION_PLAN_WHERE => $where,
                'orders' => [
                    $order
                ],
                Constant::DB_EXECUTION_PLAN_OFFSET => $offset,
                Constant::DB_EXECUTION_PLAN_LIMIT => $limit,
                'isPage' => true,
                'isOnlyGetCount' => false,
                Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                    'des' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'des',
                        Constant::RESPONSE_DATA_KEY => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => 'array',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => '{@#}',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => []
                    ]
                ],
            ],
                //'sqlDebug' => true,
        ];

        $_data = FunctionHelper::getResponseData(null, $dbExecutionPlan, false, false, 'list');

        return [
            Constant::RESPONSE_DATA_KEY => $_data
        ];
    }

    /**
     * 获取类目下商品列表
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $categoryId 类目id
     * @param int $page 页码
     * @param int $pageSize 页大小
     * @param int $customerId 会员id
     * @return array
     */
    public static function getCategoryProductList($storeId, $actId, $categoryId, $page, $pageSize, $customerId = 0) {
        $params = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::REQUEST_PAGE => $page,
            Constant::REQUEST_PAGE_SIZE => $pageSize,
            Constant::CATEGORY_ID => $categoryId,
        ];

        data_set($params, 'alias', 'activity_products');
        $_data = static::getPublicData($params);
        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, []);
        $order = data_get($params, 'orderBy', ['sort', 'ASC']);
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, Constant::DB_EXECUTION_PLAN_LIMIT, data_get($pagination, 'page_size', 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0));

        $select = ['activity_products.' . Constant::DB_TABLE_PRIMARY, 'name', 'sub_name', 'des', 'qty', 'qty_apply', 'is_recommend', 'img_url', 'mb_img_url', 'type', 'url', Constant::IN_STOCK, 'aa.id as apply_id', 'aa.audit_status'];
        $dbExecutionPlan = [
            'parent' => [
                Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                'builder' => null,
                'make' => ActivityProductService::getModelAlias(),
                'from' => '',
                Constant::DB_EXECUTION_PLAN_SELECT => $select,
                Constant::DB_EXECUTION_PLAN_WHERE => $where,
                'orders' => [
                    $order
                ],
                Constant::DB_EXECUTION_PLAN_OFFSET => $offset,
                Constant::DB_EXECUTION_PLAN_LIMIT => $limit,
                'isPage' => true,
                'isOnlyGetCount' => false,
                Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                    'des' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'des',
                        Constant::RESPONSE_DATA_KEY => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => 'array',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => '{@#}',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => []
                    ],
//                    'apply_id' => [
//                        Constant::DB_EXECUTION_PLAN_FIELD => 'activity_applie.id',
//                        Constant::RESPONSE_DATA_KEY => [],
//                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
//                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
//                        'glue' => '',
//                        Constant::DB_EXECUTION_PLAN_DEFAULT => 0,
//                    ],
//                    'audit_status' => [
//                        Constant::DB_EXECUTION_PLAN_FIELD => 'activity_applie.audit_status',
//                        Constant::RESPONSE_DATA_KEY => [],
//                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
//                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
//                        'glue' => '',
//                        Constant::DB_EXECUTION_PLAN_DEFAULT => -1,
//                    ],
                ],
                'joinData' => [
                    [
                        'table' => 'activity_applies as aa',
                        'first' => function ($join) use ($customerId, $actId) {
                            $join->on([['aa.' . Constant::DB_TABLE_EXT_ID, '=', 'activity_products.id']])
                            ->where([['aa.act_id', '=', $actId], ['aa.customer_id', '=', $customerId], ['aa.status', '=', 1]]);
                        },
                        'operator' => null,
                        'second' => null,
                        'type' => 'left',
                    ],
            ],
//            'with' => [
//                'activity_applie' => [
//                    Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
//                    Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
//                    'relation' => 'hasOne',
//                    Constant::DB_EXECUTION_PLAN_SELECT => [Constant::DB_TABLE_PRIMARY, 'ext_id', 'audit_status'],
//                    Constant::DB_EXECUTION_PLAN_DEFAULT => [],
//                    Constant::DB_EXECUTION_PLAN_WHERE => [
//                        Constant::DB_TABLE_ACT_ID => $actId,
//                        Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
//                    ],
//                    'orders' => [
//                        [Constant::DB_TABLE_PRIMARY, Constant::DB_EXECUTION_PLAN_ORDER_DESC]
//                    ],
//                    Constant::ACT_LIMIT_KEY => 1,
//                    Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
//                    ],
//                    'unset' => ['activity_applie', 'ext_id'],
//                ],
//            ],
            ],
                //'sqlDebug' => true,
        ];

        $_data = FunctionHelper::getResponseData(null, $dbExecutionPlan, false, false, 'list');

        return [
            Constant::RESPONSE_DATA_KEY => $_data,
        ];
    }

    /**
     * 公共参数
     * @param array $params
     * @param array $order
     * @return array
     */
    public static function getPublicData($params, $order = []) {

        $where = [];

        $actId = $params[Constant::DB_TABLE_ACT_ID] ?? ''; //活动id
        $categoryId = $params[Constant::CATEGORY_ID] ?? ''; //商品类目
        $inStock = $params[Constant::IN_STOCK] ?? ''; //是否有货
        $alias = $params['alias'] ?? '';

        if ($alias) {
            if ($actId !== '') {
                $where[] = ["$alias." . Constant::DB_TABLE_ACT_ID, '=', $actId];
            }
        } else {
            if ($actId !== '') {
                $where[] = [Constant::DB_TABLE_ACT_ID, '=', $actId];
            }
        }

        if ($categoryId !== '') {
            $where[] = [Constant::CATEGORY_ID, '=', $categoryId];
        }

        if ($inStock !== '') {
            $where[] = [Constant::IN_STOCK, '=', $inStock];
        }

        $_where = [];
        if (data_get($params, Constant::DB_TABLE_PRIMARY, 0)) {
            $_where[Constant::DB_TABLE_PRIMARY] = $params[Constant::DB_TABLE_PRIMARY];
        }

        if ($where) {
            $_where[] = $where;
        }

        $order = $order ? $order : [Constant::DB_TABLE_PRIMARY, 'DESC'];
        return Arr::collapse([parent::getPublicData($params, $order), [
                        Constant::DB_EXECUTION_PLAN_WHERE => $_where,
        ]]);
    }

}
