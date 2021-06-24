<?php

namespace App\Utils;

use App\Services\DictStoreService;
use Hyperf\Utils\Str;
use Hyperf\Utils\Arr;
use Carbon\Carbon;
use App\Services\BaseService;
use App\Utils\Support\Facades\Queue;
use App\Jobs\PublicJob;
use App\Services\Monitor\MonitorServiceManager;
use App\Constants\Constant;

use Hyperf\Utils\Context;

class FunctionHelper {

    /**
     * 随机数生成
     * @author Jmiy
     * @param int $length
     * @param string $type number,low,uper,string,mixd
     * @return string
     */
    public static function randomStr($length = 6, $type = 'mixd') {

        $random = Str::random($length);

        return $random;
    }

    /**
     * 获取需要查询的字段
     * @param string $table 表名或者别名
     * @param array $addColumns 额外要查询的字段 array('s.softid','s.file1024_md5 f1024md5')
     * @return string app列表需要的字段
     */
    public static function getColumns($columns, $addColumns = array()) {

        $columns = array_merge($columns, $addColumns);
        $columns = array_filter(array_unique($columns));
        foreach ($columns as $key => $val) {
            if (is_numeric($val)) {
                unset($columns[$key]);
            }
        }

        return $columns;
    }

    /**
     * 获取有效数据
     * @return string|array
     */
    public static function getValidData($data = null, $name = null, $default = null) {
        return data_get($data, $name, $default);
    }

    /**
     * 获取统一流水数据格式
     * @param array $data
     * @return array
     */
    public static function getHistoryData($data, $expansionData = []) {
        $_data = [
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $data[Constant::DB_TABLE_CUSTOMER_PRIMARY] ?? 0,
            Constant::DB_TABLE_VALUE => $data[Constant::DB_TABLE_VALUE] ?? 0,
            Constant::DB_TABLE_ADD_TYPE => $data[Constant::DB_TABLE_ADD_TYPE] ?? 1,
            Constant::DB_TABLE_ACTION => $data[Constant::DB_TABLE_ACTION] ?? 'signup',
            Constant::DB_TABLE_EXT_ID => $data[Constant::DB_TABLE_EXT_ID] ?? 0,
            Constant::DB_TABLE_EXT_TYPE => $data[Constant::DB_TABLE_EXT_TYPE] ?? 'Customer',
            Constant::DB_TABLE_REMARK => $data[Constant::DB_TABLE_REMARK] ?? '',
            Constant::DB_TABLE_CONTENT => $data[Constant::DB_TABLE_CONTENT] ?? '',
            'sub_id' => $data['sub_id'] ?? 0,
            Constant::DB_TABLE_ACT_ID => $data[Constant::DB_TABLE_ACT_ID] ?? 0,
        ];

        return Arr::collapse([$_data, $expansionData]);
    }

    /**
     * Get the client IP address.
     *
     * @return string
     */
    public static function getClientIP($ip = null)
    {
        return getClientIP($ip);
    }

    /**
     * Checks if the ip is valid.
     *
     * @param string $ip
     *
     * @return bool
     */
    public static function isValid($ip) {
        return isValidIp($ip);
    }

    /**
     * 获取用户国家
     * @param string $ip
     * @param string $country
     * @return string
     */
    public static function getCountry($ip = '', $country = '') {

        if ($country) {
            return $country;
        }

        $ip = $ip ? $ip : static::getClientIP();

        $ipIsValid = static::isValid($ip);
        $key = 'service';
        $geoipData = geoip()->setConfig($key, config('geoip.service'))->getLocation($ip)->toArray();
        $country = data_get($geoipData, 'iso_code', '');
        if (empty($country)) {

//            $exceptionName = '通过api获取ip国家失败：';
//            $messageData = ['ip:' . $ip, ' ip是否有效：' . ($ipIsValid ? '是' : '否')];
//            $message = implode(',', $messageData);
//            $parameters = [$exceptionName, $message, ''];
//            MonitorServiceManager::handle('Ali', 'Ding', 'report', $parameters);

            $value = 'maxmind_database';
            $geoipData = geoip()->setConfig($key, $value)->getLocation($ip)->toArray();
            $country = data_get($geoipData, 'iso_code', '');

//            if (empty($country)) {
//                $exceptionName = '通过maxmind_database获取ip国家失败：';
//                $parameters = [$exceptionName, $message, ''];
//                MonitorServiceManager::handle('Ali', 'Ding', 'report', $parameters);
//            }
        }

        if (empty($country)) {//记录日志

            $key = implode(':', ['log',__FUNCTION__, $ip]);
            $ttl = BaseService::getTtl();
            $handleCacheData = static::getJobData(BaseService::getNamespaceClass(), 'remember', [$key, $ttl, function () use($ip, $ipIsValid) {
                $level = 'info';
                $type = 'ip';
                $subtype = 'country';
                $keyinfo = $ip;
                $content = [];
                $subkeyinfo = $ipIsValid ? 1 : 0;
                $extData = [];
                $dataKey = null;
                return BaseService::logs($level, $type, $subtype, $keyinfo, $content, $subkeyinfo, $extData, $dataKey);
            }]);
            BaseService::handleCache(BaseService::getCacheTags(), $handleCacheData);
        }

        return $country ? $country : ($ipIsValid ? 'US' : '');
    }

    /**
     * 设置系统时区 https://www.php.net/manual/en/timezones.php
     * @param int $storeId 店铺id
     * @param string $timezone 时区
     * @param string $dbTimezone 数据库时区
     * @param string|null $appEnv 环境
     * @return boolean
     */
    public static function setTimezone($storeId, $timezone = '', $dbTimezone = '', $appEnv = null)
    {
        $storeTimezone = config('app.store_timezone.' . $storeId, []);
        if (empty($storeTimezone)) {
            $extWhere = [
                Constant::DICT => [],
                Constant::DICT_STORE => [
                    Constant::DB_TABLE_COUNTRY => $appEnv ?? '',
                ],
            ];
            $storeTimezone = BaseService::getMergeConfig($storeId, 'base', $extWhere);
        }

        if (empty($timezone) && $storeId) {
            $timezone = data_get($storeTimezone, 'timezone', $timezone);
        }

        if ($timezone) {
            date_default_timezone_set($timezone); //设置app时区 https://www.php.net/manual/en/timezones.php
        }

        return true;
    }

