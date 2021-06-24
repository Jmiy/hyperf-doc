<?php

namespace App\Services\Platform;

use App\Services\BaseService;
use App\Constants\Constant;
use App\Services\Store\PlatformServiceManager;
use App\Utils\FunctionHelper;
use App\Utils\Response;
use App\Services\Traits\GetDefaultConnectionModel;

class FulfillmentService extends BaseService {

    use GetDefaultConnectionModel;
    
    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'PlatformFulfillment';
    }

    /**
     * 记录物流订单数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据|单条物流数据
     * @return bool
     */
    public static function handle($storeId, $platform, $data) {

        if (empty($data)) {
            return false;
        }

        $fulfillmentData = PlatformServiceManager::handle($platform, 'Fulfillment', 'getFulfillmentData', [$storeId, $platform, $data]);

        //物流数据
        foreach ($fulfillmentData as $item) {

            $uniqueId = data_get($item, Constant::DB_TABLE_UNIQUE_ID, 0); //唯一id
            if (empty($uniqueId)) {
                continue;
            }

            $where = [
                Constant::DB_TABLE_UNIQUE_ID => data_get($item, Constant::DB_TABLE_UNIQUE_ID, 0), //唯一id
            ];
            $updateHandle = [
                function ($instance) use($item) {
                    return data_get($instance, 'fulfillment_updated_at', '') < data_get($item, 'fulfillment_updated_at', '');
                }
            ];
            static::updateOrCreate($storeId, $where, $item, '', FunctionHelper::getDbBeforeHandle($updateHandle, [], [], array_keys($item)));
        }

        //记录物流订单 item 数据
        FulfillmentOrderItemService::handle($storeId, $platform, $data);


        return true;
    }

    /**
     * 拉取订单
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $parameters 拉取平台订单参数
     * @return type
     */
    public static function handlePull($storeId, $platform, $parameters) {
        $fulfillmentData = PlatformServiceManager::handle($platform, 'Fulfillment', 'getFulfillments', $parameters);

        if (empty($fulfillmentData)) {
            unset($fulfillmentData);
            return Response::getDefaultResponseData(0, 'data is empty', []);
        }


        foreach ($fulfillmentData as $data) {
            static::handle($storeId, $platform, $data);
        }

        return Response::getDefaultResponseData(1, '', $orderData);
    }

}
