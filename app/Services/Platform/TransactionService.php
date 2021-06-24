<?php

namespace App\Services\Platform;

use App\Services\BaseService;
use App\Constants\Constant;
use App\Services\Store\PlatformServiceManager;
use App\Utils\FunctionHelper;
use App\Services\Traits\GetDefaultConnectionModel;

class TransactionService extends BaseService {
    
    use GetDefaultConnectionModel;

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'PlatformTransaction';
    }

    /**
     * 记录交易相关数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据|单条交易数据
     * @return bool
     */
    public static function handle($storeId, $platform, $data) {

        if (empty($data)) {
            return false;
        }

        $transactionData = PlatformServiceManager::handle($platform, 'Transaction', 'getTransactionData', [$storeId, $platform, $data]);
        if (empty($transactionData)) {
            return false;
        }

        //退款数据
        foreach ($transactionData as $item) {

            $uniqueId = data_get($item, Constant::DB_TABLE_UNIQUE_ID, 0); //交易唯一id
            if (empty($uniqueId)) {
                continue;
            }

            $where = [
                Constant::DB_TABLE_UNIQUE_ID => $uniqueId, //交易唯一id
            ];
            static::updateOrCreate($storeId, $where, $item, '', FunctionHelper::getDbBeforeHandle([], [], [], array_keys($item)));
        }

        return true;
    }

    /**
     * 交易创建
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据|单条交易数据
     * @return bool
     */
    public static function noticeCreate($storeId, $platform, $data) {
        return static::handle($storeId, $platform, $data);
    }

    /**
     * 拉取交易数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param string $orderId 平台订单id
     */
    public static function handlePull($storeId, $platform, $orderId) {
        $transactionData = PlatformServiceManager::handle($platform, 'Transaction', 'getTransactions', [$storeId, $orderId, []]);

        if ($transactionData === null) {
            return false;
        }

        foreach ($transactionData as $data) {
            static::handle($storeId, $platform, $data);
        }
    }

}
