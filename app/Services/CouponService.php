<?php

/**
 * 优惠券服务
 * User: Jmiy
 * Date: 2019-06-30
 * Time: 16:08
 */

namespace App\Services;

use Hyperf\Utils\Arr;
use Carbon\Carbon;
use App\Utils\FunctionHelper;
use App\Models\Store;
use App\Constants\Constant;
use App\Utils\Response;
use App\Utils\Cdn\CdnManager;

class CouponService extends BaseService {

    public static $country_list = [
        '1' => ["US", "CA", "UK", "DE", "FR", "JP", "IN", "IT", "ES"],
        '2' => ["US", "CA", "UK", "DE", "FR", "IT", "ES"],
        '5' => ["US", "CA", 'MX', "UK", "DE", "JP", "FR", "IT", 'AT', "ES", 'IE', 'BE', 'LU'],
    ];
    public static $countryMap = [
        '1' => [
            'GB' => "UK",
            "US" => "US",
            "CA" => "CA",
            "UK" => "UK",
            "DE" => "DE",
            "FR" => "FR",
            "JP" => "JP",
            "IN" => "IN",
            "IT" => "IT",
            "ES" => "ES",
            'OTHER' => "US",
        ],
        '2' => [
            'GB' => "UK",
            "US" => "US",
            "CA" => "CA",
            "UK" => "UK",
            "DE" => "DE",
            "FR" => "FR",
            "IT" => "IT",
            "ES" => "ES",
            'OTHER' => "US",
        ],
        '5' => [
            'GB' => "UK",
            "US" => "US",
            "CA" => "US",
            'MX' => "US",
            "UK" => "UK",
            "DE" => "DE",
            "JP" => "JP",
            "FR" => "UK",
            "IT" => "UK",
            'AT' => "UK",
            "ES" => "UK",
            'IE' => "UK",
            'BE' => "UK",
            'LU' => "UK",
            'OTHER' => "US",
        ],
    ];
    public static $timeFormat = [
        '1' => [
            'ALL' => 'y/m/d',
        ],
        '2' => [
            'ALL' => 'y/m/d',
        ],
        '3' => [
            'ALL' => 'M. j, Y',
        ],
        '5' => [
            'US' => 'm/d/y',
            'UK' => 'd/m/y',
            'DE' => 'd/m/y',
            'JP' => 'y/m/d',
            'ALL' => 'm/d/y',
        ],
        'ALL' => 'Y-m-d',
    ];

    /**
     * 根据国家获取优惠券
     * @param $country
     * @return Hyperf\Database\Model\Collection
     */
    public static function getRegCoupon($storeId, $country, $extinfo = '', $account = '') {

        $model = static::getModel($storeId, $country);

        $where = [
            Constant::DB_TABLE_COUNTRY => [$country, ''],
            Constant::DB_TABLE_STATUS => 1,
            Constant::DB_EXECUTION_PLAN_GROUP => Constant::DB_EXECUTION_PLAN_GROUP_COMMON,
        ];
        $coupons = $model->select("*")->buildWhere($where)->groupBy("type")->get()->keyBy(Constant::DB_TABLE_TYPE);

        //更新coupon状态为已使用
        $ids = $coupons->where(Constant::DB_TABLE_USE_TYPE, 1)->pluck('id')->all();
        static::setStatus($storeId, $ids, 2, '', $extinfo, $account);

        return $coupons;
    }

    /**
     * 设置状态
     * @param int $store_id  商城id
     * @param array $ids 优惠券id
     * @param int $status 状态|1未使用2已使用3无效
     * @param string $group 分组
     * @param string $extinfo 关联数据
     * @param string $account 账号
     * @return type
     */
    public static function setStatus($store_id, $ids, $status, $group = '', $extinfo = null, $account = null) {

        $where = [];
        if ($ids) {
            data_set($where, 'id', $ids);
        }
        if ($group) {
            data_set($where, Constant::DB_EXECUTION_PLAN_GROUP, $group);
        }

        if (empty($where)) {
            return 0;
        }

        $model = static::getModel($store_id);
        $attributes = [
            Constant::DB_TABLE_STATUS => $status,
            Constant::DB_TABLE_OLD_UPDATED_AT => Carbon::now()->toDateTimeString(),
        ];

        if ($extinfo !== null) {
            data_set($attributes, Constant::DB_TABLE_EXTINFO, $extinfo);
        }

        if ($account !== null) {
            data_set($attributes, 'account', $account);
        }

        return $model->buildWhere($where)->update($attributes);
    }

    /**
     * 检查是否存在
     * @param int $storeId 商城id
     * @param int $storeProductId 产品id
     * @param bool $getData 是否获取数据 true:是 false:否 默认:false
     * @return bool|object
     */
    public static function exists($storeId = 0, $code = '', $type = '', $country = '', $status = 0, $getData = false) {
        $where = [];

        if ($code) {
            $where[Constant::RESPONSE_CODE_KEY] = $code;
        }

        if ($type) {
            $where[Constant::DB_TABLE_TYPE] = $type;
        }

        if ($country) {
            $where[Constant::DB_TABLE_COUNTRY] = $country;
        }

        if ($status) {
            $where[Constant::DB_TABLE_STATUS] = $status;
        }

        if (empty($where)) {
            return $getData ? [] : false;
        }

        $query = static::getModel($storeId)->where($where);

        if ($getData) {
            $rs = $query->first();
        } else {
            $rs = $query->exists();
        }

        return $rs;
    }