    /**
     * 获取展示时间
     * @param int $second 秒
     * @return string
     */
    public static function time2string($second) {
        $day = floor($second / (3600 * 24));
        $second = abs($second % (3600 * 24));
        $hour = floor($second / 3600);
        $second = $second % 3600;
        $minute = floor($second / 60);
        $sec = $second % 60;
        return $day . '天' . $hour . '小时' . $minute . '分' . $sec . '秒';
    }

    /**
     * 账号脱敏规则： 前面取三个字母，后面2个字母，中间全部隐藏，域名显示  muc***az@outlook.com   不够隐藏的就从第四个字母开始隐藏，域名都显示出来   所有品牌统一用这个规则
     * @param string $account
     * @return string
     */
    public static function handleAccount($account = '') {

        if (empty($account)) {
            return $account;
        }

        $start = strrpos($account, '@');

        $end = $start !== false ? ($start >= 3 ? 3 : $start) : (Str::length($account) >= 3 ? 3 : Str::length($account));

        $start = $start !== false ? $start : 0;

        for ($i = 2; $i >= 0; $i--) {
            if ($start - $i >= 0) {
                $start = $start - $i;
                break;
            }
        }

        return Str::substr($account, 0, $end) . '***' . Str::substr($account, $start);
    }

    /**
     * 处理时间数据
     * @param mix $data
     * @param string $format 时间格式
     * @param string $timezone 时区
     * @return mix 时间数据
     */
    public static function handleDatetime($data, $format = null, $attributes = null, $timezone = null) {

        $timeData = static::getTimeAt($data);

        $timeValue = strtotime($timeData);
        if ($timeValue !== false) {
            $timeData = $timeValue;
        }

        if (!is_numeric($timeData)) {
            return $timeData;
        }

        $timeData = Carbon::createFromTimestamp($timeData);
        if ($timezone !== null) {
            $timeData = $timeData->setTimezone($timezone);
        }

        if ($format !== null) {
            $timeData->format($format);
        }

        if ($attributes) {
            if (is_array($attributes)) {
                $_data = [];
                foreach ($attributes as $attribute) {
                    $_data[$attribute] = $attribute == 'date' ? $timeData->rawFormat($format) : data_get($timeData, $attribute, null);
                }

                return $_data;
            }

            return $attributes == 'date' ? $timeData->rawFormat($format) : data_get($timeData, $attributes, null);
        }

        return $timeData;
    }

    /**
     * 处理数据
     * @param array|obj $value
     * @param string|array $field  [
      'field' => 'interests.*.interest',//数据字段
      'data' => [],//数据映射map
      'dataType' => 'string',//数据类型
      'dateFormat' => 'Y-m-d H:i:s',//数据格式
      'time' => '+1year',//时间处理句柄
      'glue' => ',',//分隔符或者连接符
      'is_allow_empty' => true,//是否允许为空 true：是  false：否
      'default' => '',//默认值$default
      'only' => [],
      'callback' => [
      "amount" => function($item) {
      return data_get($item, 'item_price_amount', 0) - data_get($item, 'promotion_discount_amount', 0);
      },
      ],
      ]
     * @return string|array
     */
    public static function handleData($value, $field) {

        $fieldData = []; //数据映射map
        $dataType = ''; //数据类型
        $glue = ','; //分隔符或者连接符
        $default = ''; //默认值$default
        $dateFormat = 'Y-m-d H:i:s'; //数据格式
        $time = ''; //时间处理句柄
        $isAllowEmpty = true; //是否允许为空 true：是  false：否
        $only = []; //只要 only 里面的字段
        $callback = null; //回调
        $srcFiel = $field;
        if (is_array($field)) {
            $fieldData = data_get($field, 'data', []);
            $dataType = data_get($field, 'dataType', $dataType);
            $dateFormat = data_get($field, 'dateFormat', $dateFormat);
            $glue = data_get($field, 'glue', $glue);
            $default = data_get($field, 'default', $default);
            $time = data_get($field, 'time', $time);
            $isAllowEmpty = data_get($field, 'is_allow_empty', $isAllowEmpty);
            $only = data_get($field, 'only', $only); //只要 only 里面的字段
            $callback = data_get($field, 'callback', $callback); //回调
            $field = data_get($field, 'field', $field);
        }

        if (strpos($field, '{or}') !== false) {
            $_fieldData = explode('{or}', $field);
            $_value = $default;
            foreach ($_fieldData as $orField) {
                $_field = [
                    'field' => $orField,
                    'data' => $fieldData,
                    'dataType' => $dataType,
                    'dateFormat' => $dateFormat,
                    'glue' => $glue,
                    'default' => $default,
                ];

                $_value = static::handleData($value, $_field);
                if ($_value) {
                    break;
                }
            }
            $value = $_value;
        } else if (strpos($field, '{connection}') !== false) {
            $_fieldData = explode('{connection}', $field);
            $_value = [];
            foreach ($_fieldData as $connectionField) {
                $_field = [
                    'field' => $connectionField,
                    'data' => $fieldData,
                    'dataType' => $dataType,
                    'dateFormat' => $dateFormat,
                    'glue' => $glue,
                    'default' => $default,
                ];
                $_value[] = static::handleData($value, $_field);
            }
            $value = $_value;
        } else if (strpos($field, '|') !== false) {

            $segments = explode('.', $field);
            $field = [];
            foreach ($segments as $segment) {

                if (strpos($segment, '|') === false) {
                    $field[] = $segment;
                    continue;
                }

                if ($field) {
                    $field = implode('.', $field);
                    $value = data_get($value, $field, $default);
                    $field = [];
                }

                $_segments = explode('|', $segment);
                $nextSegment = '';
                foreach ($_segments as $_key => $_segment) {

                    if ($nextSegment == $_segment) {
                        continue;
                    }

                    switch ($_segment) {
                        case 'json':
                            $nextSegment = data_get($_segments, $_key + 1, '');
                            $value = data_get($value, $nextSegment, $default);
                            $value = Arr::accessible($value) ? $value : json_decode($value, true);
                            $value = Arr::accessible($value) ? $value : $default;
                            break;

                        default:
                            $value = data_get($value, $_segment, $default);
                            break;
                    }
                }
            }

            if ($field) {
                $field = implode('.', $field);
                $value = data_get($value, $field, $default);
            }
        } else {
            $value = data_get($value, $field, $default);
        }

        if (!$isAllowEmpty && empty($value)) {//如果不允许为空并且当前值为空，就使用默认值$default
            $value = $default;
        }

        if ($fieldData) {
            $value = $value === null ? '' : $value;
            $value = data_get($fieldData, $value, $default);
        }

        if ($callback) {
            foreach ($callback as $key => $func) {
                if (Arr::accessible($value) && !Arr::isAssoc($value)) {//如果是 索引数组，就进行递归处理
                    foreach ($value as $_key => $item) {
                        if (Arr::isAssoc($item)) {
                            data_set($value, $_key, static::handleData($item, $srcFiel));
                        }
                    }
                } else {
                    if (false === strpos($key, '{nokey}')) {
                        data_set($value, $key, $func($value));
                    } else {
                        $func($value);
                    }
                }
            }
        }

        if ($only) {
            if (Arr::accessible($value) && !Arr::isAssoc($value)) {
                foreach ($value as $key => $item) {
                    $srcFiel['field'] = null;
                    data_set($value, $key, static::handleData($item, $srcFiel));
                }
            } else {
                $value = Arr::only($value, $only);
            }
        }


//        var_dump($fieldData);
//        var_dump($value);
//        exit;
//        if (strpos($field, '{or}') !== false) {
//            dd($field, $value);
//        }

        switch ($dataType) {
            case 'string':
                if (Arr::accessible($value)) {

                    if (!is_array($value)) {
                        $value = $value->toArray();
                    }

                    if (is_array($value)) {
                        $value = array_unique(array_filter($value));
                        $value = implode($glue, $value);
                    }
                }
                $value = $value . '';

                break;

            case 'array':
                $value = is_array($value) ? $value : explode($glue, $value);
                $value = array_filter(array_unique($value));
                break;

            case 'datetime':

                $value = static::handleTime($value, $time, $dateFormat);
                if ($value === '0000-00-00 00:00:00') {
                    $value = '';
                }
                break;

            case 'int':
                $value = intval($value);
                break;

            case 'price':
                $dateFormat = $dateFormat ? $dateFormat : [2, ".", ''];
                $value = number_format(floatval($value), ...$dateFormat);
                break;

            default:
                break;
        }

        return $value;
    }

