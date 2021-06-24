<?php

/**
 * Customer trait
 * User: Jmiy
 * Date: 2020-01-04
 * Time: 18:35
 */

namespace App\Services\Traits;

trait CustomerService {

    use GetDefaultConnectionModel;

    /**
     * 获取模型
     * @param int $storeId 店铺id
     * @param string $country 国家缩写
     * @return string
     */
//    public static function getModel($storeId = 1, $country = '', array $parameters = [], $make = null) {
//        return static::createModel(0, static::getMake($make), $parameters, $country);
//    }
}
