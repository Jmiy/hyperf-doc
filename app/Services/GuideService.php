<?php

/**
 * 引导服务
 * User: Bo
 * Date: 2019-07-18
 * Time: 14:19
 */

namespace App\Services;

use App\Models\CustomerGuide;

class GuideService extends BaseService {

    /**
     * 添加次数记录
     * @param int $storeId 商城id
     * @param array $where where条件
     * @param array $data  数据
     * @return int
     */
    public static function insert($data) {
        return CustomerGuide::add($data);
    }

    public static function update($data) {
        return CustomerGuide::upd($data);
    }

}
