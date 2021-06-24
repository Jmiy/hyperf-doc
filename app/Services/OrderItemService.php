<?php

/**
 * 订单item服务
 * User: Jmiy
 * Date: 2020-04-17
 * Time: 13:11
 */

namespace App\Services;

use App\Services\Traits\GetDefaultConnectionModel;
use App\Constants\Constant;
use Exception;
use App\Exception\Handler\AppExceptionHandler as ExceptionHandler;
use Carbon\Carbon;
use Hyperf\Utils\Arr;

class OrderItemService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 获取要清空的tags
     * @return array
     */
    public static function getClearTags() {
        return [static::getCacheTags()];
    }

    /**
     * 获取缓存tags
     * @return string
     */
    public static function getCacheTags() {
        return 'orderItemLock';
    }

    /**
     * 释放订单拉取分布式锁
     * @return boolean 
     */
    public static function forceReleaseOrdersLock($country, $orderItemId) {
        //释放分布式锁  如果你想在不尊重当前锁的所有者的情况下释放锁，你可以使用 forceRelease 方法 Cache::lock('foo')->forceRelease();
        $service = static::getNamespaceClass();
        $tag = static::getCacheTags();
        $cacheKey = $tag . ':' . $country . ':' . $orderItemId;
        $handleCacheData = [
            'service' => $service,
            'method' => 'lock',
            'parameters' => [
                $cacheKey
            ],
            'serialHandle' => [
                [
                    'service' => $service,
                    'method' => 'forceRelease',
                    'parameters' => [],
                ]
            ]
        ];
        return static::handleCache($tag, $handleCacheData);
    }

    /**
     * 更新订单item
     * @param int $storeId 商城id
     * @param int $orderId 订单id
     * @param array $orderItem 订单item数据
     * @return array 更新数据
     */
    public static function inputAmazonOrderItem($storeId, $orderId, $orderItem) {

        $platformOrderItemId = data_get($orderItem, Constant::DB_TABLE_PRIMARY, 0); //平台订单item id
        if (empty($platformOrderItemId)) {
            return false;
        }

        $service = static::getNamespaceClass();
        $method = __FUNCTION__;
        $parameters = func_get_args();

        $orderno = data_get($orderItem, Constant::DB_TABLE_AMAZON_ORDER_ID, ''); //亚马逊订单

        try {
            $type = Constant::DB_TABLE_PLATFORM;
            $platform = Constant::PLATFORM_AMAZON;

            $where = [
                Constant::DB_TABLE_ORDER_ID => $orderId,
                Constant::DB_TABLE_PLATFORM_ORDER_ITEM_ID => $platformOrderItemId,
            ];

            $amount = floatval(data_get($orderItem, Constant::DB_TABLE_AMOUNT, 0)); //订单产品金额
            $lisitingPrice = floatval(data_get($orderItem, Constant::DB_TABLE_LISITING_PRICE, '')); //sku的售价
            $promotionDiscountAmount = floatval(data_get($orderItem, Constant::DB_TABLE_PROMOTION_DISCOUNT_AMOUNT, 0)); //促销所产生的折扣金额
            $price = floatval(data_get($orderItem, Constant::DB_TABLE_TTEM_PRICE_AMOUNT, 0)); //订单中sku的金额
            $orderStatus = data_get($orderItem, Constant::DB_TABLE_ORDER_STATUS, 'Pending'); //订单状态 Pending Shipped Canceled

            $nowTime = Carbon::now()->toDateTimeString();
            $orderStatusData = DictService::getListByType(Constant::DB_TABLE_ORDER_STATUS, 'dict_value', 'dict_key'); //订单状态 -1:Matching 0:Pending 1:Shipped 2:Canceled 3:Failure 默认:-1

            $data = [
                Constant::DB_TABLE_TYPE => $type,
                Constant::DB_TABLE_PLATFORM => $platform,
                Constant::DB_TABLE_ORDER_NO => $orderno, //亚马逊订单
                Constant::DB_TABLE_AMOUNT => $amount ? $amount : '0.00', //订单产品金额
                Constant::DB_TABLE_CURRENCY_CODE => data_get($orderItem, Constant::DB_TABLE_CURRENCY_CODE, ''), //交易货币
                Constant::DB_TABLE_ASIN => data_get($orderItem, Constant::DB_TABLE_ASIN, ''), //asin
                Constant::DB_TABLE_SKU => data_get($orderItem, Constant::DB_TABLE_SKU, ''), //产品店铺sku
                Constant::DB_TABLE_LISITING_PRICE => $lisitingPrice ? $lisitingPrice : '0.00', //sku的售价
                Constant::DB_TABLE_PROMOTION_DISCOUNT_AMOUNT => $promotionDiscountAmount ? $promotionDiscountAmount : '0.00', //促销所产生的折扣金额
                Constant::DB_TABLE_PRICE => $price ? $price : '0.00', //订单中sku的金额
                Constant::DB_TABLE_QUANTITY_ORDERED => data_get($orderItem, Constant::DB_TABLE_QUANTITY_ORDERED, 0), //订单中的sku件数
                Constant::DB_TABLE_QUANTITY_SHIPPED => data_get($orderItem, Constant::DB_TABLE_QUANTITY_SHIPPED, ''), //订单中sku发货的件数
                Constant::DB_TABLE_IS_GIFT => data_get($orderItem, Constant::DB_TABLE_IS_GIFT, 0), //是否赠品 0 false | 1 true
                Constant::DB_TABLE_SERIAL_NUMBER_REQUIRED => data_get($orderItem, Constant::DB_TABLE_SERIAL_NUMBER_REQUIRED, 0), //是否赠品 0 false | 1 true
                Constant::DB_TABLE_IS_TRANSPARENCY => data_get($orderItem, Constant::DB_TABLE_IS_TRANSPARENCY, 0), //是否赠品 0 false | 1 true
                Constant::DB_TABLE_PRODUCT_COUNTRY => data_get($orderItem, Constant::DB_TABLE_COUNTRY, ''), //产品国家
                Constant::FILE_TITLE => data_get($orderItem, Constant::FILE_TITLE, ''), //产品标题
                Constant::DB_TABLE_IMG => data_get($orderItem, Constant::DB_TABLE_IMG, ''), //产品图片
                Constant::DB_TABLE_PLATFORM_UPDATED_AT => data_get($orderItem, Constant::DB_TABLE_MODFIY_AT_TIME, ''), //平台订单item更新时间
                Constant::DB_TABLE_ORDER_AT => Carbon::parse(data_get($orderItem, Constant::DB_TABLE_PURCHASE_DATE, $nowTime))->toDateTimeString(), //订单时间
                Constant::DB_TABLE_ORDER_STATUS => data_get($orderStatusData, $orderStatus, Constant::ORDER_STATUS_DEFAULT), //订单状态 -1:Matching 0:Pending 1:Shipped 2:Canceled 3:Failure 默认:-1
                Constant::DB_TABLE_ORDER_COUNTRY => data_get($orderItem, Constant::DB_TABLE_ORDER_COUNTRY, ''), //订单国家
                'auth_id' => data_get($orderItem, 'auth_id', 0), //对应108.auth_info的id
            ];

            return static::updateOrCreate($storeId, $where, $data);
        } catch (Exception $exc) {
            data_set($parameters, 'exc', ExceptionHandler::getMessage($exc));
            LogService::addSystemLog('error', $method, $orderno, $service, $parameters); //添加系统日志
            return false;
        }
    }

    public static function pullAmazonOrderItem($storeId, $orderId, $orderItemData) {

        $service = static::getNamespaceClass();
        $method = __FUNCTION__;
        $parameters = func_get_args();

        $rs = [];
        $exeRs = true;
        try {

            //删除无效的 订单item
            $orderItemIds = collect($orderItemData)->pluck(Constant::DB_TABLE_PRIMARY);
            static::getModel($storeId)->where(Constant::DB_TABLE_ORDER_ID, $orderId)->whereNotIn(Constant::DB_TABLE_PLATFORM_ORDER_ITEM_ID, $orderItemIds)->delete();

            foreach ($orderItemData as $key => $orderItem) {
                //更新订单数据
                $_orderItem = static::inputAmazonOrderItem($storeId, $orderId, $orderItem);

                $orderCountry = data_get($orderItem, Constant::DB_TABLE_ORDER_COUNTRY, ''); //订单国家
                $platformOrderItemId = data_get($orderItem, Constant::DB_TABLE_PRIMARY, 0); //平台订单item id
                $_key = implode('_', ['order_item', $orderCountry, $platformOrderItemId]);
                $rs[$_key] = $_orderItem;

                if ($_orderItem === false) {
                    $exeRs = $_orderItem;
                    break;
                }

                $orderItemId = data_get($_orderItem, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT); //本系统订单item id
                data_set($orderItemData, $key . '.local_order_item_id', $orderItemId);
            }

//            if ($exeRs === true) {
//                return OrderAddressService::pullAmazonOrderAddress($storeId, $orderId, $orderItemData);
//            }
        } catch (Exception $exc) {
            data_set($parameters, 'exc', ExceptionHandler::getMessage($exc));
            LogService::addSystemLog('error', 'amazon_order_item_pull', $method, $service, $parameters); //添加系统日志
            $exeRs = false;
        }

        unset($orderItemData);

        return [
            'exeRs' => $exeRs,
            'rs' => $rs,
        ];
    }

}
