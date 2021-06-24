<?php

namespace App\Services\Platform;

use App\Services\BaseService;
use App\Constants\Constant;
use App\Services\Store\PlatformServiceManager;
use App\Utils\FunctionHelper;
use App\Services\Traits\GetDefaultConnectionModel;

class RefundService extends BaseService {

    use GetDefaultConnectionModel;
    
    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'PlatformRefund';
    }

    /**
     * 记录退款相关数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条退款数据
     * @return bool
     */
    public static function handle($storeId, $platform, $data) {

        if (empty($data)) {
            return false;
        }

        $refundData = PlatformServiceManager::handle($platform, 'Refund', 'getRefundData', [$storeId, $platform, $data]);
        if (empty($refundData)) {
            return false;
        }

        //退款数据
        foreach ($refundData as $item) {

            $uniqueId = data_get($item, Constant::DB_TABLE_UNIQUE_ID, 0); //退款唯一id
            if (empty($uniqueId)) {
                continue;
            }

            $where = [
                Constant::DB_TABLE_UNIQUE_ID => $uniqueId, //退款唯一id
            ];
            static::updateOrCreate($storeId, $where, $item, '', FunctionHelper::getDbBeforeHandle([], [], [], array_keys($item)));
        }

        //记录退款item相关数据
        RefundItemService::handle($storeId, $platform, $data);

        return true;
    }

    /**
     * 退款创建
     * @param int $storeId 官网id
     * @param string $platform 平台标识
     * @param array $data 回调数据
     * @return bool
     */
    public static function noticeCreate($storeId, $platform, $data) {
        //记录退款相关数据
        return static::handle($storeId, $platform, $data);
    }

}
