<?php

namespace App\Services\Platform;

use App\Services\BaseService;
use App\Constants\Constant;
use App\Services\Store\PlatformServiceManager;
use App\Utils\FunctionHelper;
use App\Services\Traits\GetDefaultConnectionModel;

class RefundItemService extends BaseService {
    
    use GetDefaultConnectionModel;

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'PlatformRefundItem';
    }

    /**
     * 记录退款 item 相关数据
     * @param int $storeId 官网id
     * @param string $platform 平台标识
     * @param array $data 回调数据
     * @return bool
     */
    public static function handle($storeId, $platform, $data) {

        if (empty($data)) {
            return false;
        }

        $refundItemData = PlatformServiceManager::handle($platform, 'Refund', 'getRefundItemData', [$storeId, $platform, $data]);
        if (empty($refundItemData)) {
            return false;
        }

        //退款数据
        foreach ($refundItemData as $item) {

            $uniqueId = data_get($item, Constant::DB_TABLE_UNIQUE_ID, 0); //退款item唯一id
            if (empty($uniqueId)) {
                continue;
            }

            $where = [
                Constant::DB_TABLE_UNIQUE_ID => $uniqueId, //退款item唯一id
            ];
            static::updateOrCreate($storeId, $where, $item, '', FunctionHelper::getDbBeforeHandle([], [], [], array_keys($item)));
        }

        return true;
    }

}
