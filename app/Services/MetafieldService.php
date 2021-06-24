<?php

/**
 * Created by Patazon.
 * @desc   :
 * @author : Roy_qiu
 * @email  : Roy_qiu@patazon.net
 * @date   : 2020/9/4 11:17
 */

namespace App\Services;

use App\Constants\Constant;
use App\Utils\FunctionHelper;
use Hyperf\DbConnection\Db as DB;
use Hyperf\Utils\Arr;
use App\Services\Traits\GetDefaultConnectionModel;

class MetafieldService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 属性处理
     * @param int $storeId 官网id
     * @param mixed $ownerIds 属性所属者id列表
     * @param array $requestData 请求参数
     * @return bool
     */
    public static function batchHandle($storeId, $ownerIds, $requestData) {
        if (empty($storeId) || empty($ownerIds)) {
            return false;
        }
        !is_array($ownerIds) && $ownerIds = [$ownerIds];

        $platform = data_get($requestData, Constant::DB_TABLE_PLATFORM, Constant::PLATFORM_SHOPIFY);
        $ownerResource = data_get($requestData, Constant::OWNER_RESOURCE, Constant::PARAMETER_STRING_DEFAULT);
        $namespace = data_get($requestData, Constant::NAME_SPACE, Constant::PARAMETER_STRING_DEFAULT);
        $metaFields = data_get($requestData, Constant::METAFIELDS, Constant::PARAMETER_ARRAY_DEFAULT);
        $opAction = data_get($requestData, Constant::OP_ACTION, 'add');

        if (empty($platform) || empty($ownerResource) || empty($namespace)) {
            return false;
        }

        if ($opAction != 'del' && empty($metaFields)) {
            return false;
        }

        switch ($opAction) {
            case 'add':
                static::add($storeId, $platform, $ownerResource, $ownerIds, $namespace, $metaFields, $requestData);
                break;

            case 'edit':
                static::edit($storeId, $platform, $ownerResource, $ownerIds, $namespace, $metaFields, $requestData);
                break;

            case 'del':
                static::delData($storeId, $platform, $ownerResource, $ownerIds, $namespace);
                break;

            default :
                break;
        }

        return true;
    }

    /**
     * 属性添加
     * @param int $storeId 官网id
     * @param string $platform 平台标识
     * @param string $ownerResource 属性所属资源
     * @param array $ownerIds 属性所属资源id：[123,232,222]
     * @param string $namespace 资源下细分空间
     * @param array $metaFields 属性数组，示例：[{"key":"country","value":["US","UK","DE"]},{"key":"test","value":["AAA","BBB","CCC"]}]
     * @param array $extData 扩展参数
     * @return bool
     */
    public static function add($storeId, $platform, $ownerResource, $ownerIds, $namespace, $metaFields, $extData = []) {
        foreach ($ownerIds as $ownerId) {
            $data = [];
            foreach ($metaFields as $metaField) {
                if (empty($metaField[Constant::DB_TABLE_KEY]) || empty($metaField[Constant::DB_TABLE_VALUE])) {
                    continue;
                }
                !is_array($metaField[Constant::DB_TABLE_VALUE]) && $metaField[Constant::DB_TABLE_VALUE] = [$metaField[Constant::DB_TABLE_VALUE]];

                foreach ($metaField[Constant::DB_TABLE_VALUE] as $value) {
                    $data[] = [
                        Constant::DB_TABLE_UNIQUE_ID => FunctionHelper::getUniqueId($storeId, $platform, $ownerResource, $ownerId, $namespace, $metaField[Constant::DB_TABLE_KEY]),
                        Constant::DB_TABLE_STORE_ID => $storeId,
                        Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform),
                        Constant::METAFIELD_ID => data_get($extData, Constant::METAFIELD_ID, Constant::PARAMETER_INT_DEFAULT),
                        Constant::OWNER_RESOURCE => $ownerResource,
                        Constant::OWNER_ID => $ownerId,
                        Constant::NAME_SPACE => $namespace,
                        Constant::DB_TABLE_KEY => $metaField[Constant::DB_TABLE_KEY],
                        Constant::DB_TABLE_VALUE => $value,
                        Constant::VALUE_TYPE => data_get($extData, Constant::VALUE_TYPE, 'string'),
                        Constant::DESCRIPTION => data_get($extData, Constant::DESCRIPTION, Constant::PARAMETER_STRING_DEFAULT)
                    ];
                }
            }

            static::getModel($storeId)->insert($data);
        }

        return true;
    }

    /**
     * 属性编辑，删除后添加
     * @param int $storeId 官网id
     * @param string $platform 平台标识
     * @param string $ownerResource 属性所属资源
     * @param array $ownerIds 属性所属资源id：[123,232,222]
     * @param string $namespace 资源下细分空间
     * @param array $metaFields 属性数组，示例：[{"key":"country","value":["US","UK","DE"]},{"key":"test","value":["AAA","BBB","CCC"]}]
     * @param array $extData 扩展参数
     * @return bool
     */
    public static function edit($storeId, $platform, $ownerResource, $ownerIds, $namespace, $metaFields, $extData = []) {
        foreach ($ownerIds as $ownerId) {
            foreach ($metaFields as $metaField) {
                $where = [
                    Constant::DB_TABLE_STORE_ID => $storeId,
                    Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform),
                    Constant::OWNER_RESOURCE => $ownerResource,
                    Constant::OWNER_ID => $ownerId,
                    Constant::NAME_SPACE => $namespace,
                    Constant::DB_TABLE_KEY => $metaField[Constant::DB_TABLE_KEY]
                ];
                static::delete($storeId, $where);

                if (empty($metaField[Constant::DB_TABLE_KEY]) || empty($metaField[Constant::DB_TABLE_VALUE])) {
                    continue;
                }
                !is_array($metaField[Constant::DB_TABLE_VALUE]) && $metaField[Constant::DB_TABLE_VALUE] = [$metaField[Constant::DB_TABLE_VALUE]];

                $data = [];
                foreach ($metaField[Constant::DB_TABLE_VALUE] as $value) {
                    $data[] = [
                        Constant::DB_TABLE_UNIQUE_ID => FunctionHelper::getUniqueId($storeId, $platform, $ownerResource, $ownerId, $namespace, $metaField[Constant::DB_TABLE_KEY]),
                        Constant::DB_TABLE_STORE_ID => $storeId,
                        Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform),
                        Constant::METAFIELD_ID => data_get($extData, Constant::METAFIELD_ID, Constant::PARAMETER_INT_DEFAULT),
                        Constant::OWNER_RESOURCE => $ownerResource,
                        Constant::OWNER_ID => $ownerId,
                        Constant::NAME_SPACE => $namespace,
                        Constant::DB_TABLE_KEY => $metaField[Constant::DB_TABLE_KEY],
                        Constant::DB_TABLE_VALUE => $value,
                        Constant::VALUE_TYPE => data_get($extData, Constant::VALUE_TYPE, 'string'),
                        Constant::DESCRIPTION => data_get($extData, Constant::DESCRIPTION, Constant::PARAMETER_STRING_DEFAULT)
                    ];
                }
                static::getModel($storeId)->insert($data);
            }
        }

        return true;
    }

    /**
     * 删除属性
     * @param int $storeId 官网id
     * @param string $platform 平台标识
     * @param string $ownerResource 属性所属资源
     * @param array $ownerIds 属性所属资源id：[123,232,222]
     * @param string $namespace 资源下细分空间
     * @return bool
     */
    public static function delData($storeId, $platform, $ownerResource, $ownerIds, $namespace) {
        $where = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform),
            Constant::OWNER_RESOURCE => $ownerResource,
            Constant::OWNER_ID => $ownerIds,
            Constant::NAME_SPACE => $namespace,
        ];
        return static::delete($storeId, $where);
    }

    /**
     * 添加默认属性
     * @param int $storeId 官网id
     * @param string $platform 平台标识
     * @param string $ownerResource
     * @param int $ownerId
     * @param string $namespace
     * @param string $key
     * @param string|array $value
     * @return bool
     */
    public static function addDefaultMetaField($storeId, $platform, $ownerResource, $ownerId, $namespace, $key, $value) {
        $where = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform),
            Constant::OWNER_RESOURCE => $ownerResource,
            Constant::OWNER_ID => $ownerId,
            Constant::NAME_SPACE => $namespace,
            Constant::DB_TABLE_KEY => $key,
        ];
        $exists = static::existsOrFirst($storeId, '', $where);
        if ($exists) {
            return true;
        }

        //添加国家属性
        $requestData = [];
        data_set($requestData, Constant::OWNER_RESOURCE, ProductService::getModelAlias());
        data_set($requestData, Constant::OP_ACTION, 'add');
        data_set($requestData, Constant::NAME_SPACE, data_get($requestData, Constant::NAME_SPACE, 'point_store'));
        data_set($requestData, Constant::DB_TABLE_PLATFORM, $platform);
        data_set($requestData, 'metafields.0.key', Constant::DB_TABLE_COUNTRY);
        data_set($requestData, 'metafields.0.value', $value);
        return MetafieldService::batchHandle($storeId, $ownerId, $requestData);
    }

    /**
     * 构建自定义属性where
     * @param int $storeId 品牌商店id
     * @param string $platform pingt
     * @param array $metafields 属性数据
     * @param string|array $ownerIdColumn
     * @return array
     */
    public static function buildCustomizeWhere($storeId, $platform, $metafields, $ownerIdColumn = 'p.' . Constant::DB_TABLE_UNIQUE_ID) {
        $customizeWhere = [];
        foreach ($metafields as $metafield) {

            $ownerResource = data_get($metafield, Constant::OWNER_RESOURCE);
            $key = data_get($metafield, Constant::DB_TABLE_KEY);
            $values = data_get($metafield, Constant::DB_TABLE_VALUE);

            if (!empty($key) && !empty($values)) {

                $customizeWhere[] = [
                    Constant::METHOD_KEY => 'whereExists',
                    Constant::PARAMETERS_KEY => function ($query) use ($ownerIdColumn, $ownerResource, $key, $values) {

                        $ownerResourceWhere = Constant::DB_EXECUTION_PLAN_WHERE;
                        $keyWhere = Constant::DB_EXECUTION_PLAN_WHERE;
                        $valueWhere = Constant::DB_EXECUTION_PLAN_WHERE;
                        if (is_array($ownerResource)) {
                            $ownerResourceWhere = 'whereIn';
                        }

                        if (is_array($key)) {
                            $keyWhere = 'whereIn';
                        }

                        if (is_array($values)) {
                            $valueWhere = 'whereIn';
                        }

                        $query = $query->select(DB::raw(1))
                                ->from(DB::raw('`ptxcrm`.`crm_metafields` as crm_m'))
                                ->whereColumn('m.owner_id', $ownerIdColumn)
                                ->{$ownerResourceWhere}('m.' . Constant::OWNER_RESOURCE, $ownerResource)
                                ->{$keyWhere}('m.' . Constant::DB_TABLE_KEY, $key)
                                ->{$valueWhere}('m.' . Constant::DB_TABLE_VALUE, $values)
                                ->where('m.status', 1)
                                ->limit(1)
                        ;
                    },
                ];
            }
        }

        return $customizeWhere;
    }

    /**
     * 添加属性 Jmiy 2020-09-11 19:01
     * @param int $storeId 品牌商店id
     * @param string $platform
     * @param int $metafieldId
     * @param string $ownerResource
     * @param int $ownerId
     * @param string $nameSpace
     * @param string $key
     * @param string $value
     * @param string $valueType
     * @param string $description
     * @return array
     */
    public static function insert($storeId, $platform, $metafieldId, $ownerResource, $ownerId, $nameSpace = Constant::POINT_STORE_NAME_SPACE, $key = '', $value = '', $valueType = Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, $description = '') {

        $where = [
            Constant::DB_TABLE_UNIQUE_ID => FunctionHelper::getUniqueId($storeId, $platform, $metafieldId, $ownerResource, $ownerId, $nameSpace, $key, $value, $valueType), //平台订单唯一id
        ];

        $item = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform),
            Constant::METAFIELD_ID => $metafieldId,
            Constant::OWNER_RESOURCE => $ownerResource,
            Constant::OWNER_ID => $ownerId,
            Constant::NAME_SPACE => $nameSpace,
            Constant::DB_TABLE_KEY => $key,
            Constant::DB_TABLE_VALUE => $value,
            Constant::VALUE_TYPE => $valueType,
            Constant::DESCRIPTION => $description
        ];

        return static::updateOrCreate($storeId, $where, $item, '', FunctionHelper::getDbBeforeHandle([], [], [], array_keys($item)));
    }

    /**
     * 获取属性值
     * @param array $metafieldData
     * @param string|array $key
     * @param type $glue
     * @return array|string 
     */
    public static function getMetafieldValue($metafieldData, $keys, $glue = null) {
        if (!is_array($keys)) {
            $keys = [$keys];
        }

        $data = [];
        foreach ($keys as $key) {
            $_data = array_map(function($metafield) use($key) {
                return data_get($metafield, Constant::DB_TABLE_KEY) == $key ? data_get($metafield, Constant::DB_TABLE_VALUE) : null;
            }, $metafieldData);

            $_data = array_filter($_data, function($value) {
                return $value !== null;
            });

            $_data = Arr::flatten($_data);
            $data[$key] = $glue === null ? $_data : implode($glue, $_data);
        }
        return count($keys) > 1 ? $data : Arr::first($data);
    }

}
