<?php

/**
 * 会员同步shopify流水服务
 * User: Jmiy
 * Date: 2019-11-13
 * Time: 16:50
 */

namespace App\Services;

use App\Services\Traits\GetDefaultConnectionModel;

class CustomerSyncService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 检查是否存在
     * @author harry
     * @param int $storeId
     * @param int $customerId
     * @param string $account
     * @return bool
     */
    public static function exists($storeId = 0, $account = '', $getData = false) {
        $where = [];

        if ($storeId) {
            $where['store_id'] = $storeId;
        }

        if ($account) {
            $where['account'] = $account;
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
