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

class OrderAddressService extends BaseService {

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
        return 'orderAddressLock';
    }

    /**
     * 释放订单拉取分布式锁
     * @return boolean 
     */
    public static function forceReleaseOrdersLock($storeId, $orderItemId, $orderId) {
        //释放分布式锁  如果你想在不尊重当前锁的所有者的情况下释放锁，你可以使用 forceRelease 方法 Cache::lock('foo')->forceRelease();
        $service = static::getNamespaceClass();
        $tag = static::getCacheTags();
        $cacheKey = $tag . ':' . $orderId . ':' . $orderItemId;
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
     * @param int $orderItemId 订单item id
     * @param int $orderId 订单id
     * @param array $orderItem 订单item数据
     * @return array 更新数据
     */
    public static function inputAmazonOrderAddress($storeId, $orderItemId, $orderId, $orderItem) {

        if (empty($orderItemId)) {
            return false;
        }

        $service = static::getNamespaceClass();
        $method = __FUNCTION__;
        $parameters = func_get_args();
        $orderno = data_get($orderItem, Constant::DB_TABLE_AMAZON_ORDER_ID, ''); //亚马逊订单
        try {

            //更新订单收件数据
            $where = [
                Constant::DB_TABLE_ORDER_ITEM_ID => $orderItemId,
            ];

            $data = [
                Constant::DB_TABLE_ORDER_ID => $orderId,
                Constant::DB_TABLE_PLATFORM_ORDER_ITEM_ID => data_get($orderItem, Constant::DB_TABLE_PRIMARY, 0),
                Constant::DB_TABLE_ORDER_NO => $orderno, //亚马逊订单
                Constant::DB_TABLE_IS_PRIME => data_get($orderItem, Constant::DB_TABLE_IS_PRIME, 0), //是否会员 0:否;1是
                Constant::DB_TABLE_EMAIL => data_get($orderItem, Constant::DB_TABLE_BUYER_EMAIL, ''), //买家邮箱
                Constant::DB_TABLE_NAME => data_get($orderItem, Constant::DB_TABLE_BUYER_NAME, ''), //买家
                Constant::DB_TABLE_ADDRESS => data_get($orderItem, Constant::DB_TABLE_SHIPPING_ADDRESS_NAME, ''), //买家详细地址
                Constant::DB_TABLE_STATE => data_get($orderItem, Constant::DB_TABLE_STATE_OR_REGION, ''), //收件地址 城市
                Constant::DB_TABLE_CITY => data_get($orderItem, Constant::DB_TABLE_CITY, ''), //邮编
                Constant::DB_TABLE_POSTAL_CODE => data_get($orderItem, Constant::DB_TABLE_POSTAL_CODE, ''), //邮编
                Constant::DB_TABLE_ADDRESS_LINE_1 => data_get($orderItem, Constant::DB_TABLE_ADDRESS_LINE_1, ''), //详细地址
                Constant::DB_TABLE_ADDRESS_LINE_2 => data_get($orderItem, Constant::DB_TABLE_ADDRESS_LINE_2, ''), //详细地址
                Constant::DB_TABLE_ADDRESS_LINE_3 => data_get($orderItem, Constant::DB_TABLE_ADDRESS_LINE_3, ''), //详细地址
            ];

            return static::updateOrCreate($storeId, $where, $data);
        } catch (Exception $exc) {
            data_set($parameters, 'exc', ExceptionHandler::getMessage($exc));
            LogService::addSystemLog('error', $method, $orderno, $service, $parameters); //添加系统日志
            return false;
        }
    }

    /**
     * 拉取订单收件地址
     * @param int $storeId 商城id
     * @param int $orderItemId 订单item id
     * @param int $orderId 订单id
     * @param array $orderItemData 订单item
     * @return array $rs
     */
    public static function pullAmazonOrderAddress($storeId, $orderId, $orderItemData) {

        $service = static::getNamespaceClass();
        $method = __FUNCTION__;
        $parameters = func_get_args();

        $rs = [];
        $exeRs = true;
        try {

            //删除无效的 订单收件数据
            $orderItemIds = collect($orderItemData)->pluck(Constant::DB_TABLE_PRIMARY);
            static::getModel($storeId)->where(Constant::DB_TABLE_ORDER_ID, $orderId)->whereNotIn(Constant::DB_TABLE_PLATFORM_ORDER_ITEM_ID, $orderItemIds)->delete();

            foreach ($orderItemData as $orderItem) {

                $orderItemId = data_get($orderItem, 'local_order_item_id', Constant::PARAMETER_INT_DEFAULT); //本系统订单item id
                $orderAddress = static::inputAmazonOrderAddress($storeId, $orderItemId, $orderId, $orderItem);

                $orderCountry = data_get($orderItem, Constant::DB_TABLE_ORDER_COUNTRY, ''); //订单国家
                $platformOrderItemId = data_get($orderItem, Constant::DB_TABLE_PRIMARY, 0); //平台订单item id
                $_key = implode('_', ['order_address', $orderCountry, $platformOrderItemId]);
                $rs[$_key] = $orderAddress;

                if ($orderAddress === false) {
                    $exeRs = $orderAddress;
                    break;
                }
            }
        } catch (Exception $exc) {
            data_set($parameters, 'exc', ExceptionHandler::getMessage($exc));
            LogService::addSystemLog('error', 'amazon_order_address_pull', $method, $service, $parameters); //添加系统日志
            $exeRs = false;
        }

        unset($orderItemData);

        return [
            'exeRs' => $exeRs,
            'rs' => $rs,
        ];
    }

}
