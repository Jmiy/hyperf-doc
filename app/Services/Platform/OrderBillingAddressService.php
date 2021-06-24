<?php

namespace App\Services\Platform;

use App\Services\BaseService;
use App\Constants\Constant;
use App\Services\Store\PlatformServiceManager;
use App\Utils\FunctionHelper;

use App\Services\Traits\GetDefaultConnectionModel;

class OrderBillingAddressService extends BaseService {
    
    use GetDefaultConnectionModel;

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'PlatformOrderBillingAddress';
    }

    /**
     * 记录订单发票地址相关数据
     * @param int $storeId 官网id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据
     * @return bool
     */
    public static function handle($storeId, $platform, $data) {

        if (empty($data)) {
            return false;
        }

        //订单发票地址数据
        $address = PlatformServiceManager::handle($platform, 'Order', 'getBillingAddress', [$storeId, $platform, $data]);
        $uniqueId = data_get($address, Constant::DB_TABLE_ORDER_UNIQUE_ID, 0); //唯一id
        if (empty($uniqueId)) {
            return false;
        }

        $where = [
            Constant::DB_TABLE_ORDER_UNIQUE_ID => $uniqueId, //订单 唯一id
        ];
        static::updateOrCreate($storeId, $where, $address, '', FunctionHelper::getDbBeforeHandle([], [], [], array_keys($address)));

        return true;
    }

}
