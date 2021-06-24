<?php

/**
 * base trait
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services\Traits;

trait ExistsFirst {

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
    public static function existsOrFirst($storeId = 0, $country = '', $where = [], $getData = false, $select = null) {

        if (empty($where)) {
            return $getData ? [] : true;
        }

        $query = static::getModel($storeId, $country)->buildWhere($where);
        if ($getData) {
            if($select !== null){
                $query = $query->select($select);
            }
            $rs = $query->first();
        } else {
            $rs = $query->count();
        }

        return $rs;
    }

}
