<?php

namespace App\Services\Platform;

use App\Services\BaseService;
use App\Constants\Constant;
use App\Services\Store\PlatformServiceManager;
use App\Utils\FunctionHelper;
use App\Services\Traits\GetDefaultConnectionModel;

class FulfillmentOrderItemService extends BaseService {
    
    use GetDefaultConnectionModel;

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'PlatformFulfillmentOrderItem';
    }

    /**
     * 记录物流订单 item 数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据|单条物流数据
     * @return bool
     */
    public static function handle($storeId, $platform, $data) {

        if (empty($data)) {
            return false;
        }

        /**
         * 获取物流订单item数据
         */
        $fulfillmentLineItems = PlatformServiceManager::handle($platform, 'Fulfillment', 'getFulfillmentOrderItems', [$storeId, $platform, $data]);

        //处理物流订单item数据
        foreach ($fulfillmentLineItems as $item) {

            $orderItemUniqueId = data_get($item, Constant::DB_TABLE_ORDER_ITEM_UNIQUE_ID, 0);
            if (empty($orderItemUniqueId)) {
                continue;
            }

            $where = [
                Constant::DB_TABLE_ORDER_ITEM_UNIQUE_ID => data_get($item, Constant::DB_TABLE_ORDER_ITEM_UNIQUE_ID, 0), //订单 item 唯一id
                Constant::DB_TABLE_FULFILLMENT_UNIQUE_ID => data_get($item, Constant::DB_TABLE_FULFILLMENT_UNIQUE_ID, 0), //物流 唯一id
            ];
            static::updateOrCreate($storeId, $where, $item, '', FunctionHelper::getDbBeforeHandle([], [], [], array_keys($item)));
        }

        return true;
    }

    /**
     * 物流创建
     * @param int $storeId 官网id
     * @param string $platform 平台标识
     * @param array $data 回调数据
     * @return bool
     */
    public static function fulfillmentCreate($storeId, $platform, $data) {
        $fulfillmentId = data_get($data, Constant::DB_TABLE_PRIMARY) ?? 0; //发货id
        $orderId = data_get($data, Constant::DB_TABLE_ORDER_ID) ?? 0; //订单ID
        //记录回调数据
        CallbackDetailService::handle($storeId, $platform, $fulfillmentId, 'fulfillment', Constant::CREATE, $orderId, Constant::ORDER, $data);

        return true;
    }

    /**
     * 物流更新
     * @param int $storeId 官网id
     * @param string $platform 平台标识
     * @param array $data 回调数据
     * @return bool
     */
    public static function fulfillmentUpdate($storeId, $platform, $data) {
        $fulfillmentId = data_get($data, Constant::DB_TABLE_PRIMARY) ?? 0; //发货id
        $orderId = data_get($data, Constant::DB_TABLE_ORDER_ID) ?? 0; //订单ID
        //记录回调数据
        //CallbackDetailService::handle($storeId, $platform, $fulfillmentId, 'fulfillment', 'update', $orderId, Constant::ORDER, $data);

        return true;
    }

}
