<?php

/**
 * 日榜服务
 * User: Jmiy
 * Date: 2019-11-13
 * Time: 16:50
 */

namespace App\Services;

class RankDayService extends BaseService {

    /**
     * 检查是否存在
     * @author harry
     * @param int $storeId
     * @param int $customerId
     * @param string $account
     * @return bool
     */
    public static function exists($storeId, $customerId = 0, $actId = 0, $getData = false) {
        $where = [];

        if ($customerId) {
            $where['customer_id'] = $customerId;
        }

        if ($actId) {
            $where['act_id'] = $actId;
        }

        if (empty($where)) {
            if ($getData) {
                $rs = null;
            } else {
                $rs = true;
            }
            return $rs;
        }

        $query = static::getModel($storeId, '')->where($where);

        if ($getData) {
            $rs = $query->first();
        } else {
            $rs = $query->count();
        }

        return $rs;
    }

}
