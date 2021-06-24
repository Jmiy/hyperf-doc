<?php

/**
 * 系统字典服务
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services;

use App\Utils\Support\Facades\Cache;
use App\Services\Traits\GetDefaultConnectionModel;
use App\Constants\Constant;
use App\Utils\FunctionHelper;
use Hyperf\Utils\Arr;

class DictService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 获取要清空的tags
     * @return array
     */
    public static function getClearTags() {
        return ['dict'];
    }

    /**
     * 获取字典
     * @param string $type  字典类型
     * @param string $keyField 数组key
     * @param string $valueField  数组value
     * @return type
     */
    public static function getListByType($type, $keyField = null, $valueField = null, $orderby = 'sorts asc', $country = null, $extWhere = [], $select = []) {

        $tags = config('cache.tags.dict');
        $ttl = config('cache.ttl', 86400); //认证缓存时间 单位秒
        $cacheKey = md5(json_encode(func_get_args()));
        $data = Cache::tags($tags)->remember($cacheKey, $ttl, function () use($type, $keyField, $valueField, $orderby, $country, $extWhere, $select) {

            $type = !is_array($type) ? ($type . '') : $type;
            $where = [
                'type' => $type,
            ];

            if ($country) {
                data_set($where, Constant::DB_TABLE_COUNTRY, $country);
            }

            $where = Arr::collapse([$where, $extWhere]);

            $dbExecutionPlan = [
                Constant::DB_EXECUTION_PLAN_PARENT => FunctionHelper::getExePlan('default_connection_0', null, static::getModelAlias(), '', $select, $where, $orderby),
            ];

            if ($keyField) {
                $keyField = is_array($keyField) ? $keyField : FunctionHelper::getExePlanHandleData((is_array($type) ? ('type{connection}' . $keyField) : $keyField), Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, 'string', Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, '_');
                data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_PARENT . '.' . Constant::DB_EXECUTION_PLAN_HANDLE_DATA . '.{key}', $keyField);
            }

            if ($valueField) {
                $valueField = is_array($valueField) ? $valueField : FunctionHelper::getExePlanHandleData((is_array($type) ? ('type{connection}' . $valueField) : $valueField), Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, 'string', Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, '_'); //FunctionHelper::getExePlanHandleData($valueField);
                data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_PARENT . '.' . Constant::DB_EXECUTION_PLAN_HANDLE_DATA . '.{value}', $valueField);
            }

            $dataStructure = 'list';
            $flatten = false;
            $data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);

            return collect($data);
        });

        return FunctionHelper::handleCollect($data, $type, $keyField, $valueField);
    }

    /**
     * 获取字典
     * @param $type    字典类型
     * @param $dictKey     字典key
     * @param boolean $onlyValue 是否只获取值 true:是 false:否 默认:false
     * @param bool $isArray 是否数组形式返回
     * @return array|ActiveRecord[]
     */
    public static function getByTypeAndKey($type, $dictKey, $onlyValue = false, $isArray = true) {

        $tags = config('cache.tags.dict');
        $key = md5(json_encode(func_get_args()));
        $ttl = config('cache.ttl', 86400); //认证缓存时间 单位秒
        return Cache::tags($tags)->remember($key, $ttl, function () use($type, $dictKey, $onlyValue, $isArray) {

                    $where = [
                        'type' => $type,
                        'dict_key' => $dictKey,
                    ];
                    $query = static::getModel()->buildWhere($where); //->withTrashed()

                    if ($onlyValue) {
                        $data = $query->value('dict_value');
                        return $data ? $data : '';
                    }

                    $data = $query->first();

                    return $data ? ($isArray ? $data->toArray() : $data) : ($isArray ? [] : $data);
                });
    }

    /**
     * 获取字典数据
     * @param int $storeId 商城id
     * @param string $type 字典类型
     * @param string $keyField 商城字典key
     * @param string $valueField 商城字典value
     * @param string $distKeyField  系统字典key
     * @param string $distValueField  系统字典value
     * @param string $orderby 排序
     * @param string $country 国家
     * @return collect 字典数据
     */
    public static function getDistData($storeId = Constant::PARAMETER_INT_DEFAULT, $type = Constant::PARAMETER_STRING_DEFAULT, $keyField = null, $valueField = null, $distKeyField = null, $distValueField = null, $orderby = 'sorts asc', $country = null, $extWhere = [], $select = []) {

        $data = DictStoreService::getListByType($storeId, $type, $orderby, $keyField, $valueField, $country, data_get($extWhere, Constant::DICT_STORE, []), data_get($select, Constant::DICT_STORE, []));
        if ($data->isEmpty()) {//如果商城字典没有数据，就获取系统字典数据
            return static::getListByType($type, $distKeyField, $distValueField, $orderby, $country, data_get($extWhere, Constant::DICT, []), data_get($select, Constant::DICT, []));
        }

        if (!is_array($type)) {//如果商城字典有数据， 并且$type 不是数组，就直接返回商城字典数据
            return $data;
        }

        $typeData = array_diff($type, $data->keys()->all());
        if (empty($typeData)) {//如果 $type 对应的字典数据都可以从商城字典获取， 就直接返回商城字典数据
            return $data;
        }

        return collect(Arr::collapse([static::getListByType($typeData, $distKeyField, $distValueField, $orderby, $country, data_get($extWhere, Constant::DICT, []), data_get($select, Constant::DICT, [])), $data])); //合并 商城字典数据 和 系统字典数据
    }

    /**
     * 获取 合并 商城字典数据 和 系统字典数据  优先商城字典配置，如果商城字典没有配置，就以系统字典为准
     * @param int $storeId 商城id
     * @param string $type 字典类型
     * @param string $keyField 商城字典key
     * @param string $valueField 商城字典value
     * @param string $distKeyField  系统字典key
     * @param string $distValueField  系统字典value
     * @param string $orderby 排序
     * @param string $country 国家
     * @return collect 字典数据
     */
    public static function getDistConfig($storeId = Constant::PARAMETER_INT_DEFAULT, $type = Constant::PARAMETER_STRING_DEFAULT, $keyField = null, $valueField = null, $distKeyField = null, $distValueField = null, $orderby = 'sorts asc', $country = null, $extWhere = [], $select = []) {

        $data = DictStoreService::getListByType($storeId, $type, $orderby, $keyField, $valueField, $country, data_get($extWhere, Constant::DICT_STORE, []), data_get($select, Constant::DICT_STORE, []));
        $dictData = static::getListByType($type, $distKeyField, $distValueField, $orderby, $country, data_get($extWhere, Constant::DICT, []), data_get($select, Constant::DICT, []));

        $_data = collect(Arr::collapse([$dictData, $data]));//合并 商城字典数据 和 系统字典数据
        if (!is_array($type)) {//如果 $type 不是数组，就直接返回合并后的字典数据
            return $_data;
        }

        foreach ($_data as $key => $item){
            $_tmp = data_get($dictData,$key);
            if($_tmp){
                data_set($_data,$key,Arr::collapse([$_tmp,$item]));
            }
        }

        return $_data;
    }

}