    public static function dbDebug(&$dbExecutionPlan, $storeId = 0, $relationKey = '', $query = null, $dbConnection = null) {

        if (empty(data_get($dbExecutionPlan, 'sqlDebug', false))) {
            return false;
        }

        if ($dbConnection) {
            $dbExecutionPlan['sql'][$storeId] = [
                'dbConnection' => $dbConnection,
                'docComment' => ['query' => $query->toSql(), 'bindings' => $query->getBindings(), 'relation' => $relationKey],
            ];
            $dbConnection->enableQueryLog();
        } else {
            foreach ($dbExecutionPlan['sql'] as $storeId => $sqlData) {
                dump($sqlData['dbConnection']->getQueryLog());
                //dump($sqlData['docComment']);
            }
        }
    }

    /**
     * 获取响应数据
     * @return mix 当前路由uri
     */
    public static function handleRelation($data = null, &$dbExecutionPlan = []) {
        $with = data_get($dbExecutionPlan, 'with', []);
        if (empty($with)) {
            return $data;
        }

        foreach ($with as $relationKey => $relationData) {
            $data = $data->with([$relationKey => function($relation) use($relationData, $relationKey, &$dbExecutionPlan) {

                    $setConnection = data_get($relationData, 'setConnection', false);
                    $storeId = data_get($relationData, 'storeId', 0);
                    if ($setConnection) {
                        BaseService::createModel($storeId, null, [], '', $relation); //设置关联对象relation 数据库连接
                    }

                    $morphToConnection = data_get($relationData, 'morphToConnection', []);
                    if ($morphToConnection) {
                        $relation->getModel()->setMorphToConnection($morphToConnection);
                    }

                    $where = data_get($relationData, 'where', []);
                    if ($where) {
                        $relation->buildWhere($where);
                    }

                    $select = data_get($relationData, 'select', []);
                    if ($select) {
                        $relation->select($select);
                    }

                    $groupBy = data_get($relationData, 'groupBy', '');
                    if ($groupBy) {
                        $relation = $relation->groupBy($groupBy);
                    }

                    $orders = data_get($relationData, 'orders', []);
                    if ($orders) {
                        foreach ($orders as $order) {
                            $relation->orderBy($order[0], $order[1]);
                        }
                    }

                    $offset = data_get($relationData, 'offset', null);
                    if ($offset !== null) {
                        $relation->offset($offset);
                    }

                    $limit = data_get($relationData, 'limit', null);
                    if ($limit !== null) {
                        $relation->limit($limit);
                    }

                    static::dbDebug($dbExecutionPlan, $storeId, $relationKey, $relation, $relation->getRelated()->getConnection());

                    static::handleRelation($relation, $relationData);
                }
            ]);
        }
        return $data;
    }

