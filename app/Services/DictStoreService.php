<?php

/**
 * 商城配置服务
 * User: Jmiy
 * Date: 2019-09-27
 * Time: 16:50
 */

namespace App\Services;

use App\Utils\Support\Facades\Cache;
use App\Utils\FunctionHelper;
use App\Services\Traits\GetDefaultConnectionModel;
use App\Constants\Constant;
use Hyperf\Utils\Arr;

class DictStoreService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 获取要清空的tags
     * @return array
     */
    public static function getClearTags() {
        return ['storeDict'];
    }

    /**
     * 获取商城字典
     * @param int $storeId 商城id
     * @param string $type 字典类型
     * @param string $keyField 数组key
     * @param string $valueField 数组value
     * @param string $country 国家
     * @return object(Illuminate\Support\Collection) $data
     */
    public static function getListByType($storeId, $type, $orderby = 'sorts asc', $keyField = null, $valueField = null, $country = '', $extWhere = [], $select = []) {

        $tags = config('cache.tags.storeDict', ['{storeDict}']);
        $ttl = config('cache.ttl', 86400); //认证缓存时间 单位秒
        $key = md5(json_encode(func_get_args()));
        $data = Cache::tags($tags)->remember($key, $ttl, function () use($storeId, $type, $orderby, $keyField, $valueField, $country, $extWhere, $select) {
            $where = [
                'store_id' => $storeId,
            ];

            $type = !is_array($type) ? ($type . '') : $type;
            data_set($where, 'type', $type);

            if ($country) {
                $where['country'] = $country;
            }

            $where = Arr::collapse([$where, $extWhere]);

            $dbExecutionPlan = [
                Constant::DB_EXECUTION_PLAN_PARENT => FunctionHelper::getExePlan($storeId, null, static::getNamespaceClass(), '', $select, $where, $orderby),
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
     * 通过 type和key 获取字典数据  这个方法禁止调用 FunctionHelper::getResponseData  会造成死循环
     * @param int $storeId 商城id
     * @param string $type 字典类型
     * @param string $conf_key 字典key
     * @param boolean $onlyValue 是否只有值 true：是  false:否
     * @param boolean $isArray 是否转化为数组
     * @param string $country 国家
     * @return string|array|object(Illuminate\Support\Collection) $data
     */
    public static function getByTypeAndKey($storeId, $type, $conf_key, $onlyValue = false, $isArray = false, $country = '') {

        $tags = config('cache.tags.storeDict', ['{storeDict}']);
        $key = md5(json_encode(func_get_args()));
        $ttl = config('cache.ttl', 86400); //认证缓存时间 单位秒
        return Cache::tags($tags)->remember($key, $ttl, function () use($storeId, $type, $conf_key, $onlyValue, $isArray, $country) {
                    $where = [
                        'store_id' => $storeId,
                    ];

                    $type = !is_array($type) ? ($type . '') : $type;
                    data_set($where, 'type', $type);

                    if ($conf_key) {
                        data_set($where, 'conf_key', $conf_key);
                    }

                    if ($country) {
                        data_set($where, 'country', $country);
                    }

                    $query = static::getModel($storeId, $country)->buildWhere($where);
                    if ($onlyValue) {
                        $confValue = $query->value('conf_value');
                        return $confValue !== null ? $confValue : '';
                    }

                    $data = $query->first();

                    return $data ? ($isArray ? $data->toArray() : $data) : [];
                });
    }

}
