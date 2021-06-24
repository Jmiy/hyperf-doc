<?php

namespace App\Services\Store\Localhost\Orders;

use App\Services\Store\Localhost\BaseService;
use App\Constants\Constant;
use App\Utils\FunctionHelper;

class Transaction extends BaseService {

    /**
     * 获取交易流水
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据|单条交易数据
     * @return array 交易流水
     */
    public static function getTransactionData($storeId, $platform, $data) {

        $storeId = static::castToString($storeId);

        $items = [];
        if (empty($data)) {
            return $items;
        }

        $transactions = data_get($data, 'transactions');
        if (empty($transactions)) {
            return $items;
        }

        foreach ($transactions as $transaction) {

            if (empty($transaction)) {
                continue;
            }

            $transactionId = data_get($transaction, Constant::DB_TABLE_PRIMARY) ?? 0; //交易号
            $orderId = data_get($transaction, Constant::DB_TABLE_ORDER_ID) ?? 0; //订单号
            $createdAt = FunctionHelper::handleTime(data_get($transaction, Constant::DB_TABLE_CREATED_AT)); //交易创建时间

            $parameters = [$storeId, $platform, $transactionId, static::getCustomClassName()];
            $orderParameters = [$storeId, $platform, $orderId, Order::getCustomClassName()];

            $item = [
                Constant::DB_TABLE_UNIQUE_ID => FunctionHelper::getUniqueId(...$parameters), //交易唯一id
                Constant::DB_TABLE_ORDER_UNIQUE_ID => FunctionHelper::getUniqueId(...$orderParameters), //订单 唯一id
                Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform), //平台
                Constant::DB_TABLE_STORE_ID => $storeId, //官网id
                Constant::DB_TABLE_TRANSACTION_ID => $transactionId, //交易号
                Constant::DB_TABLE_PLATFORM_ORDER_ID => $orderId, //订单号
                'parent_id' => data_get($transaction, 'parent_id') ?? 0, //交易父id
                'kind' => data_get($transaction, 'kind') ?? '', //交易类型
                Constant::DB_TABLE_GATEWAY => data_get($transaction, Constant::DB_TABLE_GATEWAY) ?? '', //交易网关
                'transaction_status' => data_get($transaction, Constant::DB_TABLE_STATUS) ?? '', //交易状态
                'message' => data_get($transaction, 'message') ?? '', //交易描述
                'transaction_created_at' => $createdAt, //交易创建时间
                'transaction_processed_at' => FunctionHelper::handleTime(data_get($transaction, Constant::DB_TABLE_PROCESSED_AT)), //交易处理时间
                Constant::DB_TABLE_TEST => data_get($transaction, Constant::DB_TABLE_TEST, false) ? 1 : 0, //是否测试
                Constant::DB_TABLE_AMOUNT => FunctionHelper::handleNumber(data_get($transaction, Constant::DB_TABLE_AMOUNT)), //交易金额
                Constant::DB_TABLE_CURRENCY => data_get($transaction, Constant::DB_TABLE_CURRENCY) ?? '', //交易货币
                Constant::DB_TABLE_ADMIN_GRAPHQL_API_ID => data_get($transaction, Constant::DB_TABLE_ADMIN_GRAPHQL_API_ID) ?? '', //
            ];

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Creates a transaction for an order https://shopify.dev/docs/admin-api/rest/reference/orders/transaction?api[version]=2020-04#create-2020-04
     * @param int $storeId 商城id
     * @param string $orderId 订单id
     * @param int $total 订单金额
     * @return array|boolean $res
     */
    public static function create($storeId = 2, $orderId = '', $total = 0) {
        return null;
    }

    /**
     * Retrieves a list of transactions https://shopify.dev/docs/admin-api/rest/reference/orders/transaction?api[version]=2020-04#index-2020-04
     * @param int $storeId 商城id
     * @param string $orderId 订单id
     * @param int $total 订单金额
     * @return array|boolean $res
     */
    public static function getTransactions($storeId = 1, $orderId = '', $extData = []) {

        $storeId = static::castToString($storeId);

        static::setConf($storeId);

        return [];
    }

}
