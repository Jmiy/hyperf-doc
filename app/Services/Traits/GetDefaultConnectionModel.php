<?php

/**
 * base trait
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services\Traits;

use App\Models\BaseModel;

trait GetDefaultConnectionModel {

    /**
     * 获取模型
     * @param int $storeId 店铺id
     * @param string $country 国家缩写
     * @return string
     */
    public static function getModel($storeId = 0, $country = '', array $parameters = [], $make = null) {

        data_set($parameters, 'attributes.storeId', $storeId, false); //设置 model attributes.storeId

        return BaseModel::createModel('default_connection_' . $storeId, static::getMake($make), $parameters, $country);
    }

}
