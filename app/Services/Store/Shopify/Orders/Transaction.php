<?php

namespace App\Services\Store\Shopify\Orders;

use App\Services\Store\Shopify\BaseService;
use App\Services\LogService;
use App\Constants\Constant;
use App\Utils\FunctionHelper;
use App\Services\Store\Shopify\Orders\Order;

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

        $transactions = data_get($data, 'transactions', [$data]);
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
        
        $storeId = static::castToString($storeId);
        
        //static::setConf($storeId);

        $storeUrl = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . "orders/";

        //更新订单金额
        $url = $storeUrl . $orderId . "/transactions.json";
        $requestData = json_encode([
            "transaction" => [
                "amount" => $total,
                "kind" => "capture"
            ]
        ]);
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $res = static::request($storeId, $url, $requestData, $username, $password);
        LogService::addSystemLog('info', 'shopify', 'orderback', $orderId, ['order' => $orderId, 'content' => $requestData, 'res' => $res]);

        return $res;
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
        
        //static::setConf($storeId);

        $storeUrl = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . "orders/";
        //获取交易数据
        $url = $storeUrl . $orderId . "/transactions.json";

        $sinceId = data_get($extData, 'sinceId', ''); //Show orders after the specified ID.
        $fields = data_get($extData, 'fields', []); //Retrieve only certain fields, specified by a comma-separated list of fields names.
        $fields = $fields ? implode(',', $fields) : '';
        $inShopCurrency = data_get($extData, 'in_shop_currency', false); //

        $requestData = array_filter([
            'since_id' => $sinceId ? $sinceId : '', //925376970775
            'fields' => $fields,
            'in_shop_currency' => $inShopCurrency,
        ]);
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'GET';
        $headers = [];
        $dataKey = 'transactions';
        $curlExtData = [
            'dataKey' => $dataKey,
        ];
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod, $headers, $curlExtData);

        return data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . $dataKey);
    }

}
