<?php

/**
 * Customer trait
 * User: Jmiy
 * Date: 2020-01-04
 * Time: 18:35
 */

namespace App\Services\Traits;

use App\Constants\Constant;
use App\Services\DictStoreService;
use App\Services\CustomerInfoService;
use App\Utils\FunctionHelper;
use App\Services\DictService;

trait Order {

    /**
     * 获取订单邮件配置
     * @param int $storeId 商城id
     * @return array 订单邮件配置
     */
    public static function getOrderConfig($storeId = 0, $country = '') {
        return DictStoreService::getListByType($storeId, Constant::ORDER, 'sorts asc', Constant::DB_TABLE_STORE_DICT_KEY, Constant::DB_TABLE_STORE_DICT_VALUE, $country);
    }

    /**
     * 处理订单延保时间
     * @param int $storeId 商城id
     * @param int $orderId 订单id
     * @param int $customerId 账号id
     * @return int 影响记录条数
     */
    public static function handleWarrantyAt($storeId, $orderId, $customerId, $extData = []) {

        if (empty($orderId)) {//如果订单id为空，就直接返回
            return false;
        }

        $customerInfo = [];
        if ($customerId) {
            $customerInfo = CustomerInfoService::exists($storeId, $customerId, '', true, ['vip']);
        }
        $vip = data_get($customerInfo, 'vip', 1);

        //获取vip等级延保配置
        $warrantyDate = DictStoreService::getByTypeAndKey($storeId, Constant::WARRANTY_DATE, $vip, true);
        $warrantyDes = DictStoreService::getByTypeAndKey($storeId, Constant::WARRANTY_DES, $vip, true);

        //获取订单公共延保配置
        $orderConfig = static::getOrderConfig($storeId, '');

        $where = [Constant::DB_TABLE_PRIMARY => $orderId];
        $warrantyDate = $warrantyDate ? $warrantyDate : data_get($orderConfig, Constant::WARRANTY_DATE, '+' . Constant::DEFAULT_WARRANTY_DATE);

        $orderAt = data_get($extData, Constant::DB_TABLE_ORDER_AT, Constant::DB_TABLE_ORDER_TIME);
        $orderData = static::existsOrFirst($storeId, '', $where, true, [$orderAt]);
        $handleData = FunctionHelper::getExePlanHandleData($orderAt, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::DB_EXECUTION_PLAN_DATATYPE_DATETIME, 'Y-m-d H:i:s', $warrantyDate);

        $data = [
            Constant::WARRANTY_DATE => $warrantyDate,
            Constant::WARRANTY_DES => $warrantyDes ? $warrantyDes : data_get($orderConfig, Constant::WARRANTY_DES, Constant::DEFAULT_WARRANTY_DATE),
            Constant::WARRANTY_AT => FunctionHelper::handleData($orderData, $handleData), //订单延保时间
        ];

        return static::updateOrCreate($storeId, $where, $data);
    }

    /**
     * 获取用户端延保数据
     * @param int $storeId 品牌商店id
     * @param int $orderWarrantyId 延保订单id|延保数据
     * @return type
     */
    public static function getClientWarrantyData($storeId, $orderId, $extData = []) {
        $orderWarrantyData = $orderId;
        $orderAt = data_get($extData, Constant::DB_TABLE_ORDER_AT, Constant::DB_TABLE_ORDER_AT);
        if (is_numeric($orderId)) {
            $where = [
                Constant::DB_TABLE_PRIMARY => $orderId,
            ];
            $select = [
                $orderAt,
                Constant::DB_TABLE_ORDER_STATUS, //-1:Matching 0:Pending 1:Shipped 2:Canceled 3:Failure 4:partially_refunded 5:partially_paid 6:authorized
                Constant::WARRANTY_DATE,
                Constant::WARRANTY_AT,
            ];
            $orderWarrantyData = static::existsOrFirst($storeId, '', $where, true, $select);
        }

        $orderConfig = data_get($extData, 'orderConfig', DictStoreService::getByTypeAndKey($storeId, Constant::ORDER, [Constant::CONFIG_KEY_WARRANTY_DATE_FORMAT, Constant::WARRANTY_DATE]));
        //订单状态 -1:Matching 0:Pending 1:Shipped 2:Canceled 3:Failure 4:partially_refunded 5:partially_paid 6:authorized
        $warrantyAtData = data_get($extData, 'orderStatusData', DictService::getListByType(Constant::DB_TABLE_ORDER_STATUS, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE));
        $warrantyStatusData = clone $warrantyAtData;
        $warrantyAtData[1] = ''; //显示延保时间
        $warrantyAtData[4] = ''; //显示延保时间

        $orderStatus = data_get($orderWarrantyData, Constant::DB_TABLE_ORDER_STATUS, -1);
        $showStatus = [
            5 => 1,
            6 => 0,
        ];
        $orderStatusMap = data_get($showStatus, $orderStatus, $orderStatus);

        $isShowWarrantyAt = data_get($warrantyAtData, $orderStatusMap, data_get($warrantyAtData, '-1'));
        data_set($orderWarrantyData, 'clientWarranty', $isShowWarrantyAt);

        //延保时间
        switch ($storeId) {
            case 3:
                $handle = FunctionHelper::getExePlanHandleData('clientWarranty{or}88', Constant::HOLIFE_WARRANTY_DATE);
                break;

            case 5:
                $handle = FunctionHelper::getExePlanHandleData('clientWarranty{or}88', Constant::IKICH_WARRANTY_DATE);

                break;

            default:
                //$warrantyDate = data_get($orderWarrantyData, Constant::WARRANTY_DATE, '');
                //$handle = FunctionHelper::getExePlanHandleData('clientWarranty{or}' . $orderAt, '', Constant::PARAMETER_ARRAY_DEFAULT, Constant::DB_EXECUTION_PLAN_DATATYPE_DATETIME, data_get($orderConfig, Constant::CONFIG_KEY_WARRANTY_DATE_FORMAT, 'Y-m-d H:i'), ($warrantyDate ? $warrantyDate : data_get($orderConfig, Constant::WARRANTY_DATE, '+' . Constant::DEFAULT_WARRANTY_DATE)));
                $handle = FunctionHelper::getExePlanHandleData('clientWarranty{or}' . Constant::WARRANTY_AT, '', Constant::PARAMETER_ARRAY_DEFAULT, Constant::DB_EXECUTION_PLAN_DATATYPE_DATETIME, data_get($orderConfig, Constant::CONFIG_KEY_WARRANTY_DATE_FORMAT, 'Y-m-d H:i'));
                break;
        }
        $warranty = FunctionHelper::handleData($orderWarrantyData, $handle);

        return [
            Constant::RESPONSE_WARRANTY => $warranty,
            Constant::DB_TABLE_ORDER_STATUS => $orderStatusMap, //C端显示的状态标识
            'order_status_show' => data_get($warrantyStatusData, $orderStatusMap, data_get($warrantyStatusData, -1)), //C端显示的状态
            'warrantyAtData' => $warrantyAtData, //C端显示延保的状态标识配置数据
            'isShowWarrantyAt' => $isShowWarrantyAt ? 0 : 1, //是否显示延保时间
        ];
    }

}
