<?php

namespace App\Services\Platform;

use App\Services\BaseService;
use App\Constants\Constant;
use App\Services\Store\PlatformServiceManager;
use App\Utils\FunctionHelper;
use App\Services\Traits\GetDefaultConnectionModel;
use App\Services\DictService;

class CountryService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 记录交易相关数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据|单条交易数据
     * @return bool
     */
    public static function handle($storeId, $platform, $data) {

        if (empty($data)) {
            return false;
        }

        $_data = PlatformServiceManager::handle($platform, ['Erp', 'Commons', 'Country'], 'getCountryData', [$storeId, $platform, $data]);
        if (empty($_data)) {
            return false;
        }

        $where = [
            Constant::DB_TABLE_TYPE => data_get($_data, Constant::DB_TABLE_TYPE, ''),
            Constant::DB_TABLE_DICT_KEY => data_get($_data, Constant::DB_TABLE_DICT_KEY, ''),
        ];
        DictService::updateOrCreate($storeId, $where, $_data, '', FunctionHelper::getDbBeforeHandle([], [], [], array_keys($_data)));

        return true;
    }

    /**
     * 拉取交易数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $parameters  请求参数
     */
    public static function handlePull($storeId, $platform, $parameters = []) {

        $_data = PlatformServiceManager::handle($platform, ['Erp', 'Commons', 'Country'], 'getCountry', [$storeId, $parameters]);

        if ($_data === null) {
            return false;
        }

        foreach ($_data as $key => $value) {
            static::handle($storeId, $platform, [
                Constant::DB_TABLE_DICT_KEY => $key,
                Constant::DB_TABLE_DICT_VALUE => $value,
            ]);
        }

        DictService::clear(); //清空系统字典缓存

        DictService::getListByType('country_cn', Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE); //缓存系统字典

        return true;
    }

}