    /**
     * 处理响应数据
     * @param \Illuminate\Database\Eloquent\Collection  $data obj $data 数据句柄
     * @param array $dbExecutionPlan  sql执行计划
     * @param boolean $flatten  是否将数据平铺  true：是  false：否
     * @param boolean $isGetQuery 是否获取查询句柄Query true：是  false:否
     * @param string $dataStructure 数据结构
     * @return obj|array  响应数据
     */
    public static function handleResponseData($data = null, &$dbExecutionPlan = [], $flatten = false, $isGetQuery = false, $dataStructure = 'one') {

        if ($data->isEmpty()) {
            return [];
        }

        $allData = $data->toArray();

        $parentData = data_get($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_PARENT, []);
        $with = data_get($dbExecutionPlan, 'with', []);
        $itemHandleData = data_get($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA, []); //数据行整体处理
        foreach ($allData as $index => $data) {
            $forgetKeys = [];

            $handleData = data_get($parentData, 'handleData', []);
            if ($handleData) {
                foreach ($handleData as $key => $field) {
                    data_set($data, $key, static::handleData($data, $field));
                }
            }

            $unset = data_get($parentData, 'unset', []);
            if ($unset) {
                $forgetKeys = Arr::collapse([$forgetKeys, $unset]);
            }

            foreach ($with as $relationKey => $relationData) {

                $relation = data_get($relationData, 'relation', '');
                $relationDbDefaultData = data_get($relationData, 'default', []);

                $relationDbData = data_get($data, $relationKey, []);
                $handleData = data_get($relationData, 'handleData', []);
                if (empty($relationDbData) && $relation == 'hasOne') {//如果关系数据为空，就设置默认值
                    $select = data_get($with, $relationKey . '.select', []);
                    foreach ($select as $key) {

                        if (stripos($key, ' as ') !== false) {
                            $segments = preg_split('/\s+as\s+/i', $key);
                            $key = end($segments) ? end($segments) : $key;
                        }

                        $arrIndex = $relationKey . '.' . $key;
                        data_set($data, $arrIndex, data_get($data, data_get($relationDbDefaultData, $key, ''), (isset($handleData[$arrIndex]['default']) ? $handleData[$arrIndex]['default'] : '')));
                    }
                }

                if ($handleData) {
                    if ($relation && $relation != 'hasOne') {
                        foreach ($relationDbData as $_index => $item) {
                            foreach ($handleData as $key => $field) {
                                data_set($data, $relationKey . '.' . $_index . '.' . $key, static::handleData($item, $field));
                            }
                        }
                    } else {
                        foreach ($handleData as $key => $field) {
                            data_set($data, $key, static::handleData($data, $field));
                        }
                    }
                }

                $unset = data_get($relationData, 'unset', []);
                if ($unset) {
                    $forgetKeys = Arr::collapse([$forgetKeys, $unset]);
                }
            }

            if ($itemHandleData) {
                $data = static::handleData($data, $itemHandleData);
            }

            if ($flatten) {
                $data = Arrays\MyArr::flatten($data);
            }

            if ($forgetKeys) {
                Arr::forget($data, $forgetKeys);
            }

            $allData[$index] = $data;
        }

        $dataStructure = strtolower($dataStructure);
        switch ($dataStructure) {
            case 'one':
                $data = Arr::first($allData);
                break;

            default:
                $data = $allData;
                break;
        }

        return $data;
    }

    /**
     * 获取响应数据
     * @param obj $builder 数据库操作句柄
     * @param array $dbExecutionPlan  sql执行计划
     * @param boolean $flatten  是否将数据平铺  true：是  false：否
     * @param boolean $isGetQuery 是否获取查询句柄Query true：是  false:否
     * @param string $dataStructure 数据结构
     * @return obj|array  响应数据
     */
    public static function getResponseData($builder = null, &$dbExecutionPlan = [], $flatten = false, $isGetQuery = false, $dataStructure = 'one') {

        $parentData = data_get($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_PARENT, []);

        $countBuilder = null;
        $isPage = data_get($parentData, 'isPage', false); //是否获取分页
        $isOnlyGetCount = data_get($parentData, 'isOnlyGetCount', false); //是否只要分页数据
        $pagination = data_get($parentData, 'pagination', []); //分页数据
        if (empty($builder)) {
            if (empty($parentData)) {
                return $builder;
            }

            $builder = data_get($parentData, 'builder', null);
            $countBuilder = $builder ? (clone $builder) : null;
            if (empty($builder)) {
                $make = data_get($parentData, 'make', '');
                if (empty($make)) {
                    return $builder;
                }

                $storeId = data_get($parentData, Constant::DB_EXECUTION_PLAN_STOREID, 0);
                $parameters = data_get($parentData, 'parameters', []);
                $country = data_get($parentData, 'country', []);

                if (false !== strpos($make, '\\App\\Services\\')) {
                    $builder = $make::getModel($storeId, $country, $parameters);
                } else {
                    $builder = BaseService::createModel($storeId, $make, $parameters, $country);
                }

                $from = data_get($parentData, 'from', '');
                if ($from) {
                    $builder = $builder->from($from);
                }


                $joinData = data_get($parentData, Constant::DB_EXECUTION_PLAN_JOIN_DATA);
                if ($joinData) {
                    foreach ($joinData as $joinItem) {
                        $table = data_get($joinItem, Constant::DB_EXECUTION_PLAN_TABLE, '');
                        $first = data_get($joinItem, Constant::DB_EXECUTION_PLAN_FIRST, '');
                        $operator = data_get($joinItem, Constant::DB_TABLE_OPERATOR, null);
                        $second = data_get($joinItem, Constant::DB_EXECUTION_PLAN_SECOND, null);
                        $type = data_get($joinItem, Constant::DB_TABLE_TYPE, 'inner');
                        $where = data_get($joinItem, Constant::DB_EXECUTION_PLAN_WHERE, false);
                        $builder = $builder->join($table, $first, $operator, $second, $type, $where);
                    }
                }

                $where = data_get($parentData, 'where', []);
                if ($where) {
                    $builder = $builder->buildWhere($where);
                }

                if ($isPage || $isOnlyGetCount) {
                    $countBuilder = clone $builder;
                }

                $select = data_get($parentData, 'select', []);
                if ($select) {
                    $builder = $builder->select($select);
                }

                $groupBy = data_get($parentData, 'groupBy', '');
                if ($groupBy) {
                    $builder = $builder->groupBy($groupBy);
                }

                $orders = data_get($parentData, 'orders', []);
                if ($orders) {
                    $orders = is_array($orders) ? $orders : [$orders];
                    foreach ($orders as $order) {

                        if (empty($order)) {
                            continue;
                        }

                        if (is_string($order)) {
                            $builder = $builder->orderByRaw($order);
                        } else if (is_array($order)) {
                            $column = data_get($order, 0, '');
                            $direction = data_get($order, 1, 'asc');
                            if ($column) {
                                $builder = $builder->orderBy($column, $direction);
                            }
                        }
                    }
                }

                $offset = data_get($parentData, 'offset', null);
                if ($offset !== null) {
                    $builder = $builder->offset($offset);
                }

                $limit = data_get($parentData, 'limit', null);
                if ($limit !== null) {
                    $builder = $builder->limit($limit);
                }
            }
        }

        static::dbDebug($dbExecutionPlan, data_get($builder->getModel(), 'storeId', $builder->getModel()->getStoreId()), Constant::DB_EXECUTION_PLAN_PARENT, $builder, $builder->getConnection());

        $count = true;
        if (!$isGetQuery && $countBuilder && ($isPage || $isOnlyGetCount)) {
            $limit = data_get($pagination, 'page_size', 10);
            $count = $countBuilder->count();
            data_set($pagination, 'total', $count);
            data_set($pagination, 'total_page', ceil($count / $limit));
        }

        if ($isOnlyGetCount) {
            static::dbDebug($dbExecutionPlan);
            return $pagination;
        }

        if (empty($count)) {
            static::dbDebug($dbExecutionPlan);
            return $isPage ? ['data' => [], 'pagination' => $pagination] : [];
        }

        static::handleRelation($builder, $dbExecutionPlan);

        if ($isGetQuery) {
            static::dbDebug($dbExecutionPlan);
            return $isPage ? ['countBuilder' => $countBuilder, 'builder' => $builder] : $builder;
        }

        $data = $builder->get();

        static::dbDebug($dbExecutionPlan);

        $data = static::handleResponseData($data, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);

        return $isPage ? ['data' => $data, 'pagination' => $pagination,] : $data;
    }

