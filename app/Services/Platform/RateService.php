<?php

namespace App\Services\Platform;

use App\Services\BaseService;
use App\Constants\Constant;
use App\Services\Store\PlatformServiceManager;
use App\Utils\FunctionHelper;
use App\Services\Traits\GetDefaultConnectionModel;
use App\Services\DictService;

class RateService extends BaseService {

    use GetDefaultConnectionModel;

    public static $currency = [
        'us' => 'USD', //美元
        'uk' => 'GBP', //英镑
        'fr' => 'EUR', //欧元
        'jp' => 'JPY', //日元
        'es' => 'EUR', //欧元
        'it' => 'EUR', //欧元
        'de' => 'EUR', //欧元
        'mx' => 'MXN', //墨西哥比索
        'ca' => 'CAD', //加元
        'in' => 'INR', //印度卢比
        'au' => 'AUD', //澳大利亚元
        'ae' => 'AED', //阿联酋
    ];

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

        $rateData = PlatformServiceManager::handle($platform, ['Erp', 'Finances', 'Rate'], 'getRateData', [$storeId, $platform, $data]);
        if (empty($rateData)) {
            return false;
        }

        $where = [
            Constant::DB_TABLE_TYPE => data_get($rateData, Constant::DB_TABLE_TYPE, ''),
            Constant::DB_TABLE_DICT_KEY => data_get($rateData, Constant::DB_TABLE_DICT_KEY, ''),
        ];
        DictService::updateOrCreate($storeId, $where, $rateData, '', FunctionHelper::getDbBeforeHandle([], [], [], array_keys($rateData)));

        return true;
    }

    /**
     * 拉取交易数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $parameters  请求参数
     */
    public static function handlePull($storeId, $platform, $parameters = []) {

        $rateData = PlatformServiceManager::handle($platform, ['Erp', 'Finances', 'Rate'], 'getRate', [$storeId, $parameters]);

        if ($rateData === null) {
            return false;
        }

        foreach ($rateData as $data) {
            static::handle($storeId, $platform, $data);
        }

        DictService::clear(); //清空系统字典缓存

        DictService::getListByType('exchange', Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE); //缓存系统字典

        return true;
    }

}
