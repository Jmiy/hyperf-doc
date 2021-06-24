<?php

/**
 * 经验流水服务
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services;

use Hyperf\DbConnection\Db as DB;
use Hyperf\Utils\Arr;
use Carbon\Carbon;
use App\Models\CustomerInfo;
use App\Utils\FunctionHelper;
use App\Services\DictService;
use App\Constants\Constant;

class ExpService extends BaseService {

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'ExpLog';
    }

    /**
     * 检查是否存在
     * @param int $id 角色id
     * @param bool $getData 是否获取数据 true:是 false:否 默认:false
     * @return mixed|static
     */
    public static function exists($storeId = 0, $where = [], $getData = false) {

        if (empty($where)) {
            return $getData ? [] : true;
        }

        $query = static::getModel($storeId)->buildWhere($where);

        if ($getData) {
            $rs = $query->first();
        } else {
            $rs = $query->count();
        }

        return $rs;
    }

    /**
     * 添加记录
     * @param $storeId
     * @param $params
     * @return bool
     */
    public static function insert($storeId, $data) {
        $data[Constant::DB_TABLE_OLD_CREATED_AT] = data_get($data, Constant::DB_TABLE_OLD_CREATED_AT, Carbon::now()->toDateTimeString());
        $id = static::getModel($storeId)->insertGetId($data);
        if (!$id) {
            return false;
        }

        return $id;
    }

    /**
     * 变动
     * @param $params
     * @return bool
     */
    public static function handle($params) {

        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, 0);
        $value = data_get($params, Constant::DB_TABLE_VALUE, 0);
        $value = abs(ceil($value));

        if ($value == 0 || $storeId == 0) {
            return false;
        }

        $params[Constant::DB_TABLE_VALUE] = $value;
        $creditHistory = FunctionHelper::getHistoryData($params);
        $ctime = data_get($params, 'created_at', '');
        if ($ctime) {
            data_set($creditHistory, Constant::DB_TABLE_OLD_CREATED_AT, $ctime);
        }

        $customerId = data_get($creditHistory, Constant::DB_TABLE_CUSTOMER_PRIMARY, -1);
        $where = [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId];
        $select = ['exp', 'vip'];
        $customerInfo = CustomerInfo::select($select)->where($where)->first();
        if (empty($customerInfo)) {
            return false;
        }

        $data = [];
        switch ($creditHistory[Constant::DB_TABLE_ADD_TYPE]) {
            case 1:
                $data = [
                    'exp' => DB::raw('exp+' . $value),
                ];

                $supportVip = DictStoreService::getByTypeAndKey($storeId, Constant::CUSTOMER, 'support_vip', true, true); //会员是否支持等级 1:支持 0:不支持
                if ($supportVip) {//如果会员支持等级，就更新等级
                    $viplv = CustomerService::getVipLv($storeId, ($customerInfo->exp + $value));
                    if ($customerInfo->vip < $viplv) {
                        $data['vip'] = $viplv;
                    }
                }


                break;

            case 2:
                if ($customerInfo->exp < abs($value)) {
                    $result['msg'] = 'exp insufficient';
                    return $result;
                }

                $data = [
                    'exp' => DB::raw('exp-' . $value),
                ];

                break;

            default:
                break;
        }

        if (empty($data)) {
            return false;
        }

        $data['mtime'] = Carbon::now()->toDateTimeString();
        $ret = CustomerInfo::where($where)->update($data);
        if (!$ret) {
            return false;
        }

        return self::insert($storeId, $creditHistory);
    }

    /**
     * 获取db query
     * @param array $where
     * @return \Illuminate\Database\Query\Builder|static $query
     */
    public static function getQuery($storeId = 1, $country = '', $where = []) {
        return static::getModel($storeId)->buildWhere($where)
                        ->with([Constant::CUSTOMER => function($query) {
                                $query->select([Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::DB_TABLE_ACCOUNT]);
                            }]);
    }

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @return array
     */
    public static function getPublicData($params, $order = []) {

        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, 0);
        $account = data_get($params, Constant::DB_TABLE_ACCOUNT, '');
        $params[Constant::START_TIME] = $params[Constant::START_TIME] ?? '';
        $params[Constant::DB_TABLE_END_TIME] = $params[Constant::DB_TABLE_END_TIME] ?? '';

        $where = static::whereCustomer($storeId, $account, []);

        if ($params[Constant::START_TIME]) {
            $where[] = [Constant::DB_TABLE_OLD_CREATED_AT, '>=', $params[Constant::START_TIME]];
        }

        if ($params[Constant::DB_TABLE_END_TIME]) {
            $where[] = [Constant::DB_TABLE_OLD_CREATED_AT, '<=', $params[Constant::DB_TABLE_END_TIME]];
        }

        $_where = [];
        if (data_get($params, 'id', 0)) {
            $_where['id'] = $params['id'];
        }

        if ($where) {
            $_where[] = $where;
        }

        $order = $order ? $order : [Constant::DB_TABLE_OLD_CREATED_AT, 'desc'];
        return Arr::collapse([parent::getPublicData($params, $order), [
                        Constant::DB_EXECUTION_PLAN_WHERE => $_where,
        ]]);
    }

    /**
     * 列表
     * @param $params
     * @param bool $toArray
     * @param bool $isPage
     * @return array
     */
    public static function getListData($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {

        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, 0);
        $_data = static::getPublicData($params);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, null);
        $order = data_get($params, Constant::DB_EXECUTION_PLAN_ORDER_BY, data_get($_data, Constant::ORDER, Constant::PARAMETER_ARRAY_DEFAULT));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, Constant::PARAMETER_ARRAY_DEFAULT);
        $limit = data_get($params, Constant::DB_EXECUTION_PLAN_LIMIT, data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0));

        $select = $select ? $select : ['*'];

        //处理数据
        $actionData = DictService::getListByType('credit_action', 'dict_key', 'dict_value');
        $addTypeData = [
            1 => '+',
            2 => '-',
        ];

        $field = Constant::DB_TABLE_ADD_TYPE;
        $data = $addTypeData;
        $dataType = Constant::PARAMETER_STRING_DEFAULT;
        $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
        $time = Constant::PARAMETER_STRING_DEFAULT;
        $glue = Constant::PARAMETER_STRING_DEFAULT;
        $isAllowEmpty = true;
        $default = Constant::PARAMETER_STRING_DEFAULT;
        $callback = Constant::PARAMETER_ARRAY_DEFAULT;
        $only = Constant::PARAMETER_ARRAY_DEFAULT;

        $parameters = [$field, $default, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only];
        $handleData = [
            Constant::DB_TABLE_ADD_TYPE => FunctionHelper::getExePlanHandleData(...$parameters), //增加方式|1加,2减
            Constant::DB_TABLE_VALUE => FunctionHelper::getExePlanHandleData('add_type{connection}value', $default, Constant::PARAMETER_ARRAY_DEFAULT, 'string', $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only), //积分值
            Constant::DB_TABLE_ACTION => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_ACTION, $default, $actionData, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only), //积分来源
        ];

        $joinData = Constant::PARAMETER_ARRAY_DEFAULT;
        $with = [
            Constant::CUSTOMER => FunctionHelper::getExePlan(0, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, [Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::DB_TABLE_ACCOUNT], [], [], null, null, false, [], false, [], [], [], [Constant::CUSTOMER], Constant::HAS_ONE, true, [Constant::DB_TABLE_CUSTOMER_PRIMARY => Constant::DB_TABLE_CUSTOMER_PRIMARY]), //关联优惠劵
        ];
        $unset = Constant::PARAMETER_ARRAY_DEFAULT;
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getModelAlias(), '', $select, $where, [$order], $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
        ];

        if (data_get($params, Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_PRIMARY, false)) {//如果仅仅获取主键id，就不需要处理数据，不关联
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_PARENT . Constant::LINKER . Constant::DB_EXECUTION_PLAN_HANDLE_DATA, Constant::PARAMETER_ARRAY_DEFAULT);
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_WITH, Constant::PARAMETER_ARRAY_DEFAULT);
        }

        $dataStructure = 'list';
        $flatten = true;
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

}
