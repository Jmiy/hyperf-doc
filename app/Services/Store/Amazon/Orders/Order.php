<?php

namespace App\Services\Store\Amazon\Orders;

use App\Services\Monitor\MonitorServiceManager;
use App\Services\Store\Amazon\BaseService;
use App\Services\Store\Amazon\Products\Product;
use Exception;
use App\Services\LogService;
use App\Utils\FunctionHelper;
use App\Constants\Constant;
use App\Services\OrdersService;
use App\Exception\Handler\AppExceptionHandler as ExceptionHandler;
use App\Models\Erp\Amazon\AmazonOrderItem;
use App\Services\DictService;
use App\Models\Erp\Amazon\ShopAsin;
use App\Models\Erp\Amazon\ShopInfo;

class Order extends BaseService {

    /**
     * 获取订单状态 订单状态 -1:Matching 0:Pending 1:Shipped 2:Canceled 3:Failure 4:partially_refunded 5:partially_paid 6:authorized
     * @param string|null $key
     * @param mix $default
     * @return mix
     */
    public static function getOrderStatus($key, $default = 0) {
        $orderStatusData = DictService::getListByType(Constant::DB_TABLE_ORDER_STATUS, Constant::DB_TABLE_DICT_VALUE, Constant::DB_TABLE_DICT_KEY); //订单状态 -1:Matching 0:Pending 1:Shipped 2:Canceled 3:Failure 默认:-1
        return data_get($orderStatusData, $key, $default);
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

    /**
     * 获取订单唯一id
     * @param int $storeId 商城id
     * @param string $orderCountry 订单国家
     * @param string $platform 平台
     * @param string $orderId  订单号
     * @return string 订单唯一id
     */
    public static function getOrderUniqueId($storeId, $orderCountry, $platform, $orderId) {
        $storeId = static::castToString($storeId);
        return FunctionHelper::getUniqueId($orderCountry, $platform, $orderId, static::getCustomClassName());
    }

    /**
     * 获取平台订单id
     * @param int $storeId 商城id
     * @param string $orderCountry 订单国家
     * @param string $platform 平台
     * @param string $orderId  订单号
     * @return string 平台订单id
     */
    public static function getPlatformOrderId($storeId, $orderCountry, $platform, $orderId) {
        return static::getOrderUniqueId($storeId, $orderCountry, $platform, $orderId);
    }

    /**
     * 获取平台订单id
     * @param int $storeId 商城id
     * @param string $orderCountry 订单国家
     * @param string $platform 平台
     * @param string $orderId  订单号
     * @return string 平台订单id
     */
    public static function getOrderItemUniqueId($storeId, $platform, $orderCountry, $orderId, $asin, $sku) {
        $storeId = static::castToString($storeId);
        return FunctionHelper::getUniqueId($platform, $orderCountry, $orderId, $asin, $sku, static::getCustomClassName() . 'Item');
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

        $orderId = data_get($data, Constant::DB_TABLE_PRIMARY) ?? 0; //平台订单主键id
        if (empty($orderId)) {
            return [];
        }

        $financialStatus = data_get($data, 'financial_status') ?? ''; //支付状态
        $createdAt = FunctionHelper::handleTime(data_get($data, Constant::DB_TABLE_CREATED_AT)); //订单创建时间
        $orderName = data_get($data, 'name') ?? ''; //订单名称

        return [
            Constant::DB_TABLE_UNIQUE_ID => data_get($data, Constant::DB_TABLE_UNIQUE_ID) ?? 0, //订单 唯一id
            Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform), //平台
            //Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_PLATFORM_CUSTOMER_ID => 0,
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
            Constant::DB_TABLE_ORDER_NO => data_get($data, Constant::DB_TABLE_ORDER_NO) ?? '',
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

        foreach ($lineItems as $lineItem) {

            $itemId = data_get($lineItem, Constant::DB_TABLE_PRIMARY) ?? 0; //item id

            if (empty($itemId)) {
                continue;
            }

            $price = FunctionHelper::handleNumber(data_get($lineItem, Constant::DB_TABLE_PRICE)); //单价
            $quantity = data_get($lineItem, Constant::DB_TABLE_QUANTITY) ?? 0; //商品数量

            $item = [
                Constant::DB_TABLE_UNIQUE_ID => data_get($lineItem, Constant::DB_TABLE_UNIQUE_ID) ?? 0, //订单item 唯一id
                Constant::DB_TABLE_ORDER_UNIQUE_ID => data_get($lineItem, Constant::DB_TABLE_ORDER_UNIQUE_ID) ?? 0, //订单 唯一id
                Constant::DB_TABLE_PRODUCT_VARIANT_UNIQUE_ID => data_get($lineItem, Constant::DB_TABLE_PRODUCT_VARIANT_UNIQUE_ID) ?? 0, //产品变种 唯一id
                Constant::DB_TABLE_PRODUCT_UNIQUE_ID => data_get($lineItem, Constant::DB_TABLE_PRODUCT_UNIQUE_ID) ?? 0, //产品 唯一id
                Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform), //平台
                //Constant::DB_TABLE_STORE_ID => $storeId,
                Constant::DB_TABLE_PLATFORM_ORDER_ID => data_get($lineItem, Constant::DB_TABLE_PLATFORM_ORDER_ID) ?? 0, //平台订单主键id
                Constant::DB_TABLE_PLATFORM_ORDER_ITEM_ID => data_get($lineItem, Constant::DB_TABLE_PLATFORM_ORDER_ITEM_ID) ?? 0, //平台订单item 主键id
                Constant::VARIANT_ID => data_get($lineItem, Constant::VARIANT_ID) ?? 0, //平台产品变种 主键id
                'variant_title' => data_get($lineItem, 'variant_title') ?? '', //商品variant title
                Constant::DB_TABLE_PRODUCT_ID => data_get($lineItem, Constant::DB_TABLE_PRODUCT_ID) ?? 0, //产品 id
                'fulfillable_quantity' => data_get($lineItem, 'fulfillable_quantity') ?? 0, //发货的数量
                Constant::DB_TABLE_FULFILLMENT_SERVICE => data_get($lineItem, Constant::DB_TABLE_FULFILLMENT_SERVICE) ?? '', //物流服务提供者
                Constant::DB_TABLE_FULFILLMENT_STATUS => data_get($lineItem, Constant::DB_TABLE_FULFILLMENT_STATUS) ?? '', //物流状态
                Constant::FILE_TITLE => data_get($lineItem, Constant::FILE_TITLE) ?? '', //商品标题
                'vendor' => data_get($lineItem, 'vendor') ?? '', //商品供应方
                Constant::DB_TABLE_REQUIRES_SHIPPING => data_get($lineItem, Constant::DB_TABLE_REQUIRES_SHIPPING, false) ? 1 : 0, //
                'taxable' => data_get($lineItem, 'taxable', false) ? 1 : 0, //是否需要税
                'gift_card' => data_get($lineItem, 'gift_card', false) ? 1 : 0, //是否礼品卡
                'name' => data_get($lineItem, 'name') ?? '', //产品名字
                'variant_inventory_management' => data_get($lineItem, 'variant_inventory_management') ?? '', //库存管理
                'product_exists' => data_get($lineItem, 'product_exists', false) ? 1 : 0, //产品是否存在
                'grams' => FunctionHelper::handleNumber(data_get($lineItem, 'grams')), //重量
                Constant::DB_TABLE_ADMIN_GRAPHQL_API_ID => data_get($lineItem, Constant::DB_TABLE_ADMIN_GRAPHQL_API_ID) ?? '', //
                Constant::DB_TABLE_PRICE => $price, //单价
                Constant::DB_TABLE_QUANTITY => $quantity, //商品数量
                'total_discount' => data_get($lineItem, 'total_discount', 0.00), //优惠金额
                Constant::DB_TABLE_AMOUNT => data_get($lineItem, Constant::DB_TABLE_AMOUNT, 0.00), //item应付总金额
                Constant::DB_TABLE_COUNTRY => data_get($lineItem, Constant::DB_TABLE_COUNTRY) ?? '',
                Constant::DB_TABLE_ASIN => data_get($lineItem, Constant::DB_TABLE_ASIN) ?? '',
                Constant::DB_TABLE_SKU => data_get($lineItem, Constant::DB_TABLE_SKU) ?? '', //sku
                Constant::DB_TABLE_IMG => data_get($lineItem, Constant::DB_TABLE_IMG) ?? '', //图片地址
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

        return [
            Constant::DB_TABLE_ORDER_UNIQUE_ID => data_get($address, Constant::DB_TABLE_ORDER_UNIQUE_ID) ?? 0, //订单 唯一id
            Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform), //平台
            //Constant::DB_TABLE_STORE_ID => $storeId, //官网id
            Constant::DB_TABLE_PLATFORM_ORDER_ID => data_get($address, Constant::DB_TABLE_PLATFORM_ORDER_ID) ?? 0, //平台订单 主键id
            Constant::DB_TABLE_EMAIL => data_get($address, Constant::DB_TABLE_EMAIL) ?? '', //用户邮箱
            Constant::DB_TABLE_PLATFORM_CUSTOMER_ID => data_get($address, Constant::DB_TABLE_PLATFORM_CUSTOMER_ID) ?? 0, //平台会员id
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

        return [
            Constant::DB_TABLE_ORDER_UNIQUE_ID => data_get($clientDetails, Constant::DB_TABLE_ORDER_UNIQUE_ID) ?? 0, //订单 唯一id
            Constant::DB_TABLE_PLATFORM_ORDER_ID => data_get($clientDetails, Constant::DB_TABLE_PLATFORM_ORDER_ID) ?? 0, //平台订单 主键id
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

        $clientDetails = static::getClientDetails($storeId, $platform, $data);
        $browserIp = data_get($clientDetails, 'browser_ip', '');
        if ($browserIp) {
            data_set($customerData, Constant::DB_TABLE_IP, $browserIp);
            data_set($customerData, Constant::DB_TABLE_COUNTRY, FunctionHelper::getCountry($browserIp));
        }

        return $customerData;
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
    public static function getOrderItem($storeId = 1, $parameters = []) {
        $storeId = static::castToString($storeId);

        static::setConf($storeId);

        $order = data_get($parameters, 'orderno', '');
        $country = data_get($parameters, 'order_country', '');
        $order = trim($order);
        $countryData = OrdersService::$orderCountryData;
        $country = strtolower($country);

        if (in_array($country, $countryData)) {
            array_unshift($countryData, $country);
            $countryData = array_unique($countryData);
        }

        $orderItemData = [];
        try {

            $select = [
                Constant::DB_TABLE_PRIMARY, //订单国家 item id
                'auth_id',
                //'number_of_items', //条目的数量
                Constant::DB_TABLE_AMAZON_ORDER_ID, //订单no
                Constant::DB_TABLE_ORDER_STATUS, //订单状态 Pending Shipped Canceled
                Constant::DB_TABLE_SHIP_SERVICE_LEVEL, //发货优先级
                Constant::DB_TABLE_AMOUNT, //订单金额
                Constant::DB_TABLE_CURRENCY_CODE, //订单结算的货币
                Constant::DB_TABLE_COUNTRY_CODE, //寄送地址 国家代码
                Constant::DB_TABLE_PURCHASE_DATE, //下单日期(当前国家对应的时间)
                Constant::DB_TABLE_PURCHASE_DATE_ORIGIN, //MWS接口下单时间 2017-05-01T00:01:05Z
                Constant::DB_TABLE_RATE, //汇率
                Constant::DB_TABLE_RATE_AMOUNT, //折算汇率金额
                Constant::DB_TABLE_IS_REPLACEMENT_ORDER, //是否替换订单 0 false | 1 true
                Constant::DB_TABLE_IS_PREMIUM_ORDER, //是否重要订单 0 false | 1 true
                Constant::DB_TABLE_SHIPMENT_SERVICE_LEVEL_CATEGORY, //装运服务等级类别
                Constant::DB_TABLE_LATEST_SHIP_DATE, //最新发货日期
                Constant::DB_TABLE_EARLIEST_SHIP_DATE, //最早的发货日期
                Constant::DB_TABLE_SALES_CHANNEL, //销售渠道
                Constant::DB_TABLE_IS_BUSINESS_ORDER, //是否B2B订单 0:否;1是
                Constant::DB_TABLE_FULFILLMENT_CHANNEL, //发货渠道
                Constant::DB_TABLE_PAYMENT_METHOD, //支付方式
                Constant::DB_TABLE_IS_HAND, //是否手工单 0:否;1是
                Constant::DB_TABLE_ORDER_TYPE, //订单类型
                Constant::DB_TABLE_LAST_UPDATE_DATE, //订单总表更新时间
                Constant::DB_TABLE_MODFIY_AT_TIME, //订单item更新时间
                Constant::DB_TABLE_ORDER_ITEM_ID, //订单item id
                Constant::DB_TABLE_ASIN, //asin
                Constant::DB_TABLE_SKU . ' as seller_sku', //产品店铺sku
                Constant::DB_TABLE_SKU, //产品店铺sku
                Constant::DB_TABLE_LISITING_PRICE, //sku的售价
                Constant::DB_TABLE_PROMOTION_DISCOUNT_AMOUNT, //促销所产生的折扣金额
                Constant::DB_TABLE_TTEM_PRICE_AMOUNT, //订单中sku的金额
                Constant::DB_TABLE_QUANTITY_ORDERED, //订单中的sku件数
                Constant::DB_TABLE_QUANTITY_SHIPPED, //订单中sku发货的件数
                Constant::DB_TABLE_IS_GIFT, //是否赠品 0 false | 1 true
                Constant::DB_TABLE_SERIAL_NUMBER_REQUIRED, //是否赠品 0 false | 1 true
                Constant::DB_TABLE_IS_TRANSPARENCY, //是否赠品 0 false | 1 true
                Constant::DB_TABLE_IS_PRIME, //是否会员 0:否;1是
                Constant::DB_TABLE_BUYER_EMAIL, //买家邮箱
                Constant::DB_TABLE_BUYER_NAME, //买家
                Constant::DB_TABLE_SHIPPING_ADDRESS_NAME, //买家详细地址
                Constant::DB_TABLE_STATE_OR_REGION, //收件地址 州/省
                Constant::DB_TABLE_CITY, //城市
                Constant::DB_TABLE_POSTAL_CODE, //邮编
                Constant::DB_TABLE_ADDRESS_LINE_1, //详细地址
                Constant::DB_TABLE_ADDRESS_LINE_2, //详细地址
                Constant::DB_TABLE_ADDRESS_LINE_3, //详细地址
            ];
            $dataStructure = 'list';
            $flatten = true;
            $orderCountry = '';

            $_nowTime = microtime(true);

            foreach ($countryData as $country) {
                $orderCountry = strtoupper($country);
                $field = Constant::DB_TABLE_PURCHASE_DATE;
                $data = Constant::PARAMETER_ARRAY_DEFAULT;
                $dataType = 'datetime';
                $dateFormat = 'Y-m-d H:i:s';
                $time = Constant::PARAMETER_STRING_DEFAULT;
                $glue = Constant::PARAMETER_STRING_DEFAULT;
                $isAllowEmpty = true;
                $default = Constant::PARAMETER_STRING_DEFAULT;
                $callback = Constant::PARAMETER_ARRAY_DEFAULT;
                $only = Constant::PARAMETER_ARRAY_DEFAULT;

                $parameters = [$field, $default, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only];
                $handleData = [
                    Constant::DB_TABLE_PURCHASE_DATE => FunctionHelper::getExePlanHandleData(...$parameters), //下单时间
                ];

                $joinData = [];
                $with = [];
                $unset = ['shop_asin', 'shop_info'];
                $exePlan = FunctionHelper::getExePlan('default_connection_1', null, 'AmazonOrderItem', (AmazonOrderItem::$tablePrefix . '_' . $country), $select, [Constant::DB_TABLE_AMAZON_ORDER_ID => $order], [], null, null, false, [], false, $joinData, [], $handleData, $unset);

                $itemHandleDataCallback = [
                    Constant::DB_TABLE_TTEM_PRICE_AMOUNT => function($item) {
                        return floatval(data_get($item, Constant::DB_TABLE_TTEM_PRICE_AMOUNT, 0));
                    },
                    Constant::DB_TABLE_PROMOTION_DISCOUNT_AMOUNT => function($item) {
                        return floatval(data_get($item, Constant::DB_TABLE_PROMOTION_DISCOUNT_AMOUNT, 0));
                    },
                    Constant::DB_TABLE_AMOUNT => function($item) {
                        $amount = data_get($item, Constant::DB_TABLE_TTEM_PRICE_AMOUNT, 0) - data_get($item, Constant::DB_TABLE_PROMOTION_DISCOUNT_AMOUNT, 0);
                        return number_format($amount, 2, '.', '') + 0;
                    },
                    'shop_asin' => function($item) use($orderCountry) {
                        $sku = data_get($item, Constant::DB_TABLE_SKU, '');
                        $asin = data_get($item, Constant::DB_TABLE_ASIN, '');
                        $product = ShopAsin::select([
                                    Constant::DB_TABLE_PRIMARY . ' as ' . Constant::VARIANT_ID, //平台订单 item 主键id
                                    Constant::DB_TABLE_ASIN,
                                    Constant::DB_TABLE_COUNTRY,
                                    Constant::FILE_TITLE,
                                    Constant::DB_TABLE_IMG,
                                    'shop_sku',
                                ])->where(['asin' => $asin, 'shop_sku' => $sku, Constant::DB_TABLE_COUNTRY => $orderCountry])
                                ->where(Constant::FILE_TITLE, '!=', '')
                                ->where(Constant::DB_TABLE_STATUS, '<=', 1)
                                ->orderBy(Constant::DB_TABLE_MODFIY_AT_TIME, 'DESC')
                                ->orderBy(Constant::DB_TABLE_PRIMARY, 'DESC')
                                ->first();
                        return $product ? $product->toArray() : [];
                    },
                    'shop_info' => function($item) use($orderCountry) {
                        $sku = data_get($item, Constant::DB_TABLE_SKU, '');
                        $asin = data_get($item, Constant::DB_TABLE_ASIN, '');
                        $product = ShopInfo::select([
                                    Constant::DB_TABLE_PRIMARY . ' as ' . Constant::DB_TABLE_PRODUCT_ID, //平台产品 主键id
                                ])->where(['asin' => $asin, Constant::DB_TABLE_COUNTRY => $orderCountry, Constant::DB_TABLE_STATUS => 0, 'is_hidden' => 0])
                                ->orderBy(Constant::DB_TABLE_UPDATED_AT, 'DESC')
                                ->orderBy(Constant::DB_TABLE_PRIMARY, 'DESC')
                                ->first();
                        return $product ? $product->toArray() : [];
                    },
                ];
                $dbExecutionPlan = [
                    Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
                    Constant::DB_EXECUTION_PLAN_WITH => $with,
                    Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback, Constant::PARAMETER_ARRAY_DEFAULT),
                ];

                $orderItemData = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);
                if ($orderItemData) {
                    break;
                }
            }

            $xcTime = (number_format(microtime(true) - $_nowTime, 8, '.', '') * 1000) . ' ms';
            static::logs('debug', 'order_pull_time', $order, $country, $orderItemData, $xcTime);//添加系统日志

            if (empty($orderItemData)) {
                return $orderItemData;
            }

            //更新订单数据
            data_set($orderItemData, '*.' . Constant::DB_TABLE_ORDER_COUNTRY, $orderCountry);
        } catch (Exception $exc) {
            $parameters = func_get_args();
            $exceptionData = ExceptionHandler::getMessage($exc);
            data_set($parameters, 'exc', $exceptionData);
            static::logs(Constant::LEVEL_ERROR, 'order_bind', 'get_order_item', '查询销参订单item出错', $parameters);//添加系统日志

            //添加系统异常监控
            $exceptionName = '连接销参数据库异常：';
            $message = data_get($exceptionData, 'message', '');
            $code = data_get($exceptionData, 'exception_code') ? data_get($exceptionData, 'exception_code') : (data_get($exceptionData, 'http_code') ? data_get($exceptionData, 'http_code') : -101);
            $_parameters = [$exceptionName, $message, $code, data_get($exceptionData, 'file'), data_get($exceptionData, 'line'), $exceptionData];
            MonitorServiceManager::handle('Ali', 'Ding', 'report', $_parameters);
        }

        return $orderItemData;
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
    public static function getOrder($storeId = 1, $parameters = [], $orderItemData = null) {

        $storeId = static::castToString($storeId);

        static::setConf($storeId);

        $orderItemData = $orderItemData ? $orderItemData : static::getOrderItem($storeId, $parameters);

        if (empty($orderItemData)) {
            return $orderItemData;
        }

        //组装 订单 数据
        $data = current($orderItemData);
        $_orderItemData = collect($orderItemData);
        $shippedItem = $_orderItemData->firstWhere(Constant::DB_TABLE_ORDER_STATUS, '=','Shipped');
        if (!empty($shippedItem)) {
            $data = $shippedItem;
        }

        $orderCountry = strtoupper(trim(data_get($data, Constant::DB_TABLE_ORDER_COUNTRY, '')));
        $orderno = trim(data_get($data, Constant::DB_TABLE_AMAZON_ORDER_ID, ''));

        $_orderItemData = collect($orderItemData);
        $total_line_items_price = $_orderItemData->sum(Constant::DB_TABLE_TTEM_PRICE_AMOUNT);
        $total_discounts = $_orderItemData->sum(Constant::DB_TABLE_PROMOTION_DISCOUNT_AMOUNT);
        $sum = floatval($total_line_items_price) - floatval($total_discounts);
        $amount = number_format(floatval($sum), 2, '.', '') + 0; //订单金额
        $uniqueId = static::getOrderUniqueId($storeId, $orderCountry, Constant::PLATFORM_SERVICE_AMAZON, $orderno); //订单 唯一id
        //组装 订单item 数据
        $lineItems = [];
        foreach ($orderItemData as $lineItem) {
            $asin = data_get($lineItem, Constant::DB_TABLE_ASIN) ?? '';
            $sku = data_get($lineItem, Constant::DB_TABLE_SKU) ?? '';
            $quantity = data_get($lineItem, Constant::DB_TABLE_QUANTITY_ORDERED) ?? 0;

            $productVariantUniqueId = Product::getProductVariantUniqueId($storeId, Constant::PLATFORM_SERVICE_AMAZON, $orderCountry, $asin, $sku); //产品变种 唯一id
            $productUniqueId = Product::getProductUniqueId($storeId, Constant::PLATFORM_SERVICE_AMAZON, $orderCountry, $asin); //产品 唯一id
            $itemId = data_get($lineItem, Constant::DB_TABLE_PRIMARY) ?? 0; //平台订单item 主键id

            $lineItems[] = [
                Constant::DB_TABLE_PRIMARY => $itemId, //平台订单item 主键id
                Constant::DB_TABLE_PLATFORM_ORDER_ITEM_ID => $itemId, //平台订单item 主键id
                Constant::DB_TABLE_UNIQUE_ID => static::getOrderItemUniqueId($storeId, Constant::PLATFORM_SERVICE_AMAZON, $orderCountry, $orderno, $asin, $sku), //订单item 唯一id
                Constant::DB_TABLE_ORDER_UNIQUE_ID => $uniqueId, //订单 唯一id
                Constant::DB_TABLE_PRODUCT_VARIANT_UNIQUE_ID => $productVariantUniqueId, //产品变种 唯一id
                Constant::DB_TABLE_PRODUCT_UNIQUE_ID => $productUniqueId, //产品 唯一id
                Constant::DB_TABLE_PLATFORM_ORDER_ID => $uniqueId, //平台订单 主键id
                Constant::VARIANT_ID => data_get($lineItem, Constant::VARIANT_ID) ?? 0, //平台产品变种 主键id
                'variant_title' => data_get($lineItem, Constant::FILE_TITLE) ?? '', //商品variant title
                Constant::DB_TABLE_PRODUCT_ID => data_get($lineItem, Constant::DB_TABLE_PRODUCT_ID) ?? 0, //平台产品 主键id
                'fulfillable_quantity' => $quantity, //发货的数量
                Constant::DB_TABLE_FULFILLMENT_SERVICE => data_get($lineItem, Constant::DB_TABLE_FULFILLMENT_CHANNEL) ?? '', //物流服务提供者
                Constant::DB_TABLE_FULFILLMENT_STATUS => data_get($lineItem, Constant::DB_TABLE_FULFILLMENT_STATUS) ?? '', //物流状态
                Constant::FILE_TITLE => data_get($lineItem, Constant::FILE_TITLE) ?? '', //商品标题
                'vendor' => '', //商品供应方
                Constant::DB_TABLE_REQUIRES_SHIPPING => 1, //
                'taxable' => data_get($lineItem, 'requires_shipping') ? 1 : 0, //是否需要税
                'gift_card' => data_get($lineItem, Constant::DB_TABLE_IS_GIFT) ? 1 : 0, //是否礼品卡
                'name' => data_get($lineItem, Constant::FILE_TITLE) ?? '', //产品名字
                'variant_inventory_management' => data_get($lineItem, 'variant_inventory_management') ?? '', //库存管理
                'product_exists' => 1, //产品是否存在
                'grams' => FunctionHelper::handleNumber(data_get($lineItem, 'grams')), //重量
                Constant::DB_TABLE_ADMIN_GRAPHQL_API_ID => data_get($lineItem, Constant::DB_TABLE_ADMIN_GRAPHQL_API_ID) ?? '', //
                Constant::DB_TABLE_PRICE => FunctionHelper::handleNumber(data_get($lineItem, Constant::DB_TABLE_LISITING_PRICE)), //单价
                Constant::DB_TABLE_QUANTITY => $quantity, //商品数量
                'total_discount' => FunctionHelper::handleNumber(data_get($lineItem, Constant::DB_TABLE_PROMOTION_DISCOUNT_AMOUNT)), //优惠金额
                Constant::DB_TABLE_AMOUNT => FunctionHelper::handleNumber(data_get($lineItem, Constant::DB_TABLE_AMOUNT)), //item应付总金额
                Constant::DB_TABLE_COUNTRY => $orderCountry,
                Constant::DB_TABLE_ASIN => $asin,
                Constant::DB_TABLE_SKU => $sku, //sku
                Constant::DB_TABLE_IMG => data_get($lineItem, Constant::DB_TABLE_IMG) ?? '', //图片地址
            ];
        }

        $contactEmail = data_get($data, Constant::DB_TABLE_BUYER_EMAIL) ?? '';

        $address2 = array_filter([(data_get($data, Constant::DB_TABLE_ADDRESS_LINE_1) ?? ''), (data_get($data, Constant::DB_TABLE_ADDRESS_LINE_2) ?? ''), (data_get($data, Constant::DB_TABLE_ADDRESS_LINE_3) ?? '')]);
        return [
            [
                Constant::DB_TABLE_PRIMARY => $uniqueId, //平台订单主键id
                Constant::DB_TABLE_UNIQUE_ID => $uniqueId, //订单唯一id
                'number' => $orderno, //订单编号
                'order_number' => $orderno, //订单编号
                Constant::DB_TABLE_NOTE => data_get($data, Constant::DB_TABLE_NOTE) ?? '', //订单说明
                Constant::DB_TABLE_ORDER_TYPE => 1, //订单类型,1:正常购买订单,2积分兑换订单,3秒杀订单,其他值待定义
                Constant::DB_TABLE_TOTAL_PRICE => FunctionHelper::handleNumber($sum), //订单最终支付总金额
                'total_line_items_price' => FunctionHelper::handleNumber($total_line_items_price), //订单商品总金额
                'subtotal_price' => FunctionHelper::handleNumber($sum), //订单优惠后的总金额
                'total_discounts' => FunctionHelper::handleNumber($total_discounts), //优惠总金额
                Constant::DB_TABLE_TOTAL_TAX => FunctionHelper::handleNumber(0), //税总金额
                'total_tip_received' => FunctionHelper::handleNumber(0), //手续费总金额
                'total_shipping' => FunctionHelper::handleNumber(0), //运费总金额
                Constant::DB_TABLE_AMOUNT => FunctionHelper::handleNumber($sum), //订单最终支付总金额
                'total_weight' => FunctionHelper::handleNumber(0),
                'taxes_included' => 0, //是否包含税
                Constant::DB_TABLE_CURRENCY => data_get($data, Constant::DB_TABLE_CURRENCY_CODE) ?? '', //货币
                'financial_status' => data_get($data, Constant::DB_TABLE_ORDER_STATUS) ?? '', //支付状态
                'name' => $orderno, //订单名称
                Constant::DB_TABLE_PHONE => '', //电话号码
                Constant::DB_TABLE_FULFILLMENT_STATUS => data_get($data, Constant::DB_TABLE_SHIP_SERVICE_LEVEL) ?? '', //物流状态
                Constant::DB_TABLE_PRESENTMENT_CURRENCY => data_get($data, Constant::DB_TABLE_CURRENCY_CODE) ?? '', //货币
                'contact_email' => $contactEmail, //联系邮箱
                'order_status_url' => '', //订单状态url
                Constant::DB_TABLE_GATEWAY => data_get($data, Constant::DB_TABLE_PAYMENT_METHOD) ?? '', //支付网关
                Constant::DB_TABLE_TEST => 0, //是否测试数据
                Constant::DB_TABLE_COUNTRY => $orderCountry, //订单国家
                Constant::DB_TABLE_ORDER_STATUS => static::getOrderStatus(data_get($data, Constant::DB_TABLE_ORDER_STATUS) ?? ''),
                Constant::DB_TABLE_ORDER_AT => data_get($data, Constant::DB_TABLE_PURCHASE_DATE) ?? '', //订单创建时间
                Constant::DB_TABLE_ORDER_NO => $orderno,
                Constant::DB_TABLE_CREATED_AT => data_get($data, Constant::DB_TABLE_PURCHASE_DATE) ?? '',
                Constant::DB_TABLE_UPDATED_AT => data_get($data, Constant::DB_TABLE_MODFIY_AT_TIME) ?? '',
                'line_items' => $lineItems,
                'shipping_address' => [
                    Constant::DB_TABLE_ORDER_UNIQUE_ID => $uniqueId, //订单唯一id
                    Constant::DB_TABLE_PLATFORM_ORDER_ID => $uniqueId, //平台订单主键id
                    Constant::DB_TABLE_EMAIL => $contactEmail, //用户邮箱
                    Constant::DB_TABLE_PLATFORM_CUSTOMER_ID => data_get($data, Constant::CUSTOMER . Constant::LINKER . Constant::DB_TABLE_PRIMARY) ?? 0, //平台会员id
                    Constant::DB_TABLE_ADDRESS1 => data_get($data, Constant::DB_TABLE_SHIPPING_ADDRESS_NAME) ?? '', //地址
                    Constant::DB_TABLE_ADDRESS2 => implode(' ', $address2), //可选地址
                    Constant::DB_TABLE_PHONE => data_get($data, Constant::DB_TABLE_PHONE) ?? '', //电话
                    Constant::DB_TABLE_CITY => data_get($data, Constant::DB_TABLE_CITY) ?? '', //城市
                    Constant::DB_TABLE_ZIP => data_get($data, Constant::DB_TABLE_POSTAL_CODE) ?? '', //邮编
                    Constant::DB_TABLE_PROVINCE => data_get($data, Constant::DB_TABLE_STATE_OR_REGION) ?? '', //省份
                    Constant::DB_TABLE_PROVINCE_CODE => data_get($data, Constant::DB_TABLE_STATE_OR_REGION) ?? '', //省份编码
                    Constant::DB_TABLE_COUNTRY => $orderCountry, //国家
                    Constant::DB_TABLE_COUNTRY_CODE => $orderCountry, //国家编码
                    Constant::DB_TABLE_NAME => data_get($data, Constant::DB_TABLE_BUYER_NAME) ?? '', //名字
                    Constant::DB_TABLE_FIRST_NAME => data_get($data, Constant::DB_TABLE_BUYER_NAME) ?? '', //名字
                    Constant::DB_TABLE_LAST_NAME => data_get($data, Constant::DB_TABLE_LAST_NAME) ?? '', //名字
                    Constant::DB_TABLE_COMPANY => data_get($data, Constant::DB_TABLE_COMPANY) ?? '', //公司
                    Constant::DB_TABLE_LATITUDE => data_get($data, Constant::DB_TABLE_LATITUDE) ?? '', //维度
                    Constant::DB_TABLE_LONGITUDE => data_get($data, Constant::DB_TABLE_LONGITUDE) ?? '', //经度
                ],
            ]
        ];
    }

}
