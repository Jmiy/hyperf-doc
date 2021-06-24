<?php

/**
 * base服务类
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services;

use App\Utils\Support\Facades\Redis;
use Hyperf\Utils\Arr;
use App\Models\BaseModel;
use App\Utils\Support\Facades\Cache;
use App\Utils\Routes;
use App\Services\Traits\ExistsFirst;
use App\Services\Traits\HandleCache;
use App\Constants\Constant;
use App\Utils\Response;
use App\Services\Traits\Base;
use App\Services\Traits\TraitsDb;
use App\Utils\FunctionHelper;
use App\Services\Traits\AppLog;

class BaseService {

    use ExistsFirst,
        HandleCache,
        Base,
        TraitsDb,
        AppLog;

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return static::getCustomClassName();
    }

    /**
     * 获取模型
     * @param int $storeId 店铺id
     * @param string $make 模型别名
     * @param array $parameters 参数
     * @param string $country 国家
     * @param \Hyperf\Database\Model\Relations\Relation $relation
     * @return type
     */
    public static function createModel($storeId = 1, $make = null, array $parameters = [], $country = '', &$relation = null) {

        $dbConfig = [];
        if (false === strpos($storeId, 'default_connection_')) {
            $database = DictStoreService::getByTypeAndKey($storeId, 'db', Constant::DB_DATABASE, true);
            if (empty($database)) {
                $database = DictStoreService::getByTypeAndKey(0, 'db', Constant::DB_DATABASE, true);
            }
            $dbConfig = [
                Constant::DB_DATABASE => $database,
            ];
        }

        return BaseModel::createModel($storeId, $make, $parameters, $country, $relation, $dbConfig);
    }

    /**
     * 获取 make
     * @param null|string $make
     * @return string
     */
    public static function getMake($make = null) {
        return $make === null ? static::getModelAlias() : $make;
    }

    /**
     * 获取模型
     * @param int $storeId 店铺id
     * @param string $country 国家缩写
     * @param array $parameters model初始化参数
     * @param string $make model别名 默认:null
     * @return obj
     */
    public static function getModel($storeId = 1, $country = '', array $parameters = [], $make = null) {
        return static::createModel($storeId, static::getMake($make), $parameters, $country);
    }

    /**
     * 更新
     * @param int $storeId 商城id
     * @param array $where  更新的条件
     * @param array $data  更新的数据
     * @return bool
     */
    public static function update($storeId, $where, $data) {

        if (empty($where) || empty($data)) {
            return false;
        }

        return static::getModel($storeId, '')->buildWhere($where)->update($data);
    }

    /**
     * 删除
     * @param int $storeId 商城id
     * @param array $where  删除条件
     * @return boolean
     */
    public static function delete($storeId, $where) {

        if (empty($where)) {
            return false;
        }

        return static::getModel($storeId, '')->buildWhere($where)->delete(); //逻辑删除
    }

    /**
     * 更新或者新增记录
     * @param int $storeId 商城id
     * @param array $where where条件
     * @param array $data 数据
     * @return array [
     *        'dbOperation' => data_get($rs, 'dbOperation', 'no'),
     *        'data' => $rs,
     *    ];
     */
    public static function updateOrCreate($storeId, $where, $data, $country = '', $handleData = []) {

        $model = static::getModel($storeId, $country);

        $key = serialize(Arr::collapse([
                    [
                        $model->getConnectionName(),
                        $model->getTable(),
                    ], $where
        ]));
        $key = md5($key);

        $select = data_get($handleData, Constant::DB_OPERATION_SELECT, []);
        if (!empty($select)) {
            //$select = Arr::collapse([[$model->getKeyName()], array_keys($data)]);
            $select = array_unique(Arr::collapse([[$model->getKeyName()], $select]));
            $model = $model->select($select);
        }

        $service = static::getNamespaceClass();

        $parameters = [
            function () use($storeId, $where, $data, $country, $handleData, $model) {
                return $model->updateOrCreate($where, $data, $handleData); // ->select($select) updateOrCreate：不可以添加主键id的值  updateOrInsert：可以添加主键id的值
            }
        ];
        $rs = $lock = static::handleLock([$key], $parameters);

        if ($rs === false) {//如果获取分布式锁失败，就直接查询数据
            $serialHandle = data_get($handleData, Constant::SERIAL_HANDLE, []);

            $forceRelease = data_get($serialHandle, 'forceRelease', true); //是否强制释放锁 true：是  false：否
            $releaseTime = data_get($serialHandle, 'releaseTime', 1);

            if ($forceRelease) {
                sleep($releaseTime);
                //释放锁
                $serialHandle = Arr::collapse([$serialHandle, [
                                FunctionHelper::getJobData($service, 'forceReleaseLock', [$key, 'forceRelease', 0]), //获取分布式锁失败时，强制释放锁
                ]]);
            }
//            else {
//                $rs = $model->buildWhere($where)->first();
//            }

            foreach ($serialHandle as $handle) {
                $service = data_get($handle, Constant::SERVICE_KEY, '');
                $method = data_get($handle, Constant::METHOD_KEY, '');
                $parameters = data_get($handle, Constant::PARAMETERS_KEY, []);

                if (empty($service) || empty($method) || !method_exists($service, $method)) {
                    continue;
                }

                $service::{$method}(...$parameters);
            }

            return static::updateOrCreate($storeId, $where, $data, $country, $handleData);
        }

        return [
            'lock' => $lock,
            Constant::DB_OPERATION => $lock === false ? Constant::DB_OPERATION_SELECT : data_get($rs, Constant::DB_OPERATION, Constant::DB_OPERATION_DEFAULT),
            Constant::RESPONSE_DATA_KEY => $rs,
        ];
    }

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @return array
     */
    public static function getPublicData($data, $order = []) {
        $page = $data['page'] ?? 1;
        $limit = $data[Constant::REQUEST_PAGE_SIZE] ?? 50;
        $offset = $limit * ($page - 1);
        $pagination = [
            'page_index' => $page,
            Constant::REQUEST_PAGE_SIZE => $limit,
            'offset' => $offset,
        ];

        $params[Constant::ORDER_BY] = $params[Constant::ORDER_BY] ?? '';
        if (
                $params[Constant::ORDER_BY] &&
                is_array($params[Constant::ORDER_BY]) &&
                count($params[Constant::ORDER_BY]) == 2 &&
                $params[Constant::ORDER_BY][0] &&
                $params[Constant::ORDER_BY][1] &&
                in_array($params[Constant::ORDER_BY][1], ['asc', 'desc'])
        ) {
            $order[0] = $params[Constant::ORDER_BY][0];
            $order[1] = $params[Constant::ORDER_BY][1];
        }

        return [
            Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
            'where' => [],
            'order' => $order,
        ];
    }

    /**
     * 获取数据列表
     * @param array $data
     * @param boolean $toArray 是否转化为数组 true:是 false:否 默认:false
     * @param boolean $isPage  是否分页 true:是 false:否 默认:true
     * @param array $select
     * @param boolean $isRaw 是否原始 select
     * @param boolean $isGetQuery 是否获取 query
     * @return array|\Hyperf\Database\Model\Builder
     */
    public static function getList($data, $toArray = false, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false) {

        $query = $data['query'];
        unset($data['query']);

        $data['data'] = [];
        if (empty($query)) {

            if ($isGetQuery) {
                return $query;
            }

            unset($query);
            return $data;
        }

        if ($isRaw) {
            $query = $query->selectRaw(implode(',', $select));
        } else {
            $query = $query->select($select);
        }

        if ($isPage) {
            $offset = $data[Constant::DB_EXECUTION_PLAN_PAGINATION]['offset'];
            $limit = $data[Constant::DB_EXECUTION_PLAN_PAGINATION][Constant::REQUEST_PAGE_SIZE];
            $query = $query->offset($offset)->limit($limit);
        }

        if ($isGetQuery) {
            return $query;
        }

        $_data = $query->get();

        $_data = $_data ? ($toArray ? $_data->toArray() : $_data) : ($toArray ? [] : $_data);
        $data['data'] = $_data;

        unset($query);

        return $data;
    }

    /**
     * 获取集合成员
     * @param array $member 成员
     * @return string $member
     */
    public static function getZsetMember($member) {
        return json_encode($member, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 获取集合成员原始数据
     * @param string $member 成员 json
     * @return array $member
     */
    public static function getSrcMember($member) {
        return json_decode($member, true);
    }

    /**
     * 删除缓存数据
     * @param string|array $key
     */
    public static function del($key) {
        return Redis::del($key);
    }

    /**
     * 获取账号where
     * @param int $storeId 商城id
     * @param string $account 账号
     * @param array $where    where条件
     * @return array $where   where条件
     */
    public static function whereCustomer($storeId, $account, $where = []) {

        if (empty($account)) {
            return $where;
        }

        $customer = CustomerService::customerExists($storeId, 0, $account, 0, true, [Constant::DB_TABLE_CUSTOMER_PRIMARY]);
        $where[] = [Constant::DB_TABLE_CUSTOMER_PRIMARY, '=', data_get($customer, Constant::DB_TABLE_CUSTOMER_PRIMARY, 0)];

        return $where;
    }

    /**
     * 获取后台列表记录总数
     * @param array $requestData 请求参数
     * @param \Illuminate\Database\Query\Builder $relation
     * @return int 后台列表记录总数
     */
    public static function adminCount($requestData, $relation) {
        $tags = config('cache.tags.adminCount');
        $ttl = config('cache.admin_count_ttl');
        return static::count($requestData, $relation, $tags, $ttl);
    }

    /**
     * 获取列表记录总数
     * @param array $requestData 请求参数
     * @param \Illuminate\Database\Query\Builder $builder
     * @param array $tags 缓存tag
     * @param int $ttl 缓存有效时间  单位：秒
     * @return int 列表记录总数
     */
    public static function count($requestData, $builder, $tags, $ttl) {

        if (isset($requestData['page'])) {
            unset($requestData['page']);
        }

        if (isset($requestData[Constant::REQUEST_PAGE_SIZE])) {
            unset($requestData[Constant::REQUEST_PAGE_SIZE]);
        }

        data_set($requestData, 'current_route_uri', Routes::getCurrentRouteUri());
        $key = md5(static::getZsetMember($requestData));
        return Cache::tags($tags)->remember($key, $ttl, function () use($builder) {
                    return $builder->count();
                });
    }

    public static function getResponseData($requestFailedCode, $errorCode, $data) {

        if ($data === false || $data === null) {//如果请求接口失败，就直接返回推送失败
            return Response::getDefaultResponseData($requestFailedCode);
        }

        $errors = data_get($data, 'errors');
        if (empty($errors)) {
            return true;
        }

        $_msg = [];
        foreach ($errors as $key => $value) {
            $_msg[] = $key . ': ' . implode('|', Arr::flatten($value));
        }

        $errorsMsg = implode(', ', $_msg);
        return Response::getDefaultResponseData($errorCode, $errorsMsg);
    }

//    public static function logs($level, $type = Constant::PLATFORM_SHOPIFY, $subtype = '', $keyinfo = '', $content = [], $subkeyinfo = '', $extData = [], $dataKey = null) {
//        $service = LogService::getNamespaceClass();
//        $method = 'addSystemLog'; //记录请求日志
//        $parameters = [$level, $type, $subtype, $keyinfo, $content, $subkeyinfo, $extData];
//
//        if ($dataKey === null) {
//            return FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters));
//        }
//
//        $data = data_get($extData, Constant::RESPONSE_TEXT . Constant::LINKER . $dataKey, []);
//        if (empty($data)) {
//            return FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters));
//        }
//
//        foreach ($data as $item) {
//            $parameters = [$level, $type, $subtype, $keyinfo, $content, $subkeyinfo, $item];
//            FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters));
//        }
//
//        return true;
//    }

    /**
     * 强制转换为字符串
     * @param mix $value
     * @return string $value
     */
//    public static function castToString($value) {
//        return (string) $value;
//    }

    /**
     * 获取配置
     * @param int $storeId 品牌id
     * @param string $configType 配置类型
     * @param array $extWhere 扩展where
     * @param array|null $orderby 排序
     * @param string|null $country 国家
     * @return collect|type 配置
     */
    public static function getConfig($storeId, $configType = Constant::ORDER, $extWhere = [], $orderby = null, $country = null)
    {

//        $extWhere = $extWhere ? $extWhere : [
//            Constant::DICT => [
//                //Constant::DB_TABLE_DICT_KEY => ['is_force_release_order_lock', 'release_time', 'each_pull_time', 'ttl'],
//            ],
//            Constant::DICT_STORE => [],
//        ];
        $dictSelect = [
            Constant::DICT => [
                Constant::DB_TABLE_TYPE, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE,
            ],
            Constant::DICT_STORE => [
                Constant::DB_TABLE_TYPE, Constant::DB_TABLE_STORE_DICT_KEY, Constant::DB_TABLE_STORE_DICT_VALUE,
            ],
        ];
        return DictService::getDistData($storeId, $configType, Constant::DB_TABLE_STORE_DICT_KEY, Constant::DB_TABLE_STORE_DICT_VALUE, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE, $orderby, $country, $extWhere, $dictSelect); //配置数据
    }

    /**
     * 获取 合并 商城字典数据 和 系统字典数据  优先商城字典配置，如果商城字典没有配置，就以系统字典为准
     * @param $storeId 品牌id
     * @param string $configType 配置类型
     * @param array $extWhere 扩展where
     * @param array|null $orderby 排序
     * @param string|null $country 国家
     * @return collect|type 配置
     */
    public static function getDistConfig($storeId, $configType = Constant::ORDER, $extWhere = [], $orderby = null, $country = null)
    {
        return static::getMergeConfig($storeId, $configType, $extWhere, $orderby, $country); //配置数据
    }

    /**
     * 获取 合并 商城字典数据 和 系统字典数据  优先商城字典配置，如果商城字典没有配置，就以系统字典为准
     * @param $storeId 品牌id
     * @param string $configType 配置类型
     * @param array $extWhere 扩展where
     * @param array|null $orderby 排序
     * @param string|null $country 国家
     * @return collect|type 配置
     */
    public static function getMergeConfig($storeId, $configType = Constant::ORDER, $extWhere = [], $orderby = null, $country = null)
    {
        $dictSelect = [
            Constant::DICT => [
                Constant::DB_TABLE_TYPE, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE,
            ],
            Constant::DICT_STORE => [
                Constant::DB_TABLE_TYPE, Constant::DB_TABLE_STORE_DICT_KEY, Constant::DB_TABLE_STORE_DICT_VALUE,
            ],
        ];
        return DictService::getDistConfig($storeId, $configType, Constant::DB_TABLE_STORE_DICT_KEY, Constant::DB_TABLE_STORE_DICT_VALUE, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE, $orderby, $country, $extWhere, $dictSelect); //配置数据
    }

}
