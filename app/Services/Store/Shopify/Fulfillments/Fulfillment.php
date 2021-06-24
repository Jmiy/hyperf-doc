<?php

namespace App\Services\Store\Shopify\Fulfillments;

use App\Services\Store\Shopify\BaseService;
use App\Constants\Constant;
use App\Utils\FunctionHelper;
use Hyperf\Utils\Arr;
use App\Services\Store\Shopify\Orders\Order;

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

        $fulfillments = data_get($data, 'fulfillments', [$data]);
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

        $fulfillments = data_get($data, 'fulfillments', [$data]);
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
     * 获取订单数据 https://shopify.dev/docs/admin-api/rest/reference/shipping-and-fulfillment/fulfillment?api[version]=2020-04#index-2020-04
     * @param int $storeId 商城id
     * @param string $createdAtMin 最小创建时间
     * @param string $createdAtMax 最大创建时间
     * @param array $ids shopify会员id
     * @param string $sinceId shopify会员id
     * @param int $limit 记录条数
     * @param array $extData 扩展数据
     * @return array
     */
    public static function getFulfillments($storeId = 1, $orderId = 0, $extData = []) {
        
        $storeId = static::castToString($storeId);

        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . "orders/{$orderId}/fulfillments.json";

        $sinceId = data_get($extData, 'sinceId', ''); //Restrict results to after the specified ID.
        $limit = data_get($extData, 'limit', 250);
        $limit = $limit > 250 ? 250 : $limit;

        $createdAtMin = data_get($extData, 'created_at_min', '');
        $createdAtMax = data_get($extData, 'created_at_max', '');

        $updatedAtMin = data_get($extData, 'updated_at_min', '');
        $updatedAtMax = data_get($extData, 'updated_at_max', '');

        $fields = data_get($extData, 'fields', []); //A comma-separated list of fields to include in the response.
        $fields = $fields ? implode(',', $fields) : '';

        $requestData = array_filter([
            'since_id' => $sinceId ? $sinceId : '', //925376970775
            'created_at_min' => $createdAtMin ? Carbon::parse($createdAtMin)->toIso8601String() : '', //2019-02-25T16:15:47+08:00
            'created_at_max' => $createdAtMax ? Carbon::parse($createdAtMax)->toIso8601String() : '',
            'updated_at_min' => $updatedAtMin ? Carbon::parse($updatedAtMin)->toIso8601String() : '', //2019-02-25T16:15:47+08:00
            'updated_at_max' => $updatedAtMax ? Carbon::parse($updatedAtMax)->toIso8601String() : '',
            'limit' => $limit,
            'fields' => $fields,
        ]);
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'GET';
        $headers = [];
        $dataKey = 'fulfillments';
        $curlExtData = [
            'dataKey' => $dataKey,
        ];
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod, $headers, $curlExtData);

        $data = data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . $dataKey, []);
        $count = count($data);
        if ($count >= 250) {
            $_updatedAtMax = data_get($data, (($count - 1) . '.updated_at'), '');

            if ($updatedAtMax) {
                data_set($extData, 'updated_at_max', $_updatedAtMax);
            }

            sleep(1);
            $_data = static::getFulfillments($storeId, $orderId, $extData);

            return $data = Arr::collapse([$data, $_data]);
        }

        return $data;
    }

}
