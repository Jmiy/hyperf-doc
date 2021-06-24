<?php

/**
 * 活动点击日志
 * User: Bo
 * Date: 2020-02-06
 * Time: 17:32
 */

namespace App\Services;

class ActivityClickLogService extends BaseService {

    /**
     * 添加
     * @return string
     */
    public static function addLog($storeId, $data) {
        return static::getModel($storeId)->insertGetId($data);
    }

}