    /**
     * 添加记录
     * @param int $storeId 商城id
     * @param array $data  添加数据
     * @return int|boolean
     */
    public static function insert($storeId, $data) {
        $nowTime = Carbon::now()->toDateTimeString();
        $data[Constant::DB_TABLE_OLD_CREATED_AT] = Arr::get($data, Constant::DB_TABLE_OLD_CREATED_AT, $nowTime);
        $data[Constant::DB_TABLE_OLD_UPDATED_AT] = Arr::get($data, Constant::DB_TABLE_OLD_UPDATED_AT, $nowTime);

        $id = static::getModel($storeId)->insertGetId($data);
        if (!$id) {
            return false;
        }

        return $id;
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

        $where = [];

        $group = $params[Constant::DB_EXECUTION_PLAN_GROUP] ?? ''; //分组
        $code = $params[Constant::RESPONSE_CODE_KEY] ?? ''; //兑换码
        $type = $params[Constant::DB_TABLE_TYPE] ?? ''; //coupon 种类
        $country = $params[Constant::DB_TABLE_COUNTRY] ?? ''; //国家
        $status = intval($params[Constant::DB_TABLE_STATUS] ?? ''); //状态
        $asin = $params[Constant::DB_TABLE_ASIN] ?? ''; //产品asin
        $receive = $params[Constant::DB_TABLE_RECEIVE] ?? ''; //coupon 是否领取
        $startTime = $params['start_time'] ?? ''; //创建开始时间
        $endTime = $params[Constant::DB_TABLE_END_TIME] ?? ''; //创建结束时间
        $codeType = $params[Constant::DB_TABLE_CODE_TYPE] ?? ''; //code类型，1折扣码，2礼品卡


        if ($group) {
            $where[] = [Constant::DB_EXECUTION_PLAN_GROUP, '=', $group];
        }

        if ($code) {
            $where[] = [Constant::RESPONSE_CODE_KEY, '=', $code];
        }

        if ($type) {
            $where[] = [Constant::DB_TABLE_TYPE, '=', $type];
        }

        if ($country) {
            $where[] = [Constant::DB_TABLE_COUNTRY, '=', $country];
        }

        if ($status) {
            $where[] = [Constant::DB_TABLE_STATUS, '=', $status];
        }

        if ($asin) {
            $where[] = [Constant::DB_TABLE_ASIN, '=', $asin];
        }

        if ($receive) {
            $where[] = [Constant::DB_TABLE_RECEIVE, '=', $receive];
        }

        if ($startTime) {
            $where[] = [Constant::DB_TABLE_OLD_CREATED_AT, '>=', $startTime];
        }

        if ($endTime) {
            $where[] = [Constant::DB_TABLE_OLD_CREATED_AT, '<=', $endTime];
        }

        if ($codeType) {
            $where[] = [Constant::DB_TABLE_CODE_TYPE, '=', $codeType];
        }

        $_where = [];
        if (data_get($params, Constant::DB_TABLE_PRIMARY, 0)) {
            $_where[Constant::DB_TABLE_PRIMARY] = $params[Constant::DB_TABLE_PRIMARY];
        }

        if ($where) {
            $_where[] = $where;
        }

        $order = $order ? $order : [Constant::DB_TABLE_PRIMARY, 'desc'];
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

        $where = $_data[Constant::DB_EXECUTION_PLAN_WHERE];
        $order = data_get($params, 'orderBy', data_get($_data, 'order', []));
        $pagination = $_data[Constant::DB_EXECUTION_PLAN_PAGINATION];
        $limit = data_get($params, 'limit', data_get($pagination, 'page_size', 10));

        $customerCount = true;
        $storeId = data_get($params, 'store_id', 1);
        $country = data_get($params, Constant::DB_TABLE_COUNTRY, '');
        $query = static::getQuery($storeId, $country, $where);

        if ($isPage || $isOnlyGetCount) {
            $customerCount = static::adminCount($params, $query);
            $pagination['total'] = $customerCount;
            $pagination['total_page'] = ceil($customerCount / $limit);

            if ($isOnlyGetCount) {
                return $pagination;
            }
        }

        if (empty($customerCount)) {
            $query = null;
            return [
                'data' => [],
                Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
            ];
        }

        if ($order) {
            $query = $query->orderBy($order[0], $order[1]);
        }

        $data = [
            'query' => $query,
            Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
        ];

        $select = $select ? $select : ['*'];
        $data = static::getList($data, $toArray, $isPage, $select, $isRaw, $isGetQuery);

        if ($isGetQuery) {
            return $data;
        }

        //状态|1未使用2已使用3无效
        $statusData = [
            1 => '未使用',
            2 => '已使用',
            3 => '无效',
        ];
        if ($params['source'] == 'admin') {
            foreach ($data['data'] as $key => $item) {
                $data['data'][$key][Constant::DB_TABLE_STATUS] = Arr::get($statusData, $item[Constant::DB_TABLE_STATUS], $statusData[1]);
                $data['data'][$key][Constant::DB_TABLE_TYPE] = $item[Constant::DB_TABLE_TYPE] ? $item[Constant::DB_TABLE_TYPE] : $item[Constant::DB_TABLE_ASIN];
            }
        }

        return $data;
    }

    /**
     * 批量检查是否存在
     * @param $store_id
     * @param $tableData
     * @return array
     */
    public static function checkBatchExists($storeId, $tableData) {

        $codelist = array_column($tableData, Constant::RESPONSE_CODE_KEY);
        $codeBatch = array_chunk($codelist, 200);

        $model = static::getModel($storeId);
        foreach ($codeBatch as $codes) {
            $couponList = $model->whereIn(Constant::RESPONSE_CODE_KEY, array_values($codes))->pluck(Constant::RESPONSE_CODE_KEY)->toArray();
            if ($couponList) {
                $existsList = array_slice($couponList, 0, 10);
                $str = implode(',', $existsList);
                return Response::getDefaultResponseData(0, '导入失败,发现已存在数据code:' . $str . ',...');
            }
        }

        return Response::getDefaultResponseData(1);
    }

    /**
     * 转换成数据表数据
     * @param string $group
     * @param $excelData
     * @return array
     */
    public static function convToTableData($group = Constant::DB_EXECUTION_PLAN_GROUP_COMMON, $excelData = []) {
        $tableData = [];
        foreach ($excelData as $k => $row) {
            $country = data_get($row, 0, '');
            if (empty($country)) {
                continue;
            }

            data_set($tableData, $k . '.group', $group);
            data_set($tableData, $k . '.country', $country);
            data_set($tableData, $k . '.type', data_get($row, 1, ''));
            data_set($tableData, $k . '.code', data_get($row, 2, ''));
            data_set($tableData, $k . '.amount', data_get($row, 3, 0));

            $startTime = data_get($row, 4, false);
            if ($startTime !== false) {
                data_set($tableData, $k . '.satrt_time', $startTime);
            }

            $endTime = data_get($row, 5, false);
            if ($endTime !== false) {
                data_set($tableData, $k . '.end_time', $endTime);
            }
        }

        return $tableData;
    }

