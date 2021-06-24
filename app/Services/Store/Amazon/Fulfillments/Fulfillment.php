<?php

namespace App\Services\Store\Amazon\Fulfillments;

use App\Services\Store\Amazon\BaseService;
use App\Constants\Constant;
use App\Utils\FunctionHelper;
use Hyperf\Utils\Arr;
use App\Services\Store\Amazon\Orders\Order;

class Fulfillment extends BaseService {

    /**
     * 获取物流数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据|单条物流数据
     * @return array 物流数据
     */
    public static function getFulfillmentData($storeId, $platform, $data) {

        $storeId = static::castToString($storeId);

        $items = [];

        if (empty($data)) {
            return $items;
        }

        $fulfillments = data_get($data, 'fulfillments');
        if (empty($fulfillments)) {
            return $items;
        }

        foreach ($fulfillments as $fulfillment) {

            if (empty($fulfillment)) {
                continue;
            }

            $fulfillmentId = data_get($fulfillment, Constant::DB_TABLE_PRIMARY) ?? 0; //物流 id
            $orderId = data_get($fulfillment, Constant::DB_TABLE_ORDER_ID) ?? 0; //订单号

            $parameters = [$storeId, $platform, $fulfillmentId, static::getCustomClassName()];
            $orderParameters = [$storeId, $platform, $orderId, Order::getCustomClassName()];

            $item = [
                Constant::DB_TABLE_UNIQUE_ID => FunctionHelper::getUniqueId(...$parameters), //唯一id
                Constant::DB_TABLE_ORDER_UNIQUE_ID => FunctionHelper::getUniqueId(...$orderParameters), //订单 唯一id
                Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform), //平台
                Constant::DB_TABLE_STORE_ID => $storeId, //官网id
                Constant::DB_TABLE_FULFILLMENT_ID => $fulfillmentId, //物流 id
                Constant::DB_TABLE_PLATFORM_ORDER_ID => $orderId, //订单号
                Constant::DB_TABLE_FULFILLMENT_SERVICE => data_get($fulfillment, 'service') ?? '', //物流服务提供者
                Constant::DB_TABLE_FULFILLMENT_STATUS => data_get($fulfillment, Constant::DB_TABLE_STATUS) ?? '', //物流状态
                'fulfillment_created_at' => FunctionHelper::handleTime(data_get($fulfillment, Constant::DB_TABLE_CREATED_AT)), //物流创建时间
                'fulfillment_updated_at' => FunctionHelper::handleTime(data_get($fulfillment, Constant::DB_TABLE_UPDATED_AT)), //物流更新时间
                'tracking_company' => data_get($fulfillment, 'tracking_company') ?? '', //跟踪公司
                'shipment_status' => data_get($fulfillment, 'shipment_status') ?? '', //发货状态
                Constant::DB_TABLE_LOCATION_ID => data_get($fulfillment, Constant::DB_TABLE_LOCATION_ID) ?? '', //位置id
                'tracking_number' => data_get($fulfillment, 'tracking_number') ?? '', //跟踪号
                Constant::DB_TABLE_TRACKING_NUMBERS => data_get($fulfillment, Constant::DB_TABLE_TRACKING_NUMBERS) ? json_encode(data_get($fulfillment, Constant::DB_TABLE_TRACKING_NUMBERS)) : '', //跟踪号
                'tracking_url' => data_get($fulfillment, 'tracking_url') ?? '', //跟踪url
                Constant::DB_TABLE_TRACKING_URLS => data_get($fulfillment, Constant::DB_TABLE_TRACKING_URLS) ? json_encode(data_get($fulfillment, Constant::DB_TABLE_TRACKING_URLS)) : '', //跟踪urls
                Constant::DB_TABLE_RECEIPT => data_get($fulfillment, Constant::DB_TABLE_RECEIPT) ? json_encode(data_get($fulfillment, Constant::DB_TABLE_RECEIPT)) : '', //
                Constant::DB_TABLE_NAME => data_get($fulfillment, Constant::DB_TABLE_NAME) ?? '', //
                Constant::DB_TABLE_ADMIN_GRAPHQL_API_ID => data_get($fulfillment, Constant::DB_TABLE_ADMIN_GRAPHQL_API_ID) ?? '', //
            ];

            $items[] = $item;
        }

        return $items;
    }

    /**
     * 获取物流订单item数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据|单条物流数据
     * @return array 物流订单item数据
     */
    public static function getFulfillmentOrderItems($storeId, $platform, $data) {

        $storeId = static::castToString($storeId);

        $items = [];

        if (empty($data)) {
            return $items;
        }

        $fulfillments = data_get($data, 'fulfillments');
        if (empty($fulfillments)) {
            return $items;
        }

        foreach ($fulfillments as $fulfillment) {

            if (empty($fulfillment)) {
                continue;
            }

            $fulfillmentLineItems = data_get($fulfillment, 'line_items', []);
            $created_at = FunctionHelper::handleTime(data_get($fulfillment, Constant::DB_TABLE_CREATED_AT)); //创建时间
            $fulfillmentId = data_get($fulfillment, Constant::DB_TABLE_PRIMARY) ?? 0;

            foreach ($fulfillmentLineItems as $lineItem) {
                $itemId = data_get($lineItem, Constant::DB_TABLE_PRIMARY) ?? 0; //订单 item id

                $parameters = [$storeId, $platform, $itemId, (Order::getCustomClassName() . 'Item')];
                $_parameters = [$storeId, $platform, $fulfillmentId, static::getCustomClassName()];

                $item = [
                    Constant::DB_TABLE_ORDER_ITEM_UNIQUE_ID => FunctionHelper::getUniqueId(...$parameters), //订单 item 唯一id
                    Constant::DB_TABLE_FULFILLMENT_UNIQUE_ID => FunctionHelper::getUniqueId(...$_parameters), //物流 唯一id
                    Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform), //平台
                    Constant::DB_TABLE_PLATFORM_ORDER_ITEM_ID => $itemId, //订单 item id
                    Constant::DB_TABLE_FULFILLMENT_ID => $fulfillmentId, //物流 id
                ];

                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * 获取订单物流数据
     * @param int $storeId 商城id
     * @param int|string $orderId 订单id
     * @param array $extData 扩展数据
     * @return array
     */
    public static function getFulfillments($storeId = 1, $orderId = 0, $extData = []) {

        $storeId = static::castToString($storeId);

        static::setConf($storeId);

        return [];
    }

}