    /**
     * 获取 job 执行配置数据
     * @param string $service
     * @param string $method
     * @param array $parameters
     * @return array
     */
    public static function getJobData($service, $method, $parameters, $request = null, $extData = []) {
        return Arr::collapse([
                    [
                        Constant::SERVICE_KEY => $service,
                        Constant::METHOD_KEY => $method,
                        Constant::PARAMETERS_KEY => $parameters,
                        Constant::REQUEST_DATA_KEY => $request ?? app('request')->all(),
                    ],
                    $extData
        ]);
    }

    /**
     * Push a new job onto the queue.
     *
     * @param  string|object|array  $job
     * @param  mixed   $data
     * @param  string|null  $queue 队列名称
     * @return mixed
     */
    public static function pushQueue($job, $data = '', $queue = null)
    {

        $delay = data_get($job, 'delay', 0);

        if ($delay > 0) {//如果job要延时执行，就使用延时队列
            return static::laterQueue($delay, $job, $data, $queue);
        }

        try {
            $connection = data_get($job, 'queueConnectionName');
            $queue = $queue !== null ? $queue : data_get($job, 'queue');

            if(is_array($job)){
                $data = ['data'=>$job];
                $job = PublicJob::class;
                //$job = new PublicJob($job);
            }

            return Queue::push($job, $data, $delay, $connection, $queue);

        } catch (\Exception $exc) {

        }

        return false;
    }

    /**
     * 创建延时任务
     * @param int $time
     * @param  string|object|array  $job
     * @param  mixed   $data
     * @param  string|null  $queue 队列名称
     * @return mixed
     */
    public static function laterQueue($time, $job, $data = '', $queue = null) {

        //later 创建延时任务
        //Queue::later(10, new ExampleJob(['a' => 123]), null, 'QueueName');

        try {

            $connection = data_get($job, 'queueConnectionName');
            $queue = $queue !== null ? $queue : data_get($job, 'queue');

            if(is_array($job)){
                $data = ['data'=>$job];
                $job = PublicJob::class;
            }

            return Queue::push($job, $data, $time, $connection, $queue);

        } catch (\Exception $exc) {

        }

        return false;
    }

    /**
     * 时间转化
     * @return array
     */
    public static function getShowTime($data) {
        return static::getTimeAt($data === null ? 'null' : $data);
    }

    /**
     * 时间转化
     * @return array
     */
    public static function getTimeAt($time) {

        if ($time === null) {
            return $time;
        }

        $timeData = [
            '不限' => null,
            'null' => '不限',
        ];

        return $time === 'all' ? $timeData : data_get($timeData, $time, $time);
    }

    /**
     * 国家转化
     * @param string $country
     * @return string
     */
    public static function getDbCountry($country = null) {
        $countryData = [
            '不限' => 'all',
            'all' => '不限',
        ];
        return data_get($countryData, $country, $country);
    }

    /**
     * 是否转化
     * @param string $whether
     * @return string
     */
    public static function getWhetherData($whether = '是') {
        $whetherData = [
            Constant::WHETHER_YES_VALUE => Constant::WHETHER_YES_VALUE_CN,
            Constant::WHETHER_NO_VALUE => Constant::WHETHER_NO_VALUE_CN,
            Constant::WHETHER_YES_VALUE_CN => Constant::WHETHER_YES_VALUE,
            Constant::WHETHER_NO_VALUE_CN => Constant::WHETHER_NO_VALUE,
        ];
        return data_get($whetherData, $whether, $whether);
    }

