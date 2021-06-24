<?php

namespace App\Services\Store;

use App\Services\DictService;

class StoreService {

    /**
     * 获取积分方式数据
     * @return array
     */
    public static function getActionList() {

        $list = DictService::getListByType('credit_action');
        $data = [];
        foreach ($list as $k => $item) {
            $key = data_get($item, 'dict_key', null);
            if ($key !== null) {
                $data[] = [
                    'key' => data_get($item, 'dict_key', 0),
                    'value' => data_get($item, 'dict_value', ''),
                ];
            }
        }
        return $data;
    }

    /**
     * 获取积分方式
     * @return array
     */
//    public static function getActionData($key = null, $default = null) {
//        $data = array_column(static::getActionList(), 'value', 'key');
//        return Arr::get($data, $key, $default);
//    }
}
