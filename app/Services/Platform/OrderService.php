<?php

namespace App\Services\Platform;

use App\Services\BaseService;
use App\Services\CustomerService;
use App\Services\DictService;
use App\Services\OrderWarrantyService;
use App\Services\Store\PlatformServiceManager;
use App\Constants\Constant;
use App\Utils\FunctionHelper;
use App\Utils\Response;
use Hyperf\DbConnection\Db as DB;
use App\Services\Traits\Order;
use Hyperf\Utils\Arr;
use App\Services\Monitor\MonitorServiceManager;
use App\Services\MetafieldService;
use App\Services\Traits\GetDefaultConnectionModel;
use App\Services\OrdersService;
use App\Services\OrderReviewService;

class OrderService extends BaseService {

    use GetDefaultConnectionModel,
        Order;

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'PlatformOrder';
    }

    /**
     * 订单发货
     * @param int $storeId 官网id
     * @param string $platform 平台标识
     * @param array $data 回调数据
     * @return bool
     */
    public static function noticeDelivery($storeId, $platform, $data) {
        return static::handle($storeId, $platform, $data);
    }

    /**
     * 订单付款
     * @param int $storeId 官网id
     * @param string $platform 平台标识
     * @param array $data 回调数据
     * @return bool
     */
    public static function noticePayment($storeId, $platform, $data) {
        return static::handle($storeId, $platform, $data);
    }

    /**
     * 订单删除
     * @param int $storeId 官网id
     * @param string $platform 平台标识
     * @param array $data 回调数据
     * @return bool
     */
    public static function noticeDelete($storeId, $platform, $data) {
        return static::handle($storeId, $platform, $data);
    }

    /**
     * 订单取消
     * @param int $storeId 官网id
     * @param string $platform 平台标识
     * @param array $data 回调数据
     * @return bool
     */
    public static function noticeCancel($storeId, $platform, $data) {
        return static::handle($storeId, $platform, $data);
    }

    /**
     * 订单更新(订单发货|付款|取消|删除|退款事件都会触发更新事件的回调)
     * @param int $storeId 官网id
     * @param string $platform 平台标识
     * @param array $data 回调数据
     * @return bool
     */
    public static function noticeUpdate($storeId, $platform, $data) {
        return static::handle($storeId, $platform, $data);
    }

    /**
     * 订单关联会员
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据
     * @param int $orderId 订单id
     * @param int $orderType 订单类型
     * @return boolean
     */
    public static function relatedCustomer($storeId, $platform, $data, $orderId, $orderType = 1) {

        if (empty($orderId)) {
            return false;
        }

        $customerSource = data_get($data, Constant::CUSTOMER_SOUTCE, -1); //30014
        $customerData = PlatformServiceManager::handle($platform, 'Order', 'getCustomer', [$storeId, $platform, $data]);
        if (empty($customerData)) {
            return false;
        }
        CustomerService::handle($storeId, $platform, [$customerData], $customerSource);
        $customerId = 0;
        $account = data_get($customerData, Constant::DB_TABLE_EMAIL, '');
        if ($account) {
            $where = [
                Constant::DB_TABLE_STORE_ID => $storeId,
                Constant::DB_TABLE_ACCOUNT => $account,
            ];
            $customer = CustomerService::existsOrFirst($storeId, '', $where, true, [Constant::DB_TABLE_CUSTOMER_PRIMARY]);
            $customerId = data_get($customer, Constant::DB_TABLE_CUSTOMER_PRIMARY, $customerId);
        }

        if ($customerId) {
            $updateData = [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId];
            static::updateOrCreate($storeId, [Constant::DB_TABLE_PRIMARY => $orderId], $updateData, '', FunctionHelper::getDbBeforeHandle([], [], [], array_keys($updateData)));
        }

        //处理订单延保时间
        static::handleWarrantyAt($storeId, $orderId, $customerId, [Constant::DB_TABLE_ORDER_AT => Constant::DB_TABLE_ORDER_AT]);

        //vt,mpow积分兑换订单不延保
        if (in_array($storeId, [1, 2]) && in_array($orderType, [2])) {
            return true;
        }

        //处理订单延保
        static::handleOrderWarranty($storeId, $orderId, $customerId);

        return true;
    }

    /**
     * 记录订单相关数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据
     * @return bool
     */
    public static function handle($storeId, $platform, $data) {

        if (empty($data)) {
            return false;
        }

        $orderRecord = PlatformServiceManager::handle($platform, 'Order', 'getOrderData', [$storeId, $platform, $data]);
        if (empty($orderRecord)) {
            return false;
        }

        $uniqueId = data_get($orderRecord, Constant::DB_TABLE_UNIQUE_ID, 0);
        if (empty($uniqueId)) {
            return false;
        }

        //订单数据
        $where = [
            Constant::DB_TABLE_UNIQUE_ID => $uniqueId, //订单唯一id
        ];
        $updateHandle = [
            function ($instance) use($orderRecord) {
                return data_get($instance, 'platform_updated_at', '') < data_get($orderRecord, 'platform_updated_at', '');
            }
        ];
        $orderData = static::updateOrCreate($storeId, $where, $orderRecord, '', FunctionHelper::getDbBeforeHandle($updateHandle, [], [], array_keys($orderRecord)));

        OrderClientDetailService::handle($storeId, $platform, $data);

        //订单 item 数据
        OrderItemService::handle($storeId, $platform, $data);

        //订单收件地址数据
        OrderShippingAddressService::handle($storeId, $platform, $data);

        //订单发票地址数据
        OrderBillingAddressService::handle($storeId, $platform, $data);

        //物流数据
        FulfillmentService::handle($storeId, $platform, $data);

        //记录退款相关数据
        RefundService::handle($storeId, $platform, $data);

        /*         * *******订单关联账户****************** */
        $orderId = data_get($orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_PRIMARY, 0); //订单id
        $orderType = data_get($orderRecord, Constant::DB_TABLE_ORDER_TYPE, 1); //订单类型

        if ($platform == Constant::PLATFORM_SERVICE_SHOPIFY) {
            $service = static::getNamespaceClass();
            $method = 'relatedCustomer'; //交易拉取
            $parameters = [$storeId, $platform, $data, $orderId, $orderType];
            FunctionHelper::laterQueue(1, FunctionHelper::getJobData($service, $method, $parameters), '', '{pull-platform-order}');

            $orderNo = data_get($orderRecord, Constant::DB_TABLE_ORDER_NO, Constant::PARAMETER_STRING_DEFAULT);
            if (empty($orderNo)) {//如果订单号为空，就发出钉钉预警
                $exceptionName = $platform . ' 订单号为空：';
                $messageData = [('store: ' . $storeId . ' email:' . data_get($orderRecord, Constant::DB_TABLE_EMAIL, Constant::PARAMETER_STRING_DEFAULT) . ' 订单号：' . $orderNo)];
                $message = implode(',', $messageData);
                $parameters = [$exceptionName, $message, ''];
                MonitorServiceManager::handle('Ali', 'Ding', 'report', $parameters);
            }
        }

        return $orderData;
    }

    /**
     * 拉取订单
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $parameters 拉取平台订单参数
     * @return type
     */
    public static function handlePull($storeId, $platform, $parameters) {

        $orderData = PlatformServiceManager::handle($platform, 'Order', 'getOrder', $parameters);

        if ($orderData === null) {
            return Response::getDefaultResponseData(0, 'pull data failure', []);
        }

        if (empty($orderData)) {
            unset($orderData);
            return Response::getDefaultResponseData(0, 'data is empty', []);
        }

        data_set($orderData, '*' . Constant::LINKER . Constant::CUSTOMER_SOUTCE, data_get($parameters, ('1' . Constant::LINKER . Constant::CUSTOMER_SOUTCE), 30014), false);
        foreach ($orderData as $data) {
            static::handle($storeId, $platform, $data);
        }

        if ($platform == Constant::PLATFORM_SERVICE_SHOPIFY) {
            $service = TransactionService::getNamespaceClass();
            $method = 'handlePull'; //交易拉取
            $time = 1;
            foreach ($orderData as $data) {

                $orderId = data_get($data, Constant::DB_TABLE_PRIMARY) ?? 0; //订单ID
                $where = [
                    'platform_order_id' => $orderId,
                ];
                $isExists = TransactionService::existsOrFirst($storeId, '', $where);
                if ($isExists) {
                    continue;
                }

                $parameters = [$storeId, $platform, $orderId];
                FunctionHelper::laterQueue($time, FunctionHelper::getJobData($service, $method, $parameters), '', '{pull-platform-order}');
                $time += 2;
            }
        }

        return Response::getDefaultResponseData(1, '', $orderData);
    }

    /**
     * 获取订单延保详情数据兼容亚马逊
     * @param int $storeId 品牌商店id
     * @param int $orderId 订单id
     * @return array 订单延保详情
     */
    public static function getOrderWarrantyDetails($storeId, $orderId) {

        $select = [
            Constant::DB_TABLE_UNIQUE_ID, //平台订单唯一id
            Constant::DB_TABLE_PRIMARY, //订单id
            Constant::DB_TABLE_ORDER_NO . ' as ' . Constant::DB_TABLE_AMAZON_ORDER_ID, //订单no
            Constant::DB_TABLE_ORDER_STATUS, //订单状态 -1:Matching 0:Pending 1:Shipped 2:Canceled 3:Failure 4:partially_refunded 5:partially_paid 6:authorized
            DB::raw("''" . ' as ' . Constant::DB_TABLE_SHIP_SERVICE_LEVEL), //发货优先级
            Constant::DB_TABLE_AMOUNT, //订单金额
            Constant::DB_TABLE_CURRENCY . ' as ' . Constant::DB_TABLE_CURRENCY_CODE, //交易货币
            Constant::DB_TABLE_COUNTRY . ' as ' . Constant::DB_TABLE_COUNTRY_CODE, //寄送地址 国家代码
            Constant::DB_TABLE_COUNTRY, //订单国家
            Constant::DB_TABLE_PLATFORM_CREATED_AT . ' as ' . Constant::DB_TABLE_PURCHASE_DATE, //下单日期(当前国家对应的时间)
        ];

        $where = [
            Constant::DB_TABLE_PRIMARY => $orderId,
        ];

        $orderStatusData = DictService::getListByType(Constant::DB_TABLE_ORDER_STATUS, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE); //订单状态 -1:Matching 0:Pending 1:Shipped 2:Canceled 3:Failure 默认:-1
        $field = Constant::DB_TABLE_ORDER_STATUS;
        $data = $orderStatusData;
        $dataType = 'string';
        $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
        $time = Constant::PARAMETER_STRING_DEFAULT;
        $glue = Constant::PARAMETER_STRING_DEFAULT;
        $isAllowEmpty = true;
        $default = data_get($orderStatusData, -1, Constant::PARAMETER_STRING_DEFAULT);
        $callback = Constant::PARAMETER_ARRAY_DEFAULT;
        $only = Constant::PARAMETER_ARRAY_DEFAULT;

        $parameters = [$field, $default, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only];
        $handleData = [
            'items.*' . Constant::LINKER . Constant::DB_TABLE_ORDER_STATUS => FunctionHelper::getExePlanHandleData(...$parameters),
            'items.*' . Constant::LINKER . Constant::DB_TABLE_SHIP_SERVICE_LEVEL => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_SHIP_SERVICE_LEVEL, '', Constant::PARAMETER_ARRAY_DEFAULT, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
            'items.*' . Constant::LINKER . Constant::DB_TABLE_PURCHASE_DATE => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_PURCHASE_DATE, '', Constant::PARAMETER_ARRAY_DEFAULT, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
            Constant::DB_TABLE_ORDER_STATUS => FunctionHelper::getExePlanHandleData(...$parameters),
            'items.*' . Constant::LINKER . Constant::DB_TABLE_AMAZON_ORDER_ID => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_AMAZON_ORDER_ID, '', Constant::PARAMETER_ARRAY_DEFAULT, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
            'items.*' . Constant::LINKER . Constant::DB_TABLE_CURRENCY_CODE => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_CURRENCY_CODE, '', Constant::PARAMETER_ARRAY_DEFAULT, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
            'items.*' . Constant::LINKER . Constant::DB_TABLE_COUNTRY_CODE => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_COUNTRY, '', Constant::PARAMETER_ARRAY_DEFAULT, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
            'items.*' . Constant::LINKER . Constant::DB_TABLE_PRODUCT_COUNTRY => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_COUNTRY, '', Constant::PARAMETER_ARRAY_DEFAULT, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
            'items.*' . Constant::LINKER . Constant::DB_TABLE_ORDER_ID => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_PRIMARY, '', Constant::PARAMETER_ARRAY_DEFAULT, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
        ];

        $joinData = Constant::PARAMETER_ARRAY_DEFAULT;

        $itemHandleData = [
            Constant::DB_TABLE_IMG => FunctionHelper::getExePlanHandleData('variant' . Constant::LINKER . 'image' . Constant::LINKER . 'src{or}product' . Constant::LINKER . 'image_src', '', Constant::PARAMETER_ARRAY_DEFAULT, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
        ];
        $itemSelect = [
            Constant::DB_TABLE_UNIQUE_ID, //平台订单item唯一id
            Constant::DB_TABLE_ORDER_UNIQUE_ID, //订单 唯一id
            Constant::DB_TABLE_PRODUCT_VARIANT_UNIQUE_ID, //产品变种 唯一id
            Constant::DB_TABLE_PRODUCT_UNIQUE_ID, //产品 唯一id
            Constant::DB_TABLE_SKU . ' as ' . Constant::DB_TABLE_SELLER_SKU, //产品店铺sku
            Constant::DB_TABLE_PRICE . ' as ' . Constant::DB_TABLE_TTEM_PRICE_AMOUNT, //订单中sku的金额
            'total_discount as ' . Constant::DB_TABLE_PROMOTION_DISCOUNT_AMOUNT, //促销所产生的折扣金额
            DB::raw("''" . ' as ' . Constant::DB_TABLE_ASIN), //asin
            Constant::DB_TABLE_QUANTITY . ' as ' . Constant::DB_TABLE_QUANTITY_ORDERED, //订单中的sku件数
            Constant::DB_TABLE_AMOUNT, //订单产品金额
            Constant::FILE_TITLE,
        ];
        $itemOrders = Constant::PARAMETER_ARRAY_DEFAULT; //[[Constant::DB_TABLE_AMOUNT, Constant::DB_EXECUTION_PLAN_ORDER_DESC]];

        $defaultHandleData = [];

        $imageSelect = [
            Constant::DB_TABLE_UNIQUE_ID, //唯一id
            'src',
        ];
        $imageWith = [
            'image' => FunctionHelper::getExePlan(
                    $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $imageSelect, [], $itemOrders, null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], $defaultHandleData, Constant::PARAMETER_ARRAY_DEFAULT, 'hasOne', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联产品图片
        ];

        $variantSelect = [
            Constant::DB_TABLE_UNIQUE_ID, //唯一id
            Constant::DB_TABLE_PRODUCT_IMAGE_UNIQUE_ID,
        ];

        $productSelect = [
            Constant::DB_TABLE_UNIQUE_ID, //唯一id
            'image_src',
        ];

        $variantWith = [
            'variant' => FunctionHelper::getExePlan(
                    $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $variantSelect, [], $itemOrders, null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, $imageWith, $defaultHandleData, Constant::PARAMETER_ARRAY_DEFAULT, 'hasOne', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联产品变种
            'product' => FunctionHelper::getExePlan(
                    $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $productSelect, [], $itemOrders, null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], $defaultHandleData, Constant::PARAMETER_ARRAY_DEFAULT, 'hasOne', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联产品变种
        ];

        $with = [
            'items' => FunctionHelper::getExePlan(
                    $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $itemSelect, [], $itemOrders, null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, $variantWith, $itemHandleData, [], 'hasMany', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联订单item
        ];
        $unset = Constant::PARAMETER_ARRAY_DEFAULT;
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getModelAlias(), Constant::PARAMETER_STRING_DEFAULT, $select, $where, [], null, null, false, [], false, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
        ];

        $dataStructure = 'one';
        $flatten = false;

        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);
    }

    /**
     * 处理订单延保
     * @param int $storeId 品牌商店id
     * @param int $orderId 订单id
     * @param int $customerId 账号id
     * @return boolean
     */
    public static function handleOrderWarranty($storeId, $orderId, $customerId) {

        $where = [Constant::DB_TABLE_PRIMARY => $orderId];
        $select = [
            Constant::DB_TABLE_UNIQUE_ID,
            Constant::DB_TABLE_ORDER_NO,
            Constant::DB_TABLE_COUNTRY,
            Constant::DB_TABLE_AMOUNT,
            Constant::DB_TABLE_CURRENCY,
            Constant::DB_TABLE_ORDER_AT,
            Constant::DB_TABLE_ORDER_STATUS,
            Constant::WARRANTY_DATE,
            Constant::WARRANTY_DES,
            'warranty_at',
        ];
        $orderData = static::existsOrFirst($storeId, '', $where, true, $select);

        $uniqueId = data_get($orderData, Constant::DB_TABLE_UNIQUE_ID, 0);
        if (empty($uniqueId)) {
            return false;
        }

        //获取账号数据
        $customerWhere = [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId];
        $customerSelect = [Constant::DB_TABLE_ACCOUNT];
        $customerData = CustomerService::existsOrFirst($storeId, '', $customerWhere, true, $customerSelect);

        $amount = data_get($orderData, Constant::DB_TABLE_AMOUNT, 0.00);
        $currencyCode = data_get($orderData, Constant::DB_TABLE_CURRENCY, '');
        $data = [
            Constant::DB_TABLE_ORDER_UNIQUE_ID => $uniqueId,
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_TYPE => Constant::DB_TABLE_PLATFORM,
            Constant::DB_TABLE_PLATFORM => Constant::PLATFORM_SHOPIFY,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId ?? Constant::DEFAULT_CUSTOMER_PRIMARY_VALUE,
            Constant::DB_TABLE_ACCOUNT => data_get($customerData, Constant::DB_TABLE_ACCOUNT, ''), //会员账号
            Constant::DB_TABLE_ORDER_NO => data_get($orderData, Constant::DB_TABLE_ORDER_NO, ''),
            Constant::DB_TABLE_COUNTRY => data_get($orderData, Constant::DB_TABLE_COUNTRY, ''),
            Constant::DB_TABLE_AMOUNT => $amount,
            Constant::DB_TABLE_CURRENCY_CODE => $currencyCode,
            Constant::DB_TABLE_CONTENT => json_encode(static::getOrderWarrantyDetails($storeId, $orderId), JSON_UNESCAPED_UNICODE),
            Constant::DB_TABLE_ORDER_TIME => data_get($orderData, Constant::DB_TABLE_ORDER_AT, ''),
            Constant::DB_TABLE_ORDER_STATUS => data_get($orderData, Constant::DB_TABLE_ORDER_STATUS, 0),
            Constant::WARRANTY_DATE => data_get($orderData, Constant::WARRANTY_DATE, ''),
            Constant::WARRANTY_DES => data_get($orderData, Constant::WARRANTY_DES, ''),
            'warranty_at' => data_get($orderData, 'warranty_at', ''),
            Constant::DB_TABLE_EXT_TYPE => static::getModelAlias(), //关联模型
        ];

        $orderWarrantyWhere = [
            Constant::DB_TABLE_ORDER_UNIQUE_ID => $uniqueId,
                //Constant::DB_TABLE_ORDER_NO => data_get($orderData, Constant::DB_TABLE_ORDER_NO, ''),
        ];
        $orderWarrantyData = OrderWarrantyService::updateOrCreate($storeId, $orderWarrantyWhere, $data, '', FunctionHelper::getDbBeforeHandle([], [], [], array_keys($data)));

        //处理订单延保 积分和经验
        OrderWarrantyService::handleCreditAndExp($storeId, data_get($orderWarrantyData, Constant::RESPONSE_DATA_KEY, -1));

        //推送订单邮件到消息队列
        $orderWarrantyId = data_get($orderWarrantyData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_PRIMARY) ?? 0; //延保订单id
        OrderWarrantyService::pushEmailQueue($storeId, $orderWarrantyId, 0); //推送订单邮件到消息队列

        return true;
    }

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @return array
     */
    public static function getPublicData($params, $order = Constant::PARAMETER_ARRAY_DEFAULT) {

        $where = Constant::PARAMETER_ARRAY_DEFAULT;
        $_where = Constant::PARAMETER_ARRAY_DEFAULT;
        $platform = data_get($params, Constant::DB_TABLE_PLATFORM, 0);
        $customerId = data_get($params, Constant::DB_TABLE_CUSTOMER_PRIMARY, 0);
        $orderType = data_get($params, Constant::DB_TABLE_ORDER_TYPE, 0);
        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, 0);

        if ($platform) {
            $_platform = [];
            $platform = is_array($platform) ? $platform : [$platform];
            foreach ($platform as $v) {
                $_platform[] = FunctionHelper::getUniqueId($v);
            }

            $_where[Constant::DB_TABLE_PLATFORM] = $_platform;
        }

        if ($storeId) {
            $where[Constant::DB_TABLE_STORE_ID] = $storeId;
        }

        if ($customerId) {
            $where[Constant::DB_TABLE_CUSTOMER_PRIMARY] = $customerId;
        }

        if ($orderType) {
            $where[Constant::DB_TABLE_ORDER_TYPE] = $orderType;
        }



        if ($where) {
            $_where[] = $where;
        }

        $order = $order ? $order : [Constant::DB_TABLE_PLATFORM_CREATED_AT, Constant::ORDER_DESC];
        return Arr::collapse([parent::getPublicData($params, $order), [
                        Constant::DB_EXECUTION_PLAN_WHERE => $_where,
        ]]);
    }

    /**
     * 订单创建
     * @param int $storeId 官网id
     * @param int $customerId 会员id
     * @param string $account 账号
     * @param array $variantItems 下单产品id及数量
     * @param string $platform 平台标识
     * @param int $orderType 订单类型
     * @param array $requestData 请求参数
     * @return \App\Services\Store\max|bool
     */
    public static function create($storeId, $customerId, $account, $variantItems, $platform, $orderType, $requestData) {
        $email = $account;
        $phone = data_get($requestData, Constant::DB_TABLE_PHONE, Constant::PARAMETER_STRING_DEFAULT);
        $lineItems = $variantItems;
        $shippingAddress = [
            Constant::DB_TABLE_FIRST_NAME => data_get($requestData, Constant::DB_TABLE_FIRST_NAME, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_LAST_NAME => data_get($requestData, Constant::DB_TABLE_LAST_NAME, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_ADDRESS1 => data_get($requestData, Constant::DB_TABLE_ADDRESS, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_PHONE => $phone,
            Constant::DB_TABLE_CITY => data_get($requestData, Constant::DB_TABLE_CITY, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_PROVINCE => data_get($requestData, Constant::DB_TABLE_PROVINCE, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_COUNTRY => data_get($requestData, Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_ZIP => data_get($requestData, Constant::DB_TABLE_ZIP, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_COMPANY => data_get($requestData, Constant::DB_TABLE_COMPANY, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_ADDRESS2 => data_get($requestData, 'apartment', Constant::PARAMETER_STRING_DEFAULT),
        ];
        $billingAddress = $shippingAddress;
        $tags = [];
        $transactions = [];
        $discountCodes = [];
        $noteAttributes = [];
        $financialStatus = data_get($requestData, 'financial_status', Constant::ORDER_STATUS_PENDING);

        //积分兑换订单类型
        if ($orderType == 2) {
            //100%优惠扣减
            $discountCodes = [[
            "code" => "points exchange",
            "amount" => 100,
            "type" => "percentage"
                ]
            ];

            //创建已支付订单
            $financialStatus = "paid";

            //备注属性,订单类型
            $noteAttributes = [[
            "name" => "order_type",
            "value" => $orderType
                ]
            ];

            //积分兑换订单标签
            $tags = [
                "points_exchange"
            ];
        }

        $data = [$storeId, $email, $phone, $lineItems, $shippingAddress, $billingAddress, $transactions, $financialStatus, $discountCodes, $noteAttributes, $tags];

        $createRet = PlatformServiceManager::handle($platform, 'Order', 'create', $data);
        if (!$createRet['is_success']) {
            return $createRet;
        }

        //处理本地订单
        $orderData = static::handle($storeId, $platform, data_get($createRet, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::ORDER, []));
        data_set($createRet, Constant::ORDER_DATA, $orderData);

        return $createRet;
    }

    public static function getOrderCountWithMetafields($storeId, $customerId, $metafieldData, $where = []) {

        $customizeWhere = [];

        if ($metafieldData) {
            $metafield = [
                Constant::OWNER_RESOURCE => static::getModelAlias()
            ];
            foreach ($metafieldData as $_metafield) {
                $metafields[] = Arr::collapse([$metafield, $_metafield]);
            }
            $customizeWhere = MetafieldService::buildCustomizeWhere($storeId, '', $metafields, 'po.' . Constant::DB_TABLE_UNIQUE_ID);
        }

        $where = Arr::collapse([[
                Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                    ], $where]);
        if ($customizeWhere) {
            $where['{customizeWhere}'] = $customizeWhere;
        }

        return static::getModel($storeId)->from('platform_orders as po')->buildWhere($where)->count();
    }

    /**
     * 获取订单列表
     * @param array $requestData 请求参数
     * @return type
     */
    public static function getOrderList($requestData) {
        data_set($requestData, Constant::DB_TABLE_ORDER_TYPE, 1, false); //订单类型,1:正常购买订单,2积分兑换订单,3秒杀订单,其他值待定义
        $_data = OrderService::getPublicData($requestData);

        $orderType = data_get($requestData, Constant::DB_TABLE_ORDER_TYPE, 1);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, null);
        $order = data_get($_data, Constant::DB_EXECUTION_PLAN_ORDER_BY, data_get($_data, Constant::ORDER, Constant::PARAMETER_ARRAY_DEFAULT));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, Constant::PARAMETER_ARRAY_DEFAULT);
        $limit = data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10);
        $offset = data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0);

        $storeId = data_get($requestData, Constant::DB_TABLE_STORE_ID, 0); //商城id
        $select = [
            Constant::DB_TABLE_PRIMARY,
            Constant::DB_TABLE_ORDER_NO,
            Constant::DB_TABLE_ORDER_AT,
            Constant::DB_TABLE_ORDER_STATUS,
            Constant::DB_TABLE_AMOUNT,
            Constant::WARRANTY_AT,
            Constant::DB_TABLE_CURRENCY,
            'financial_status as order_status_show',
            Constant::DB_TABLE_PRIMARY . ' as ' . Constant::DB_TABLE_EXT_ID,
            DB::raw("'" . OrderService::getModelAlias() . "' as " . Constant::DB_TABLE_EXT_TYPE),
            Constant::DB_TABLE_UNIQUE_ID,
            Constant::DB_TABLE_PLATFORM_CREATED_AT,
        ];

        $orderStatusData = DictService::getListByType(Constant::DB_TABLE_ORDER_STATUS, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE); //订单状态 -1:匹配中 0:未支付 1:已经支付 2:取消 默认:-1
        $currencyData = DictService::getListByType(Constant::DB_TABLE_CURRENCY, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE); //订单状态 -1:匹配中 0:未支付 1:已经支付 2:取消 默认:-1

        $field = Constant::DB_TABLE_ORDER_STATUS;
        $data = $orderStatusData;
        $dataType = Constant::PARAMETER_STRING_DEFAULT;
        $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
        $time = Constant::PARAMETER_STRING_DEFAULT;
        $glue = Constant::PARAMETER_STRING_DEFAULT;
        $isAllowEmpty = true;
        $default = Constant::PARAMETER_STRING_DEFAULT;
        $callback = Constant::PARAMETER_ARRAY_DEFAULT;
        $only = Constant::PARAMETER_ARRAY_DEFAULT;
        $parameters = [$field, $default, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only];

        $handleData = [
            'order_status_show' => FunctionHelper::getExePlanHandleData(...$parameters), //订单状态
            Constant::DB_TABLE_ORDER_AT => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_ORDER_AT, $default, Constant::PARAMETER_ARRAY_DEFAULT, Constant::DB_EXECUTION_PLAN_DATATYPE_DATETIME, 'Y-m-d', $time, $glue, $isAllowEmpty, $callback, $only), //订单时间
            Constant::WARRANTY_AT => FunctionHelper::getExePlanHandleData(Constant::WARRANTY_AT, $default, Constant::PARAMETER_ARRAY_DEFAULT, Constant::DB_EXECUTION_PLAN_DATATYPE_DATETIME, 'Y-m-d', $time, $glue, $isAllowEmpty, $callback, $only), //延保时间
            Constant::CURRENCY_SYMBOL => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_CURRENCY, data_get($currencyData, 'USD', '$'), $currencyData), //货币符号
        ];

        $joinData = [];
        $with = Constant::PARAMETER_ARRAY_DEFAULT;

        $unset = [
            Constant::DB_TABLE_ORDER_STATUS,
            Constant::WARRANTY_AT,
            Constant::DB_TABLE_CURRENCY,
        ];


        if ($orderType == 2) {
            $joinData = Constant::PARAMETER_ARRAY_DEFAULT;

            $callback = [];
            $itemHandleData = [
                Constant::DB_TABLE_IMG => FunctionHelper::getExePlanHandleData('variant' . Constant::LINKER . 'image' . Constant::LINKER . 'src{or}product' . Constant::LINKER . 'image_src{or}' . Constant::DB_TABLE_IMG, '', Constant::PARAMETER_ARRAY_DEFAULT, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
            ];
            $itemSelect = [
                Constant::DB_TABLE_PRIMARY, //订单item主键id
                Constant::DB_TABLE_UNIQUE_ID, //平台订单item唯一id
                Constant::DB_TABLE_ORDER_UNIQUE_ID, //订单 唯一id
                Constant::DB_TABLE_PRODUCT_VARIANT_UNIQUE_ID, //产品变种 唯一id
                Constant::DB_TABLE_PRODUCT_UNIQUE_ID, //产品 唯一id
                Constant::FILE_TITLE,
                'total_discount', //促销所产生的折扣金额
                Constant::DB_TABLE_QUANTITY, //订单中的sku件数
                Constant::DB_TABLE_AMOUNT, //订单产品金额
                Constant::DB_TABLE_CREATED_AT, //订单item创建时间
                Constant::DB_TABLE_IMG,
            ];
            $itemOrders = Constant::PARAMETER_ARRAY_DEFAULT; //[[Constant::DB_TABLE_AMOUNT, Constant::DB_EXECUTION_PLAN_ORDER_DESC]];

            $defaultHandleData = [];

            $imageSelect = [
                Constant::DB_TABLE_UNIQUE_ID, //唯一id
                'src',
            ];
            $imageWith = [
                'image' => FunctionHelper::getExePlan(
                        $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $imageSelect, [], $itemOrders, null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], $defaultHandleData, Constant::PARAMETER_ARRAY_DEFAULT, 'hasOne', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联产品图片
            ];

            $variantSelect = [
                Constant::DB_TABLE_UNIQUE_ID, //唯一id
                Constant::DB_TABLE_PRODUCT_IMAGE_UNIQUE_ID,
            ];

            $productSelect = [
                Constant::DB_TABLE_UNIQUE_ID, //唯一id
                'image_src',
            ];

            $itemFulfillmentSelect = [
                Constant::DB_TABLE_ORDER_ITEM_UNIQUE_ID,
                Constant::DB_TABLE_FULFILLMENT_UNIQUE_ID
            ];

            $fulfillmentSelect = [
                Constant::DB_TABLE_UNIQUE_ID, //唯一id
                'tracking_number',
                'tracking_company',
                'tracking_url',
            ];
            $fulfillmentWith = [
                'fulfillment' => FunctionHelper::getExePlan(
                        $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $fulfillmentSelect, [], $itemOrders, null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], $defaultHandleData, Constant::PARAMETER_ARRAY_DEFAULT, 'hasOne', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联物流数据
            ];


            $metafieldsSelect = [
                Constant::OWNER_RESOURCE,
                Constant::OWNER_ID,
                Constant::NAME_SPACE,
                Constant::DB_TABLE_KEY,
                Constant::DB_TABLE_VALUE,
                Constant::VALUE_TYPE,
            ];
            $orderItemMetafieldWhere = [
                Constant::OWNER_RESOURCE => OrderItemService::getModelAlias(),
            ];
            $variantWith = [
                'variant' => FunctionHelper::getExePlan(
                        $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $variantSelect, [], $itemOrders, null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, $imageWith, $defaultHandleData, Constant::PARAMETER_ARRAY_DEFAULT, 'hasOne', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联产品变种
                'product' => FunctionHelper::getExePlan(
                        $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $productSelect, [], $itemOrders, null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], $defaultHandleData, Constant::PARAMETER_ARRAY_DEFAULT, 'hasOne', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联产品变种
                'item_fulfillment' => FunctionHelper::getExePlan(
                        $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $itemFulfillmentSelect, [], $itemOrders, null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, $fulfillmentWith, $defaultHandleData, Constant::PARAMETER_ARRAY_DEFAULT, 'hasOne', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联产品变种
                Constant::METAFIELDS => FunctionHelper::getExePlan(
                        $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $metafieldsSelect, $orderItemMetafieldWhere, [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], [], Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany', false
                ),
            ];

            $addressSelect = [
                Constant::DB_TABLE_ORDER_UNIQUE_ID,
                Constant::DB_TABLE_NAME, //名字
                Constant::DB_TABLE_ZIP, //邮编
                Constant::DB_TABLE_ADDRESS1, //地址
                Constant::DB_TABLE_ADDRESS2, //可选地址
                Constant::DB_TABLE_CITY, //城市
                Constant::DB_TABLE_PROVINCE, //省份
                Constant::DB_TABLE_COUNTRY, //国家
                Constant::DB_TABLE_PHONE, //电话
                Constant::DB_TABLE_FIRST_NAME, //first name
                Constant::DB_TABLE_LAST_NAME, //last name
            ];

            $orderMetafieldWhere = [
                Constant::OWNER_RESOURCE => OrderService::getModelAlias(),
            ];
            $with = [
                'items' => FunctionHelper::getExePlan(
                        $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $itemSelect, [], $itemOrders, null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, $variantWith, $itemHandleData, [], 'hasMany', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联订单item
                'shipping_address' => FunctionHelper::getExePlan(
                        $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $addressSelect, [], $itemOrders, null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], [], [], 'hasOne', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联订单收件地址
                Constant::METAFIELDS => FunctionHelper::getExePlan(
                        $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $metafieldsSelect, $orderMetafieldWhere, [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], [], Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany', false
                ),
            ];

            $extWhere = [
                Constant::DICT => [
                    Constant::DB_TABLE_DICT_KEY => 'is_show_participation_order',
                ],
                Constant::DICT_STORE => [
                    Constant::DB_TABLE_STORE_DICT_KEY => 'is_show_participation_order',
                ],
            ];
            $orderConfigData = static::getConfig($storeId, Constant::ORDER, $extWhere);
            $isShowParticipationOrder = data_get($orderConfigData, 'is_show_participation_order', 1);
            if ($isShowParticipationOrder == 0) {//如果不展示安慰奖订单，就只展示非安慰奖
                $where[Constant::DB_TABLE_IS_PARTICIPATION_AWARD] = 0; //是否安慰奖 1:是 0:否 默认:0
            }
        }

        $isPage = true;
        $isOnlyGetCount = false;
        $exePlan = FunctionHelper::getExePlan($storeId, null, OrderService::getNamespaceClass(), '', $select, $where, [$order], $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        $itemHandleDataCallback = [
            'order_status_show' => function($item) use($storeId) {
                $clientWarrantData = OrderService::getClientWarrantyData($storeId, $item);
                return data_get($clientWarrantData, 'order_status_show', '');
            },
            Constant::RESPONSE_WARRANTY => function($item) use($storeId) {

                $clientWarrantData = OrderService::getClientWarrantyData($storeId, $item);
                $isShowWarrantyAt = data_get($clientWarrantData, 'isShowWarrantyAt', 0);
                if (!$isShowWarrantyAt) {//如果不显示延保时间，就直接显示延保状态即可
                    return data_get($item, 'order_status_show', '');
                }

                $warranty = data_get($item, Constant::WARRANTY_AT, '');
                if ($storeId == 5) {
                    $warranty = Constant::IKICH_WARRANTY_DATE;
                }
                return $warranty;
            },
            'items.*.' . Constant::DB_TABLE_CREATED_AT => function($item) {
                return data_get($item, Constant::DB_TABLE_PLATFORM_CREATED_AT, '');
            },
        ];
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback, $only),
        ];

        $dataStructure = 'list';
        $flatten = false;
        $data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);

        return Response::getDefaultResponseData(1, null, $data);
    }

    /**
     * 获取订单详情
     * @param int $storeId 品牌商店id
     * @param string $orderno 订单号
     * @param type $type
     * @param type $platform
     * @return type
     */
    public static function getOrderDetails($storeId, $orderno, $country = Constant::PARAMETER_STRING_DEFAULT, $platform = Constant::PLATFORM_SERVICE_AMAZON) {

        if (empty($orderno)) {
            return Constant::PARAMETER_ARRAY_DEFAULT;
        }

        $select = [
            Constant::DB_TABLE_PRIMARY, //订单 主键id
            Constant::DB_TABLE_UNIQUE_ID, //订单 唯一id
            Constant::DB_TABLE_ORDER_NO . ' as ' . Constant::DB_TABLE_AMAZON_ORDER_ID, //订单no
            Constant::DB_TABLE_ORDER_STATUS, //订单状态 Pending Shipped Canceled
            Constant::DB_TABLE_FULFILLMENT_STATUS . ' as ' . Constant::DB_TABLE_SHIP_SERVICE_LEVEL, //发货优先级
            Constant::DB_TABLE_AMOUNT, //订单金额
            Constant::DB_TABLE_CURRENCY . ' as ' . Constant::DB_TABLE_CURRENCY_CODE, //交易货币
            Constant::DB_TABLE_COUNTRY . ' as ' . Constant::DB_TABLE_COUNTRY_CODE, //寄送地址 国家代码
            Constant::DB_TABLE_COUNTRY, //订单国家
            Constant::DB_TABLE_ORDER_AT . ' as ' . Constant::DB_TABLE_PURCHASE_DATE, //下单日期(当前国家对应的时间)
            Constant::DB_TABLE_CURRENCY, //交易货币
        ];

        $where = [
            Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform),
            Constant::DB_TABLE_ORDER_NO => $orderno,
        ];
        if ($country) {
            $where[Constant::DB_TABLE_COUNTRY] = strtoupper($country);
        }

        $orderStatusData = static::getConfig($storeId, Constant::DB_TABLE_ORDER_STATUS); //订单状态 -1:Matching 0:Pending 1:Shipped 2:Canceled 3:Failure 默认:-1
        $field = Constant::DB_TABLE_ORDER_STATUS;
        $data = $orderStatusData;
        $dataType = 'string';
        $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
        $time = Constant::PARAMETER_STRING_DEFAULT;
        $glue = Constant::PARAMETER_STRING_DEFAULT;
        $isAllowEmpty = true;
        $default = data_get($orderStatusData, -1, Constant::PARAMETER_STRING_DEFAULT);
        $callback = Constant::PARAMETER_ARRAY_DEFAULT;
        $only = Constant::PARAMETER_ARRAY_DEFAULT;

        $parameters = [$field, $default, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only];
        $handleData = [
            'items.*' . Constant::LINKER . Constant::DB_TABLE_ORDER_STATUS => FunctionHelper::getExePlanHandleData(...$parameters),
            'items.*' . Constant::LINKER . Constant::DB_TABLE_SHIP_SERVICE_LEVEL => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_SHIP_SERVICE_LEVEL, '', Constant::PARAMETER_ARRAY_DEFAULT, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
            'items.*' . Constant::LINKER . Constant::DB_TABLE_PURCHASE_DATE => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_PURCHASE_DATE, '', Constant::PARAMETER_ARRAY_DEFAULT, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
            'items.*' . Constant::LINKER . Constant::DB_TABLE_COUNTRY_CODE => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_COUNTRY),
            'items.*' . Constant::LINKER . Constant::DB_TABLE_PRODUCT_COUNTRY => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_COUNTRY),
            'items.*' . Constant::LINKER . Constant::DB_TABLE_ORDER_COUNTRY => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_COUNTRY),
            'items.*' . Constant::LINKER . Constant::DB_TABLE_AMAZON_ORDER_ID => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_AMAZON_ORDER_ID),
            'items.*' . Constant::LINKER . Constant::DB_TABLE_CURRENCY_CODE => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_CURRENCY_CODE), //交易货币
            Constant::DB_TABLE_ORDER_STATUS => FunctionHelper::getExePlanHandleData(...$parameters),
        ];

        $joinData = Constant::PARAMETER_ARRAY_DEFAULT;

        $itemHandleData = [];
        $itemSelect = [
            Constant::DB_TABLE_PRIMARY, //订单item 主键id
            Constant::DB_TABLE_UNIQUE_ID, //订单item 唯一id
            Constant::DB_TABLE_ORDER_UNIQUE_ID,
            Constant::DB_TABLE_ORDER_UNIQUE_ID . ' as ' . Constant::DB_TABLE_ORDER_ID, //订单id
            Constant::DB_TABLE_SKU . ' as ' . Constant::DB_TABLE_SELLER_SKU, //产品店铺sku
            Constant::DB_TABLE_PRICE . ' as ' . Constant::DB_TABLE_TTEM_PRICE_AMOUNT, //订单中sku的金额
            'total_discount as ' . Constant::DB_TABLE_PROMOTION_DISCOUNT_AMOUNT, //促销所产生的折扣金额
            Constant::DB_TABLE_ASIN, //asin
            Constant::DB_TABLE_QUANTITY . ' as ' . Constant::DB_TABLE_QUANTITY_ORDERED, //订单中的sku件数
            Constant::DB_TABLE_AMOUNT, //订单产品金额
            Constant::FILE_TITLE,
            Constant::DB_TABLE_IMG,
            Constant::DB_TABLE_SKU,
        ];
        $itemOrders = [[Constant::DB_TABLE_AMOUNT, Constant::DB_EXECUTION_PLAN_ORDER_DESC]];
        $with = [
            'items' => FunctionHelper::getExePlan(
                    Constant::DB_EXECUTION_PLAN_DEFAULT_CONNECTION . $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $itemSelect, [], $itemOrders, null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], $itemHandleData, Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联订单item
        ];
        $unset = Constant::PARAMETER_ARRAY_DEFAULT;
        $exePlan = FunctionHelper::getExePlan(Constant::DB_EXECUTION_PLAN_DEFAULT_CONNECTION . $storeId, null, static::getModelAlias(), Constant::PARAMETER_STRING_DEFAULT, $select, $where, [], null, null, false, [], false, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
        ];

        $dataStructure = 'one';
        $flatten = false;

        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);
    }

    /**
     * 拉取订单
     * @param int $storeId 品牌id
     * @param string $platform
     * @param string $orderNo
     * @param boolean $isUseLocal
     * @param string $country
     * @param int $warrantyId 订单延保 主键id
     * @return array 订单数据
     */
    public static function pullOrder($storeId, $platform = Constant::PLATFORM_SERVICE_AMAZON, $orderNo = '', $isUseLocal = true, $country = '', $warrantyId = 0) {

        $orderData = [];
        if ($isUseLocal) {
            $where = [
                Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform), //平台
                Constant::DB_TABLE_ORDER_NO => $orderNo
            ];
            if ($country) {
                $where[Constant::DB_TABLE_COUNTRY] = $country;
            }
            $orderData = static::existsOrFirst($storeId, '', $where, true, [Constant::DB_TABLE_PRIMARY, Constant::DB_TABLE_UNIQUE_ID, Constant::DB_TABLE_ORDER_NO]);
        }

        if (empty($orderData)) {

            $parameters = [
                Constant::DB_TABLE_ORDER_NO => $orderNo,
            ];
            if ($country) {
                $parameters[Constant::DB_TABLE_ORDER_COUNTRY] = $country;
            }

            $data = static::handlePull($storeId, $platform, [$storeId, $parameters]);

            $orderData = data_get($data, Constant::RESPONSE_DATA_KEY . '.0');
        }

        if (empty($orderData)) {
            return false;
        }

        if ($warrantyId) {//更新延保订单的订单唯一id
            $orderUniqueId = data_get($orderData, Constant::DB_TABLE_UNIQUE_ID, 0); //订单唯一id

            $warrantyWhere = [Constant::DB_TABLE_PRIMARY => $warrantyId];
            $warrantyData = OrderWarrantyService::existsOrFirst($storeId, '', $warrantyWhere, true, [Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::DB_TABLE_STORE_ID, Constant::DB_TABLE_ORDER_UNIQUE_ID]);
            $customerId = data_get($warrantyData, Constant::DB_TABLE_CUSTOMER_PRIMARY, 0);
            if ($customerId) {
                $where = [
                    Constant::DB_TABLE_UNIQUE_ID => $orderUniqueId,
                    Constant::DB_TABLE_CUSTOMER_PRIMARY => 0,
                ];
                $updateData = [
                    Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                    Constant::DB_TABLE_STORE_ID => data_get($warrantyData, Constant::DB_TABLE_STORE_ID, 0),
                ];
                static::update($storeId, $where, $updateData);

                $_orderData = static::existsOrFirst($storeId, '', [Constant::DB_TABLE_UNIQUE_ID => $orderUniqueId], true, [Constant::DB_TABLE_PRIMARY]);

                //处理订单延保时间
                static::handleWarrantyAt($storeId, data_get($_orderData, Constant::DB_TABLE_PRIMARY, 0), $customerId, [Constant::DB_TABLE_ORDER_AT => Constant::DB_TABLE_ORDER_AT]);
            }

            OrderWarrantyService::update($storeId, $warrantyWhere, [Constant::DB_TABLE_ORDER_UNIQUE_ID => $orderUniqueId]); //更新订单唯一id

            OrderReviewService::update($storeId, [Constant::DB_TABLE_ORDER_NO => data_get($orderData, Constant::DB_TABLE_ORDER_NO, '-1')], [Constant::DB_TABLE_ORDER_UNIQUE_ID => $orderUniqueId]); //更新订单唯一id
        }

        return true;
    }

    /**
     * 判断订单是否存在
     * @param int $storeId 品牌id
     * @param string $orderNo 订单号
     * @return int
     */
    public static function isExists($storeId, $orderNo, $platform = Constant::PLATFORM_SERVICE_AMAZON) {

        $service = static::getNamespaceClass();
        $method = 'pullOrder';
        $parameters = [$storeId, $platform, $orderNo];
        FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters), null, '{data-import}'); //把任务加入消息队列

        return OrdersService::isExists($storeId, $orderNo, $platform);
    }

    /**
     * 获取订单信息
     * @param string $orderNo 订单id
     * @param string $country 订单国家
     * @param string $platform 订单平台
     * @param int $storeId 品牌id 默认：1
     * @param boolean $isUseLocal 是否从本地获取订单数据 true：是 false：否  默认：true
     * @param int $warrantyId 订单延保 主键id
     * @return array 订单数据
     */
    public static function getOrderData($orderNo, $country, $platform = Constant::PLATFORM_SERVICE_AMAZON, $storeId = Constant::ORDER_STATUS_SHIPPED_INT, $isUseLocal = true, $warrantyId = 0) {
        $service = static::getNamespaceClass();
        $method = 'pullOrder';
        $parameters = [$storeId, $platform, $orderNo, $isUseLocal, '', $warrantyId];
        FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters), null, '{data-import}'); //把任务加入消息队列

        return OrdersService::getOrderData($orderNo, $country, $platform, $storeId, $isUseLocal);
    }


    /**
     * 获取订单信息
     * @param string $orderNo 订单id
     * @param string $country 订单国家
     * @param string $platform 订单平台
     * @param int $storeId 品牌id 默认：1
     * @param boolean $isUseLocal 是否从本地获取订单数据 true：是 false：否  默认：true
     * @param int $warrantyId 订单延保 主键id
     * @return array 订单数据
     */
    public static function getOrderDataNew($orderNo, $country, $platform = Constant::PLATFORM_SERVICE_AMAZON, $storeId = Constant::ORDER_STATUS_SHIPPED_INT, $isUseLocal = true, $warrantyId = 0) {
        $rs = Response::getDefaultResponseData(39001);

        $service = static::getNamespaceClass();
        $method = 'pullOrder';
        $parameters = [$storeId, $platform, $orderNo, $isUseLocal, '', $warrantyId];
        FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters), null, '{data-import}'); //把任务加入消息队列

        $_parameters = [
            'orderno' => $orderNo,
            'order_country' => $country,
        ];

        //优先获取本系统的订单数据
        $orderData = OrdersService::getOrderDetails($storeId, $orderNo, Constant::DB_TABLE_PLATFORM, $platform);
        if ($orderData && data_get($orderData, 'items', [])) {
            $orderStatus = data_get($orderData, Constant::DB_TABLE_ORDER_STATUS, Constant::PARAMETER_STRING_DEFAULT);
            if (strtolower($orderStatus) == 'shipped') {
                data_set($rs, Constant::RESPONSE_CODE_KEY, 1);
                data_set($rs, Constant::RESPONSE_DATA_KEY, $orderData);
                return $rs;
            }
        }

        $orderData = OrdersService::handlePull($storeId, $platform, $_parameters); //获取亚马逊订单数据
        if (data_get($orderData, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_INT_DEFAULT) == 0) {
            return $rs;
        }

        $orderStatus = data_get($orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_ORDER_STATUS, Constant::PARAMETER_STRING_DEFAULT);
        if (strtolower($orderStatus) == 'shipped') {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 1);
            data_set($rs, Constant::RESPONSE_DATA_KEY, $orderData);
            return $rs;
        }

        if (strtolower($orderStatus) == 'pending') {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 39007);
            data_set($rs, Constant::RESPONSE_DATA_KEY, data_get($orderData, Constant::RESPONSE_DATA_KEY, Constant::PARAMETER_ARRAY_DEFAULT));
        }

        if (strtolower($orderStatus) == 'canceled') {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 39008);
            data_set($rs, Constant::RESPONSE_DATA_KEY, data_get($orderData, Constant::RESPONSE_DATA_KEY, Constant::PARAMETER_ARRAY_DEFAULT));
        }

        return $rs;
    }
}