    /**
     * 获取产品列表数据
     * @param Illuminate\Database\Query\Builder $builder 查询构造器
     * @param int|null $page  分页  页码
     * @param int|null $pageSize  分页 每页记录条数
     * @param int|null $medium    兴趣
     * @param array $cateIds      分类id数据
     * @param boolean $isNeedModule  是否需要模块数据  true：需要  false:不需要  默认：true
     * @return array 产品列表数据
     */
    public static function getProductListData($builder, $page = null, $pageSize = null, $medium = null, $cateIds = [], $isNeedModule = true) {

        if ($page && $pageSize) {
            $offset = ($page - 1) * $pageSize;
            $builder = $builder->offset($offset);
        }

        if ($pageSize) {
            $builder = $builder->limit($pageSize);
        }

        //\Illuminate\Support\Facades\DB::enableQueryLog();
        $productData = $builder->with('advance_promotion')
                ->get()
                ->each(function ($item, $key) {
                    if ($item->advance_promotion) {
                        $item->skus = null;
                        $item->quantity_skill = $item->advance_promotion->quantity_skill;
                        $item->price_skill = $item->advance_promotion->price_skill;
                        return \App\Model\Topic_promotion::handleProduct($item);
                    } else {
                        return $item;
                    }
                })
                ->keyBy('product_id')
                ->toArray();
        $_data = array_values($productData);

        if ($isNeedModule) {//如果需要模块数据，就获取模块数据
            //获取模块数据
            $moduleData = \App\Model\Module::getMoudelsProductImages($cateIds, $page, $pageSize, $medium, 4, 1);

            $is_insert = false;
            foreach ($moduleData as $key => $item) {
                if ($item['sort'] > 0) {
                    $is_insert = true;
                    $_offset = $item['sort'] - $offset - 1;
                    array_splice($_data, $_offset, 0, [$item]); //将
                }
            }

            if (!$is_insert) {
                $offset = count($_data);
                array_splice($_data, $offset, 0, $moduleData); //将广告数据插入到最后面
            }
        }

        $data['data'] = $_data;
        $data['prod_id'] = implode(',', array_keys($productData));

        return $data;
    }

    /**
     * 获取统一的列表数据
     * @param array $data
     * @return array 统一的列表数据
     */
    public static function getListData($data) {

        if (empty($data['data'])) {
            return $data;
        }

        $now = \Carbon\Carbon::now()->toDateTimeString(); //服务器当前时间
        foreach ($data['data'] as $key => $item) {
            if ($item['data_type'] == 'product') {//如果是产品数据，就根据秒杀活动调整价格
                if ($item['advance_promotion'] && $item['advance_promotion']['start_at'] <= $now && $now <= $item['advance_promotion']['end_at']) {
                    $data['data'][$key]['special'] = $item['shop_price'];
                    $data['data'][$key]['price'] = $item['market_price'];
                }
                $data['data'][$key]['discount'] = $data['data'][$key]['price'] ? (($data['data'][$key]['special'] - $data['data'][$key]['price']) / $data['data'][$key]['price']) : 0;
            }
        }

        $handleKeys = [
            1 => ['image'], //图片
            //100 => [],//html
            200 => ['price', 'special'], //价格
            201 => ['discount'], //折扣
        ];
        $data = \App\Utils\Resources::handleResources($data, $handleKeys); //图片cdn加速

        return $data;
    }

//    public static function hash($str) {
//
//        // hash(i) = hash(i-1) * 33 + str[i]
//        $hash = 0;
//        $s = md5($str);
//        $seed = 5;
//        $len = 32;
//
//        for ($i = 0; $i < $len; $i++) {
//            // (hash << 5) + hash 相当于 hash * 33
//            //$hash = sprintf("%u", $hash * 33) + ord($s{$i});
//            //$hash = ($hash * 33 + ord($s{$i})) & 0x7FFFFFFF;
//            $hash = ($hash << $seed) + $hash + ord($s{$i});
//        }
//
//        return $hash & 0x7FFFFFFF;
//    }
//
//    public static function myHash($str) {
//        return static::hash($str);
//    }
//
//    // server列表
//    public static $_server_list = array();
//    // 延迟排序，因为可能会执行多次addServer
//    public static $_layze_sorted = FALSE;
//
//    public static function addServer($server) {
//        $hash = static::hash($server);
//        static::$_layze_sorted = FALSE;
//
//        if (!isset(static::$_server_list[$server])) {
//            static::$_server_list[$server] = $hash;
//        }
//
//        return static::$_server_list;
//    }
//
//    public static function find($key) {
//        // 排序
//        if (!static::$_layze_sorted) {
//            arsort(static::$_server_list);
//            static::$_layze_sorted = TRUE;
//        }
//
//        $hash = static::hash($key);
//        $len = sizeof(static::$_server_list);
//        if ($len == 0) {
//            return FALSE;
//        }
//
//        $keys = array_keys(static::$_server_list);
//        $values = array_values(static::$_server_list);
//
//        // 如果不在区间内，则返回最后一个server
//        if ($hash <= $values[0]) {
//            return $keys[0];
//        }
//
//        if ($hash >= $values[$len - 1]) {
//            return $keys[$len - 1];
//        }
//
//        foreach ($values as $key => $pos) {
//            $next_pos = NULL;
//            if (isset($values[$key + 1])) {
//                $next_pos = $values[$key + 1];
//            }
//
//            if (is_null($next_pos)) {
//                return $keys[$key];
//            }
//
//            // 区间判断
//            if ($hash >= $pos && $hash <= $next_pos) {
//                return $keys[$key];
//            }
//        }
//    }

    /**
     * 获取折扣
     * @param int $discount 折扣
     * @return int $discount 折扣
     */
    public static function getDiscount($discount) {
        $discount = intval($discount); //折扣
        $discount = $discount > 100 ? 100 : $discount;
        $discount = $discount < 0 ? 0 : $discount;
        return $discount;
    }

    /**
     * 获取折扣价
     * @param float $price 产品销售价
     * @param int $discount 折扣
     * @return float 折扣价
     */
    public static function getDiscountPrice($price, $discount) {
        $discount = (100 - $discount) / 100;
        return number_format(floatval($discount * $price), 2, '.', '');
    }

    /**
     * 获取星级
     * @param mix $star 星级
     * @return float $star 星级
     */
    public static function getStar($star) {
        $star = floatval($star);
        $star = $star > 5 ? 5 : $star;
        $star = $star < 0 ? 0 : $star;
        return floatval($star);
    }

