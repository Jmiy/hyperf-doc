<?php

/**
 * 积分服务
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services;

use App\Utils\Cdn\CdnManager;
use App\Constants\Constant;
use Hyperf\DbConnection\Db as DB;
use Hyperf\Utils\Arr;
use Carbon\Carbon;
use App\Models\CustomerInfo;
use App\Utils\FunctionHelper;
use Hyperf\HttpServer\Contract\RequestInterface as Request;

class CreditService extends BaseService {

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'CreditLog';
    }

    /**
     * 检查是否存在
     * @param int $id 角色id
     * @param bool $getData 是否获取数据 true:是 false:否 默认:false
     * @return mixed|static
     */
    public static function exists($storeId = 0, $where = [], $getData = false, $select = null) {
        return static::existsOrFirst($storeId, '', $where, $getData, $select);
    }

    /**
     * 添加积分记录
     * @param $storeId
     * @param $data
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
     * 积分变动
     * @param $params
     * @return bool
     */
    public static function handle($params) {

        $result = ['code' => 0, 'msg' => '', 'data' => []];
        $credit1 = $params[Constant::DB_TABLE_CREDIT] ?? 0;
        $credit2 = $params[Constant::DB_TABLE_VALUE] ?? 0;
        $value = $credit1 > $credit2 ? $credit1 : $credit2;
        $value = abs(ceil($value));

        $storeId = $params[Constant::DB_TABLE_STORE_ID] ?? 0;

        if ($value == 0 || $storeId == 0) {
            return $result;
        }

        $params[Constant::DB_TABLE_VALUE] = $value;
        $creditHistory = FunctionHelper::getHistoryData($params);
        $ctime = data_get($params, 'created_at', '');
        if ($ctime) {
            data_set($creditHistory, Constant::DB_TABLE_OLD_CREATED_AT, $ctime);
        }

        $customerId = data_get($creditHistory, Constant::DB_TABLE_CUSTOMER_PRIMARY, -1);
        $where = [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId];
        $select = [Constant::DB_TABLE_CREDIT];
        $customerInfo = CustomerInfo::select($select)->where($where)->first();
        if (empty($customerInfo)) {
            $result['msg'] = 'this account not exists';
            return $result;
        }

        $data = [];
        switch ($creditHistory[Constant::DB_TABLE_ADD_TYPE]) {
            case 1://增加方式 1加
                $data = [
                    Constant::DB_TABLE_CREDIT => DB::raw('credit+' . $value),
                    'total_credit' => DB::raw('total_credit+' . $value),
                ];

                break;

            case 2://增加方式 2减
                if ($customerInfo->credit < abs($value)) {
                    $result['msg'] = 'credit insufficient';
                    return $result;
                }

                $data = [
                    Constant::DB_TABLE_CREDIT => DB::raw('credit-' . $value),
                ];

                break;

            default:
                break;
        }

        if (empty($data)) {
            $result['msg'] = 'add_type fail';
            return $result;
        }

        $data['mtime'] = Carbon::now()->toDateTimeString();

        //更新客户总积分
        $ret = CustomerInfo::where($where)->update($data);
        if (!$ret) {
            $result['msg'] = 'change fail';
            return $result;
        }

        //添加积分流水
        $creditRet = static::insert($storeId, $creditHistory);
        if (!$creditRet) {
            LogService::addSystemLog('error', Constant::DB_TABLE_CREDIT, 'change', $params[Constant::DB_TABLE_CUSTOMER_PRIMARY], $creditHistory, 'credit addhistory fail');
        }
        $result['code'] = 1;
        $result['data']['creditLogId'] = $creditRet;

        return $result;
    }

    /**
     * 获取db query
     * @param array $where
     * @return \Illuminate\Database\Query\Builder|static $query
     */
    public static function getQuery($storeId = 1, $country = '', $where = []) {
        return static::getModel($storeId)->buildWhere($where);
    }

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @return array
     */
    public static function getPublicData($params, $order = []) {

        $storeId = Arr::get($params, Constant::DB_TABLE_STORE_ID, 0);
        $account = Arr::get($params, 'account', '');
        $params[Constant::START_TIME] = $params[Constant::START_TIME] ?? '';
        $params[Constant::DB_TABLE_END_TIME] = $params[Constant::DB_TABLE_END_TIME] ?? '';
        $params[Constant::DB_TABLE_ACTION] = $params[Constant::DB_TABLE_ACTION] ?? '';
        $params['account_' . Constant::DB_TABLE_COUNTRY] = $params['account_' . Constant::DB_TABLE_COUNTRY] ?? '';
        $source = data_get($params, 'source', '');

        $customerId = data_get($params, Constant::DB_TABLE_CUSTOMER_PRIMARY); //用户账号id
        $where = [];

        if ($customerId) {
            $where[] = [Constant::DB_TABLE_CUSTOMER_PRIMARY, '=', $customerId];
        } else {
            if ($account) {
                $where[] = ['c' . Constant::LINKER . Constant::DB_TABLE_ACCOUNT, '=', $account];
            }
        }

        if ($params[Constant::START_TIME]) {
            $where[] = ['cl' . Constant::LINKER . Constant::DB_TABLE_OLD_CREATED_AT, '>=', $params[Constant::START_TIME]];
        }

        if ($params[Constant::DB_TABLE_END_TIME]) {
            $where[] = ['cl' . Constant::LINKER . Constant::DB_TABLE_OLD_CREATED_AT, '<=', $params[Constant::DB_TABLE_END_TIME]];
        }

        if ($params['account_' . Constant::DB_TABLE_COUNTRY]) {
            $where[] = ['ci' . Constant::LINKER . Constant::DB_TABLE_COUNTRY, '=', $params['account_' . Constant::DB_TABLE_COUNTRY]];
        }

        $_where = [];
        if (data_get($params, Constant::DB_TABLE_PRIMARY, 0)) {
            $_where['cl' . Constant::LINKER . Constant::DB_TABLE_PRIMARY] = $params[Constant::DB_TABLE_PRIMARY];
        }

        if ($source == 'admin') {
            if ($params[Constant::DB_TABLE_ACTION] && $params[Constant::DB_TABLE_ACTION] == 'other') {
                $_where['cl' . Constant::LINKER . Constant::DB_TABLE_ACTION] = [
                    'other',
                    'deductActWarrantyPoint'
                ];
            } elseif ($params[Constant::DB_TABLE_ACTION]) {
                $where[] = ['cl' . Constant::LINKER . Constant::DB_TABLE_ACTION, '=', $params[Constant::DB_TABLE_ACTION]];
            }
        } else {
            $where[] = ['cl' . Constant::LINKER . Constant::DB_TABLE_ACTION, '!=', 'deductActWarrantyPoint'];
            if ($params[Constant::DB_TABLE_ACTION]) {
                $where[] = ['cl' . Constant::LINKER . Constant::DB_TABLE_ACTION, '=', $params[Constant::DB_TABLE_ACTION]];
            }
        }

        if ($where) {
            $_where[] = $where;
        }

        $order = $order ? $order : [['cl' . Constant::LINKER . Constant::DB_TABLE_OLD_CREATED_AT, 'desc']];
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

        $_data = static::getPublicData($params);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, null);
        $order = data_get($params, Constant::DB_EXECUTION_PLAN_ORDER_BY, data_get($_data, 'order', []));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, 'limit', data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0));
        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, 1);
        $source = data_get($params, 'source', '');

        $select = $select ? $select : [
            'cl' . Constant::LINKER . Constant::DB_TABLE_PRIMARY,
            'cl' . Constant::LINKER . Constant::DB_TABLE_VALUE,
            'cl' . Constant::LINKER . Constant::DB_TABLE_ACTION,
            'cl' . Constant::LINKER . Constant::DB_TABLE_OLD_CREATED_AT,
            'cl' . Constant::LINKER . Constant::DB_TABLE_ADD_TYPE,
            'cl' . Constant::LINKER . Constant::DB_TABLE_REMARK,
        ];

        //处理数据
        $type = 'credit_action';
        if ($source == 'api') {
            //积分来源
            $orderby = null;
            $country = null;
            $extWhere = [
                Constant::DICT => [
                //Constant::DB_TABLE_DICT_KEY => ['is_force_release_order_lock', 'release_time', 'each_pull_time', 'ttl'],
                ],
                Constant::DICT_STORE => [],
            ];
            $dictSelect = [
                Constant::DICT => [
                    Constant::DB_TABLE_TYPE, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE,
                ],
                Constant::DICT_STORE => [
                    Constant::DB_TABLE_TYPE, Constant::DB_TABLE_STORE_DICT_KEY, Constant::DB_TABLE_STORE_DICT_VALUE,
                ],
            ];
            $actionData = DictService::getDistData($storeId, $type, Constant::DB_TABLE_STORE_DICT_KEY, Constant::DB_TABLE_STORE_DICT_VALUE, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE, $orderby, $country, $extWhere, $dictSelect);
        } else {
            $actionData = DictService::getListByType($type, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE);

            if (!data_get($params, Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_PRIMARY, false)) {
                $select = Arr::collapse([$select, [
                                'cl' . Constant::LINKER . Constant::DB_TABLE_CONTENT,
                                'c' . Constant::LINKER . Constant::DB_TABLE_ACCOUNT,
                                'ci' . Constant::LINKER . Constant::DB_TABLE_FIRST_NAME,
                                'ci' . Constant::LINKER . Constant::DB_TABLE_LAST_NAME,
                                'ci' . Constant::LINKER . Constant::DB_TABLE_IP,
                                'ci' . Constant::LINKER . Constant::DB_TABLE_COUNTRY,
                ]]);
            }
        }

        $addTypeData = [
            1 => '+',
            2 => '-',
        ];

        $field = Constant::DB_TABLE_ADD_TYPE;
        $data = Constant::PARAMETER_ARRAY_DEFAULT;
        $dataType = Constant::PARAMETER_STRING_DEFAULT;
        $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
        $time = Constant::PARAMETER_STRING_DEFAULT;
        $glue = Constant::PARAMETER_STRING_DEFAULT;
        $isAllowEmpty = true;
        $default = Constant::PARAMETER_STRING_DEFAULT;
        $callback = Constant::PARAMETER_ARRAY_DEFAULT;
        $only = Constant::PARAMETER_ARRAY_DEFAULT;
        $actionDefault = ($source == 'admin') ? data_get($actionData, 'other', '') : Constant::PARAMETER_STRING_DEFAULT;

        $parameters = [$field, $default, $addTypeData, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only];
        $handleData = [
            Constant::DB_TABLE_ADD_TYPE => FunctionHelper::getExePlanHandleData(...$parameters),
            Constant::DB_TABLE_VALUE => FunctionHelper::getExePlanHandleData('add_type{connection}value', $default, $data, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only), //积分值
            Constant::DB_TABLE_ACTION => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_ACTION, $actionDefault, $actionData, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only), //积分来源
        ];

        $joinData = [];
        if ($source == 'api') {
            if ($storeId != 2) {
                //获取积分来源配置
                $dateFormat = $storeId == 1 ? 'Y-m-d H:i:s' : 'Y-m-d';
                $handleData[Constant::DB_TABLE_OLD_CREATED_AT] = FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_OLD_CREATED_AT, $default, $data, 'datetime', $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only); //时间

                data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA, FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, [], [
                            Constant::DB_TABLE_PRIMARY,
                            Constant::DB_TABLE_VALUE,
                            Constant::DB_TABLE_ACTION,
                            Constant::DB_TABLE_OLD_CREATED_AT,
                        ])
                );
            } else {
                $handleData = Constant::PARAMETER_ARRAY_DEFAULT;
            }
        } else {
            $joinData = [
                FunctionHelper::getExePlanJoinData(DB::raw('`ptxcrm`.`crm_customer` as crm_c'), function ($join) {
                            $join->on([['c.' . Constant::DB_TABLE_CUSTOMER_PRIMARY, '=', 'cl.' . Constant::DB_TABLE_CUSTOMER_PRIMARY]])->where('c.' . Constant::DB_TABLE_STATUS, '=', 1);
                        }),
                FunctionHelper::getExePlanJoinData(DB::raw('`ptxcrm`.`crm_customer_info` as crm_ci'), function ($join) {
                            $join->on([['ci.' . Constant::DB_TABLE_CUSTOMER_PRIMARY, '=', 'c.' . Constant::DB_TABLE_CUSTOMER_PRIMARY]]);
                        }),
            ];
            $handleData[Constant::DB_TABLE_CONTENT] = FunctionHelper::getExePlanHandleData('json|' . Constant::DB_TABLE_CONTENT, $default, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only); //时间
            $handleData[Constant::DB_TABLE_NAME] = FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_FIRST_NAME . '{connection}' . Constant::DB_TABLE_LAST_NAME, $default, $data, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, $dateFormat, $time, ' ', $isAllowEmpty, $callback, $only); //时间
        }

        $with = Constant::PARAMETER_ARRAY_DEFAULT;
        $unset = Constant::PARAMETER_ARRAY_DEFAULT;
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getModelAlias(), 'credit_logs as cl', $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
        ];

        if (data_get($params, Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_PRIMARY, false)) {//如果仅仅获取主键id，就不需要处理数据，不关联
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_PARENT . '.' . Constant::DB_EXECUTION_PLAN_HANDLE_DATA, Constant::PARAMETER_ARRAY_DEFAULT);
        }

        $dataStructure = 'list';
        $flatten = false;
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    /**
     * 处理vip 积分体系更新
     * @param int $storeId 商城id
     * @param int $customerId 账号id
     * @param string $action  积分方式
     * @param string|null $type  配置类型
     * @param string|int $confKey  配置key|积分
     * @param array $requestData 请求参数
     * @param string $expType  经验配置类型
     * @param array $expConfKey 经验配置key|经验
     * @param int $addType 积分变更方式  1：加; 2：减
     * @return boolean 是否处理成功  true：成功  false：失败
     */
    public static function handleVip($storeId, $customerId, $action, $type, $confKey, $requestData = [], $expType = null, $expConfKey = '', $addType = 1) {

        if (empty($storeId) || empty($customerId)) {
            return false;
        }

        //如果被邀请人注册成功，就根据配置给邀请人添加对应的积分
        $data = FunctionHelper::getHistoryData([
                    Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                    Constant::DB_TABLE_VALUE => 0,
                    Constant::DB_TABLE_ADD_TYPE => $addType,
                    Constant::DB_TABLE_ACTION => $action,
                    Constant::DB_TABLE_EXT_TYPE => data_get($requestData, Constant::DB_TABLE_EXT_TYPE, ''),
                    Constant::DB_TABLE_EXT_ID => data_get($requestData, Constant::DB_TABLE_EXT_ID, 0),
                    Constant::DB_TABLE_ACT_ID => data_get($requestData, Constant::DB_TABLE_ACT_ID, 0),
                        ], [
                    Constant::DB_TABLE_STORE_ID => $storeId,
        ]);

        if ($type !== null) {
            $credit = DictStoreService::getByTypeAndKey($storeId, $type, $confKey, true);
        } else {
            $credit = $confKey;
        }

        if ($credit) {
            $data[Constant::DB_TABLE_VALUE] = $credit;
            static::handle($data); //记录积分流水
        }

        //处理经验相关
        if ($expType !== null) {
            $exp = DictStoreService::getByTypeAndKey($storeId, $expType, $expConfKey, true);
        } else {
            $exp = $expConfKey;
        }

        if ($exp) {
            $data[Constant::DB_TABLE_VALUE] = $exp;
            ExpService::handle($data); //记录经验流水
        }

        return true;
    }

    /**
     * 积分批量导入
     * @param Request $request 请求对象
     * @return array
     */
    public static function importData(Request $request) {
        $result = [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => Constant::PARAMETER_STRING_DEFAULT,
            Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
        ];

        $storeId = $request->input(Constant::DB_TABLE_STORE_ID, Constant::PARAMETER_INT_DEFAULT);

        $credits = static::getCredits($request);
        if (empty($credits)) {
            $result[Constant::RESPONSE_CODE_KEY] = -1;
            return $result;
        }

        //积分写入
        $failMsg = '';
        foreach ($credits as $credit) {
            $creditItem = [
                Constant::DB_TABLE_STORE_ID => $storeId,
                Constant::DB_TABLE_VALUE => data_get($credit, Constant::DB_TABLE_VALUE),
                Constant::DB_TABLE_CUSTOMER_PRIMARY => data_get($credit, Constant::DB_TABLE_CUSTOMER_PRIMARY),
                Constant::DB_TABLE_ADD_TYPE => 1,
                Constant::DB_TABLE_ACTION => data_get($credit, Constant::DB_TABLE_ACTION),
                Constant::DB_TABLE_EXT_ID => data_get($credit, Constant::DB_TABLE_CUSTOMER_PRIMARY),
                Constant::DB_TABLE_EXT_TYPE => CustomerService::getMake(),
                Constant::DB_TABLE_REMARK => data_get($credit, Constant::DB_TABLE_REMARK),
            ];
            $handleRet = CreditService::handle($creditItem);
            if ($handleRet[Constant::RESPONSE_CODE_KEY] != 1) {
                $failMsg .= $credit[Constant::DB_TABLE_ACCOUNT] . "--" . $handleRet[Constant::RESPONSE_MSG_KEY] . ";";
            }
        }

        if (!empty($failMsg)) {
            $result[Constant::RESPONSE_CODE_KEY] = -1;
            $result[Constant::RESPONSE_MSG_KEY] = $failMsg;
        }

        return $result;
    }

    /**
     * 获取积分
     * @param Request $request 请求对象
     * @return array
     */
    public static function getCredits(Request $request) {

        ini_set('memory_limit', '512M');
        $env = config('app.env', 'production');
        $storeId = $request->input(Constant::DB_TABLE_STORE_ID, Constant::PARAMETER_INT_DEFAULT);

        $fileData = CdnManager::upload(Constant::UPLOAD_FILE_KEY, $request, '/upload/file/');
        if (data_get($fileData, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_INT_DEFAULT) != 1) {
            $env === 'local' && $fp = fopen(data_get($_FILES, 'file.tmp_name'), 'r');
        } else {
            $env === 'local' && $fp = fopen(data_get($fileData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::FILE_FULL_PATH, Constant::PARAMETER_STRING_DEFAULT), 'r');
        }

        $data = [];
        if ($env === 'local') {
            if (!is_resource($fp)) {
                return [];
            }

            while (!feof($fp)) {
                $line = trim(fgets($fp));
                if (empty($line)) {
                    continue;
                }
                $data[] = explode(',', $line);
            }
            fclose($fp);
        } else {
            if (data_get($fileData, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_INT_DEFAULT) != 1) {
                return [];
            }

            try {
                $typeData = [
                    \Vtiful\Kernel\Excel::TYPE_STRING,
                    \Vtiful\Kernel\Excel::TYPE_STRING,
                    \Vtiful\Kernel\Excel::TYPE_STRING,
                    \Vtiful\Kernel\Excel::TYPE_STRING,
                ];

                $data = ExcelService::parseExcelFile(data_get($fileData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::FILE_FULL_PATH, ''), $typeData);
            } catch (\Exception $exception) {
                return [];
            }
        }

        return static::convertToTableData($storeId, $data);
    }

    /**
     * 获取积分数据
     * @param int $storeId 官网id
     * @param array $excelData 从文件读取的数据
     * @return array
     */
    public static function convertToTableData($storeId, $excelData) {
        if (empty($excelData)) {
            return [];
        }

        $actionList = \App\Services\Store\StoreService::getActionList();
        !empty($actionList) && $actionList = array_column($actionList, NULL, Constant::DB_TABLE_VALUE);

        $header = [];
        $tableData = [];
        $isHeader = true;
        foreach ($excelData as $k => $row) {
            if ($isHeader) {
                $temp = array_flip($row);
                foreach (static::$creditHeaderMap as $key => $value) {
                    data_set($header, $value, data_get($temp, $key));
                }
                $isHeader = false;
                continue;
            }

            $action = data_get($row, data_get($header, Constant::DB_TABLE_ACTION), Constant::PARAMETER_STRING_DEFAULT);
            $account = data_get($row, data_get($header, Constant::DB_TABLE_ACCOUNT), Constant::PARAMETER_STRING_DEFAULT);
            $where = [
                Constant::DB_TABLE_STORE_ID => $storeId,
                Constant::DB_TABLE_ACCOUNT => $account,
            ];
            $customerRet = CustomerService::existsOrFirst($storeId, '', $where, true, [Constant::DB_TABLE_CUSTOMER_PRIMARY]);
            if (empty($customerRet)) {
                continue;
            }

            data_set($tableData, $k . '.account', $account);
            data_set($tableData, $k . '.customer_id', data_get($customerRet, Constant::DB_TABLE_CUSTOMER_PRIMARY));
            data_set($tableData, $k . '.action', data_get($actionList, "$action.key", 'other'));
            data_set($tableData, $k . '.value', data_get($row, data_get($header, Constant::DB_TABLE_VALUE), Constant::PARAMETER_STRING_DEFAULT));
            data_set($tableData, $k . '.remark', data_get($row, data_get($header, Constant::DB_TABLE_REMARK), Constant::PARAMETER_STRING_DEFAULT));
        }

        return $tableData;
    }

    /**
     * 积分导入模板文件头
     * @var array
     */
    public static $creditHeaderMap = [
        '邮箱' => Constant::DB_TABLE_ACCOUNT,
        '积分值' => Constant::DB_TABLE_VALUE,
        '积分明细方式' => Constant::DB_TABLE_ACTION,
        '备注' => Constant::DB_TABLE_REMARK,
    ];

    /**
     * 活动积分，根据订单时间是否在活动期间内，加双倍积分
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param float|int $creditValue 非活动下的积分
     * @param string $orderTime 订单时间
     * @param string $type 活动积分配置类型
     * @return float|int|mixed 积分
     */
    public static function actCredit($storeId, $actId, $customerId, $creditValue, $orderTime, $type = 'credit_warranty') {
        $activityData = ActivityService::getActivityData($storeId, $actId);
        //$currentDate = date('Y-m-d H:i:s');
        $startAt = data_get($activityData, Constant::DB_TABLE_START_AT, Constant::PARAMETER_STRING_DEFAULT);
        $endAt = data_get($activityData, Constant::DB_TABLE_END_AT, Constant::PARAMETER_STRING_DEFAULT);

        //是否给活动积分
        $isGiveActCredit = data_get($activityData, 'is_give_act_credit', Constant::PARAMETER_INT_DEFAULT);
        if (!$isGiveActCredit) {
            return $creditValue;
        }

        //活动期间
        if ($startAt <= $orderTime && $orderTime <= $endAt) {
            //获取活动积分配置
            $actCreditConfig = ActivityService::getActivityConfigData($storeId, $actId, $type);
            $actCreditConfig = array_values($actCreditConfig);
            //不存在配置
            if (empty($actCreditConfig)) {
                return $creditValue;
            }

            $key = data_get($actCreditConfig, '0.key', Constant::PARAMETER_STRING_DEFAULT);
            $value = data_get($actCreditConfig, '0.value', Constant::PARAMETER_INT_DEFAULT);
            switch ($key) {
                case "{$type}_multiply":
                    $creditValue = $creditValue * $value;
                    break;

                case "{$type}_plus":
                    $creditValue = $creditValue + $value;
                    break;

                case "{$type}_divide":
                    $creditValue = $creditValue - $value;
                    break;

                case "{$type}_reduce":
                    $value > 0 && $creditValue = $creditValue / $value;
                    break;

                default:
                    break;
            }

            //延保积分排行榜
            if (in_array($storeId, [2]) && !empty($actId)) {
                //获取用户信息
                $customerInfo = CustomerInfoService::existsOrFirst($storeId, '', [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId], true);

                //更新延保排行榜积分
                GameService::rankUpdate($storeId, $actId, 1, 0, $customerId, abs(ceil($creditValue)), $customerInfo);
            }
        }

        return $creditValue;
    }

}
