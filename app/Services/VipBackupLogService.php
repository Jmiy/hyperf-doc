<?php

/**
 * 积分服务
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services;

use App\Services\Traits\GetDefaultConnectionModel;

class VipBackupLogService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 检查是否存在
     * @param int $id 角色id
     * @param bool $getData 是否获取数据 true:是 false:否 默认:false
     * @return mixed|static
     */
    public static function exists($storeId = 0, $where = [], $getData = false) {
        return static::existsOrFirst($storeId, '', $where, $getData);
    }

}