    /**
     * 获取SQL执行计划
     * @param mix $star 星级
     * @return float $star 星级
     */
    public static function getExePlan($storeId, $builder = null, $make = Constant::PARAMETER_STRING_DEFAULT, $from = Constant::PARAMETER_STRING_DEFAULT, $select = [], $where = [], $order = [], $limit = null, $offset = null, $isPage = false, $pagination = [], $isOnlyGetCount = false, $joinData = Constant::PARAMETER_ARRAY_DEFAULT, $with = [], $handleData = Constant::PARAMETER_ARRAY_DEFAULT, $unset = Constant::PARAMETER_ARRAY_DEFAULT, $relation = Constant::PARAMETER_STRING_DEFAULT, $setConnection = true, $default = Constant::PARAMETER_ARRAY_DEFAULT, $groupBy = Constant::PARAMETER_ARRAY_DEFAULT) {
        return [
            Constant::DB_EXECUTION_PLAN_SETCONNECTION => $setConnection,
            Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
            Constant::DB_EXECUTION_PLAN_BUILDER => $builder,
            Constant::DB_EXECUTION_PLAN_MAKE => $make,
            Constant::DB_EXECUTION_PLAN_FROM => $from,
            Constant::DB_EXECUTION_PLAN_SELECT => $select,
            Constant::DB_EXECUTION_PLAN_WHERE => $where,
            Constant::DB_EXECUTION_PLAN_ORDERS => $order,
            Constant::DB_EXECUTION_PLAN_LIMIT => $limit,
            Constant::DB_EXECUTION_PLAN_OFFSET => $offset,
            Constant::DB_EXECUTION_PLAN_IS_PAGE => $isPage,
            Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
            Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_COUNT => $isOnlyGetCount,
            Constant::DB_EXECUTION_PLAN_JOIN_DATA => $joinData,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
            Constant::DB_EXECUTION_PLAN_HANDLE_DATA => $handleData,
            Constant::DB_EXECUTION_PLAN_UNSET => $unset,
            Constant::DB_EXECUTION_PLAN_RELATION => $relation,
            Constant::DB_EXECUTION_PLAN_DEFAULT => $default,
            'groupBy' => $groupBy,
        ];
    }

    /**
     * 获取SQL执行计划 表关联
     * @param string $table 表
     * @param string|function $first
     * @param string|null $operator
     * @param string|null $second
     * @param string $type
     * @return array SQL执行计划 表关联
     */
    public static function getExePlanJoinData($table, $first, $operator = null, $second = null, $type = 'left') {
        return [
            Constant::DB_EXECUTION_PLAN_TABLE => $table,
            Constant::DB_EXECUTION_PLAN_FIRST => $first,
            Constant::DB_TABLE_OPERATOR => $operator,
            Constant::DB_EXECUTION_PLAN_SECOND => $second,
            Constant::DB_TABLE_TYPE => $type,
        ];
    }

    /**
     * 获取SQL执行计划 数据处理结构数据
     * @param string $field 字段名
     * @param mix $default 默认值
     * @param array $data 数据映射map
     * @param string $dataType 数据类型
     * @param string $dateFormat 数据格式
     * @param string $time 时间处理句柄
     * @param string $glue 分隔符或者连接符
     * @param boolean $isAllowEmpty 是否允许为空 true：是  false：否
     * @param array $callback 回调闭包数组
     * @param array $only 返回字段
     * @return array 数据处理结构数据
     */
    public static function getExePlanHandleData($field = null, $default = Constant::PARAMETER_STRING_DEFAULT, $data = Constant::PARAMETER_ARRAY_DEFAULT, $dataType = Constant::PARAMETER_STRING_DEFAULT, $dateFormat = Constant::PARAMETER_STRING_DEFAULT, $time = Constant::PARAMETER_STRING_DEFAULT, $glue = Constant::PARAMETER_STRING_DEFAULT, $isAllowEmpty = true, $callback = Constant::PARAMETER_ARRAY_DEFAULT, $only = Constant::PARAMETER_ARRAY_DEFAULT) {
        return [
            Constant::DB_EXECUTION_PLAN_FIELD => $field, //数据字段
            Constant::RESPONSE_DATA_KEY => $data, //数据映射map
            Constant::DB_EXECUTION_PLAN_DATATYPE => $dataType, //数据类型
            Constant::DB_EXECUTION_PLAN_DATA_FORMAT => $dateFormat, //数据格式
            Constant::DB_EXECUTION_PLAN_TIME => $time, //时间处理句柄
            Constant::DB_EXECUTION_PLAN_GLUE => $glue, //分隔符或者连接符
            Constant::DB_EXECUTION_PLAN_IS_ALLOW_EMPTY => $isAllowEmpty, //是否允许为空 true：是  false：否
            Constant::DB_EXECUTION_PLAN_DEFAULT => $default, //默认值$default
            Constant::DB_EXECUTION_PLAN_CALLBACK => $callback,
            Constant::DB_EXECUTION_PLAN_ONLY => $only,
        ];
    }

    /**
     * 获取shopify uri
     * @param string $mark 标识
     * @return string shopify uri
     */
    public static function getShopifyUri($mark) {
        return '/' . implode('/', [Constant::SHOPIFY_URL_PREFIX, $mark]);
    }

    /**
     * 获取shopify host
     * @param int $storeId 商城id
     * @return string shopify host
     */
    public static function getShopifyHost($storeId, $host = null) {
        $host = $host ? $host : (in_array(config('app.env', 'production'), ['test', 'dev']) ? config('app.sync.sandbox_' . $storeId . '.shopify.host', '') : \App\Services\StoreService::getModel($storeId)->where([Constant::DB_TABLE_PRIMARY => $storeId])->value('host'));
        return $host;
    }

