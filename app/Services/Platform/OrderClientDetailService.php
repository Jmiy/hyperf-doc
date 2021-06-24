<?php

namespace App\Services\Platform;

use App\Services\BaseService;
use App\Services\Store\PlatformServiceManager;
use App\Constants\Constant;
use App\Utils\FunctionHelper;

use App\Services\Traits\GetDefaultConnectionModel;

class OrderClientDetailService extends BaseService {

    use GetDefaultConnectionModel;
    
    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'PlatformOrderClientDetail';
    }

    /**
     * 记录订单买家客户端数据
     * @param int $storeId 官网id
     * @param string $platform 平台标识
     * @param array $data 回调数据
     * @return bool
     */
    public static function handle($storeId, $platform, $data) {

        if (empty($data)) {
            return false;
        }

        //买家客户端数据
        $clientDetails = PlatformServiceManager::handle($platform, 'Order', 'getClientDetails', [$storeId, $platform, $data]);
        if (empty($clientDetails)) {
            return false;
        }

        $uniqueId = data_get($clientDetails, Constant::DB_TABLE_ORDER_UNIQUE_ID); //订单唯一id
        if (empty($uniqueId)) {
            return false;
        }

        $where = [
            Constant::DB_TABLE_ORDER_UNIQUE_ID => $uniqueId, //订单唯一id
        ];
        return static::updateOrCreate($storeId, $where, $clientDetails, '', FunctionHelper::getDbBeforeHandle([], [], [], array_keys($clientDetails)));
    }

}
