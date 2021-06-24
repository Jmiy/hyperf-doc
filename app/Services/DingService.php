<?php

/**
 * Ding服务
 * User: Bo
 * Date: 2020-01-02
 * Time: 10:44
 */

namespace App\Services;
use App\Constants\Constant;

class DingService extends BaseService {

    /**
     * 更新或者添加
     * @param int $storeId 商店id
     * @param int $customeTotal 会员列表汇总
     * @param int $creditTotal 积分管理汇总
     * @param int $orderTotal 订单列表汇总
     * @param int $time 时间
     * @param int $type 类型 1 会员列表 2 积分管理 3 订单列表 4.....
     * @return array 返回值
     */
    public static function getDingData($storeId, $customeTotal, $creditTotal, $orderTotal, $time, $type) {
        switch ($type) {
            case 1:
                $Data = [
                    Constant::DB_TABLE_STORE_ID => $storeId,
                    'type' => $type,
                    Constant::TOTAL => $customeTotal,
                    Constant::DB_TABLE_CREATED_AT => $time,
                ];
                break;
            case 2:
                $Data = [
                    Constant::DB_TABLE_STORE_ID => $storeId,
                    'type' => $type,
                    Constant::TOTAL => $creditTotal,
                    Constant::DB_TABLE_CREATED_AT => $time,
                ];
                break;
            case 3:
                $Data = [
                    Constant::DB_TABLE_STORE_ID => $storeId,
                    'type' => $type,
                    Constant::TOTAL => $orderTotal,
                    Constant::DB_TABLE_CREATED_AT => $time,
                ];
                break;
            
            default:
                break;
        }
        $where = [
            'updated_at' => $time,
            'type' => $type,
            Constant::DB_TABLE_STORE_ID => $storeId,
        ];
        return static::getModel('default_connection_ding', '')->updateOrInsert($where, $Data);
    }

}