    /**
     * 处理集合数据
     * @param array|collect $data 待处理的数据
     * @param string|array $type 类型
     * @param string $keyField key
     * @param string $valueField value
     * @return collect 集合数据
     */
    public static function handleCollect($data, $type = null, $keyField = null, $valueField = null) {

        $data = collect($data);

        if ($keyField) {
            $keyField = '{key}';
        }

        if ($valueField) {
            $valueField = '{value}';
        }

        $data = $data->pluck($valueField, $keyField);

        if (!is_array($type)) {
            return $data;
        }

        $_data = [];
        $_keyData = [];
        foreach ($type as $typeValue) {
            foreach ($data as $key => $value) {
                if (Arr::accessible($value) || is_object($value)) {
                    if ($keyField && strpos(data_get($value, $keyField, ''), $typeValue) !== false) {
                        $keyData = explode(($typeValue . '_'), $key, 2);
                        $_key = $typeValue . '.' . data_get($keyData, 1, 0);
                        data_set($_data, $_key, $value);
                        unset($data[$key]);
                    } else {
                        if (data_get($value, 'type', '') == $typeValue) {
                            $currentKey = data_get($_keyData, $typeValue, 0);
                            $_key = $typeValue . '.' . $currentKey;
                            data_set($_data, $_key, $value);
                            data_set($_keyData, $typeValue, $currentKey + 1);
                        }
                    }
                } else {
                    $haystack = $key;
                    if ($keyField) {
                        $haystack = $key;
                    } else if ($valueField) {
                        $haystack = $value;
                    }
                    if (strpos($haystack, $typeValue) !== false) {
                        $keyData = explode(($typeValue . '_'), $haystack, 2);
                        $valueData = explode(($typeValue . '_'), $value, 2);

                        if ($keyField) {
                            $_key = $typeValue . '.' . data_get($keyData, 1, 0);
                            data_set($_data, $_key, data_get($valueData, 1, 0));
                        } else if ($valueField) {
                            $currentKey = data_get($_keyData, $typeValue, 0);
                            $_key = $typeValue . '.' . $currentKey;
                            data_set($_data, $_key, data_get($valueData, 1, 0));
                            data_set($_keyData, $typeValue, $currentKey + 1);
                        }
                        unset($data[$key]);
                    }
                }
            }
        }
        $data = collect($_data);
        unset($_data);
        unset($_keyData);

        return $data;
    }

    /**
     * 处理时间
     * @param string|max $dataTime 时间数据
     * @param string $time
     * @param string $dateFormat 时间格式 默认：Y-m-d H:i:s
     * @return string 时间
     */
    public static function handleTime($dataTime, $time = '', $dateFormat = 'Y-m-d H:i:s') {

        $timeValue = strtotime($dataTime);

        if (!($timeValue !== false && $dataTime != '0000-00-00 00:00:00')) {
            return $dataTime;
        }

        if (is_string($dataTime)) {
            $value = Carbon::parse($dataTime)->rawFormat($dateFormat);
        } else {
            $value = Carbon::createFromTimestamp($dataTime)->rawFormat($dateFormat);
        }

        if ($time) {
            $time = strtotime($time, strtotime($value));
            $value = Carbon::createFromTimestamp($time)->rawFormat($dateFormat);
        }

        return $value;
    }

    /**
     * 处理数值
     * @param mix $value 要处理的数值
     * @param array $dateFormat 数据格式
     * @return mix 数值
     */
    public static function handleNumber($value, $dateFormat = [2, ".", '']) {
        $dateFormat = $dateFormat ? $dateFormat : [2, ".", ''];
        return number_format(floatval($value), ...$dateFormat);
    }

    /**
     * 获取唯一id
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param mix $id 平台主键id
     * @return int 取唯一id
     */
    public static function getUniqueId(...$parameters) {
        return \App\Services\UniqueIdService::getUniqueId(...$parameters);
    }

    public static function getDbBeforeHandle($updateHandle = [], $deleteHandle = [], $insertHandle = [], $selectHandle = null) {
        return [
            Constant::DB_OPERATION_UPDATE => $updateHandle,
            Constant::DB_OPERATION_DELETE => $deleteHandle,
            Constant::DB_OPERATION_INSERT => $insertHandle,
            Constant::DB_OPERATION_SELECT => $selectHandle,
        ];
    }

    /**
     * 设备类型 1:手机 2：平板 3：桌面
     * @param int $storeId
     * @return array 设备类型数据
     */
    public static function getDeviceType($storeId = 1) {
        return [
            1 => '手机',
            2 => '平板',
            3 => '桌面',
        ];
    }

    /**
     * 修复唯一id
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param mix $id 平台主键id
     * @return int 取唯一id
     */
    public static function repairUniqueId(...$parameters) {

        return $parameters;

//        $parameters[] = [
//            'value' => FunctionHelper::myHash(json_encode($parameters))
//        ];
//        return \App\Services\UniqueIdService::getUniqueId(...$parameters);
    }

    public static function checkOrderNo($orderNo) {
        return preg_match('/^([\d]{3}-[\d]{7}-[\d]{7})$/', $orderNo) || preg_match('/^(S[\d]{2}-[\d]{7}-[\d]{7})$/', $orderNo);
    }

    public static function setLocale($country) {
        getTranslator()->setLocale($country);
    }

    /**
     * 根据订单国家设置locale变量
     * @param int $storeId 官网id
     * @param int $orderItem 订单item数据
     */
    public static function setLocaleByOrderCountry($storeId, $orderItem) {
        $country = data_get($orderItem, Constant::DB_TABLE_ORDER_COUNTRY, Constant::PARAMETER_STRING_DEFAULT);
        $country = strtoupper($country);

        $localeCountry = 'US';
        $countries = DictStoreService::getByTypeAndKey($storeId, 'lang', 'country', true);
        if (!empty($countries)) {
            $countries = explode(',', $countries);
            if (!empty($countries) && in_array($country, $countries)) {
                $localeCountry = $country;
            }
        }

        FunctionHelper::setLocale($localeCountry);
    }

    public static function setInterfaceLang($storeId, $uri) {
        $dictConfig = DictStoreService::getListByType($storeId, 'interface_lang');
        return $dictConfig->firstWhere('conf_value', $uri);
    }
}
