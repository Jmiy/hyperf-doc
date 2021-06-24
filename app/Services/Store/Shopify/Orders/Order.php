<?php

namespace App\Services\Store\Shopify\Orders;

use App\Services\Store\Shopify\BaseService;
use App\Services\LogService;
use App\Constants\Constant;
use Carbon\Carbon;
use App\Utils\FunctionHelper;
use Hyperf\Utils\Arr;
use App\Services\Store\Shopify\Products\Product;

class Order extends BaseService {

    /**
     * 获取订单状态 订单状态 -1:Matching 0:Pending 1:Shipped 2:Canceled 3:Failure 4:partially_refunded 5:partially_paid 6:authorized
     * @param string|null $key
     * @param mix $default
     * @return mix
     */
    public static function getOrderStatus($key, $default = 0) {
        $data = [
            'pending' => 0, //付款正在处理中 The payments are pending. Payment might fail in this state. Check again to confirm whether the payments have been paid successfully.
            'authorized' => 6, //付款已被授权 The payments have been authorized.
            'partially_paid' => 5, //订单已部分支付 The order have been partially paid.
            'paid' => 1, //付款已经支付 The payments have been paid.
            'partially_refunded' => 4, //付款已部分退还 The payments have been partially refunded.
            'refunded' => 2, //付款已退还 The payments have been refunded.
            'voided' => 3, //付款作废 The payments have been voided.
        ];
        return data_get($data, $key, $default);
    }

