<?php

/**
 * 唯一id服务
 * User: Jmiy
 * Date: 2020-09-01
 * Time: 16:00
 */

namespace App\Services;

use App\Services\Traits\GetDefaultConnectionModel;
use App\Constants\Constant;
use App\Utils\FunctionHelper;
use Hyperf\Utils\Arr;

class UniqueIdService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 记录订单相关数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据
     * @return bool
     */
    public static function handle(...$parameters) {

        $key = json_encode($parameters);

        $tag = static::getCacheTags();

        $handleCacheData = FunctionHelper::getJobData(static::getNamespaceClass(), 'remember', [$key, config('cache.ttl', 86400), function () use($key, $parameters, $tag) {
                        $_data = Arr::last($parameters);
                        $_data = is_array($_data) && Arr::isAssoc($_data) ? $_data : [];

                        if ($_data) {
                            unset($parameters[count($parameters) - 1]);
                            $key = json_encode($parameters);
                        }

                        $data = static::updateOrCreate(1, ['key' => $key], $_data, '', [
                                    Constant::DB_OPERATION_SELECT => Arr::collapse([[Constant::DB_TABLE_PRIMARY, Constant::DB_TABLE_KEY, Constant::DB_TABLE_VALUE], array_keys($_data)])
                        ]);

                        //删除缓存
                        $handleCacheData = FunctionHelper::getJobData(static::getNamespaceClass(), 'forget', [$key]);
                        static::handleCache($tag, $handleCacheData);
                        return data_get($data, Constant::RESPONSE_DATA_KEY, []);
                    }]);


        return static::handleCache($tag, $handleCacheData);
    }

    public static function getUniqueId(...$parameters) {
        $data = static::handle(...$parameters);
        $value = data_get($data, Constant::DB_TABLE_VALUE);
        return $value ?: data_get($data, Constant::DB_TABLE_PRIMARY);
    }

}
