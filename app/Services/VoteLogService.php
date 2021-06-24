<?php

/**
 * 投票流水服务
 * User: Jmiy
 * Date: 2019-11-11
 * Time: 16:50
 */

namespace App\Services;

use Hyperf\Utils\Arr;

class VoteLogService extends BaseService {

    /**
     * 检查是否存在
     * @param int $storeId 商城id
     * @param string $country 国家
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param array $where where条件
     * @param array $getData 是否获取记录  true:是  false:否
     * @return int|object
     */
    public static function exists($storeId = 0, $country = '', $actId = 0, $customerId = 0, $where = [], $getData = false) {

        $_where = [];
        if ($actId) {
            data_set($_where, 'act_id', $actId);
        }

        if ($customerId) {
            data_set($_where, 'customer_id', $customerId);
        }

        $where = Arr::collapse([$_where, $where]);

        return static::existsOrFirst($storeId, $country, $where, $getData);
    }

}
