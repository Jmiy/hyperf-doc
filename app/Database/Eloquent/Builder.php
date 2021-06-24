<?php

namespace App\Database\Eloquent;

use Hyperf\Database\Model\Builder as ModelBuilder;
use Carbon\Carbon;
use App\Utils\FunctionHelper;
use App\Constants\Constant;
use Hyperf\Snowflake\IdGeneratorInterface;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;

/**
 * @mixin \Hyperf\Database\Query\Builder
 */
class Builder extends ModelBuilder {

    public static $dbOperation = [
        0 => Constant::DB_OPERATION_SELECT,
        1 => Constant::DB_OPERATION_INSERT,
        2 => Constant::DB_OPERATION_UPDATE,
        3 => Constant::DB_OPERATION_DELETE,
    ];

    /**
     * 获取modelClass
     * @return type
     */
    public function getModelClass() {
        return get_class($this->getModel());
    }

    /**
     * 获取属性数据
     * @param array $attributes
     * @return array
     */

    /**
     * 获取model属性数据
     * @param array $values
     * @param string $dbOperation 数据库操作
     * @return array $values model属性数据
     */
    public function getAttributesData(array $values, $dbOperation = 'insert') {

        // Since every insert gets treated like a batch insert, we will make sure the
        // bindings are structured in a way that is convenient when building these
        // inserts statements by verifying these elements are actually an array.
        if (empty($values)) {
            return $values;
        }

        $modelClass = $this->getModelClass();

        if (is_array(reset($values))) {
            foreach ($values as $key => $value) {
                $values[$key] = $this->getAttributesData($value, $dbOperation);
            }
            return $values;
        }

        $requestData = Context::get(Constant::CONTEXT_REQUEST_DATA, []);
        $requestMark = data_get($requestData,Constant::REQUEST_MARK, '');

        //设置时区
        $storeId = data_get($requestData,Constant::DB_TABLE_STORE_ID, 0);//data_get($this->getModel(), 'storeId', 0);
        FunctionHelper::setTimezone($storeId);

        $nowTime = Carbon::now()->toDateTimeString();

        switch ($dbOperation) {
            case data_get(static::$dbOperation, 1, null):
                if ($modelClass::CREATED_AT) {
                    data_set($values, $modelClass::CREATED_AT, $nowTime, false);
                }

                if (defined($modelClass . '::CREATED_MARK') && $modelClass::CREATED_MARK) {
                    data_set($values, $modelClass::CREATED_MARK, $requestMark, false);
                }

                if (!isset($values[$this->getModel()->getKeyName()])) {
                    $container = ApplicationContext::getContainer();
                    $generator = $container->get(IdGeneratorInterface::class);
                    data_set($values, $this->getModel()->getKeyName(), $generator->generate(), false);
                }

                break;

            default:
                break;
        }

        if ($modelClass::UPDATED_AT) {
            data_set($values, $modelClass::UPDATED_AT, $nowTime, false);
        }

        if (defined($modelClass . '::UPDATED_MARK') && $modelClass::UPDATED_MARK) {
            data_set($values, $modelClass::UPDATED_MARK, $requestMark, false);
        }

        //dump($this->getModel()->getTable(), $dbOperation, $storeId, $nowTime, $values);

        return $values;
    }

    /**
     * Get the first record matching the attributes or create it.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return \Hyperf\Database\Model\Model|static
     */
    public function firstOrCreate(array $attributes, array $values = []) {
        if (!is_null($instance = $this->where($attributes)->first())) {
            data_set($instance, Constant::DB_OPERATION, data_get(static::$dbOperation, 0, null));
            return $instance;
        }

        $dbOperation = data_get(static::$dbOperation, 1, null);
        return tap($this->newModelInstance($attributes + $this->getAttributesData($values, $dbOperation)), function ($instance) use($dbOperation) {
            $instance->save();
            data_set($instance, Constant::DB_OPERATION, $dbOperation);
        });
    }

    /**
     * Create or update a record matching the attributes, and fill it with values.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return \Hyperf\Database\Model\Model|static
     */
    public function updateOrCreate(array $attributes, array $values = [], array $handleData = []) {
        return tap($this->firstOrNew($attributes), function ($instance) use ($values, $handleData) {

            $dbOperation = data_get(static::$dbOperation, ($instance->exists ? 2 : 1), null);

            $srcInstance = clone $instance; //克隆原始model实例

            if (empty($instance->exists)) {

                $isInsert = true;
                $insertHandle = data_get($handleData, Constant::DB_OPERATION_INSERT, []);
                foreach ($insertHandle as $func) {
                    $isInsert = $func($srcInstance);
                }

                if (!$isInsert) {
                    $dbOperation = data_get(static::$dbOperation, 0, null);
                    data_set($instance, Constant::DB_OPERATION, $dbOperation, false); //设置数据库操作
                    return $instance;
                }

                $values = $this->getAttributesData($values, $dbOperation);
            }

            $instance->fill($values); //Fill the model with an array of attributes. 比较要更新的字段的值是否有更新，并且把最新的值更新到model实例对应的字段属性
            if ($instance->exists) {
                if (empty($instance->getDirty())) {//Get the attributes that have been changed since last sync. 如果没有更新，数据库操作dbOperation：select 并且直接返回查询结果
                    $dbOperation = data_get(static::$dbOperation, 0, null);
                } else {//如果有更新，数据库操作dbOperation：update 更新数据库的更新时间，并且返回更新以后的结果
                    $isUpdate = true;
                    $updateHandle = data_get($handleData, Constant::DB_OPERATION_UPDATE, []);
                    foreach ($updateHandle as $func) {
                        $isUpdate = $func($srcInstance);
                    }

                    if (!$isUpdate) {
                        $dbOperation = data_get(static::$dbOperation, 0, null);
                        $instance->fill($srcInstance->toArray());
                        data_set($instance, Constant::DB_OPERATION, $dbOperation, false); //设置数据库操作
                        return $instance;
                    }

                    $values = $this->getAttributesData($values, $dbOperation);
                    $instance->fill($values);
                }
            }

            $instance->save();

            data_set($instance, Constant::DB_OPERATION, $dbOperation, false); //设置数据库操作
        });
    }

    /**
     * Update a record in the database.
     *
     * @param  array  $values
     * @return int
     */
    public function update(array $values) {
        $values = $this->getAttributesData($values, data_get(static::$dbOperation, 2, null));
        return parent::update($values);
    }

    /**
     * Dynamically handle calls into the query instance.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters) {
        switch ($method) {
            case 'insert':
            case 'insertGetId':
                data_set($parameters, '0', $this->getAttributesData(data_get($parameters, '0', []), data_get(static::$dbOperation, 1, null)));
                break;

            default:
                break;
        }

        return parent::__call($method, $parameters);
    }

}