    /**
     * 批量添加
     * @param $listData
     * @return array
     */
    public static function addBatch($storeId, $listData) {

        $result = Response::getDefaultResponseData(1);
        $success = 0;
        $failCount = 0;

        //释放锁
        $serialHandle = [
            'releaseTime' => 0,
        ];

        $handleData = [
            Constant::SERIAL_HANDLE => $serialHandle,
        ];

        foreach ($listData as $row) {
            $row = Arr::first(static::convToTableData('common', [$row]));
            $where = [
                Constant::RESPONSE_CODE_KEY => data_get($row, Constant::RESPONSE_CODE_KEY, '-1')
            ];

            $_handleData = Arr::collapse([FunctionHelper::getDbBeforeHandle([], [], [], array_keys($row)), $handleData]);
            $rs = static::updateOrCreate($storeId, $where, $row, '', $_handleData);

            if (data_get($rs, 'lock') !== false) {
                $success++;
            } else {
                $failCount++;
            }
        }

        if (!$success) {
            $result[Constant::RESPONSE_CODE_KEY] = 0;
        }

        $result['msg'] = '合格 ' . $success . ' 个 and  false ' . $failCount . ' 个';
        return $result;
    }

    /**
     * 单个添加
     * @param int $storeId 商城id
     * @param array $data 数据
     * @return int
     */
    public static function addOne($storeId, $data) {
        return static::insert($storeId, $data);
    }

    /**
     * 监控Coupon使用情况
     * @return boolean
     */
    public static function monitorCoupon() {


        $storeData = Store::pluck('name', 'id');

        //模板类型 0 未选择 1 新品 2 常规 3 主推
        $activityProductMbtype = [
            0 => '未选择',
            1 => '新品',
            2 => '常规',
            3 => '主推',
        ];

        foreach ($storeData as $storeId => $storeName) {
            $type = Constant::COUPON;
            $orderby = 'sorts asc';
            $keyField = 'conf_key';
            $valueField = 'conf_value';

            $couponConfig = DictStoreService::getListByType($storeId, $type, $orderby, $keyField, $valueField);
            if ($couponConfig->isEmpty()) {
                continue;
            }

            $monitor = data_get($couponConfig, 'monitor', 0);
            if (empty($monitor)) {
                continue;
            }

            $countrys = data_get($couponConfig, Constant::DB_TABLE_COUNTRY, '');
            if (empty($countrys)) {
                continue;
            }

            $countrys = array_unique(array_filter(explode(',', $countrys)));
            if (empty($countrys)) {
                continue;
            }

            $data = [];
            $model = static::getModel($storeId);
            $activityProductModel = ActivityProductService::getModel($storeId, '');
            foreach ($countrys as $country) {
                $where = [
                    Constant::DB_TABLE_COUNTRY => $country,
                ];

                $group = Constant::DB_TABLE_TYPE;
                if ($storeId == 2) {
                    $group = Constant::DB_TABLE_ASIN;
                }
                $types = $model->select($group)->where($where)->groupBy($group)->get();
                if (empty($types)) {
                    $data[] = $storeName . ' 官网' . $country . '站点，code已经使用完，请在会员系统尽快上传更新code';
                    continue;
                }

                foreach ($types as $type) {

                    if ($storeId == 2) {
                        $mbTypes = $activityProductModel->select('mb_type', Constant::DB_TABLE_ASIN, Constant::DB_TABLE_COUNTRY)->where(['product_status' => 1])->get();
                        foreach ($mbTypes as $mbType) {
                            $mbTypeName = '';
                            if ($mbType) {
                                $mbTypeName = Arr::get($activityProductMbtype, $mbType->mb_type, '');
                                $mbTypeName = $mbTypeName ? (' ' . $mbTypeName . '模板，') : $mbTypeName;
                            }
                            $connt = $model->where([Constant::DB_TABLE_COUNTRY => $mbType->country, Constant::DB_TABLE_ASIN => $mbType->asin, Constant::DB_TABLE_EXTINFO => '', Constant::DB_TABLE_STATUS => 1])->count();
                            $msg = $storeName . ' 官网deal页面' . $mbTypeName . $mbType->country . '站点， ' . $mbType->asin;
                            if ($connt < 200) {

                                if ($connt == 0) {
                                    $msg .= 'code已经使用完，请在会员系统尽快上传更新code';
                                } else {
                                    $msg .= ' code快使用完，剩余：' . $connt . ' 请在会员系统尽快上传更新code';
                                }
                                $data[] = $msg;
                            }
                        }
                    } else {
                        $connt = $model->where([Constant::DB_TABLE_COUNTRY => $country, $group => $type->{$group}, Constant::DB_TABLE_STATUS => 1])->count();
                        if ($connt < 200) {

                            if ($connt == 0) {
                                $msg .= 'code已经使用完，请在会员系统尽快上传更新code';
                            } else {
                                $msg .= ' code快使用完，剩余：' . $connt . ' 请在会员系统尽快上传更新code';
                            }
                            $data[] = $msg;
                        }
                    }
                }
            }

            if ($data) {
                EmailService::sendToAdmin($storeId, 'coupon库存不足', implode('<br/>', $data));
            }
        }

        return true;
    }

