<?php

/**
 * Db trait
 * User: Jmiy
 * Date: 2020-10-21
 * Time: 14:30
 */

namespace App\Services\Traits;

use App\Constants\Constant;
use Hyperf\DbConnection\Db as DB;

trait TraitsDb {

    /**
     * 获取订单配置
     * @return array
     */
    public static function getDbConfig($storeId = 1, $country = '', array $parameters = [], $make = null) {

        $model = static::getModel($storeId, $country, $parameters, $make);
        $dbConfig = config('databases.' . $model->getConnectionName(), config('databases.default'));

        $tableName = $model->getTable();
        $prefix = data_get($dbConfig, 'prefix', ''); //表前缀
        $fullTable = $prefix . $tableName;
        $fullDbTable = '`' . implode('`.`', [data_get($dbConfig, 'database', ''), $fullTable]) . '`';
        data_set($dbConfig, 'table', $tableName, false);
        data_set($dbConfig, 'full_table', $fullTable, false);
        data_set($dbConfig, 'full_db_table', $fullDbTable, false);

        $tableAlias = $fullDbTable;
        $fullTableAlias = $fullDbTable;
        if ($model::TABLE_ALIAS !== null) {
            $tableAlias = $model::TABLE_ALIAS;
            $fullTableAlias = $prefix . $tableAlias;
        }
        data_set($dbConfig, 'table_alias', $tableAlias, false);
        data_set($dbConfig, 'full_table_alias', $fullTableAlias, false);

        if ($model::TABLE_ALIAS !== null) {
            data_set($dbConfig, 'raw_from', $fullDbTable . ' as ' . $fullTableAlias, false);
            data_set($dbConfig, 'from', $tableName . ' as ' . $tableAlias, false);
        } else {
            data_set($dbConfig, 'raw_from', $fullDbTable, false);
            data_set($dbConfig, 'from', $tableName, false);
        }


        data_set($dbConfig, 'username', null);
        data_set($dbConfig, 'password', null);
        return $dbConfig;
    }

    /**
     * 构建自定义属性where
     * @param int $storeId 品牌商店id
     * @param string $platform pingt
     * @param array $metafields 属性数据
     * @param string|array $ownerIdColumn
     * @return array
     */
    public static function buildWhereExists($storeId, $whereFields, $whereColumns, $extData = []) {

        $customizeWhere = [
            Constant::METHOD_KEY => 'whereExists',
            Constant::PARAMETERS_KEY => function ($query) use ($storeId, $whereFields, $whereColumns, $extData) {


                $dbConfig = static::getDbConfig($storeId);

                $query = $query->select(DB::raw(1))
                        ->from(DB::raw(data_get($dbConfig, 'raw_from', '')))

                ;

                $tableAlias = data_get($dbConfig, 'table_alias', '');
                foreach ($whereColumns as $whereColumn) {
                    $foreignKey = data_get($whereColumn, 'foreignKey');
                    $localKey = data_get($whereColumn, 'localKey');
                    $query = $query->whereColumn($tableAlias . Constant::LINKER . $foreignKey, $localKey);
                }


                foreach ($whereFields as $whereField) {

                    $keyWhere = Constant::DB_EXECUTION_PLAN_WHERE;
                    $_parameters = Constant::PARAMETER_ARRAY_DEFAULT;

                    $field = data_get($whereField, 'field');
                    $values = data_get($whereField, Constant::DB_TABLE_VALUE);

                    $method = data_get($whereField, Constant::METHOD_KEY);
                    if ($method !== null) {
                        $keyWhere = $method;
                        $_parameters = data_get($whereField, Constant::PARAMETERS_KEY);
                    } else {
                        if (!empty($field)) {
                            if (is_array($values)) {
                                $values = array_unique($values);
                                $_parameters = [function ($query) use($field, $values) {
                                        foreach ($values as $item) {
                                            $query->OrWhere($field, '=', $item);
                                        }
                                    }];
                            } else {
                                $_parameters = [$field, 'like', '%' . $values . '%'];
                            }
                        }
                    }

                    $query = $query->{$keyWhere}(...$_parameters);
                }

                $joinData = data_get($extData, Constant::DB_EXECUTION_PLAN_JOIN_DATA, '');
                if ($joinData) {
                    foreach ($joinData as $joinItem) {
                        $table = data_get($joinItem, Constant::DB_EXECUTION_PLAN_TABLE, '');
                        $first = data_get($joinItem, Constant::DB_EXECUTION_PLAN_FIRST, '');
                        $operator = data_get($joinItem, Constant::DB_TABLE_OPERATOR, null);
                        $second = data_get($joinItem, Constant::DB_EXECUTION_PLAN_SECOND, null);
                        $type = data_get($joinItem, Constant::DB_TABLE_TYPE, 'inner');
                        $where = data_get($joinItem, Constant::DB_EXECUTION_PLAN_WHERE, false);
                        $query = $query->join($table, $first, $operator, $second, $type, $where);
                    }
                }

                $query = $query->where($tableAlias . Constant::LINKER . Constant::DB_TABLE_STATUS, 1)
                        ->limit(1)
                ;
            },
        ];

        return [$customizeWhere];
    }

}
