<?php

/**
 * 国家服务
 * User: Bo
 * Date: 2019-07-18
 * Time: 14:19
 */

namespace App\Services;

use App\Utils\Support\Facades\Cache;
use App\Constants\Constant;
use App\Services\Traits\GetDefaultConnectionModel;

class CountryService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 查询国家记录
     * @param int $storeId 商城id
     * @param array $where where条件
     * @param array $data  数据
     * @return int
     */
    public static function country($data) {
        $tags = config('cache.tags.country', ['{country}']);
        $ttl = config('cache.ttl', 86400); //缓存24小时 单位秒
        $key = 'key_country';
        return Cache::tags($tags)->remember($key, $ttl, function ()use($data) {
                    return static::getModel(0)->select([Constant::DB_TABLE_COUNTRY_CODE, Constant::DB_TABLE_COUNTRY_NAME])->get();
                });
    }

    /**
     * 查询单个国家记录
     * @param string $countryId 国家简写
     * @param boolean $onlyValue 是否只获取值 true:是 false:否 默认:false
     * @return string|obj $data
     */
    public static function countryOne($countryCode, $onlyValue = false) {

        $tags = config('cache.tags.country', ['{country}']);
        $ttl = config('cache.ttl', 86400); //缓存24小时 单位秒
        $key = $countryCode . ':' . $onlyValue;
        return Cache::tags($tags)->remember($key, $ttl, function ()use($countryCode, $onlyValue) {

                    $query = static::getModel(0)->select([Constant::DB_TABLE_COUNTRY_CODE, Constant::DB_TABLE_COUNTRY_NAME])
                            ->where([Constant::DB_TABLE_COUNTRY_CODE => $countryCode])
                    ;

                    if ($onlyValue) {
                        $data = $query->value(Constant::DB_TABLE_COUNTRY_NAME);
                        return $data ? $data : '';
                    }

                    return $query->first();
                });
    }

}