    /**
     * VT Deal 后台coupon列表
     * @param $params
     * @param bool $toArray
     * @param bool $isPage
     * @return array
     */
    public static function getListDeal($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false) {

        $_data = static::getPublicData($params);

        $where = $_data[Constant::DB_EXECUTION_PLAN_WHERE];
        $order = $_data['order'];
        $pagination = $_data[Constant::DB_EXECUTION_PLAN_PAGINATION];
        $limit = $pagination['page_size'];

        $customerCount = true;
        $storeId = $params['store_id'] ?? 1;
        $country = $params[Constant::DB_TABLE_COUNTRY] ?? '';
        $query = static::getQuery($storeId, $country, $where);

        if ($isPage) {
            $customerCount = $query->count();
            $pagination['total'] = $customerCount;
            $pagination['total_page'] = ceil($customerCount / $limit);
        }

        if (empty($customerCount)) {
            $query = null;
            return [
                'data' => [],
                Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
            ];
        }

        $query = $query->orderBy($order[0], $order[1]);
        $data = [
            'query' => $query,
            Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
        ];

        $select = ['id', Constant::RESPONSE_CODE_KEY, Constant::DB_TABLE_COUNTRY, Constant::DB_TABLE_ASIN, Constant::DB_TABLE_RECEIVE, Constant::DB_TABLE_START_TIME, Constant::DB_TABLE_END_TIME, Constant::DB_TABLE_OLD_CREATED_AT, Constant::DB_TABLE_OLD_UPDATED_AT];
        $isGetQuery = true;
        $data = static::getList($data, $toArray, $isPage, $select, $isRaw, $isGetQuery);
        $receive = [
            1 => '已使用',
            2 => '未使用'
        ];
        $dbExecutionPlan = [
            'parent' => [
                'setConnection' => true,
                'storeId' => $storeId,
                'builder' => $data,
                'make' => '',
                'from' => '',
                'select' => $select,
                Constant::DB_EXECUTION_PLAN_WHERE => $where,
                'handleData' => [
                    Constant::DB_TABLE_RECEIVE => [
                        'field' => Constant::DB_TABLE_RECEIVE,
                        'data' => $receive,
                        'dataType' => '',
                        'dateFormat' => '',
                        'glue' => '',
                        'default' => Arr::get($receive, '2', ''),
                    ],
                ],
            //'unset' => ['customer_id'],
            ],
        ];

        $dataStructure = 'list';
        $flatten = true;
        $isGetQuery = false;
        $_data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);