    /**
     * 获取订单数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据
     * @return array|max 订单数据
     */
    public static function getOrderData($storeId, $platform, $data) {

        $storeId = static::castToString($storeId);

        if (empty($data)) {
            return [];
        }

        $orderId = data_get($data, Constant::DB_TABLE_PRIMARY) ?? 0; //订单号
        if (empty($orderId)) {
            return [];
        }

        $financialStatus = data_get($data, 'financial_status') ?? ''; //支付状态
        $createdAt = FunctionHelper::handleTime(data_get($data, Constant::DB_TABLE_CREATED_AT)); //订单创建时间
        $orderName = data_get($data, 'name') ?? ''; //订单名称

        $parameters = [$storeId, $platform, $orderId, static::getCustomClassName()];

        return [
            Constant::DB_TABLE_UNIQUE_ID => FunctionHelper::getUniqueId(...$parameters), //平台订单唯一id
            Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform), //平台
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_PLATFORM_CUSTOMER_ID => data_get($data, Constant::CUSTOMER . Constant::LINKER . Constant::DB_TABLE_PRIMARY) ?? 0,
            Constant::DB_TABLE_EMAIL => data_get($data, Constant::DB_TABLE_EMAIL) ?? '', //用户邮箱
            Constant::DB_TABLE_PLATFORM_ORDER_ID => $orderId, //订单号
            Constant::DB_TABLE_PLATFORM_CREATED_AT => $createdAt, //订单创建时间
            Constant::DB_TABLE_PLATFORM_UPDATED_AT => FunctionHelper::handleTime(data_get($data, Constant::DB_TABLE_UPDATED_AT)), //订单更新时间
            Constant::DB_TABLE_PLATFORM_CLOSED_AT => FunctionHelper::handleTime(data_get($data, 'closed_at')), //订单关闭时间
            Constant::DB_TABLE_PLATFORM_CANCELLED_AT => FunctionHelper::handleTime(data_get($data, 'cancelled_at')), //订单取消时间
            Constant::DB_TABLE_PLATFORM_CANCELLED_REASON => data_get($data, 'cancel_reason') ?? '', //订单取消理由
            Constant::DB_TABLE_PROCESSED_AT => FunctionHelper::handleTime(data_get($data, Constant::DB_TABLE_PROCESSED_AT)), //处理时间
            'number' => data_get($data, 'number') ?? 0, //订单编号
            'order_number' => data_get($data, 'order_number') ?? 0, //订单编号
            Constant::DB_TABLE_NOTE => data_get($data, Constant::DB_TABLE_NOTE) ?? '', //订单说明
            Constant::DB_TABLE_ORDER_TYPE => static::getOrderType(data_get($data, 'note_attributes') ?? []), //订单类型,1:正常购买订单,2积分兑换订单,3秒杀订单,其他值待定义
            Constant::DB_TABLE_TOTAL_PRICE => FunctionHelper::handleNumber(data_get($data, Constant::DB_TABLE_TOTAL_PRICE)), //订单最终支付总金额
            'total_line_items_price' => FunctionHelper::handleNumber(data_get($data, 'total_line_items_price')), //订单商品总金额
            'subtotal_price' => FunctionHelper::handleNumber(data_get($data, 'subtotal_price')), //订单优惠后的总金额
            'total_discounts' => FunctionHelper::handleNumber(data_get($data, 'total_discounts')), //优惠总金额
            Constant::DB_TABLE_TOTAL_TAX => FunctionHelper::handleNumber(data_get($data, Constant::DB_TABLE_TOTAL_TAX)), //税总金额
            'total_tip_received' => FunctionHelper::handleNumber(data_get($data, 'total_tip_received')), //手续费总金额
            'total_shipping' => FunctionHelper::handleNumber(data_get($data, 'total_shipping_price_set.shop_money.amount')), //运费总金额
            Constant::DB_TABLE_AMOUNT => FunctionHelper::handleNumber(data_get($data, Constant::DB_TABLE_TOTAL_PRICE)), //订单最终支付总金额
            'total_weight' => FunctionHelper::handleNumber(data_get($data, 'total_weight')),
            'taxes_included' => data_get($data, 'taxes_included', false) ? 1 : 0, //是否包含税
            Constant::DB_TABLE_CURRENCY => data_get($data, Constant::DB_TABLE_CURRENCY) ?? '', //货币
            'financial_status' => $financialStatus, //支付状态
            'name' => $orderName, //订单名称
            Constant::DB_TABLE_PHONE => data_get($data, Constant::DB_TABLE_PHONE) ?? '', //电话号码
            Constant::DB_TABLE_FULFILLMENT_STATUS => data_get($data, Constant::DB_TABLE_FULFILLMENT_STATUS) ?? '', //
            Constant::DB_TABLE_PRESENTMENT_CURRENCY => data_get($data, Constant::DB_TABLE_PRESENTMENT_CURRENCY) ?? '', //
            'contact_email' => data_get($data, 'contact_email') ?? '', //联系邮箱
            'order_status_url' => data_get($data, 'order_status_url') ?? '', //订单状态url
            Constant::DB_TABLE_GATEWAY => data_get($data, Constant::DB_TABLE_GATEWAY) ?? '', //支付网关
            Constant::DB_TABLE_TEST => data_get($data, Constant::DB_TABLE_TEST, false) ? 1 : 0, //是否测试数据
            Constant::DB_TABLE_COUNTRY => data_get($data, 'shipping_address' . Constant::LINKER . Constant::DB_TABLE_COUNTRY_CODE, data_get($data, 'billing_address' . Constant::LINKER . Constant::DB_TABLE_COUNTRY_CODE, '')) ?? '',
            Constant::DB_TABLE_ORDER_STATUS => static::getOrderStatus($financialStatus),
            Constant::DB_TABLE_ORDER_AT => $createdAt, //订单创建时间
            Constant::DB_TABLE_ORDER_NO => $orderName,
        ];
    }

    /**
     * 获取客户端数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据
     * @return array|max 客户端数据
     */
    public static function getClientDetails($storeId, $platform, $data) {

        $storeId = static::castToString($storeId);

        $clientDetails = data_get($data, 'client_details', []);
        if (empty($clientDetails)) {
            return $clientDetails;
        }

        $orderId = data_get($data, Constant::DB_TABLE_PRIMARY) ?? 0; //订单号
        if (empty($orderId)) {
            return [];
        }

        $parameters = [$storeId, $platform, $orderId, static::getCustomClassName()];

        return [
            Constant::DB_TABLE_ORDER_UNIQUE_ID => FunctionHelper::getUniqueId(...$parameters), //订单唯一id
            Constant::DB_TABLE_PLATFORM_ORDER_ID => $orderId, //订单号
            'accept_language' => data_get($clientDetails, 'accept_language') ?? '', //买家接受语言
            'browser_height' => data_get($clientDetails, 'browser_height') ?? 0, //浏览器高度
            'browser_width' => data_get($clientDetails, 'browser_width') ?? 0, //浏览器宽度
            'browser_ip' => data_get($clientDetails, 'browser_ip') ?? '', //浏览ip
            'session_hash' => data_get($clientDetails, 'session_hash') ?? '', //会话hash
            'user_agent' => data_get($clientDetails, 'user_agent') ?? '', //user_agent 信息
            'customer_locale' => data_get($data, 'customer_locale') ?? '', //customer locale
            Constant::DB_TABLE_LOCATION_ID => data_get($data, Constant::DB_TABLE_LOCATION_ID) ?? 0, //物理位置id
        ];
    }

    /**
     * 获取订单item数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据
     * @return array
     */
    public static function getItemData($storeId, $platform, $data) {

        $storeId = static::castToString($storeId);

        $items = [];
        $lineItems = data_get($data, 'line_items', []);
        if (empty($lineItems)) {
            return $items;
        }

        $orderId = data_get($data, Constant::DB_TABLE_PRIMARY) ?? 0; //订单号
        $createdAt = FunctionHelper::handleTime(data_get($data, Constant::DB_TABLE_CREATED_AT)); //订单创建时间

        foreach ($lineItems as $lineItem) {

            $itemId = data_get($lineItem, Constant::DB_TABLE_PRIMARY) ?? 0; //item id

            if (empty($itemId)) {
                continue;
            }

            $price = FunctionHelper::handleNumber(data_get($lineItem, Constant::DB_TABLE_PRICE)); //单价
            $quantity = data_get($lineItem, Constant::DB_TABLE_QUANTITY) ?? 0; //商品数量
            $totalDiscount = FunctionHelper::handleNumber(data_get($lineItem, 'discount_allocations.0.amount')); //优惠金额
            $productId = data_get($lineItem, 'product_id') ?? 0;
            $variantId = data_get($lineItem, 'variant_id') ?? 0; //item variant id

            $parameters = [$storeId, $platform, $itemId, (static::getCustomClassName() . 'Item')];
            $orderParameters = [$storeId, $platform, $orderId, static::getCustomClassName()];
            $variantParameters = [$storeId, $platform, $variantId, (Product::getCustomClassName() . 'Variant')];
            $productParameters = [$storeId, $platform, $productId, Product::getCustomClassName()];

            $item = [
                Constant::DB_TABLE_UNIQUE_ID => FunctionHelper::getUniqueId(...$parameters), //唯一id
                Constant::DB_TABLE_ORDER_UNIQUE_ID => FunctionHelper::getUniqueId(...$orderParameters), //订单 唯一id
                Constant::DB_TABLE_PRODUCT_VARIANT_UNIQUE_ID => FunctionHelper::getUniqueId(...$variantParameters), //产品变种 唯一id
                Constant::DB_TABLE_PRODUCT_UNIQUE_ID => FunctionHelper::getUniqueId(...$productParameters), //产品 唯一id
                Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform), //平台
                Constant::DB_TABLE_STORE_ID => $storeId,
                Constant::DB_TABLE_PLATFORM_ORDER_ID => $orderId, //订单号
                Constant::DB_TABLE_PLATFORM_ORDER_ITEM_ID => $itemId, //item id
                'variant_id' => $variantId, //item variant id
                'variant_title' => data_get($lineItem, 'variant_title') ?? '', //商品variant title
                'product_id' => $productId, //产品 id
                'fulfillable_quantity' => data_get($lineItem, 'fulfillable_quantity') ?? 0, //发货的数量
                Constant::DB_TABLE_FULFILLMENT_SERVICE => data_get($lineItem, Constant::DB_TABLE_FULFILLMENT_SERVICE) ?? '', //物流服务提供者
                Constant::DB_TABLE_FULFILLMENT_STATUS => data_get($lineItem, Constant::DB_TABLE_FULFILLMENT_STATUS) ?? '', //物流状态
                Constant::FILE_TITLE => data_get($lineItem, Constant::FILE_TITLE) ?? '', //商品标题
                'sku' => data_get($lineItem, 'sku') ?? '', //sku
                'vendor' => data_get($lineItem, 'vendor') ?? '', //商品供应方
                Constant::DB_TABLE_REQUIRES_SHIPPING => data_get($lineItem, Constant::DB_TABLE_REQUIRES_SHIPPING, false) ? 1 : 0, //
                'taxable' => data_get($lineItem, 'requires_shipping', false) ? 1 : 0, //是否需要税
                'gift_card' => data_get($lineItem, 'gift_card', false) ? 1 : 0, //是否礼品卡
                'name' => data_get($lineItem, 'name') ?? '', //产品名字
                'variant_inventory_management' => data_get($lineItem, 'variant_inventory_management') ?? '', //库存管理
                'product_exists' => data_get($lineItem, 'requires_shipping', false) ? 1 : 0, //产品是否存在
                'grams' => FunctionHelper::handleNumber(data_get($lineItem, 'grams')), //重量
                Constant::DB_TABLE_ADMIN_GRAPHQL_API_ID => data_get($lineItem, Constant::DB_TABLE_ADMIN_GRAPHQL_API_ID) ?? '', //
                Constant::DB_TABLE_PRICE => $price, //单价
                Constant::DB_TABLE_QUANTITY => $quantity, //商品数量
                'total_discount' => $totalDiscount, //优惠金额
                Constant::DB_TABLE_AMOUNT => FunctionHelper::handleNumber($price * $quantity - $totalDiscount), //item应付总金额
            ];

            $items[] = $item;
        }

        return $items;
    }

    /**
     * 获取统一的地址数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据
     * @param array $address 单条地址数据
     * @return array 统一的地址数据
     */
    public static function getAddress($storeId, $platform, $data, $address) {

        $storeId = static::castToString($storeId);

        if (empty($address)) {
            return [];
        }

        $orderId = data_get($data, Constant::DB_TABLE_PRIMARY) ?? 0; //订单号
        $parameters = [$storeId, $platform, $orderId, static::getCustomClassName()];

        return [
            Constant::DB_TABLE_ORDER_UNIQUE_ID => FunctionHelper::getUniqueId(...$parameters), //订单 唯一id
            Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform), //平台
            Constant::DB_TABLE_STORE_ID => $storeId, //官网id
            Constant::DB_TABLE_PLATFORM_ORDER_ID => $orderId, //订单号
            Constant::DB_TABLE_EMAIL => data_get($data, Constant::DB_TABLE_EMAIL) ?? '', //用户邮箱
            Constant::DB_TABLE_PLATFORM_CUSTOMER_ID => data_get($data, Constant::CUSTOMER . Constant::LINKER . Constant::DB_TABLE_PRIMARY) ?? 0, //平台会员id
            Constant::DB_TABLE_ADDRESS1 => data_get($address, Constant::DB_TABLE_ADDRESS1) ?? '', //地址
            Constant::DB_TABLE_ADDRESS2 => data_get($address, Constant::DB_TABLE_ADDRESS2) ?? '', //可选地址
            Constant::DB_TABLE_PHONE => data_get($address, Constant::DB_TABLE_PHONE) ?? '', //电话
            Constant::DB_TABLE_CITY => data_get($address, Constant::DB_TABLE_CITY) ?? '', //城市
            Constant::DB_TABLE_ZIP => data_get($address, Constant::DB_TABLE_ZIP) ?? '', //邮编
            Constant::DB_TABLE_PROVINCE => data_get($address, Constant::DB_TABLE_PROVINCE) ?? '', //省份
            Constant::DB_TABLE_COUNTRY => data_get($address, Constant::DB_TABLE_COUNTRY) ?? '', //国家
            Constant::DB_TABLE_NAME => data_get($address, Constant::DB_TABLE_NAME) ?? '', //名字
            Constant::DB_TABLE_FIRST_NAME => data_get($address, Constant::DB_TABLE_FIRST_NAME) ?? '', //名字
            Constant::DB_TABLE_LAST_NAME => data_get($address, Constant::DB_TABLE_LAST_NAME) ?? '', //名字
            Constant::DB_TABLE_COMPANY => data_get($address, Constant::DB_TABLE_COMPANY) ?? '', //公司
            Constant::DB_TABLE_LATITUDE => data_get($address, Constant::DB_TABLE_LATITUDE) ?? '', //维度
            Constant::DB_TABLE_LONGITUDE => data_get($address, Constant::DB_TABLE_LONGITUDE) ?? '', //经度
            Constant::DB_TABLE_COUNTRY_CODE => data_get($address, Constant::DB_TABLE_COUNTRY_CODE) ?? '', //国家编码
            Constant::DB_TABLE_PROVINCE_CODE => data_get($address, Constant::DB_TABLE_PROVINCE_CODE) ?? '', //省份编码
        ];
    }

    /**
     * 获取订单地址数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据
     * @return array
     */
    public static function getShippingAddress($storeId, $platform, $data) {
        $storeId = static::castToString($storeId);
        $address = data_get($data, 'shipping_address', []); //送货地址
        return static::getAddress($storeId, $platform, $data, $address);
    }

    /**
     * 获取订单地址数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据
     * @return array
     */
    public static function getBillingAddress($storeId, $platform, $data) {
        $storeId = static::castToString($storeId);
        $address = data_get($data, 'billing_address', []); //帐单地址
        return static::getAddress($storeId, $platform, $data, $address);
    }

    /**
     * 通过订单数据获取会员数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据|单条物流数据
     * @return type
     */
    public static function getCustomer($storeId, $platform, $data) {
        $storeId = static::castToString($storeId);
        $customerData = data_get($data, Constant::CUSTOMER, []);
        if (empty($customerData)) {
            return [];
        }

        //$customerData[Constant::DB_TABLE_EMAIL] ?? data_set($customerData, Constant::DB_TABLE_EMAIL, data_get($customerData, Constant::DB_TABLE_PHONE, ''));

        $clientDetails = static::getClientDetails($storeId, $platform, $data);
        $browserIp = data_get($clientDetails, 'browser_ip', '');
        if ($browserIp) {
            data_set($customerData, Constant::DB_TABLE_IP, $browserIp);
            data_set($customerData, Constant::DB_TABLE_COUNTRY, FunctionHelper::getCountry($browserIp));
        }

        return $customerData;

        //return Customer::getCustomerData([$customerData], $storeId, 666666);//订单购买 06-29/30
    }

    /**
     * Updates an order https://shopify.dev/docs/admin-api/rest/reference/orders/order?api[version]=2020-04#update-2020-04
     * @param int $storeId 商城id
     * @param string $orderId 订单id
     * @param int $total 订单金额
     * @return array|boolean $res
     */
    public static function update($storeId = 2, $orderId = '', $note = '') {

        $storeId = static::castToString($storeId);

        //static::setConf($storeId);

        $storeUrl = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . "orders/";

        //更新订单备注
        $url = $storeUrl . $orderId . ".json";
        $requestData = json_encode([
            "order" => [
                "id" => (int) $orderId,
                "note" => $note
            ]
        ]);
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'put';
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod);
        LogService::addSystemLog('info', 'shopify', 'ordernode', $orderId, ['url' => $url, 'content' => $requestData, 'res' => $res]);

        return $res;
    }

    /**
     * 获取订单数据 https://shopify.dev/docs/admin-api/rest/reference/orders/order?api[version]=2020-04#index-2020-04
     * @param int $storeId 商城id
     * @param string $createdAtMin 最小创建时间
     * @param string $createdAtMax 最大创建时间
     * @param array $ids shopify会员id
     * @param string $sinceId shopify会员id
     * @param int $limit 记录条数
     * @param array $parameters 扩展数据
     * @return array
     */
    public static function getOrder($storeId = 1, $parameters = []) {

        $storeId = static::castToString($storeId);

        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . "orders.json";

        $ids = data_get($parameters, 'ids', []); //Retrieve only orders specified by a comma-separated list of order IDs.
        $sinceId = data_get($parameters, 'sinceId', ''); //Show orders after the specified ID.
        $limit = data_get($parameters, 'limit', 250);
        $limit = $limit > 250 ? 250 : $limit;

        $createdAtMin = data_get($parameters, 'created_at_min', '');
        $createdAtMax = data_get($parameters, 'created_at_max', '');

        $updatedAtMin = data_get($parameters, 'updated_at_min', '');
        $updatedAtMax = data_get($parameters, 'updated_at_max', '');

        $processedAtMin = data_get($parameters, 'processed_at_min', '');
        $processedAtMax = data_get($parameters, 'processed_at_max', '');

        $attributionAppId = data_get($parameters, 'attribution_app_id', ''); //Show orders attributed to a certain app, specified by the app ID. Set as current to show orders for the app currently consuming the API.
        $status = data_get($parameters, 'status', 'any');

        /**
         * Filter orders by their financial status.
          (default: any)
          authorized: Show only authorized orders
          pending: Show only pending orders
          paid: Show only paid orders
          partially_paid: Show only partially paid orders
          refunded: Show only refunded orders
          voided: Show only voided orders
          partially_refunded: Show only partially refunded orders
          any: Show orders of any financial status.
          unpaid: Show authorized and partially paid orders.
         *
         */
        $financialStatus = data_get($parameters, 'financial_status', 'any');

        /**
         * Filter orders by their fulfillment status.
          (default: any)
          shipped: Show orders that have been shipped. Returns orders with fulfillment_status of fulfilled.
          partial: Show partially shipped orders.
          unshipped: Show orders that have not yet been shipped. Returns orders with fulfillment_status of null.
          any: Show orders of any fulfillment status.
          unfulfilled: Returns orders with fulfillment_status of null or partial.
         */
        $fulfillmentStatus = data_get($parameters, 'fulfillment_status', 'any');

        $fields = data_get($parameters, 'fields', []); //Retrieve only certain fields, specified by a comma-separated list of fields names.
        $fields = $fields ? implode(',', $fields) : '';
        $requestData = array_filter([
            'ids' => $ids ? implode(',', $ids) : '', //207119551,1073339460
            'since_id' => $sinceId ? $sinceId : '', //925376970775
            'created_at_min' => $createdAtMin ? Carbon::parse($createdAtMin)->toIso8601String() : '', //2019-02-25T16:15:47+08:00
            'created_at_max' => $createdAtMax ? Carbon::parse($createdAtMax)->toIso8601String() : '',
            'updated_at_min' => $updatedAtMin ? Carbon::parse($updatedAtMin)->toIso8601String() : '', //2019-02-25T16:15:47+08:00
            'updated_at_max' => $updatedAtMax ? Carbon::parse($updatedAtMax)->toIso8601String() : '',
            'processed_at_min' => $processedAtMin ? Carbon::parse($processedAtMin)->toIso8601String() : '', //2019-02-25T16:15:47+08:00
            'processed_at_max' => $processedAtMax ? Carbon::parse($processedAtMax)->toIso8601String() : '',
            'attribution_app_id' => $attributionAppId ? $attributionAppId : '', //925376970775
            'limit' => $limit,
            'status' => $status,
            'financial_status' => $financialStatus,
            'fulfillment_status' => $fulfillmentStatus,
            'fields' => $fields,
        ]);
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'GET';
        $headers = [];
        $extData = [
            'dataKey' => 'orders',
        ];
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod, $headers, $extData);

        $data = data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . 'orders');
        $count = $data !== null ? count($data) : 0;
        if ($count >= 250) {
            $_updatedAtMax = data_get($data, (($count - 1) . '.updated_at'), '');

            if ($updatedAtMax) {
                data_set($parameters, 'updated_at_max', $_updatedAtMax);
            }

            if ($ids) {
                $_ids = collect($data)->keyBy(Constant::DB_TABLE_PRIMARY)->keys()->toArray();
                $ids = array_diff($ids, $_ids);
            }

            sleep(1);
            $_data = static::getOrder($storeId, $parameters);

            return $_data !== null ? Arr::collapse([$data, $_data]) : $data;
        }

        return $data;
    }

    /**
     * 获取订单数据 https://shopify.dev/docs/admin-api/rest/reference/orders/order?api[version]=2020-04
     * @param int $storeId 商城id
     * @param array $parameters 请求参数
     * @return array
     */
    public static function count($storeId = 1, $parameters = []) {

        $storeId = static::castToString($storeId);

        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . "orders/count.json";

        $createdAtMin = data_get($parameters, 'created_at_min', '');
        $createdAtMax = data_get($parameters, 'created_at_max', '');

        $updatedAtMin = data_get($parameters, 'updated_at_min', '');
        $updatedAtMax = data_get($parameters, 'updated_at_max', '');

        $status = data_get($parameters, 'status', 'any');


        /**
         * Filter orders by their financial status.
          (default: any)
          authorized: Show only authorized orders
          pending: Show only pending orders
          paid: Show only paid orders
          partially_paid: Show only partially paid orders
          refunded: Show only refunded orders
          voided: Show only voided orders
          partially_refunded: Show only partially refunded orders
          any: Show orders of any financial status.
          unpaid: Show authorized and partially paid orders.
         *
         */
        $financialStatus = data_get($parameters, 'financial_status', 'any');

        /**
         * Filter orders by their fulfillment status.
          (default: any)
          shipped: Show orders that have been shipped. Returns orders with fulfillment_status of fulfilled.
          partial: Show partially shipped orders.
          unshipped: Show orders that have not yet been shipped. Returns orders with fulfillment_status of null.
          any: Show orders of any fulfillment status.
          unfulfilled: Returns orders with fulfillment_status of null or partial.
         */
        $fulfillmentStatus = data_get($parameters, 'fulfillment_status', 'any');

        $requestData = array_filter([
            'created_at_min' => $createdAtMin ? Carbon::parse($createdAtMin)->toIso8601String() : '', //2019-02-25T16:15:47+08:00
            'created_at_max' => $createdAtMax ? Carbon::parse($createdAtMax)->toIso8601String() : '',
            'updated_at_min' => $updatedAtMin ? Carbon::parse($updatedAtMin)->toIso8601String() : '', //2019-02-25T16:15:47+08:00
            'updated_at_max' => $updatedAtMax ? Carbon::parse($updatedAtMax)->toIso8601String() : '',
            'status' => $status,
            'financial_status' => $financialStatus,
            'fulfillment_status' => $fulfillmentStatus,
        ]);
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'GET';
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod);

        return data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . 'count', 0);
    }

    /**
     * 订单创建
     * @param $storeId
     * @param $email
     * @param $phone
     * @param $lineItems
     * @param $shippingAddress
     * @param $billingAddress
     * @param $transactions
     * @param $financialStatus
     * @param $discountCodes
     * @param $noteAttributes
     * @return array
     */
    public static function create($storeId, $email, $phone, $lineItems, $shippingAddress, $billingAddress, $transactions, $financialStatus, $discountCodes, $noteAttributes, $tags) {

        $storeId = static::castToString($storeId);

        //static::setConf($storeId);

        $storeUrl = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl');

        //订单创建
        $url = $storeUrl . "orders.json";

        $requestData = json_encode([
            "order" => [
                "line_items" => $lineItems,
                "email" => $email,
                "phone" => $phone,
                "billing_address" => $billingAddress,
                "shipping_address" => $shippingAddress,
                "transactions" => $transactions,
                "financial_status" => $financialStatus,
                "discount_codes" => $discountCodes,
                "note_attributes" => $noteAttributes,
                "tags" => $tags
            ]
        ]);

        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'post';
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod);
        if (empty($res[Constant::RESPONSE_TEXT]) || !empty($res[Constant::RESPONSE_TEXT]['errors'])) {
            return [
                'is_success' => false,
                Constant::RESPONSE_DATA_KEY => $res[Constant::RESPONSE_TEXT]['errors']
            ];
        }

        return [
            'is_success' => true,
            Constant::RESPONSE_DATA_KEY => $res[Constant::RESPONSE_TEXT]
        ];
    }

    /**
     * 从备注属性中获取订单类 型
     * @param $noteAttributes
     * @return int
     */
    public static function getOrderType($noteAttributes) {
        $orderType = 1;
        if (empty($noteAttributes)) {
            return $orderType;
        }
        foreach ($noteAttributes as $attribute) {
            if ($attribute['name'] == 'order_type') {
                $orderType = intval($attribute['value']);
                break;
            }
        }
        return $orderType;
    }

}
