<?php

/**
 * 产品服务
 * User: Jmiy
 * Date: 2019-06-20
 * Time: 15:59
 */

namespace App\Services;

use Carbon\Carbon;
use App\Constants\Constant;
use App\Utils\Response;
use App\Services\MetafieldService;
use App\Services\Platform\OrderService;
use App\Services\Platform\ProductVariantService;
use App\Services\Store\PlatformServiceManager;
use Hyperf\Utils\Arr;
use App\Services\Platform\OrderItemService;
use App\Services\Platform\OrderShippingAddressService;
use App\Utils\FunctionHelper;

class PointStoreService extends ProductService {

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'Product';
    }

    public static function getPointProductMetafields($storeId, $ownerId, $key) {
        $where = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::OWNER_RESOURCE => static::getModelAlias(),
            Constant::OWNER_ID => $ownerId,
            Constant::DB_TABLE_KEY => $key,
        ];
        return MetafieldService::getModel($storeId)->buildWhere($where)->select([Constant::DB_TABLE_KEY, Constant::DB_TABLE_VALUE])->pluck(Constant::DB_TABLE_VALUE, Constant::DB_TABLE_KEY);
    }

    /**
     * 获取积分商城产品
     * @param int $storeId 商城id
     * @param array $ids 商品id
     * @param boolean $toArray 是否转化为数组 ture:是 false:否
     * @return array|object
     */
    public static function getPointStoreProducts($storeId, $ids = [], $select = ['*'], $key = null, $value = null) {

        $_where = [];
        if ($ids) {
            $_where[Constant::DB_TABLE_PRIMARY] = $ids;
        }

        return static::getModel($storeId)->select($select)->buildWhere($_where)->get()->pluck($value, $key);
    }

    /**
     * 处理积分兑换订单
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param string $account  账号
     * @param int $customerId  账号id
     * @param int $orderType   订单类型,1:正常购买订单,2积分兑换订单,3秒杀订单,其他值待定义
     * @param int $variantItems 购买产品数据
     * @param array $requestData 请求参数
     * @return array 统一响应结果
     */
    public static function handleOrder($storeId, $platform, $account, $customerId, $orderType, $variantItems, $requestData) {

        //参数检查
        if (empty($variantItems) || empty($account) || empty($customerId) || empty($platform)) {
            return Response::getDefaultResponseData(9999999999);
        }

        //参数检查
        $lineItems = $_lineItems = [];
        $pointStoreProductQuantities = [];
        foreach ($variantItems as $item) {
            if (empty($item[Constant::DB_TABLE_PRIMARY]) || empty($item[Constant::DB_TABLE_PRODUCT_ID]) || empty($item[Constant::VARIANT_ID]) || empty($item[Constant::DB_TABLE_QUANTITY])) {
                return Response::getDefaultResponseData(9999999999);
            }

            $orderItemKey = $item[Constant::DB_TABLE_PRODUCT_ID] . '_' . $item[Constant::DB_TABLE_PRIMARY];
            if (!isset($lineItems[$orderItemKey])) {
                $lineItems[$orderItemKey] = Arr::only($item, [Constant::VARIANT_ID, Constant::DB_TABLE_QUANTITY]);
            } else {
                $lineItems[$orderItemKey][Constant::DB_TABLE_QUANTITY] += $item[Constant::DB_TABLE_QUANTITY];
            }

            if (!isset($_lineItems[$orderItemKey])) {
                $_lineItems[$orderItemKey] = $item;
                $_lineItems[$orderItemKey]['product_variant_primary_id'] = $item[Constant::DB_TABLE_PRIMARY];
            } else {
                $_lineItems[$orderItemKey][Constant::DB_TABLE_QUANTITY] += $item[Constant::DB_TABLE_QUANTITY];
            }

            if (!isset($pointStoreProductQuantities[$item[Constant::DB_TABLE_PRODUCT_ID]])) {
                $pointStoreProductQuantities[$item[Constant::DB_TABLE_PRODUCT_ID]] = $item[Constant::DB_TABLE_QUANTITY];
            } else {
                $pointStoreProductQuantities[$item[Constant::DB_TABLE_PRODUCT_ID]] += $item[Constant::DB_TABLE_QUANTITY];
            }
        }

        $variantIds = array_unique(array_filter(array_column($variantItems, Constant::DB_TABLE_PRIMARY))); //商品变种主键id
        $productVariantData = ProductVariantService::getProducts($storeId, $variantIds, false, [Constant::DB_TABLE_PRIMARY, Constant::DB_TABLE_UNIQUE_ID, Constant::DB_TABLE_PRODUCT_UNIQUE_ID], Constant::DB_TABLE_PRIMARY); //通过商品变种主键id获取商品
        if (count($variantIds) != count($productVariantData)) {//如果商品变种不存在，就提示用户
            return Response::getDefaultResponseData(60006);
        }

        //获取商品
        $pointStoreProductPrimaryIds = array_keys($pointStoreProductQuantities); //商品变种主键id
        $products = static::getPointStoreProducts($storeId, $pointStoreProductPrimaryIds, [
                    Constant::DB_TABLE_PRIMARY,
                    Constant::DB_TABLE_UNIQUE_ID,
                    Constant::DB_TABLE_PRODUCT_STATUS,
                    Constant::DB_TABLE_QTY,
                    Constant::DB_TABLE_CREDIT,
                    Constant::EXPIRE_TIME
                        ], Constant::DB_TABLE_PRIMARY);
        if ($products->isEmpty() || $products->count() != count($pointStoreProductPrimaryIds)) {//商品不存在
            return Response::getDefaultResponseData(60006);
        }

        $exchangeTotalCredit = 0;
        foreach ($pointStoreProductQuantities as $pointStoreProductPrimaryId => $quantity) {
            //商品已下架
            if (data_get($products, $pointStoreProductPrimaryId . Constant::LINKER . Constant::DB_TABLE_PRODUCT_STATUS, 0) != 1) {
                return Response::getDefaultResponseData(63000);
            }

            //库存不足
            if (data_get($products, $pointStoreProductPrimaryId . Constant::LINKER . Constant::DB_TABLE_QTY, 0) < $quantity) {
                return Response::getDefaultResponseData(61109);
            }

            //兑换时间到期
            $expireTime = data_get($products, $pointStoreProductPrimaryId . Constant::LINKER . Constant::EXPIRE_TIME);
            if ($expireTime !== null && $expireTime < Carbon::now()->toDateTimeString()) {
                return Response::getDefaultResponseData(30011);
            }

            //兑换总积分累加
            $productCredit = data_get($products, $pointStoreProductPrimaryId . Constant::LINKER . Constant::DB_TABLE_CREDIT, 0);
            $exchangeTotalCredit += ($productCredit * $quantity);

            foreach ($productVariantData as $productVariantPrimaryId => $variantData) {
                $orderItemKey = $pointStoreProductPrimaryId . '_' . $productVariantPrimaryId;
                if (isset($_lineItems[$orderItemKey])) {
                    $_lineItems[$orderItemKey][Constant::DB_TABLE_CREDIT] = $productCredit * $_lineItems[$orderItemKey][Constant::DB_TABLE_QUANTITY];
                }
            }
        }

        //检查积分
        $customerInfo = CustomerInfoService::existsOrFirst($storeId, '', [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId], true, [Constant::DB_TABLE_CREDIT]);
        $customerCredit = data_get($customerInfo, Constant::DB_TABLE_CREDIT, Constant::PARAMETER_INT_DEFAULT);
        if ($customerCredit < $exchangeTotalCredit) {//积分不足
            return Response::getDefaultResponseData(20001);
        }

        $isCanBuy = static::isCanBuy($storeId, $customerId, $orderType, $pointStoreProductQuantities, $productVariantData, $_lineItems, $products, $requestData);
        if (data_get($isCanBuy, Constant::RESPONSE_CODE_KEY, 0) != 1) {
            return $isCanBuy;
        }

        $createRet = OrderService::create($storeId, $customerId, $account, array_values($lineItems), $platform, $orderType, $requestData);
        if (!$createRet['is_success']) {//订单创建失败
            foreach ($pointStoreProductQuantities as $pointStoreProductPrimaryId => $quantity) {

                $couponItem = data_get($isCanBuy, Constant::RESPONSE_DATA_KEY . '.couponData.' . $pointStoreProductPrimaryId, []);
                if (!empty($couponItem)) {
                    if ($customerId && data_get($couponItem, Constant::DB_TABLE_USE_TYPE, Constant::PARAMETER_INT_DEFAULT) == 1) {//如果是独占型，就更新为未占用
                        CouponService::setStatus($storeId, data_get($couponItem, Constant::DB_TABLE_PRIMARY, 0), 1, '', '', ''); //分配优惠劵给用户
                    }
                    return $couponData;
                }
            }

            return Response::getDefaultResponseData(30009, null, $createRet[Constant::RESPONSE_DATA_KEY]);
        }

        $orderId = data_get($createRet, Constant::ORDER_DATA . Constant::LINKER . Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_UNIQUE_ID, 0); //订单唯一id
        //产品类型 0:其他 1:礼品卡 2:coupon 3:实物 5:活动积分
        $actionData = [
            0 => 'creditExchange',
            1 => [
                0 => 'NonPhysicalExchange',
                1 => 'NonPhysicalExchange',
                2 => 'NonPhysicalExchange',
                3 => 'PhysicalProductExchange',
                5 => 'NonPhysicalExchange',
            ],
        ];

        //扣除用户积分
        $orderItemOwnerResource = OrderItemService::getModelAlias();
        $creditLog = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::DB_TABLE_ADD_TYPE => 2,
            Constant::DB_TABLE_EXT_TYPE => $orderItemOwnerResource,
            Constant::DB_TABLE_CONTENT => json_encode(PlatformServiceManager::handle($platform, 'Order', 'getShippingAddress', [$storeId, $platform, data_get($createRet, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::ORDER, [])]), JSON_UNESCAPED_UNICODE),
        ];

        //扣减积分及库存，更新已经兑换数
        foreach ($pointStoreProductQuantities as $pointStoreProductPrimaryId => $quantity) {
            //库存及兑换数量更新
            $data = [
                Constant::DB_TABLE_QTY => $quantity,
                'exchanged_nums' => $quantity,
            ];
            static::handle($storeId, [Constant::DB_TABLE_PRIMARY => $pointStoreProductPrimaryId], $data);
        }

        //添加订单积分属性
        $ownerResource = OrderService::getModelAlias();
        MetafieldService::insert($storeId, $platform, 0, $ownerResource, $orderId, Constant::POINT_STORE_NAME_SPACE, Constant::DB_TABLE_CREDIT, $exchangeTotalCredit);

        //扣减积分及库存，更新已经兑换数
        foreach ($pointStoreProductQuantities as $pointStoreProductPrimaryId => $quantity) {

            //获取产品属性
            $pointProductMetafields = static::getPointProductMetafields($storeId, data_get($products, $pointStoreProductPrimaryId . Constant::LINKER . Constant::DB_TABLE_UNIQUE_ID, 0), [Constant::DB_TABLE_TYPE, 'menu']);
            foreach ($pointProductMetafields as $key => $value) {
                MetafieldService::insert($storeId, $platform, 0, $ownerResource, $orderId, Constant::POINT_STORE_NAME_SPACE, 'product_' . $key, $value);
            }

            foreach ($productVariantData as $productVariantPrimaryId => $variantData) {
                $orderItemKey = $pointStoreProductPrimaryId . '_' . $productVariantPrimaryId;
                if (isset($_lineItems[$orderItemKey])) {

                    $where = [
                        Constant::DB_TABLE_ORDER_UNIQUE_ID => data_get($createRet, Constant::ORDER_DATA . Constant::LINKER . Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_UNIQUE_ID, 0), //订单唯一id
                        Constant::DB_TABLE_PRODUCT_UNIQUE_ID => data_get($variantData, Constant::DB_TABLE_PRODUCT_UNIQUE_ID, 0),
                        Constant::DB_TABLE_PRODUCT_VARIANT_UNIQUE_ID => data_get($variantData, Constant::DB_TABLE_UNIQUE_ID, 0),
                    ];
                    $orderItemData = OrderItemService::existsOrFirst($storeId, '', $where, true, [Constant::DB_TABLE_PRIMARY, Constant::DB_TABLE_UNIQUE_ID]);
                    $orderItemId = data_get($orderItemData, Constant::DB_TABLE_PRIMARY, 0); //订单item id
                    $orderItemUniqueId = data_get($orderItemData, Constant::DB_TABLE_UNIQUE_ID, 0); //订单item 唯一id
                    $orderItemCredit = $_lineItems[$orderItemKey][Constant::DB_TABLE_CREDIT];

                    MetafieldService::insert($storeId, $platform, 0, $orderItemOwnerResource, $orderItemUniqueId, Constant::POINT_STORE_NAME_SPACE, Constant::DB_TABLE_CREDIT, $orderItemCredit);

                    $productType = data_get($pointProductMetafields, Constant::DB_TABLE_TYPE);
                    $creditLog[Constant::DB_TABLE_VALUE] = $orderItemCredit;
                    $creditLog[Constant::DB_TABLE_EXT_ID] = $orderItemId; //订单item id
                    $creditLog[Constant::DB_TABLE_ACTION] = data_get($actionData, $storeId . Constant::LINKER . $productType, data_get($actionData, $storeId . Constant::LINKER . '0', data_get($actionData, $storeId, data_get($actionData, 0, 'creditExchange'))));
                    CreditService::handle($creditLog);

                    $couponData = data_get($isCanBuy, Constant::RESPONSE_DATA_KEY . '.couponData.' . $pointStoreProductPrimaryId, []);
                    data_set($_lineItems, $orderItemKey . Constant::LINKER . Constant::DB_TABLE_PRIMARY, $orderItemId); //订单item id
                    data_set($_lineItems, $orderItemKey . Constant::LINKER . Constant::DB_TABLE_PRODUCT_CODE, data_get($couponData, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_STRING_DEFAULT));
                    data_set($_lineItems, $orderItemKey . Constant::LINKER . Constant::DB_TABLE_PRODUCT_URL, data_get($couponData, Constant::DB_TABLE_AMAZON_URL, Constant::PARAMETER_STRING_DEFAULT));

                    foreach ($pointProductMetafields as $key => $value) {

                        MetafieldService::insert($storeId, $platform, 0, $orderItemOwnerResource, $orderItemUniqueId, Constant::POINT_STORE_NAME_SPACE, 'product_' . $key, $value);
                        data_set($_lineItems, $orderItemKey . Constant::LINKER . 'product_' . $key, $value);

                        if ($key == Constant::DB_TABLE_TYPE) {
                            switch ($value) {
                                case 1://产品类型 0:其他 1:礼品卡 2:coupon 3:实物 5:活动积分
                                    static::createGiftCardOrder($storeId, $platform, $couponData, $orderItemUniqueId, Constant::POINT_STORE_NAME_SPACE, $customerId, $account, $_lineItems[$orderItemKey]);
                                    MetafieldService::insert($storeId, $platform, 0, $orderItemOwnerResource, $orderItemUniqueId, Constant::POINT_STORE_NAME_SPACE, Constant::DB_TABLE_PRODUCT_URL, data_get($couponData, Constant::DB_TABLE_AMAZON_URL, Constant::PARAMETER_STRING_DEFAULT));
                                    break;

                                case 2://产品类型 0:其他 1:礼品卡 2:coupon 3:实物 5:活动积分
                                    static::createCouponOrder($storeId, $platform, $couponData, $orderItemUniqueId, Constant::POINT_STORE_NAME_SPACE, $customerId, $account, $_lineItems[$orderItemKey]);
                                    break;

                                default:
                                    break;
                            }
                        }
                    }
                }
            }
        }

        data_set($createRet, Constant::ORDER_DATA . Constant::LINKER . Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::LINE_ITEMS, array_values($_lineItems));

        return Response::getDefaultResponseData(1, null, $createRet);
    }

    /**
     * 判断是否可以购买
     * @param int $storeId 品牌商店id
     * @param int $customerId 账号id
     * @param int $orderType  订单类型 1:正常购买订单,2积分兑换订单,3秒杀订单,其他值待定义
     * @param array $pointStoreProductQuantities 产品数据（[产品id=>产品数量]）
     * @param array $requestData 请求参数
     * @return array [
     *       Constant::RESPONSE_CODE_KEY => $code,
     *       Constant::RESPONSE_MSG_KEY => $msg,
     *       Constant::RESPONSE_DATA_KEY => $data,
     *   ];
     */
    public static function isCanBuy($storeId, $customerId, $orderType, $pointStoreProductQuantities, $productVariantData, $lineItems, $products, $requestData) {

        $couponData = [];
        switch ($storeId) {
            case 1://mpow

                foreach ($pointStoreProductQuantities as $pointStoreProductPrimaryId => $quantity) {

                    if (empty($pointStoreProductPrimaryId)) {
                        continue;
                    }

                    //获取产品属性
                    $pointProductMetafields = static::getPointProductMetafields($storeId, data_get($products, $pointStoreProductPrimaryId . Constant::LINKER . Constant::DB_TABLE_UNIQUE_ID, 0), [Constant::DB_TABLE_TYPE]);

                    $pointProductType = data_get($pointProductMetafields, Constant::DB_TABLE_TYPE, 0); //产品类型 0:其他 1:礼品卡 2:coupon 3:实物 5:活动积分
                    $metafieldData = [
                        [
                            Constant::DB_TABLE_KEY => Constant::PRODUCT_TYPE,
                            Constant::DB_TABLE_VALUE => $pointProductType,
                        ],
                    ];
                    $where = [
                        [[Constant::DB_TABLE_CREATED_AT, '>=', Carbon::now()->rawFormat('Y-m-01 00:00:00')]],
                    ];
                    $count = OrderService::getOrderCountWithMetafields($storeId, $customerId, $metafieldData, $where);
                    if ($count > 0) {
                        return Response::getDefaultResponseData(30010);
                    }

                    if ($pointProductType == 2) {//如果是 coupon，就判断折扣券是否存在
                        $emailData = [
                            Constant::SUBJECT => '积分兑换Coupon coupon库存不足',
                            Constant::DB_TABLE_CONTENT => '官网：' . $storeId . ' 积分兑换Coupon XXX产品 coupon 库存不足',
                        ];
                        $account = data_get($requestData, Constant::DB_TABLE_ACCOUNT);

                        $_couponData = [];
                        foreach ($productVariantData as $productVariantPrimaryId => $variantData) {
                            $orderItemKey = $pointStoreProductPrimaryId . '_' . $productVariantPrimaryId;
                            if (isset($lineItems[$orderItemKey])) {
                                $productCountry = data_get($lineItems, $orderItemKey . Constant::LINKER . Constant::DB_TABLE_PRODUCT_COUNTRY);
                                $extWhere = [];
                                if ($productCountry && $productCountry != 'all') {
                                    $extWhere = [
                                        'collapseWhere' => [
                                            Constant::DB_TABLE_COUNTRY => $productCountry,
                                        ],
                                    ];
                                }

                                $_couponData = CouponService::getRelatedCoupon($storeId, $pointStoreProductPrimaryId, static::getModelAlias(), $customerId, $emailData, $account, $extWhere);
                                if (empty($_couponData)) {//如果折扣券不足，就提示用户
                                    return Response::getDefaultResponseData(30012);
                                }
                            }
                        }

                        $couponData[$pointStoreProductPrimaryId] = $_couponData;
                    }
                }

                break;

            default:
                break;
        }

        $data = [
            'couponData' => $couponData,
        ];

        return Response::getDefaultResponseData(1, null, $data);
    }

    public static function createGiftCardOrder($storeId, $platform, $couponData, $orderItemId, $nameSpace = Constant::POINT_STORE_NAME_SPACE, $customerId = 0, $account = '', $lineItems = []) {

        $ownerResource = OrderItemService::getModelAlias();

        MetafieldService::insert($storeId, $platform, 0, $ownerResource, $orderItemId, $nameSpace, Constant::DB_TABLE_PRODUCT_COUNTRY, $lineItems[Constant::DB_TABLE_PRODUCT_COUNTRY]);

        //Valid period : 12/31/2021
        //MetafieldService::insert($storeId, $platform, 0, $ownerResource, $orderItemId, $nameSpace, 'product_valid_period', '12/31/2021');
        MetafieldService::insert($storeId, $platform, 0, $ownerResource, $orderItemId, $nameSpace, 'product_valid_period', '');
    }

    public static function createCouponOrder($storeId, $platform, $couponData, $orderItemId, $nameSpace = Constant::POINT_STORE_NAME_SPACE, $customerId = 0, $account = '', $lineItems = []) {

        $ownerResource = OrderItemService::getModelAlias();

        //通过 $pointStoreProductPrimaryId 去 CouponService 获取折扣码
        MetafieldService::insert($storeId, $platform, 0, $ownerResource, $orderItemId, $nameSpace, Constant::DB_TABLE_PRODUCT_CODE, data_get($couponData, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_STRING_DEFAULT));
        MetafieldService::insert($storeId, $platform, 0, $ownerResource, $orderItemId, $nameSpace, Constant::DB_TABLE_PRODUCT_URL, data_get($couponData, Constant::DB_TABLE_AMAZON_URL, Constant::PARAMETER_STRING_DEFAULT));
        MetafieldService::insert($storeId, $platform, 0, $ownerResource, $orderItemId, $nameSpace, Constant::DB_TABLE_PRODUCT_COUNTRY, $lineItems[Constant::DB_TABLE_PRODUCT_COUNTRY]);

        //Valid period : 30 days
        $endAt = data_get($couponData, Constant::DB_TABLE_END_TIME);
        $productValidPeriod = 'Unlimited';
        if ($endAt) {
            $productValidPeriod = abs(ceil((Carbon::parse($endAt)->timestamp - Carbon::now()->timestamp) / (24 * 60 * 60))) . ' days';
        }
        MetafieldService::insert($storeId, $platform, 0, $ownerResource, $orderItemId, $nameSpace, 'product_valid_period', $productValidPeriod);
    }

    /**
     * 处理订单收件地址
     * @param int $storeId 品牌商城id
     * @param array $requestData 请求参数
     * @return array 
     */
    public static function address($storeId, $requestData) {

        $platform = data_get($requestData, Constant::DB_TABLE_PLATFORM, Constant::PLATFORM_SERVICE_AMAZON);
        $customerId = data_get($requestData, Constant::DB_TABLE_CUSTOMER_PRIMARY) ?? 0;
        $account = data_get($requestData, Constant::DB_TABLE_ACCOUNT) ?? '';

        $orderUniqueId = data_get($requestData, Constant::DB_TABLE_ORDER_UNIQUE_ID, 0);
        $firstName = data_get($requestData, Constant::DB_TABLE_FIRST_NAME) ?? Constant::PARAMETER_STRING_DEFAULT;
        $lastName = data_get($requestData, Constant::DB_TABLE_LAST_NAME) ?? Constant::PARAMETER_STRING_DEFAULT;
        $fullName = implode(' ', array_filter([$firstName, $lastName]));
        $data['shipping_address'] = [
            Constant::DB_TABLE_ORDER_UNIQUE_ID => $orderUniqueId, //订单 唯一id
            Constant::DB_TABLE_PLATFORM_ORDER_ID => $orderUniqueId, //平台订单 主键id
            Constant::DB_TABLE_EMAIL => data_get($requestData, Constant::DB_TABLE_ACCOUNT) ?? '', //用户邮箱
            Constant::DB_TABLE_PLATFORM_CUSTOMER_ID => data_get($requestData, Constant::DB_TABLE_CUSTOMER_PRIMARY) ?? 0, //平台会员id
            Constant::DB_TABLE_ADDRESS1 => data_get($requestData, Constant::DB_TABLE_ADDRESS, Constant::PARAMETER_STRING_DEFAULT), //地址
            Constant::DB_TABLE_ADDRESS2 => data_get($requestData, 'apartment', Constant::PARAMETER_STRING_DEFAULT), //可选地址
            Constant::DB_TABLE_PHONE => data_get($requestData, Constant::DB_TABLE_PHONE, Constant::PARAMETER_STRING_DEFAULT), //电话
            Constant::DB_TABLE_CITY => data_get($requestData, Constant::DB_TABLE_CITY, Constant::PARAMETER_STRING_DEFAULT), //城市
            Constant::DB_TABLE_ZIP => data_get($requestData, Constant::DB_TABLE_ZIP, Constant::PARAMETER_STRING_DEFAULT), //邮编
            Constant::DB_TABLE_PROVINCE => data_get($requestData, Constant::DB_TABLE_PROVINCE, Constant::PARAMETER_STRING_DEFAULT), //省份
            Constant::DB_TABLE_COUNTRY => data_get($requestData, Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT), //国家
            Constant::DB_TABLE_NAME => $fullName, //名字
            Constant::DB_TABLE_FIRST_NAME => $firstName, //名字
            Constant::DB_TABLE_LAST_NAME => $lastName, //名字
            Constant::DB_TABLE_COMPANY => data_get($requestData, Constant::DB_TABLE_COMPANY, Constant::PARAMETER_STRING_DEFAULT), //公司
            Constant::DB_TABLE_LATITUDE => data_get($requestData, Constant::DB_TABLE_LATITUDE) ?? '', //维度
            Constant::DB_TABLE_LONGITUDE => data_get($requestData, Constant::DB_TABLE_LONGITUDE) ?? '', //经度
            Constant::DB_TABLE_COUNTRY_CODE => data_get($requestData, Constant::DB_TABLE_COUNTRY_CODE) ?? '', //国家编码
            Constant::DB_TABLE_PROVINCE_CODE => data_get($requestData, Constant::DB_TABLE_PROVINCE_CODE) ?? '', //省份编码
        ];

        //处理订单收件地址
        $address = OrderShippingAddressService::handle($storeId, $platform, $data);

        $ownerResource = OrderService::getModelAlias();
        $where = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform),
            Constant::METAFIELD_ID => 0,
            Constant::OWNER_RESOURCE => $ownerResource,
            Constant::OWNER_ID => $orderUniqueId,
            Constant::NAME_SPACE => Constant::POINT_STORE_NAME_SPACE,
            Constant::DB_TABLE_KEY => [Constant::DB_TABLE_ACT_ID, Constant::DB_TABLE_CREDIT_LOG_ID, Constant::ACTIVITY_WINNING_ID, Constant::DB_TABLE_EXT_TYPE, Constant::DB_TABLE_PLATFORM],
        ];
        $metafieldData = MetafieldService::getModel($storeId)->buildWhere($where)->pluck(Constant::DB_TABLE_VALUE, Constant::DB_TABLE_KEY);
        $actId = data_get($metafieldData, Constant::DB_TABLE_ACT_ID); //活动id
        $creditLogId = data_get($metafieldData, Constant::DB_TABLE_CREDIT_LOG_ID); //积分流水id
        $activityWinningId = data_get($metafieldData, Constant::ACTIVITY_WINNING_ID); //中奖id
        $extType = data_get($metafieldData, Constant::DB_TABLE_EXT_TYPE);

        if ($creditLogId) {//如果有积分流水，就更新积分流水的收件地址
            $creditLog = [
                Constant::DB_TABLE_CONTENT => json_encode(PlatformServiceManager::handle($platform, 'Order', 'getShippingAddress', [$storeId, $platform, $data]), JSON_UNESCAPED_UNICODE),
            ];
            CreditService::update($storeId, [Constant::DB_TABLE_PRIMARY => $creditLogId], $creditLog);
        }

        if ($activityWinningId) {//如果有中奖id，就更新中奖的收件地址
            data_set($requestData, Constant::DB_TABLE_EXT_TYPE, $extType, false);
            data_set($requestData, 'full_name', $fullName, false);
            data_set($requestData, 'street', data_get($requestData, Constant::DB_TABLE_ADDRESS, Constant::PARAMETER_STRING_DEFAULT), false);
            data_set($requestData, 'state', data_get($requestData, Constant::DB_TABLE_PROVINCE, Constant::PARAMETER_STRING_DEFAULT), false); //省份
            data_set($requestData, 'zip_code', data_get($requestData, Constant::DB_TABLE_ZIP, Constant::PARAMETER_STRING_DEFAULT), false); //邮编

            ActivityAddressService::add($storeId, $actId, $customerId, $account, $activityWinningId, $requestData);
        }

        return $address;
    }

}
