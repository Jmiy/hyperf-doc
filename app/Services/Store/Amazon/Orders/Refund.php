<?php

namespace App\Services\Store\Amazon\Orders;

use App\Services\Store\Amazon\BaseService;
use App\Constants\Constant;
use App\Utils\FunctionHelper;

class Refund extends BaseService {

    /**
     * 获取退款订单
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据|单条退款数据
     * @return array
     */
    public static function getRefundData($storeId, $platform, $data) {

        $storeId = static::castToString($storeId);

        $items = [];

        if (empty($data)) {
            return $items;
        }

        $refunds = data_get($data, 'refunds');
        if (empty($refunds)) {
            return $items;
        }

        foreach ($refunds as $refund) {

            if (empty($refund)) {
                continue;
            }

            $refundId = data_get($refund, Constant::DB_TABLE_PRIMARY) ?? 0; //退款单号
            $orderId = data_get($refund, Constant::DB_TABLE_ORDER_ID) ?? 0; //订单号

            $createdAt = FunctionHelper::handleTime(data_get($refund, Constant::DB_TABLE_CREATED_AT)); //退款创建时间

            $parameters = [$storeId, $platform, $refundId, static::getCustomClassName()];
            $orderParameters = [$storeId, $platform, $orderId, Order::getCustomClassName()];

            $item = [
                Constant::DB_TABLE_UNIQUE_ID => FunctionHelper::getUniqueId(...$parameters), //退款唯一id
                Constant::DB_TABLE_ORDER_UNIQUE_ID => FunctionHelper::getUniqueId(...$orderParameters), //订单 唯一id
                Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform), //平台
                Constant::DB_TABLE_STORE_ID => $storeId,
                Constant::DB_TABLE_REFUND_ID => $refundId, //退款单号
                Constant::DB_TABLE_PLATFORM_ORDER_ID => $orderId, //订单号
                'refund_created_at' => $createdAt, //退款创建时间
                'refund_processed_at' => FunctionHelper::handleTime(data_get($refund, Constant::DB_TABLE_PROCESSED_AT)), //退款处理时间
                Constant::DB_TABLE_NOTE => data_get($refund, Constant::DB_TABLE_NOTE) ?? '', //备注
                'restock' => data_get($refund, 'restock') ? 1 : 0, //是否补货,1是,0否
                Constant::DB_TABLE_ADMIN_GRAPHQL_API_ID => data_get($refund, Constant::DB_TABLE_ADMIN_GRAPHQL_API_ID) ?? '', //
            ];

            $items[] = $item;
        }

        return $items;
    }

    /**
     * 获取退款item
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据|单条退款数据
     * @return array
     */
    public static function getRefundItemData($storeId, $platform, $data) {

        $storeId = static::castToString($storeId);

        $items = [];

        if (empty($data)) {
            return $items;
        }

        $refunds = data_get($data, 'refunds');
        if (empty($refunds)) {
            return $items;
        }

        foreach ($refunds as $refund) {

            if (empty($refund)) {
                continue;
            }

            $refundLineItems = data_get($refund, 'refund_line_items', []);
            if (empty($refundLineItems)) {
                continue;
            }

            $refundId = data_get($refund, Constant::DB_TABLE_PRIMARY) ?? 0; //退款单号
            $orderId = data_get($refund, Constant::DB_TABLE_ORDER_ID) ?? 0; //订单id
            $createdAt = FunctionHelper::handleTime(data_get($refund, Constant::DB_TABLE_CREATED_AT)); //退款创建时间

            foreach ($refundLineItems as $refundLineItem) {
                $refundItemId = data_get($refundLineItem, Constant::DB_TABLE_PRIMARY) ?? 0; //退款Item单号
                $orderItemId = data_get($refundLineItem, 'line_item_id') ?? 0; //订单ItemId

                $parameters = [$storeId, $platform, $refundItemId, (static::getCustomClassName() . 'Item')];
                $refundParameters = [$storeId, $platform, $refundId, static::getCustomClassName()];
                $orderItemParameters = [$storeId, $platform, $orderItemId, (Order::getCustomClassName() . 'Item')];
                $orderParameters = [$storeId, $platform, $orderId, Order::getCustomClassName()];

                $item = [
                    Constant::DB_TABLE_UNIQUE_ID => FunctionHelper::getUniqueId(...$parameters), //退款item唯一id
                    Constant::DB_TABLE_REFUND_UNIQUE_ID => FunctionHelper::getUniqueId(...$refundParameters), //退款唯一id
                    Constant::DB_TABLE_ORDER_ITEM_UNIQUE_ID => FunctionHelper::getUniqueId(...$orderItemParameters), //订单 item 唯一id
                    Constant::DB_TABLE_ORDER_UNIQUE_ID => FunctionHelper::getUniqueId(...$orderParameters), //订单 唯一id
                    Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform), //平台
                    Constant::DB_TABLE_STORE_ID => $storeId,
                    Constant::DB_TABLE_REFUND_ITEM_ID => $refundItemId, //退款Item单号
                    Constant::DB_TABLE_REFUND_ID => $refundId, //退款单号
                    Constant::DB_TABLE_PLATFORM_ORDER_ITEM_ID => $orderItemId, //订单ItemId
                    Constant::DB_TABLE_PLATFORM_ORDER_ID => $orderId, //订单号
                    Constant::DB_TABLE_QUANTITY => data_get($refundLineItem, Constant::DB_TABLE_QUANTITY) ?? 0, //退款数量
                    'subtotal' => FunctionHelper::handleNumber(data_get($refundLineItem, 'subtotal')), //退款金额
                    Constant::DB_TABLE_TOTAL_TAX => FunctionHelper::handleNumber(data_get($refundLineItem, Constant::DB_TABLE_TOTAL_TAX)), //退款税费
                    'restock_type' => data_get($refundLineItem, 'restock_type', false) ? 1 : 0, //
                ];

                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Retrieves a list of refunds for an order https://shopify.dev/docs/admin-api/rest/reference/orders/refund?api[version]=2020-04#index-2020-04
     * @param int $storeId 商城id
     * @param string $orderId 订单id
     * @param array $extData 接口请求参数
     * @return array|boolean $res
     */
    public static function getRefunds($storeId = 1, $orderId = '', $extData = []) {

//        $storeId = static::castToString($storeId);
//        static::setConf($storeId);
        
        return [];
    }

}
