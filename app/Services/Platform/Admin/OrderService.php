<?php

/**
 * Created by Patazon.
 * @desc   : 管理后台订单管理功能
 * @author : Roy_qiu
 * @email  : Roy_qiu@patazon.net
 * @date   : 2020/7/4 10:47
 */

namespace App\Services\Platform\Admin;

use App\Services\ExcelService;
use App\Services\Platform\OrderService as BaseOrderService;
use App\Constants\Constant;
use App\Utils\FunctionHelper;
use Hyperf\Utils\Arr;
use Hyperf\DbConnection\Db as DB;

class OrderService extends BaseOrderService {

    public static $platformOrder = 'po';
    public static $poiId = 'poi_id';

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
    public static function orderList($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {

        $_data = static::getPublicData($params, []);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, null);
        $order = data_get($params, Constant::ORDER_BY, data_get($_data, Constant::ORDER, []));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, Constant::ACT_LIMIT_KEY, data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, Constant::PARAMETER_INT_DEFAULT));
        $storeId = Arr::get($params, Constant::DB_TABLE_STORE_ID, Constant::PARAMETER_INT_DEFAULT);

        $select = $select ? $select : static::getListSelect();

        $only = Constant::PARAMETER_ARRAY_DEFAULT;
        $handleData = [];
        $joinData = [
            FunctionHelper::getExePlanJoinData('platform_order_items as poi', function ($join) {
                        $join->on([['poi.' . Constant::DB_TABLE_ORDER_UNIQUE_ID, '=', 'po.' . Constant::DB_TABLE_UNIQUE_ID]]);
                    }),
            FunctionHelper::getExePlanJoinData(DB::raw('`ptxcrm`.`crm_customer_info` as `crm_ci`'), function ($join) {
                        $join->on([['ci.' . Constant::DB_TABLE_CUSTOMER_PRIMARY, '=', 'po.' . Constant::DB_TABLE_CUSTOMER_PRIMARY]])
                                ->where('ci.' . Constant::DB_TABLE_STATUS, '=', 1);
                    }),
        ];
        $with = Constant::PARAMETER_ARRAY_DEFAULT;
        $unset = [];
        $exePlan = FunctionHelper::getExePlan('default_connection_' . $storeId, null, static::getModelAlias(), 'platform_orders as po', $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        $itemHandleDataCallback = [
            Constant::DB_TABLE_NAME => function ($item) {
                return data_get($item, Constant::DB_TABLE_FIRST_NAME, Constant::PARAMETER_STRING_DEFAULT) . ' ' . data_get($item, Constant::DB_TABLE_LAST_NAME, Constant::PARAMETER_STRING_DEFAULT);
            },
            Constant::DB_TABLE_ORDER_TYPE . '_show' => function ($item) {
                return data_get([
                    1 => '购买订单',
                    2 => '兑换订单',
                        ], data_get($item, Constant::DB_TABLE_ORDER_TYPE, Constant::PARAMETER_STRING_DEFAULT), Constant::PARAMETER_STRING_DEFAULT);
            },
        ];
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback, $only),
        ];

        if (data_get($params, Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_PRIMARY, false)) {//如果仅仅获取主键id，就不需要处理数据，不关联
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_PARENT . '.' . Constant::DB_EXECUTION_PLAN_HANDLE_DATA, Constant::PARAMETER_ARRAY_DEFAULT);
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_WITH, Constant::PARAMETER_ARRAY_DEFAULT);
        }

        $flatten = false;
        $dataStructure = 'list';
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    /**
     * 数据导出
     * @param array $params 请求参数
     * @param boolean $toArray 是否转化为数组 true:是 false:否 默认:true
     * @param boolean $isPage  是否分页 true:是 false:否 默认:true
     * @param array $select  查询字段
     * @param boolean $isRaw 是否原始 select true:是 false:否 默认:false
     * @param boolean $isGetQuery 是否获取 query
     * @param boolean $isOnlyGetCount 是否仅仅获取总记录数
     * @return array|\Hyperf\Database\Model\Builder 列表数据|Builder
     */
    public static function exportList($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {
        //var_dump([$toArray, $isPage, $select, $isRaw, $isGetQuery, $isOnlyGetCount]);
        $_data = static::getPublicData($params, []);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, null);
        $order = data_get($params, Constant::ORDER_BY, data_get($_data, Constant::ORDER, []));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, Constant::ACT_LIMIT_KEY, data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, Constant::PARAMETER_INT_DEFAULT));
        $storeId = Arr::get($params, Constant::DB_TABLE_STORE_ID, Constant::PARAMETER_INT_DEFAULT);

        $select = $select ? $select : static::getExportListSelect();

        $only = Constant::PARAMETER_ARRAY_DEFAULT;
        $handleData = [];
        $joinData = [
            FunctionHelper::getExePlanJoinData('platform_order_items as poi', function ($join) {
                        $join->on([['poi.' . Constant::DB_TABLE_ORDER_UNIQUE_ID, '=', 'po.' . Constant::DB_TABLE_UNIQUE_ID]]);
                    }),
            FunctionHelper::getExePlanJoinData('platform_fulfillments as pf', function ($join) {
                        $join->on([['pf.' . Constant::DB_TABLE_ORDER_UNIQUE_ID, '=', 'po.' . Constant::DB_TABLE_UNIQUE_ID]])
                                ->where('pf.' . Constant::DB_TABLE_FULFILLMENT_ID, '!=', DB::raw('NULL'));
                    }),
            FunctionHelper::getExePlanJoinData('platform_order_shipping_addresses as posa', function ($join) {
                        $join->on([['posa.' . Constant::DB_TABLE_ORDER_UNIQUE_ID, '=', 'po.' . Constant::DB_TABLE_UNIQUE_ID]]);
                    }),
        ];
        $with = Constant::PARAMETER_ARRAY_DEFAULT;
        $unset = [];
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getNamespaceClass(), 'platform_orders as po', $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        $itemHandleDataCallback = [
        ];
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback, $only),
        ];

        if (data_get($params, Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_PRIMARY, false)) {//如果仅仅获取主键id，就不需要处理数据，不关联
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_PARENT . '.' . Constant::DB_EXECUTION_PLAN_HANDLE_DATA, Constant::PARAMETER_ARRAY_DEFAULT);
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_WITH, Constant::PARAMETER_ARRAY_DEFAULT);
        }

        $flatten = false;
        $dataStructure = 'list';
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    public static function exportOrders($requestData) {
        $header = [
            'NotificationEmail' => Constant::DB_TABLE_EMAIL,
            'customer_order_id' => Constant::DB_TABLE_NAME,
            'DisplayableOrderDate' => Constant::DB_TABLE_PLATFORM_CREATED_AT,
            'MerchantSKU' => Constant::DB_TABLE_SKU,
            'Quantity' => Constant::DB_TABLE_QUANTITY,
            'MerchantFulfillmentOrderItemID' => 'sku1',
            'GiftMessage' => '',
            'DisplayableComment' => Constant::DB_TABLE_NOTE,
            'PerUnitDeclaredValue' => Constant::DB_TABLE_TOTAL_PRICE,
            'DisplayableOrderComment' => Constant::DB_TABLE_NOTE,
            'DeliverySLA' => 'tracking_company',
            'AddressName' => 'address1',
            'AddressFieldOne' => 'address2',
            'AddressFieldTwo' => '',
            'AddressFieldThree' => '',
            'AddressCity' => Constant::DB_TABLE_CITY,
            'AddressCountryCode' => Constant::DB_TABLE_COUNTRY,
            'AddressStateOrRegion' => Constant::DB_TABLE_PROVINCE,
            'AddressPostalCode' => Constant::DB_TABLE_ZIP,
            'AddressPhoneNumber' => Constant::DB_TABLE_PHONE,
            Constant::EXPORT_DISTINCT_FIELD => [
                Constant::EXPORT_PRIMARY_KEY => static::$poiId,
                Constant::EXPORT_PRIMARY_VALUE_KEY => 'poi' . Constant::LINKER . Constant::DB_TABLE_PRIMARY,
                Constant::DB_EXECUTION_PLAN_SELECT => ['poi' . Constant::LINKER . Constant::DB_TABLE_PRIMARY]
            ],
        ];

        $service = static::getNamespaceClass();
        $method = 'exportList';
        $select = static::getExportListSelect();
        $parameters = [$requestData, true, true, $select, false, false];
        $file = ExcelService::createCsvFile($header, $service, '', '', $method, $parameters);

        return [Constant::FILE_URL => $file];
    }

    /**
     * 参数处理
     * @param array $params
     * @param array $order
     * @return array
     */
    public static function getPublicData($params, $order = []) {
        $where = [];
        $_where = [];
        $storeId = $params[Constant::DB_TABLE_STORE_ID] ?? Constant::PARAMETER_INT_DEFAULT; //官网id
        $orderId = $params[Constant::DB_TABLE_ORDER_NO] ?? Constant::PARAMETER_STRING_DEFAULT; //订单号
        $country = $params[Constant::DB_TABLE_COUNTRY] ?? Constant::PARAMETER_STRING_DEFAULT; //国家
        $email = $params[Constant::DB_TABLE_EMAIL] ?? Constant::PARAMETER_STRING_DEFAULT; //邮箱
        $orderStatus = $params[Constant::DB_TABLE_ORDER_STATUS] ?? Constant::PARAMETER_STRING_DEFAULT; //订单状态
        $startTime = $params[Constant::START_TIME] ?? Constant::PARAMETER_STRING_DEFAULT; //订单开始时间
        $endTime = $params[Constant::DB_TABLE_END_TIME] ?? Constant::PARAMETER_STRING_DEFAULT; //订单结束时间
        $orderType = data_get($params, Constant::DB_TABLE_ORDER_TYPE, 0);
        $platform = data_get($params, Constant::DB_TABLE_PLATFORM, 0);

        if ($platform) {//如果平台不为空，支持 多个平台订单查询
            $_platform = [];
            $platform = is_array($platform) ? $platform : [$platform];
            foreach ($platform as $v) {
                $_platform[] = FunctionHelper::getUniqueId($v);
            }

            $_where[static::$platformOrder . Constant::LINKER . Constant::DB_TABLE_PLATFORM] = $_platform;
        }

        if ($storeId) {
            $where[] = [static::$platformOrder . Constant::LINKER . Constant::DB_TABLE_STORE_ID, '=', $storeId];
        }

        if ($orderId) {
            $where[] = [static::$platformOrder . Constant::LINKER . Constant::DB_TABLE_NAME, '=', $orderId];
        }

        if ($country) {
            $where[] = [static::$platformOrder . Constant::LINKER . Constant::DB_TABLE_COUNTRY, '=', $country];
        }

        if ($email) {
            $where[] = [static::$platformOrder . Constant::LINKER . Constant::DB_TABLE_EMAIL, '=', $email];
        }

        if ($orderStatus) {
            $where[] = [static::$platformOrder . Constant::LINKER . 'financial_status', '=', $orderStatus];
        }

        if ($startTime) {
            $where[] = [static::$platformOrder . Constant::LINKER . Constant::DB_TABLE_PLATFORM_CREATED_AT, '>=', $startTime];
        }

        if ($endTime) {
            $where[] = [static::$platformOrder . Constant::LINKER . Constant::DB_TABLE_PLATFORM_CREATED_AT, '<=', $endTime];
        }

        if ($orderType) {
            $where[static::$platformOrder . Constant::LINKER . Constant::DB_TABLE_ORDER_TYPE] = $orderType;
        }

        if (data_get($params, Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT)) {
            $_where[static::$platformOrder . Constant::LINKER . Constant::DB_TABLE_PRIMARY] = $params[Constant::DB_TABLE_PRIMARY];
        }

        if (isset($params[static::$poiId])) {
            $_where['poi' . Constant::LINKER . Constant::DB_TABLE_PRIMARY] = $params[static::$poiId];
        }

        if ($where) {
            $_where[] = $where;
        }

        $order = $order ? $order : [[static::$platformOrder . Constant::LINKER . Constant::DB_TABLE_PLATFORM_CREATED_AT, Constant::ORDER_DESC]];
        return Arr::collapse([parent::getPublicData($params, $order), [
                        Constant::DB_EXECUTION_PLAN_WHERE => $_where,
        ]]);
    }

    /**
     * 列表字段
     * @return array
     */
    public static function getListSelect() {
        return [
            static::$platformOrder . Constant::LINKER . Constant::DB_TABLE_PRIMARY,
            static::$platformOrder . Constant::LINKER . Constant::DB_TABLE_NAME . ' as order_name',
            static::$platformOrder . Constant::LINKER . Constant::DB_TABLE_COUNTRY,
            static::$platformOrder . Constant::LINKER . 'total_price',
            static::$platformOrder . Constant::LINKER . Constant::DB_TABLE_EMAIL,
            'ci' . Constant::LINKER . Constant::DB_TABLE_FIRST_NAME,
            'ci' . Constant::LINKER . Constant::DB_TABLE_LAST_NAME,
            'poi' . Constant::LINKER . Constant::DB_TABLE_SKU,
            'poi' . Constant::LINKER . Constant::DB_TABLE_QUANTITY,
            static::$platformOrder . Constant::LINKER . 'financial_status',
            static::$platformOrder . Constant::LINKER . Constant::DB_TABLE_PLATFORM_CREATED_AT,
            static::$platformOrder . Constant::LINKER . Constant::DB_TABLE_ORDER_TYPE,
        ];
    }

    /**
     * 列表字段
     * @return array
     */
    public static function getExportListSelect() {
        return [
            'poi' . Constant::LINKER . Constant::DB_TABLE_PRIMARY . ' as poi_id',
            static::$platformOrder . Constant::LINKER . Constant::DB_TABLE_PLATFORM_CREATED_AT,
            'poi' . Constant::LINKER . Constant::DB_TABLE_SKU,
            'poi' . Constant::LINKER . Constant::DB_TABLE_QUANTITY,
            'poi' . Constant::LINKER . Constant::DB_TABLE_SKU . ' as sku1',
            'poi' . Constant::LINKER . Constant::DB_TABLE_PRICE,
            static::$platformOrder . Constant::LINKER . Constant::DB_TABLE_NOTE,
            'pf' . Constant::LINKER . 'tracking_company',
            'posa' . Constant::LINKER . 'address1',
            'posa' . Constant::LINKER . 'address2',
            'posa' . Constant::LINKER . Constant::DB_TABLE_CITY,
            'posa' . Constant::LINKER . Constant::DB_TABLE_COUNTRY,
            'posa' . Constant::LINKER . Constant::DB_TABLE_PROVINCE,
            'posa' . Constant::LINKER . Constant::DB_TABLE_ZIP,
            'posa' . Constant::LINKER . Constant::DB_TABLE_PHONE,
            'posa' . Constant::LINKER . Constant::DB_TABLE_EMAIL,
            static::$platformOrder . Constant::LINKER . Constant::DB_TABLE_NAME,
            'po' . Constant::LINKER . Constant::DB_TABLE_TOTAL_PRICE,
        ];
    }

}