        return [
            'data' => $_data,
            Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
        ];
    }

    /**
     * 导入 deal coupon
     * @param string $fileFullPath 文件完整路径
     * @param int $storeId 商城id
     * @param int $type 优惠券类型 1:独占型 2:通用型 3:限制型 默认:1
     * @param string $group 优惠券分组 默认：common
     * @return string
     */
    public static function dealCouponImport($fileFullPath, $storeId, $type, $group = Constant::DB_EXECUTION_PLAN_GROUP_COMMON) {

        $result = [Constant::RESPONSE_CODE_KEY => 1, 'msg' => '', 'data' => []];
        switch ($type) {
            case 1://1:独占型
                $typeData = [
                    \Vtiful\Kernel\Excel::TYPE_STRING,
                    \Vtiful\Kernel\Excel::TYPE_STRING,
                    \Vtiful\Kernel\Excel::TYPE_STRING,
                    \Vtiful\Kernel\Excel::TYPE_STRING,
                    \Vtiful\Kernel\Excel::TYPE_TIMESTAMP,
                    \Vtiful\Kernel\Excel::TYPE_TIMESTAMP,
                ];

                break;

            case 2://2:通用型

                $typeData = [
                    \Vtiful\Kernel\Excel::TYPE_STRING,
                    \Vtiful\Kernel\Excel::TYPE_STRING,
                    \Vtiful\Kernel\Excel::TYPE_STRING,
                    \Vtiful\Kernel\Excel::TYPE_STRING,
                    \Vtiful\Kernel\Excel::TYPE_STRING,
                    \Vtiful\Kernel\Excel::TYPE_STRING,
                    \Vtiful\Kernel\Excel::TYPE_TIMESTAMP,
                    \Vtiful\Kernel\Excel::TYPE_TIMESTAMP,
                ];

                break;

            default:
                break;
        }

        ExcelService::parseExcelFile($fileFullPath, $typeData, function ($row) use ($storeId, $group, $type) {

            $country = data_get($row, 0, '');
            if ($country == '国家' || empty($country)) {
                return true;
            }

            $tableData = [];

            data_set($tableData, Constant::DB_EXECUTION_PLAN_GROUP, $group);
            data_set($tableData, Constant::DB_TABLE_USE_TYPE, $type);
            data_set($tableData, Constant::DB_TABLE_COUNTRY, $country);
            data_set($tableData, Constant::DB_TABLE_TYPE, data_get($row, 1, ''));
            data_set($tableData, Constant::RESPONSE_CODE_KEY, data_get($row, 2, ''));
            data_set($tableData, Constant::DB_TABLE_AMOUNT, data_get($row, 3, ''));


            switch ($type) {
                case 1://1:独占型
                    $startTimeIndex = 4;
                    $endTimeIndex = 5;
                    data_set($tableData, Constant::DB_TABLE_ASIN, data_get($row, 1, ''));

                    break;

                case 2://2:通用型
                    $startTimeIndex = 6;
                    $endTimeIndex = 7;
                    data_set($tableData, Constant::DB_TABLE_ASIN, data_get($row, 4, ''));
                    data_set($tableData, Constant::DB_TABLE_AMAZON_URL, data_get($row, 5, ''));

                    break;

                default:
                    $startTimeIndex = 4;
                    $endTimeIndex = 5;
                    break;
            }

            $startTime = data_get($row, $startTimeIndex, false);
            if ($startTime !== false) {
                data_set($tableData, Constant::DB_TABLE_START_TIME, $startTime);
            }

            $endTime = data_get($row, $endTimeIndex, false);
            if ($endTime !== false) {
                data_set($tableData, Constant::DB_TABLE_END_TIME, $endTime);
            }

            static::couponAddOne($storeId, $tableData);
        });

        return $result;
    }

    /**
     * 导入添加code
     * @param int $storeId 商城id
     * @return $data
     */
    public static function couponAddOne($storeId, $data) {

        $useType = data_get($data, Constant::DB_TABLE_USE_TYPE, 0);
        $code = data_get($data, Constant::RESPONSE_CODE_KEY, '');
        $where = [
            Constant::DB_TABLE_USE_TYPE => $useType,
            Constant::DB_TABLE_COUNTRY => data_get($data, Constant::DB_TABLE_COUNTRY, ''),
            Constant::DB_TABLE_ASIN => data_get($data, Constant::DB_TABLE_ASIN, ''),
        ];

        if ($useType != 2) {//如果不是通用折扣码
            data_set($where, Constant::RESPONSE_CODE_KEY, $code);
        }

        $amount = data_get($data, Constant::DB_TABLE_AMOUNT, null);
        $getdata = [
            Constant::DB_EXECUTION_PLAN_GROUP => data_get($data, Constant::DB_EXECUTION_PLAN_GROUP, ''),
            Constant::DB_TABLE_TYPE => data_get($data, Constant::DB_TABLE_TYPE, ''),
            Constant::DB_TABLE_AMOUNT => $amount ? $amount : '0.00',
            Constant::DB_TABLE_AMAZON_URL => data_get($data, Constant::DB_TABLE_AMAZON_URL, ''),
            Constant::DB_TABLE_START_TIME => data_get($data, Constant::DB_TABLE_START_TIME, ''),
            Constant::DB_TABLE_END_TIME => data_get($data, Constant::DB_TABLE_END_TIME, ''),
            Constant::RESPONSE_CODE_KEY => $code,
        ];

        return static::updateOrCreate($storeId, $where, $getdata); //updateOrCreate：不可以添加主键id的值  updateOrInsert：可以添加主键id的值
    }

    /**
     * deal根据asin获取优惠券
     * @param $country
     * @return array
     */
    public static function getAsinCoupon($storeId, $country, $asin, $getTime, $customerId = 0, $couponSelect = [], $extData = []) {

        $whereCoupon = [
            Constant::DB_TABLE_COUNTRY => $country,
            Constant::DB_TABLE_EXTINFO => $customerId,
            Constant::DB_TABLE_STATUS => 2,
            Constant::DB_TABLE_ASIN => $asin,
            Constant::DB_EXECUTION_PLAN_GROUP => Constant::DB_EXECUTION_PLAN_GROUP_COMMON,
            Constant::DB_TABLE_USE_TYPE => 1,
        ];
        $existsCode = static::existsOrFirst($storeId, $country, $whereCoupon, true, $couponSelect);
        if ($existsCode) {
            return [
                Constant::RESPONSE_CODE_KEY => 1,
                Constant::RESPONSE_MSG_KEY => '',
                Constant::RESPONSE_DATA_KEY => $existsCode,
            ];
        }

        $model = static::getModel($storeId, $country);
        $where = [
            Constant::DB_TABLE_COUNTRY => $country,
            Constant::DB_TABLE_STATUS => 1,
            Constant::DB_TABLE_ASIN => $asin,
            Constant::DB_EXECUTION_PLAN_GROUP => Constant::DB_EXECUTION_PLAN_GROUP_COMMON,
            Constant::DB_TABLE_USE_TYPE => 1,
        ];
        //优惠劵生效时间如果为空，或者结束时间为空。优惠劵的有效范围
        $couponData = $model->select($couponSelect)
                        ->where($where)
                        ->where(function ($model) use($getTime) {
                            $model->whereNull(Constant::DB_TABLE_START_TIME)->orWhere(Constant::DB_TABLE_START_TIME, '<=', $getTime);
                        })
                        ->where(function ($model) use($getTime) {
                            $model->whereNull(Constant::DB_TABLE_END_TIME)->orWhere(Constant::DB_TABLE_END_TIME, '>=', $getTime);
                        })->first();

        if (!empty($couponData)) {

            static::setStatus($storeId, [data_get($couponData, Constant::DB_TABLE_PRIMARY, 0)], 2, '', $customerId, data_get($extData, 'account', '')); //分配优惠劵给用户

            return [
                Constant::RESPONSE_CODE_KEY => 1,
                Constant::RESPONSE_MSG_KEY => '',
                Constant::RESPONSE_DATA_KEY => $couponData,
            ];
        }

        $level = 'log';
        $type = 'deal_coupon';
        $subtype = $country;
        $keyinfo = $asin;
        $content = $country . ' coupon库存不足';
        $subkeyinfo = $customerId;
        $where = [
            'level' => $level, //日志等级
            Constant::DB_TABLE_TYPE => $type, //日志类型
            'subtype' => $subtype, //日志子类型
            'keyinfo' => $keyinfo, //关键信息
        ];
        $exists = LogService::existsSystemLog($storeId, $where);
        if (empty($exists)) {//查询日志发现没有重复数据则添加流水，发送邮件
            LogService::addSystemLog($level, $type, $subtype, $keyinfo, $content, $subkeyinfo); //添加系统日志
            EmailService::sendToAdmin($storeId, 'coupon库存不足', '官网：' . $storeId . ' ' . $country . ' ' . $asin . ' coupon 库存不足'); //发送 邮件 提醒管理员
        }

        return [
            Constant::RESPONSE_CODE_KEY => 10059,
            Constant::RESPONSE_MSG_KEY => 'Coupon has been used up',
            Constant::RESPONSE_DATA_KEY => [],
        ];
    }

    /**
     * 更新coupon领取情况
     * @param int $storeId 商城id
     * @param int $customerId 会员ID
     * @param int $Id 优惠劵ID
     * @return $data
     */
    public static function couponReceive($storeId, $customerId, $couponId) {
        $data = [
            Constant::DB_TABLE_RECEIVE => 1
        ];
        $where = [
            Constant::DB_TABLE_PRIMARY => $couponId,
            Constant::DB_TABLE_EXTINFO => $customerId,
        ];
        return static::update($storeId, $where, $data);
    }

    /**
     * 获取邮件数据
     * @param int $storeId 商城id
     * @param int $orderId 订单id
     * @param int $actId 活动id
     * @return string
     */
    public static function getEmailData($storeId, $actId, $parameters) {

        $couponCountry = data_get($parameters, Constant::DB_TABLE_COUNTRY, '');
        $emailData = Arr::collapse([$parameters, [
                        Constant::RESPONSE_CODE_KEY => 1,
                        'msg' => '',
                        'storeId' => $storeId, //商城id
                        'actId' => $actId, //活动id
                        'content' => '', //邮件内容
                        'subject' => '', //邮件主题
                        Constant::DB_TABLE_COUNTRY => $couponCountry, //邮件国家
                        Constant::DB_TABLE_EXTINFO => [], //邮件扩展数据
                        'isSendEmail' => false, //是否发送邮件 true：发送  false：不发送 默认：false
                    //'emailView' => 'emails.coupon.default',
        ]]);

        $isExists = data_get($parameters, 'isExists', false);
        if ($isExists) {
            return $emailData;
        }

        $data = [
            'CA57BN' => '',
            'GEPC034AB' => '',
            'GEPC049ABIT' => '',
            'GEPC066BB' => '',
            'GEPC066BR' => '',
            'GEPC173ABUS' => '',
            'GEPC217AB' => '',
            'HMHM235BWEU' => '',
            'HMHM235BWUK' => '',
            'VTBH267AB' => '',
            'VTCA004B' => '',
            'VTGEHM057ABUS' => '',
            'VTHM004YEU' => '',
            'VTHM004YUK' => '',
            'VTHM024ABEU' => '',
            'VTHM057AYEU' => '',
            'VTHM057BBUS' => '',
            'VTHM129BYUS' => '',
            'VTHM196AWEU' => '',
            'VTHM196BWEU' => '',
            'VTPC109AB' => '',
            'VTPC120AD' => '',
            'VTPC132AB' => '',
            'VTPC132ABES' => '',
            'VTPC149ABDE' => '',
            'VTPC149ABES' => '',
            'VTPC149ABIT' => '',
            'VTPC149ABUK' => '',
            'VTPC149ABUS' => '',
            'VTPC174ABUS' => '',
            'VTPC175ABDE' => '',
            'VTPC175ABFR' => '',
            'VTPC206ABFR' => '',
            'VTPC22BABUS' => '',
            'HM235BWEU' => '',
            'type_A' => '',
            'type_B' => '',
            'type_C' => '',
            'type_D' => '',
            'type_E' => '',
            'type_F' => '',
            'name' => '',
            'start_date' => '',
            'end_date' => '',
            'link' => '',
        ];


        $coupons = static::getRegCoupon($storeId, $couponCountry);
        if ($coupons->isEmpty()) {//如果没有优惠券，提示用户 优惠券 不足
            $emailData[Constant::RESPONSE_CODE_KEY] = 151;
            $emailData['msg'] = 'low stocks';

            LogService::addSystemLog('error', Constant::DB_TABLE_EMAIL, Constant::COUPON, $couponCountry . ' coupon库存不足'); //添加系统日志
            EmailService::sendToAdmin($storeId, 'coupon库存不足', '官网：' . $storeId . ' ' . $couponCountry . ' coupon 库存不足'); //发送 邮件 提醒管理员

            return $emailData;
        }

        $country = strtolower($couponCountry);
        $timeFormatKey = $storeId . '.' . strtoupper($country);
        $timeFormatData = static::$timeFormat;
        $timeFormat = data_get($timeFormatData, $timeFormatKey, data_get($timeFormatData, ($storeId . '.ALL'), data_get($timeFormatData, 'ALL', 'Y-m-d')));

        foreach ($coupons as $type => $coupon) {
            $data['type_' . $type] = data_get($coupon, Constant::RESPONSE_CODE_KEY, '');
            $data[$type] = data_get($coupon, Constant::RESPONSE_CODE_KEY, '');
            $data[$type . '_start_date'] = Carbon::parse(data_get($coupon, Constant::DB_TABLE_START_TIME, ''))->rawFormat($timeFormat);
            $data[$type . '_end_date'] = Carbon::parse(data_get($coupon, Constant::DB_TABLE_END_TIME, ''))->rawFormat($timeFormat);
        }

        $data["name"] = implode(' ', Arr::only($parameters, ['first_name', 'last_name']));
        if ($storeId == 5) {
            $data["name"] = data_get($parameters, 'first_name', '');
        }

        $startTime = Carbon::now()->rawFormat($timeFormat);
        $endTime = DictStoreService::getByTypeAndKey($storeId, Constant::COUPON, Constant::DB_TABLE_END_TIME, true); //获取coupon到期时间
        $endTime = $endTime ? $endTime : '+30day';
        $time = strtotime($endTime, Carbon::now()->timestamp); //holif订单延保时间为3年
        $endTime = Carbon::createFromTimestamp($time)->rawFormat($timeFormat);
        $data['start_date'] = $startTime;
        $data['end_date'] = $endTime;

        $link = DictStoreService::getByTypeAndKey($storeId, Constant::DB_TABLE_EMAIL, 'link', true, true, $country);
        $data['link'] = $link ? $link : '';

        //获取邮件模板
        $replacePairs = [];
        foreach ($data as $key => $value) {
            $replacePairs['{{$' . $key . '}}'] = $value;
        }
        $isCommon = DictStoreService::getByTypeAndKey($storeId, 'view_coupon', 'is_common', true); //coupon邮件模板是否通用 1:是 0:否  默认:0
        if ($isCommon) {//如果是通用 就获取通用coupon邮件模板
            $country = '';
        }
        $emailView = DictStoreService::getByTypeAndKey($storeId, Constant::DB_TABLE_EMAIL, 'view_coupon', true, true, $country); //获取coupon邮件模板
        $content = strtr($emailView, $replacePairs);

        $env = config('app.env', Constant::ENV_PRODUCTION);
        $env = $env != Constant::ENV_PRODUCTION ? ('-' . $env) : '';
        data_set($emailData, 'content', $content);
        data_set($emailData, 'subject', DictStoreService::getByTypeAndKey($storeId, Constant::DB_TABLE_EMAIL, 'coupon_subject', true)); // . $env
        data_set($emailData, Constant::DB_TABLE_COUNTRY, $couponCountry);
        data_set($emailData, Constant::DB_TABLE_EXTINFO, $coupons->pluck(Constant::RESPONSE_CODE_KEY)->all());
        data_set($emailData, 'isSendEmail', true);

        return $emailData;
    }

    /**
     * 获取奖励coupon
     * @param int $storeId 商城id
     * @param int $extId 奖励id
     * @param string $extType 奖励模型
     * @param int $customerId 账号id
     * @param string $rewardName 奖励名称
     * @param string $account 账号
     * @return array|obj $couponData
     */
    public static function getRewardCoupon($storeId, $extId, $extType, $customerId, $rewardName = '', $account = '') {
        $emailData = [
            Constant::SUBJECT => '订单索评奖励 coupon库存不足',
            Constant::DB_TABLE_CONTENT => '官网：' . $storeId . ' 订单索评奖励 ' . $rewardName . ' coupon 库存不足',
        ];

        return static::getRelatedCoupon($storeId, $extId, $extType, $customerId, $emailData, $account);
    }

    /**
     * 获取关联coupon
     * @param int $storeId 商城id
     * @param int $extId 关联id
     * @param string $extType 关联模型
     * @param int $customerId 账号id
     * @param array $emailData 通知邮件数据
     * @param string $account 账号
     * @return array|obj $couponData
     */
    public static function getRelatedCoupon($storeId, $extId, $extType, $customerId, $emailData = [], $account = '', $extWhere = []) {

        $nowTime = Carbon::now()->toDateTimeString();

        $where = data_get($extWhere, Constant::DB_EXECUTION_PLAN_WHERE);
        if ($where === null) {
            $where = [
                Constant::DB_TABLE_EXT_ID => $extId,
                Constant::DB_TABLE_EXT_TYPE => $extType,
                Constant::DB_TABLE_STATUS => 1,
                '{customizeWhere}' => [
                    [
                        Constant::METHOD_KEY => Constant::DB_EXECUTION_PLAN_WHERE,
                        Constant::PARAMETERS_KEY => function ($query) use($nowTime) {
                            $query->whereNull(Constant::DB_TABLE_START_TIME)->orWhere(Constant::DB_TABLE_START_TIME, '<=', $nowTime);
                        },
                    ],
                    [
                        Constant::METHOD_KEY => Constant::DB_EXECUTION_PLAN_WHERE,
                        Constant::PARAMETERS_KEY => function ($query) use($nowTime) {
                            $query->whereNull(Constant::DB_TABLE_END_TIME)->orWhere(Constant::DB_TABLE_END_TIME, '>=', $nowTime);
                        },
                    ],
                ],
            ];


            $collapseWhere = data_get($extWhere, 'collapseWhere');
            if ($collapseWhere) {
                $where = Arr::collapse([$where, $collapseWhere]);
            }
        }

        $select = [
            Constant::DB_TABLE_PRIMARY,
            Constant::RESPONSE_CODE_KEY,
            Constant::DB_TABLE_START_TIME,
            Constant::DB_TABLE_END_TIME,
            Constant::DB_TABLE_USE_TYPE,
            Constant::DB_TABLE_AMAZON_URL,
        ];
        $couponData = static::existsOrFirst($storeId, '', $where, true, $select);
        if (!empty($couponData)) {
            if ($customerId && data_get($couponData, Constant::DB_TABLE_USE_TYPE, Constant::PARAMETER_INT_DEFAULT) == 1) {//如果是独占型，就更新为已占用
                static::setStatus($storeId, [data_get($couponData, Constant::DB_TABLE_PRIMARY, 0)], 2, '', $customerId, $account); //分配优惠劵给用户
            }
            return $couponData;
        }

        $level = 'log';
        $type = $extType;
        $subtype = $extId;
        $keyinfo = $extType;
        $content = $extType . ' coupon库存不足';
        $subkeyinfo = $customerId;
        $where = [
            'level' => $level, //日志等级
            Constant::DB_TABLE_TYPE => $type, //日志类型
            'subtype' => $subtype, //日志子类型
            'keyinfo' => $keyinfo, //关键信息
        ];
        $exists = LogService::existsSystemLog($storeId, $where);
        if (empty($exists)) {//查询日志发现没有重复数据则添加流水，发送邮件
            LogService::addSystemLog($level, $type, $subtype, $keyinfo, $content, $subkeyinfo); //添加系统日志

            if (config('app.env', Constant::ENV_PRODUCTION) == Constant::ENV_PRODUCTION) {
                EmailService::sendToAdmin($storeId, data_get($emailData, Constant::SUBJECT, ''), data_get($emailData, Constant::DB_TABLE_CONTENT, '')); //发送 邮件 提醒管理员
            }
        }

        return $couponData;
    }

    /**
     * 折扣码模板文件头
     * @var array
     */
    public static $codeHeaderMap = [
        '国家 （必填）' => Constant::DB_TABLE_COUNTRY,
        '产品ASIN（必填）' => Constant::DB_TABLE_ASIN,
        '折扣码（必填）' => Constant::RESPONSE_CODE_KEY,
        '开始时间（必填）' => Constant::START_TIME,
        '结束时间（必填）' => Constant::DB_TABLE_END_TIME,
        '使用场景（必填）' => Constant::DB_TABLE_USE_CHANNEL,
        '折扣码跳转链接' => Constant::DB_TABLE_AMAZON_URL,
        '备注' => Constant::DB_TABLE_REMARKS,
    ];

    /**
     * 折扣码转换成数据表数据
     * @param array $excelData 文件数据
     * @param string $group 分组
     * @param int $useType 使用类型 1:独占型 2:通用型 3:限制型 默认:1
     * @return array
     */
    public static function convertToTableData($storeId, $extId, $extType, $group, $header, $excelData, $useType = 1, $codeType = 1) {

        if (empty($excelData)) {
            return [];
        }

        $tableData = [];

        $hostData = [
            '官网' => implode('/', ['https:/', FunctionHelper::getShopifyHost($storeId)]),
        ];

        $amazonHostData = DictService::getListByType(Constant::AMAZON_HOST, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE);

        foreach ($excelData as $k => $row) {

            $country = strtoupper(data_get($row, data_get($header, Constant::DB_TABLE_COUNTRY), Constant::PARAMETER_STRING_DEFAULT));
            $startTime = data_get($row, data_get($header, Constant::START_TIME), null);
            $endTime = data_get($row, data_get($header, Constant::DB_TABLE_END_TIME), null);
            $asin = data_get($row, data_get($header, Constant::DB_TABLE_ASIN), Constant::PARAMETER_STRING_DEFAULT);
            $code = data_get($row, data_get($header, Constant::RESPONSE_CODE_KEY), Constant::PARAMETER_STRING_DEFAULT);

            $useChannel = trim(data_get($row, data_get($header, Constant::DB_TABLE_USE_CHANNEL), Constant::PARAMETER_STRING_DEFAULT));
            $remarks = data_get($row, data_get($header, Constant::DB_TABLE_REMARKS), Constant::PARAMETER_STRING_DEFAULT);
            $amazonUrl = data_get($row, data_get($header, Constant::DB_TABLE_AMAZON_URL), Constant::PARAMETER_STRING_DEFAULT);

            if (empty($amazonUrl)) {

                switch ($useChannel) {
                    case '官网':
                        $amazonUrl = implode('/', ['https:/', FunctionHelper::getShopifyHost($storeId)]);

                        break;

                    case '亚马逊':
                        $amazonUrl = implode('/', [data_get($amazonHostData, $country, data_get($amazonHostData, 'US', Constant::PARAMETER_STRING_DEFAULT)), 'dp', $asin]);

                        break;

                    default:
                        break;
                }
            }


            if (empty($code)) {
                continue;
            }

            data_set($tableData, $k, [
                Constant::DB_EXECUTION_PLAN_GROUP => $group,
                Constant::DB_TABLE_COUNTRY => $country,
                Constant::DB_TABLE_ASIN => $asin,
                Constant::RESPONSE_CODE_KEY => $code,
                Constant::DB_TABLE_START_TIME => $startTime,
                Constant::DB_TABLE_END_TIME => $endTime,
                Constant::DB_TABLE_CODE_TYPE => $codeType,
                Constant::DB_TABLE_USE_TYPE => $useType,
                Constant::DB_TABLE_EXT_ID => $extId,
                Constant::DB_TABLE_EXT_TYPE => $extType,
                Constant::DB_TABLE_AMAZON_URL => $amazonUrl, //折扣码跳转链接
                Constant::DB_TABLE_USE_CHANNEL => $useChannel, //使用场景（必填）
                Constant::DB_TABLE_REMARKS => $remarks, //备注
            ]);
        }

        return $tableData;
    }

    /**
     * 获取数据表映射
     * @param array $data
     * @return array 数据表映射
     */
    public static function getHeader($data) {
        $temp = array_flip(array_filter($data, function ($value) {
                    return $value !== null;
                }));
        $header = [];
        foreach (static::$codeHeaderMap as $key => $value) {
            data_set($header, $value, data_get($temp, $key));
        }
        return $header;
    }

    /**
     * 批量添加
     * @param $listData
     * @return array
     */
    public static function addRelatedCoupon($storeId, $extId, $extType, $group, $header, $listData, $useType = 1, $codeType = 1) {

        $result = Response::getDefaultResponseData(1);
        $success = 0;
        $failCount = 0;

        //释放锁
        $serialHandle = [
            'releaseTime' => 0,
        ];

        $handleData = [
            Constant::SERIAL_HANDLE => $serialHandle,
        ];

        foreach ($listData as $row) {
            $row = Arr::first(static::convertToTableData($storeId, $extId, $extType, $group, $header, [$row], $useType, $codeType));
            $where = [
                Constant::RESPONSE_CODE_KEY => data_get($row, Constant::RESPONSE_CODE_KEY, '-1')
            ];

            $_handleData = Arr::collapse([FunctionHelper::getDbBeforeHandle([], [], [], array_keys($row)), $handleData]);
            $rs = static::updateOrCreate($storeId, $where, $row, '', $_handleData);

            if (data_get($rs, 'lock') !== false) {
                $success++;
            } else {
                $failCount++;
            }
        }

        if (!$success) {
            $result[Constant::RESPONSE_CODE_KEY] = 0;
        }

        $result['msg'] = '合格 ' . $success . ' 个 and  false ' . $failCount . ' 个';
        return $result;
    }

    /**
     * 获取折扣码数据
     * @param array $requestData 请求参数
     * @param string $group 折扣码分组
     * @param int $useType 使用类型
     * @return array
     */
    public static function importRelatedCoupon($requestData, $group = '', $useType = 1, $codeType = 1) {

        ini_set('memory_limit', '2048M');

        $data = [];
        $file = data_get($requestData, Constant::UPLOAD_FILE_KEY);
        $fileData = CdnManager::upload(Constant::UPLOAD_FILE_KEY, $file, '/upload/file/');
        if (data_get($fileData, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_INT_DEFAULT) != 1) {
            return $data;
        }

        $typeData = [
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_TIMESTAMP,
            \Vtiful\Kernel\Excel::TYPE_TIMESTAMP,
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
        ];

        try {
            $data = ExcelService::parseExcelFile(data_get($fileData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::FILE_FULL_PATH, ''), $typeData);
        } catch (\Exception $exception) {
            return $data;
        }

        $header = static::getHeader(data_get($data, 0, Constant::PARAMETER_ARRAY_DEFAULT));

        if (isset($data[0])) {
            unset($data[0]); //删除excel表中的表头数据
        }

        $storeId = data_get($requestData, Constant::DB_TABLE_STORE_ID, 0);
        $extId = data_get($requestData, Constant::DB_TABLE_EXT_ID, 0);
        $extType = data_get($requestData, Constant::DB_TABLE_EXT_TYPE, '');

        $dataBatch = array_chunk($data, 2000);
        $service = static::getNamespaceClass();
        $method = 'addRelatedCoupon';
        foreach ($dataBatch as $_data) {
            $parameters = [$storeId, $extId, $extType, $group, $header, $_data, $useType, $codeType];
            FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters), null, '{data-import}');
        }

        return count($data) . ' 条数据上传成功，正在写入系统，大概需要 3 分钟完成';
    }

}
