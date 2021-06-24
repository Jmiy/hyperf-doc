<?php

namespace App\Services;

use App\Utils\Cdn\CdnManager;
use App\Constants\Constant;
use App\Utils\Response;
use Carbon\Carbon;
use Hyperf\Utils\Arr;
use App\Exception\Handler\AppExceptionHandler as ExceptionHandler;
use App\Utils\FunctionHelper;
use Hyperf\DbConnection\Db as DB;
use App\Services\Platform\OrderService;

class RewardService extends BaseService {

    /**
     * 一次性折扣码模板文件头
     * @var array
     */
    public static $codeHeaderMap = [
        '站点' => Constant::DB_TABLE_COUNTRY,
        'ASIN' => Constant::DB_TABLE_ASIN,
        '奖励(折扣码)' => Constant::RESPONSE_CODE_KEY,
        '开始时间' => 'start_time',
        '结束时间' => Constant::DB_TABLE_END_TIME
    ];

    /**
     * 礼品卡模板文件头
     * @var array
     */
    public static $giftCardHeaderMap = [
        '店铺SKU' => Constant::DB_TABLE_SKU,
        'Asin' => Constant::DB_TABLE_ASIN,
        '站点' => Constant::DB_TABLE_COUNTRY,
        '礼品卡值' => Constant::DB_TABLE_NAME,
    ];

    /**
     * 礼品是否存在
     * @param int $storeId
     * @param array $requestData
     * @return int
     */
    public static function isDo($storeId, $asinRecords, $id) {

        $exitsAsinData = [];
        if (empty($asinRecords)) {
            return $exitsAsinData;
        }

        $query = static::getModel($storeId)
                ->from('rewards as r')
                ->leftjoin('reward_asins as ra', function ($join) {
            $join->on([['ra' . Constant::LINKER . Constant::DB_TABLE_EXT_ID, '=', 'r' . Constant::LINKER . Constant::DB_TABLE_PRIMARY]])
            ->where('ra' . Constant::LINKER . Constant::DB_TABLE_EXT_TYPE, '=', 'Reward')
            ->where('ra' . Constant::LINKER . Constant::DB_TABLE_STATUS, '=', 1)
            ;
        });

        if ($id) {
            $query = $query->where('r' . Constant::LINKER . Constant::DB_TABLE_PRIMARY, '!=', $id);
        }

        foreach ($asinRecords as $item) {
            $_query = clone $query;
            $asin = data_get($item, Constant::DB_TABLE_ASIN);
            $country = data_get($item, Constant::DB_TABLE_COUNTRY);
            $count = $_query->where([
                        'ra' . Constant::LINKER . Constant::DB_TABLE_ASIN => $asin,
                        'ra' . Constant::LINKER . Constant::DB_TABLE_COUNTRY => $country,
                    ])->count();

            unset($_query);
            if ($count) {
                $exitsAsinData[] = [
                    Constant::DB_TABLE_ASIN => $asin,
                    Constant::DB_TABLE_COUNTRY => $country,
                ];
                break;
            }
        }

        return $exitsAsinData;
    }

    /**
     * 添加礼品数据
     * @param int $storeId 官网id
     * @param array $requestData 请求参数
     * @return mixed return [code => 1, data => [], msg => '']
     */
    public static function add($storeId, $requestData) {

        $result = static::getAsinCode($requestData);
        $asinRecords = data_get($result, 'asin_arr', []);
        $codeRecords = data_get($result, 'code_arr', []);
        $giftRecords = data_get($result, 'gift_arr', []);

        $exitsAsinData = static::isDo($storeId, $asinRecords, 0);
        if (!empty($exitsAsinData)) {
            return Response::getDefaultResponseData(-1, 'ASIN+站点已经存在: ' . data_get($exitsAsinData, '0.' . Constant::DB_TABLE_ASIN) . '+' . data_get($exitsAsinData, '0.' . Constant::DB_TABLE_COUNTRY));
        }

        $type = data_get($requestData, Constant::DB_TABLE_TYPE, 0);
        $name = data_get($requestData, Constant::DB_TABLE_NAME, Constant::PARAMETER_STRING_DEFAULT);
        $businessType = data_get($requestData, Constant::BUSINESS_TYPE, 1);

        $rewardModel = static::getModel($storeId)->getConnection();
        try {
            $rewardModel->beginTransaction();

            //添加礼品记录
            $rewardId = static::addReward($storeId, $requestData);
            if (empty($rewardId)) {
                throw new \Exception(null, 9900000002);
            }

            //添加asin
            $asinRet = RewardAsinService::addAsins($storeId, $rewardId, $name, $businessType, $asinRecords, $requestData);
            if (!$asinRet) {
                throw new \Exception(null, 9900000002);
            }

            $records = [];
            switch ($type) {
                case 2://折扣码
                    $records = $codeRecords;

                    break;

                case 1://礼品卡
                    $records = $giftRecords;


                    break;

                default:
                    break;
            }

            if ($records) {
                $codeRet = static::addCodes($storeId, $rewardId, $records); //添加code
                if (!$codeRet) {
                    throw new \Exception(null, 9900000002);
                }
            }

            //类目
            $categoryData = data_get($requestData, 'category_data', []);
            RewardCategoryService::handle($storeId, $rewardId, $categoryData);

            $rewardModel->commit();
        } catch (\Exception $exc) {
            $rewardModel->rollBack();
            return Response::getDefaultResponseData($exc->getCode(), '礼品添加失败');
        }

        return Response::getDefaultResponseData(1, '礼品添加成功');
    }

    /**
     * 构建礼品asin数据
     * @param array $data coupon|gift
     * @return array
     */
    public static function buildRewardAsin($data) {
        $asinRecords = [];

        foreach ($data as $item) {
            $asin = data_get($item, Constant::DB_TABLE_ASIN);
            $country = data_get($item, Constant::DB_TABLE_COUNTRY);
            $key = implode('_', [$asin, $country]);
            if (!isset($asinRecords[$key])) {
                $asinRecords[$key] = [
                    Constant::DB_TABLE_ASIN => $asin,
                    Constant::DB_TABLE_COUNTRY => $country,
                ];
            }
        }

        return $asinRecords;
    }

    /**
     * 从折扣码文件解析折扣码数据 或 从请求参数中解析asin数据
     * @param array $requestData 请求参数
     * @param string $group 折扣码分组
     * @return array
     */
    public static function getAsinCode($requestData, $group = 'reward') {
        $asins = data_get($requestData, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT);
        $countries = data_get($requestData, Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT);

        $asinRecords = [];
        $codeRecords = [];
        $giftRecords = [];
        $type = data_get($requestData, Constant::DB_TABLE_TYPE); //礼品类型: 0:其他 1:礼品卡 2:coupon 3:实物 5:积分
        //礼品卡
        if ($type == 1) {
            $giftRecords = static::getGiftCardRecords($requestData, $group, 2);
            $asinRecords = static::buildRewardAsin($giftRecords);
        }

        //折扣码
        if ($type == 2) {
            $codeRecords = static::getCodeRecords($requestData, $group, 1);
            $asinRecords = static::buildRewardAsin($codeRecords);
        }

        //其他 或者 实物 或者 积分
        if (in_array($type, [0, 3, 5])) {
            $asinArr = array_unique(array_filter(explode(",", $asins)));
            $countries = array_unique(array_filter(explode(",", $countries)));
            foreach ($asinArr as $asin) {
                foreach ($countries as $country) {
                    $asinRecords[] = [
                        Constant::DB_TABLE_ASIN => trim($asin),
                        Constant::DB_TABLE_COUNTRY => $country,
                    ];
                }
            }
        }

        return [
            'asin_arr' => $asinRecords,
            'code_arr' => $codeRecords,
            'gift_arr' => $giftRecords,
        ];
    }

    /**
     * 添加/更新礼品记录
     * @param int $storeId 官网id
     * @param array $requestData 请求参数
     * @return mixed
     */
    public static function addReward($storeId, $requestData) {
        $rewardRecord = [
            Constant::BUSINESS_TYPE => data_get($requestData, Constant::BUSINESS_TYPE, Constant::PARAMETER_INT_DEFAULT),
            Constant::DB_TABLE_TYPE => data_get($requestData, Constant::DB_TABLE_TYPE, Constant::PARAMETER_INT_DEFAULT),
            Constant::DB_TABLE_TYPE_VALUE => data_get($requestData, Constant::DB_TABLE_TYPE_VALUE, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_REMARKS => data_get($requestData, Constant::DB_TABLE_REMARKS, Constant::PARAMETER_STRING_DEFAULT),
            Constant::PRODUCT_TYPE => data_get($requestData, Constant::PRODUCT_TYPE, Constant::PARAMETER_INT_DEFAULT),
            Constant::DB_TABLE_NAME => data_get($requestData, Constant::DB_TABLE_NAME, Constant::PARAMETER_STRING_DEFAULT),
        ];

        $where = [
            Constant::DB_TABLE_PRIMARY => data_get($requestData, Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT),
        ];

        $dbRet = static::updateOrCreate($storeId, $where, $rewardRecord, '', FunctionHelper::getDbBeforeHandle([], [], [], array_keys($rewardRecord)));

        return data_get($dbRet, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
    }

    /**
     * 获取折扣码数据
     * @param array $requestData 请求参数
     * @param string $group 折扣码分组
     * @param int $useType 使用类型
     * @return array
     */
    public static function getCodeRecords($requestData, $group = 'reward', $useType = 1) {

        ini_set('memory_limit', '512M');

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
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_TIMESTAMP,
            \Vtiful\Kernel\Excel::TYPE_TIMESTAMP,
            \Vtiful\Kernel\Excel::TYPE_STRING,
        ];

        try {
            $data = ExcelService::parseExcelFile(data_get($fileData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::FILE_FULL_PATH, ''), $typeData);
        } catch (\Exception $exception) {
            return $data;
        }

        return static::convertToTableData($data, $group, $useType);
    }

    /**
     * 获取礼品卡数据
     * @param array $requestData 请求参数
     * @param string $group 分组
     * @param int $useType 使用类型
     * @return array
     */
    public static function getGiftCardRecords($requestData, $group = 'reward', $useType = 1) {

        ini_set('memory_limit', '512M');

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
        ];

        try {
            $data = ExcelService::parseExcelFile(data_get($fileData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::FILE_FULL_PATH, Constant::PARAMETER_STRING_DEFAULT), $typeData);
        } catch (\Exception $exception) {
            return $data;
        }

        $name = data_get($requestData, Constant::DB_TABLE_NAME, Constant::PARAMETER_STRING_DEFAULT);

        return static::giftConvertToTableData($data, $name, $group, $useType);
    }

    /**
     * 添加code
     * @param int $storeId 官网id
     * @param int $rewardId 礼品id
     * @param array $records code数据
     * @return mixed
     */
    public static function addCodes($storeId, $rewardId, $records) {
        if (empty($records)) {
            return false;
        }

        foreach ($records as $record) {
            $where = [
                Constant::DB_TABLE_CODE_TYPE => $record[Constant::DB_TABLE_CODE_TYPE],
                Constant::DB_EXECUTION_PLAN_GROUP => $record[Constant::DB_EXECUTION_PLAN_GROUP],
                Constant::RESPONSE_CODE_KEY => $record[Constant::RESPONSE_CODE_KEY],
                Constant::DB_TABLE_ASIN => $record[Constant::DB_TABLE_ASIN],
                Constant::DB_TABLE_COUNTRY => $record[Constant::DB_TABLE_COUNTRY]
            ];
            $data = [
                Constant::DB_TABLE_START_TIME => $record[Constant::DB_TABLE_START_TIME],
                Constant::DB_TABLE_END_TIME => $record[Constant::DB_TABLE_END_TIME],
                Constant::DB_TABLE_EXT_ID => $rewardId,
                Constant::DB_TABLE_EXT_TYPE => static::getMake(),
                Constant::DB_TABLE_USE_TYPE => $record[Constant::DB_TABLE_USE_TYPE],
            ];

            $dbRet = static::getModel($storeId, '', [], 'Coupon')->updateOrCreate($where, $data);
            if (data_get($dbRet, Constant::DB_OPERATION) == Constant::DB_OPERATION_DEFAULT) {
                return false;
            }
        }

        return true;
    }

    /**
     * 折扣码转换成数据表数据
     * @param array $excelData 文件数据
     * @param string $group 分组
     * @param int $useType 使用类型
     * @return array
     */
    public static function convertToTableData($excelData, $group = 'reward', $useType = 1) {
        if (empty($excelData)) {
            return [];
        }

        $tableData = [];
        $header = [];
        $isHeader = true;
        foreach ($excelData as $k => $row) {
            if ($isHeader) {
                $temp = array_flip($row);
                foreach (static::$codeHeaderMap as $key => $value) {
                    data_set($header, $value, data_get($temp, $key));
                }
                $isHeader = false;
                continue;
            }

            $country = data_get($row, data_get($header, Constant::DB_TABLE_COUNTRY), Constant::PARAMETER_STRING_DEFAULT);
            $startTime = data_get($row, data_get($header, 'start_time'), Constant::PARAMETER_STRING_DEFAULT);
            $endTime = data_get($row, data_get($header, Constant::DB_TABLE_END_TIME), Constant::PARAMETER_STRING_DEFAULT);
            $asin = data_get($row, data_get($header, Constant::DB_TABLE_ASIN), Constant::PARAMETER_STRING_DEFAULT);
            $code = data_get($row, data_get($header, Constant::RESPONSE_CODE_KEY), Constant::PARAMETER_STRING_DEFAULT);
            if (empty($country) || empty($startTime) || empty($endTime) || empty($asin) || empty($code)) {
                continue;
            }

            data_set($tableData, $k . '.group', $group);
            data_set($tableData, $k . '.country', $country);
            data_set($tableData, $k . '.asin', $asin);
            data_set($tableData, $k . '.code', $code);
            data_set($tableData, $k . '.satrt_time', $startTime);
            data_set($tableData, $k . '.end_time', $endTime);
            data_set($tableData, $k . '.code_type', 1);
            data_set($tableData, $k . '.use_type', $useType);
        }

        return array_column($tableData, NULL, Constant::RESPONSE_CODE_KEY);
    }

    /**
     * 礼品卡转换成数据表数据
     * @param array $excelData 从excel读取的数据
     * @param string $name 礼品名
     * @param string $group 分组
     * @param int $useType 使用类型
     * @return array
     */
    public static function giftConvertToTableData($excelData, $name, $group = 'reward', $useType = 2) {
        if (empty($excelData)) {
            return [];
        }

        $header = [];
        $tableData = [];
        $isHeader = true;
        foreach ($excelData as $k => $row) {
            if ($isHeader) {
                $temp = array_flip($row);
                foreach (static::$giftCardHeaderMap as $key => $value) {
                    data_set($header, $value, data_get($temp, $key));
                }
                $isHeader = false;
                continue;
            }

            $country = data_get($row, data_get($header, Constant::DB_TABLE_COUNTRY), Constant::PARAMETER_STRING_DEFAULT);
            $asin = data_get($row, data_get($header, Constant::DB_TABLE_ASIN), Constant::PARAMETER_STRING_DEFAULT);
            if (empty($country) || empty($asin)) {
                continue;
            }

            data_set($tableData, $k . '.group', $group);
            data_set($tableData, $k . '.country', data_get($row, data_get($header, Constant::DB_TABLE_COUNTRY), Constant::PARAMETER_STRING_DEFAULT));
            data_set($tableData, $k . '.asin', data_get($row, data_get($header, Constant::DB_TABLE_ASIN), Constant::PARAMETER_STRING_DEFAULT));
            data_set($tableData, $k . '.code', $name);
            data_set($tableData, $k . '.satrt_time', '2020-05-01 00:00:00');
            data_set($tableData, $k . '.end_time', '2030-12-31 23:59:59');
            data_set($tableData, $k . '.code_type', 2);
            data_set($tableData, $k . '.use_type', $useType);
        }

        return $tableData;
    }

    /**
     * 获取礼品id
     * @param int $storeId
     * @param array $requestData
     * @return array
     */
    public static function getRewardIds($storeId, $requestData) {
        $rewardIds = [];
        $asin = data_get($requestData, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT);
        if (!empty($asin)) {
            $where = [
                Constant::DB_TABLE_ASIN => $asin
            ];
            $rewardIds = static::getModel($storeId, '', [], 'RewardAsin')->buildWhere($where)->select(['ext_id as reward_id'])->get();
        }
        !empty($rewardIds) && $rewardIds = array_column($rewardIds->toArray(), 'reward_id');

        return $rewardIds;
    }

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @param array $order 排序
     * @return array
     */
    public static function getPublicData($params, $order = []) {

        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, 0); //商城id

        $rewardDbConfig = static::getDbConfig($storeId);
        $rewardTableAlias = data_get($rewardDbConfig, 'table_alias', '');

        $rewardAsinDbConfig = RewardAsinService::getDbConfig($storeId);
        $rewardAsinTableAlias = data_get($rewardAsinDbConfig, 'table_alias', '');

        $rewardCategoryDbConfig = RewardCategoryService::getDbConfig($storeId);
        $rewardCategoryTableAlias = data_get($rewardCategoryDbConfig, 'table_alias', '');

        $where = [];

        $rewardName = $params[Constant::DB_TABLE_NAME] ?? ''; //奖品名称
        $type = $params[Constant::DB_TABLE_TYPE] ?? ''; //奖品类型
        $status = intval($params[Constant::DB_TABLE_STATUS] ?? ''); //状态
        $startTime = $params['start_time'] ?? ''; //开始时间
        $endTime = $params[Constant::DB_TABLE_END_TIME] ?? ''; //结束时间
        $country = $params[Constant::DB_TABLE_COUNTRY] ?? Constant::PARAMETER_STRING_DEFAULT; //国家

        if ($rewardName) {
            $where[] = [$rewardTableAlias . Constant::LINKER . Constant::DB_TABLE_NAME, '=', $rewardName];
        }

        if ($type !== '') {
            $where[] = [$rewardTableAlias . Constant::LINKER . Constant::DB_TABLE_TYPE, '=', $type];
        }

        if ($status) {
            $where[] = [$rewardTableAlias . Constant::LINKER . Constant::DB_TABLE_STATUS, '=', $status];
        }

        if ($startTime) {
            $where[] = [$rewardTableAlias . Constant::LINKER . Constant::DB_TABLE_CREATED_AT, '>=', $startTime];
        }

        if ($endTime) {
            $where[] = [$rewardTableAlias . Constant::LINKER . Constant::DB_TABLE_CREATED_AT, '<=', $endTime];
        }

        $_where = [];
        if (data_get($params, Constant::DB_TABLE_PRIMARY, 0)) {
            $_where[$rewardTableAlias . Constant::LINKER . Constant::DB_TABLE_PRIMARY] = $params[Constant::DB_TABLE_PRIMARY];
        }

        if ($where) {
            $_where[] = $where;
        }

        $customizeWhere = [];
        $asin = $params['asin'] ?? '';
        $rewardAsinWhereFields = [];
        if ($asin) {
            $rewardAsinWhereFields[] = [
                'field' => $rewardAsinTableAlias . Constant::LINKER . 'asin',
                Constant::DB_TABLE_VALUE => $asin,
            ];
        }

        if ($country) {//国家简写
            $rewardAsinWhereFields[] = [
                'field' => $rewardAsinTableAlias . Constant::LINKER . Constant::DB_TABLE_COUNTRY,
                Constant::DB_TABLE_VALUE => $country,
            ];
        }

        if ($rewardAsinWhereFields) {
            $rewardAsinWhereColumns = [
                [
                    'foreignKey' => Constant::DB_TABLE_EXT_ID,
                    'localKey' => $rewardTableAlias . Constant::LINKER . Constant::DB_TABLE_PRIMARY,
                ]
            ];
            $customizeWhere = Arr::collapse([$customizeWhere, RewardAsinService::buildWhereExists($storeId, $rewardAsinWhereFields, $rewardAsinWhereColumns)]);
        }



        $category_code = $params['category_code'] ?? '';
        if ($category_code) {
            $whereFields[] = [
                'field' => $rewardCategoryTableAlias . Constant::LINKER . 'category_code',
                Constant::DB_TABLE_VALUE => $category_code,
            ];

            $whereColumns = [
                [
                    'foreignKey' => 'reward_id',
                    'localKey' => $rewardTableAlias . Constant::LINKER . Constant::DB_TABLE_PRIMARY,
                ]
            ];

            $customizeWhere = Arr::collapse([$customizeWhere, RewardCategoryService::buildWhereExists($storeId, $whereFields, $whereColumns)]);
        }

        if ($customizeWhere) {
            $_where['{customizeWhere}'] = $customizeWhere;
        }

        $order = $order ? $order : [[$rewardTableAlias . Constant::LINKER . Constant::DB_TABLE_PRIMARY, Constant::DB_EXECUTION_PLAN_ORDER_DESC]];
        return Arr::collapse([parent::getPublicData($params, $order), [
                        Constant::DB_EXECUTION_PLAN_WHERE => $_where,
        ]]);
    }

    /**
     * 礼品编辑
     * @param int $storeId
     * @param array $requestData
     * @return mixed
     */
    public static function edit($storeId, $requestData) {

        $rewardId = data_get($requestData, Constant::DB_TABLE_PRIMARY, 0);

        $rewardStatus = data_get($requestData, Constant::REWARD_STATUS, Constant::WHETHER_YES_VALUE); //礼品状态
        $asins = data_get($requestData, Constant::DEL_ASIN, Constant::PARAMETER_STRING_DEFAULT); //礼品状态

        if (empty($rewardId)) {
            return Response::getDefaultResponseData(-1, '礼品不存在', []);
        }

        $result = static::getAsinCode($requestData);
        $asinRecords = data_get($result, 'asin_arr', []);
        $codeRecords = data_get($result, 'code_arr', []);
        $giftRecords = data_get($result, 'gift_arr', []);

        $exitsAsinData = static::isDo($storeId, $asinRecords, $rewardId);
        if (!empty($exitsAsinData)) {
            return Response::getDefaultResponseData(-1, 'ASIN+站点已经存在: ' . data_get($exitsAsinData, '0.' . Constant::DB_TABLE_ASIN) . '+' . data_get($exitsAsinData, '0.' . Constant::DB_TABLE_COUNTRY));
        }

        $type = data_get($requestData, Constant::DB_TABLE_TYPE, 0);
        $extId = data_get($requestData, Constant::DB_TABLE_PRIMARY, 0);
        $name = data_get($requestData, Constant::DB_TABLE_NAME, Constant::PARAMETER_STRING_DEFAULT);
        $businessType = data_get($requestData, Constant::BUSINESS_TYPE, 1);

        $rewardModel = static::getModel($storeId)->getConnection();
        try {
            $rewardModel->beginTransaction();

            //添加礼品记录
            $rewardId = static::addReward($storeId, $requestData);
            if (empty($rewardId)) {
                throw new \Exception('礼品编辑失败', 9900000002);
            }

            if ($asinRecords) {
                //删除asin
                RewardAsinService::deleteAsins($storeId, $extId);

                //添加asin
                $asinRet = RewardAsinService::addAsins($storeId, $rewardId, $name, $businessType, $asinRecords, $requestData);
                if (!$asinRet) {
                    throw new \Exception('礼品编辑失败', 9900000002);
                }
            }

            $records = [];
            switch ($type) {
                case 2://折扣码
                    $records = $codeRecords;
                    break;

                case 1://礼品卡
                    $records = $giftRecords;
                    break;

                default:
                    break;
            }

            if ($records) {

                //删除code
                static::deleteCodes($storeId, $extId);

                //添加礼品卡
                $codeRet = static::addCodes($storeId, $rewardId, $records);
                if (!$codeRet) {
                    throw new \Exception('礼品编辑失败', 9900000002);
                }
            }

            $updateRs = static::updateRewardStatus($storeId, $rewardId, $rewardStatus, $asins);
            if (data_get($updateRs, Constant::RESPONSE_CODE_KEY, 0) != 1) {
                throw new \Exception(data_get($updateRs, Constant::RESPONSE_MSG_KEY, 0), data_get($updateRs, Constant::RESPONSE_CODE_KEY, 0));
            }

            //类目
            $categoryData = data_get($requestData, 'category_data', []);
            RewardCategoryService::handle($storeId, $rewardId, $categoryData);

            $rewardModel->commit();
        } catch (\Exception $exc) {
            $rewardModel->rollBack();
            return Response::getDefaultResponseData($exc->getCode(), $exc->getMessage());
        }

        return Response::getDefaultResponseData(1, '礼品编辑成功');
    }

    /**
     * 逻辑删除code
     * @param int $storeId
     * @param int $extId 礼品id
     * @param string $extType
     * @param int $status
     */
    public static function deleteCodes($storeId, $extId, $extType = 'Reward', $status = 1) {
        $where = [
            Constant::DB_TABLE_EXT_ID => $extId,
            Constant::DB_TABLE_EXT_TYPE => $extType,
            Constant::DB_TABLE_STATUS => $status,
        ];
        static::getModel($storeId, '', [], 'Coupon')->buildWhere($where)->delete();
    }

    /**
     * 获取礼品
     * @param int $storeId
     * @param array $requestData
     * @return mixed
     */
    public static function getRewardById($storeId, $requestData) {

        $where = [
            Constant::DB_TABLE_PRIMARY => data_get($requestData, 'reward_id', Constant::PARAMETER_INT_DEFAULT),
        ];
        $select = ['*'];

        $field = '';
        $data = Constant::PARAMETER_ARRAY_DEFAULT;
        $dataType = Constant::PARAMETER_STRING_DEFAULT;
        $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
        $time = Constant::PARAMETER_STRING_DEFAULT;
        $glue = Constant::PARAMETER_STRING_DEFAULT;
        $isAllowEmpty = true;
        $default = Constant::PARAMETER_ARRAY_DEFAULT;
        $callback = Constant::PARAMETER_ARRAY_DEFAULT;
        $only = Constant::PARAMETER_ARRAY_DEFAULT;

        $handleData = [
            Constant::DB_TABLE_ASIN => FunctionHelper::getExePlanHandleData('asins.*.' . Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT, $data, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, $time, ',', $isAllowEmpty, $callback, $only),
            Constant::DB_TABLE_COUNTRY => FunctionHelper::getExePlanHandleData('asins.*.' . Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT, $data, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, $time, ',', $isAllowEmpty, $callback, $only),
        ];

        $joinData = [
        ];
        $asinsSelect = [
            Constant::DB_TABLE_EXT_ID,
            Constant::DB_TABLE_ASIN, //asin
            Constant::DB_TABLE_COUNTRY, //国家
        ];
        $asinsOrders = [];

        $categorySelect = [
            'reward_id',
            'category_code',
        ];
        $categoryOrders = [['level', Constant::DB_EXECUTION_PLAN_ORDER_ASC]];

        $with = [
            'asins' => FunctionHelper::getExePlan(
                    $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $asinsSelect, [], $asinsOrders, null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联礼品asin
            'categories' => FunctionHelper::getExePlan(
                    $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $categorySelect, [], $categoryOrders, null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联礼品类目
        ];
        $unset = ['asins',];
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getNamespaceClass(), '', $select, $where, [], 1, null, false, [], false, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        $itemHandleDataCallback = [
        ];

        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback, $only),
        ];

        return FunctionHelper::getResponseData(null, $dbExecutionPlan);
    }

    /**
     * 礼品详情
     * @param int $storeId
     * @param array $requestData
     * @return mixed
     */
    public static function info($storeId, $requestData) {
        $info = static::getRewardById($storeId, $requestData);

        $rewardId = data_get($info, Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
        $where = [
            Constant::DB_TABLE_EXT_ID => $rewardId,
            Constant::DB_TABLE_EXT_TYPE => static::getModelAlias()
        ];

        //获取最近删除的asin
        $delAsin = RewardAsinService::getModel($storeId)
                ->buildWhere(Arr::collapse([$where, [Constant::REWARD_STATUS_NO => $rewardId]]))
                ->withTrashed()
                ->pluck(Constant::DB_TABLE_ASIN);

        data_set($info, Constant::REWARD_STATUS, 2); //取消奖励设置  0:需要  2:不需要
        data_set($info, Constant::DEL_ASIN, $delAsin ? $delAsin : []); //最近删除的asin

        return $info;
    }

    /**
     * 礼品导出
     * @param array $requestData
     * @return mixed
     */
    public static function export($requestData) {

        $storeId = data_get($requestData, Constant::DB_TABLE_STORE_ID, 0); //商城id

        $rewardDbConfig = static::getDbConfig($storeId);
        $rewardTableAlias = data_get($rewardDbConfig, 'table_alias', '');

        $header = [
            '三级类目' => 'category',
            //'产品类型' => 'product_type_value',
            '礼品名称' => Constant::DB_TABLE_NAME,
            '礼品类型' => 'type_name',
            '礼品值' => Constant::DB_TABLE_TYPE_VALUE,
            '产品asin' => Constant::DB_TABLE_ASIN,
            '亚马逊站点' => Constant::DB_TABLE_COUNTRY,
            '折扣码' => Constant::RESPONSE_CODE_KEY,
            '备注' => Constant::DB_TABLE_REMARKS,
            '设置时间' => Constant::DB_TABLE_CREATED_AT,
            Constant::EXPORT_DISTINCT_FIELD => [
                Constant::EXPORT_PRIMARY_KEY => Constant::DB_TABLE_PRIMARY,
                Constant::EXPORT_PRIMARY_VALUE_KEY => $rewardTableAlias . Constant::LINKER . Constant::DB_TABLE_PRIMARY,
                Constant::DB_EXECUTION_PLAN_SELECT => [$rewardTableAlias . Constant::LINKER . Constant::DB_TABLE_PRIMARY]
            ],
        ];

        $service = static::getNamespaceClass();
        $method = 'getList';
        $select = [Constant::DB_TABLE_PRIMARY, Constant::PRODUCT_TYPE, Constant::DB_TABLE_NAME, Constant::DB_TABLE_TYPE, Constant::DB_TABLE_TYPE_VALUE,
            Constant::DB_TABLE_REMARKS, Constant::DB_TABLE_ASIN, Constant::DB_TABLE_COUNTRY, Constant::RESPONSE_CODE_KEY, Constant::DB_TABLE_CREATED_AT];
        $parameters = [$requestData, true, true, $select, false, false];
        $countMethod = $method;
        $countParameters = Arr::collapse([$parameters, [true]]);
        $file = ExcelService::createCsvFile($header, $service, $countMethod, $countParameters, $method, $parameters);

        return [Constant::FILE_URL => $file];
    }

    /**
     * 获取礼品列表
     * @param array $params
     * @param bool $toArray
     * @param bool $isPage
     * @param array $select
     * @param bool $isRaw
     * @param bool $isGetQuery
     * @param bool $isOnlyGetCount
     * @return array|\Hyperf\Database\Model\Builder|mixed
     */
    public static function getList($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {

        $_data = static::getPublicData($params, []);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, null);
        $order = data_get($params, Constant::ORDER_BY, data_get($_data, Constant::ORDER, []));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, Constant::ACT_LIMIT_KEY, data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, Constant::PARAMETER_INT_DEFAULT));
        $storeId = Arr::get($params, Constant::DB_TABLE_STORE_ID, Constant::PARAMETER_INT_DEFAULT);

        $select = $select ? $select : ['*'];

        $only = Constant::PARAMETER_ARRAY_DEFAULT;
        $handleData = [];

        $joinData = [
        ];
        $asinsSelect = [
            Constant::DB_TABLE_EXT_ID,
            Constant::DB_TABLE_ASIN, //asin
            Constant::DB_TABLE_COUNTRY, //国家
        ];
        $asinsOrders = [];

        $categorySelect = [
            'reward_id',
            'category_code',
            'category_name',
        ];
        $categoryOrders = [['level', Constant::DB_EXECUTION_PLAN_ORDER_ASC]];

        $with = [
            'asins' => FunctionHelper::getExePlan(
                    $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $asinsSelect, [], $asinsOrders, null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联礼品asin
            'categories' => FunctionHelper::getExePlan(
                    $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $categorySelect, [], $categoryOrders, null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联礼品类目
        ];
        $unset = ['asins', 'categories'];

        $rewardDbConfig = static::getDbConfig($storeId);
        $rewardTableAlias = data_get($rewardDbConfig, 'table_alias', '');
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getNamespaceClass(), data_get($rewardDbConfig, 'table', '') . ' as ' . $rewardTableAlias, $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        if (data_get($params, Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_PRIMARY, false)) {//如果仅仅获取主键id，就不需要处理数据，不关联
            $dbExecutionPlan = [
                Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            ];
        } else {
            $rewardTypes = static::getConfig($storeId, 'reward_type');

            $field = 'json|content';
            $data = Constant::PARAMETER_ARRAY_DEFAULT;
            $dataType = Constant::PARAMETER_STRING_DEFAULT;
            $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
            $time = Constant::PARAMETER_STRING_DEFAULT;
            $glue = Constant::PARAMETER_STRING_DEFAULT;
            $isAllowEmpty = true;
            $default = Constant::PARAMETER_ARRAY_DEFAULT;
            $callback = Constant::PARAMETER_ARRAY_DEFAULT;
            $only = Constant::PARAMETER_ARRAY_DEFAULT;

            $productTypeData = [
                1 => '普通产品',
                2 => '重点产品',
            ];
            $handleData = [
                'type_name' => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_TYPE, Constant::PARAMETER_STRING_DEFAULT, $rewardTypes, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
                'product_type_value' => FunctionHelper::getExePlanHandleData('product_type', data_get($productTypeData, 2), $productTypeData, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
                Constant::DB_TABLE_ASIN => FunctionHelper::getExePlanHandleData('asins.*.' . Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT, $data, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, $time, ',', $isAllowEmpty, $callback, $only),
                Constant::DB_TABLE_COUNTRY => FunctionHelper::getExePlanHandleData('asins.*.' . Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT, $data, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, $time, ',', $isAllowEmpty, $callback, $only),
                'category' => FunctionHelper::getExePlanHandleData('categories.*.category_name', Constant::PARAMETER_STRING_DEFAULT, $data, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, $time, '-', $isAllowEmpty, $callback, $only),
            ];

            $exePlan[Constant::DB_EXECUTION_PLAN_HANDLE_DATA] = $handleData;

            $itemHandleDataCallback = [
                Constant::RESPONSE_CODE_KEY => function($item) use($storeId) {//审核状态
                    $type = data_get($item, Constant::DB_TABLE_TYPE); //礼品类型: 0:其他 1:礼品卡 2:coupon 3:实物 5:积分
                    $where = [
                        Constant::DB_TABLE_EXT_ID => $item[Constant::DB_TABLE_PRIMARY],
                        Constant::DB_TABLE_EXT_TYPE => static::getModelAlias()
                    ];
                    return in_array($type, [1, 2]) ? CouponService::getModel($storeId)->buildWhere($where)->limit(10)->pluck(Constant::RESPONSE_CODE_KEY)->implode(',') : '';
                }
            ];

            $dbExecutionPlan = [
                Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
                Constant::DB_EXECUTION_PLAN_WITH => $with,
                Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback, $only),
            ];
        }

        $dataStructure = 'list';
        $flatten = false;
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    /**
     * 获取默认奖励
     * @param int $storeId 商城id
     * @param int $businessType 业务类型,1:订单延保,2其他
     * @return array 默认奖励
     */
    public static function getDefaultReward($storeId, $businessType = 1) {
        $where = [
            Constant::BUSINESS_TYPE => $businessType,
            Constant::DB_TABLE_IS_DEFAULT => Constant::WHETHER_YES_VALUE,
        ];

        $select = [
            Constant::DB_TABLE_PRIMARY, //奖励id
            Constant::DB_TABLE_NAME, //奖励名称
            Constant::DB_TABLE_TYPE, //礼品类型 0:其他 1:礼品卡 2:coupon 3:实物 5:积分
            Constant::DB_TABLE_TYPE_VALUE, //礼品值
        ];
        return static::existsOrFirst($storeId, '', $where, true, $select);
    }

    /**
     * 通过asin 获取奖励
     * @param int $storeId
     * @param string $asin
     * @param string $country
     * @return array $data 奖励
     */
    public static function getRewardFromAsin($storeId, $asin, $country) {

        if (empty($asin) || empty($country)) {
            return Constant::PARAMETER_ARRAY_DEFAULT;
        }

        $dbConfig = static::getDbConfig($storeId);
        $tableAlias = data_get($dbConfig, 'table_alias');
        $from = data_get($dbConfig, 'from');

        $rewardAsinDbConfig = RewardAsinService::getDbConfig($storeId);
        $rewardAsinTableAlias = data_get($rewardAsinDbConfig, 'table_alias');
        $rewardAsinFrom = data_get($rewardAsinDbConfig, 'from');

        $businessType = 1;
        $where = [
            $rewardAsinTableAlias . Constant::LINKER . Constant::DB_TABLE_ASIN => $asin,
            $rewardAsinTableAlias . Constant::LINKER . Constant::DB_TABLE_COUNTRY => $country,
            $rewardAsinTableAlias . Constant::LINKER . Constant::BUSINESS_TYPE => $businessType,
        ];
        $select = [
            $rewardAsinTableAlias . Constant::LINKER . Constant::DB_TABLE_NAME, //奖励名称
            $rewardAsinTableAlias . Constant::LINKER . Constant::DB_TABLE_EXT_ID, //奖励id
            $rewardAsinTableAlias . Constant::LINKER . Constant::DB_TABLE_TYPE, //礼品类型 0:其他 1:礼品卡 2:coupon 3:实物 5:积分
            $rewardAsinTableAlias . Constant::LINKER . Constant::DB_TABLE_TYPE_VALUE, //奖品值
        ];

        $handleData = Constant::PARAMETER_ARRAY_DEFAULT;
        $joinData = [
            FunctionHelper::getExePlanJoinData($rewardAsinFrom, function ($join) use($tableAlias, $rewardAsinTableAlias) {
                        $join->on([[$rewardAsinTableAlias . Constant::LINKER . Constant::DB_TABLE_EXT_ID, '=', $tableAlias . Constant::LINKER . Constant::DB_TABLE_PRIMARY]])
                                ->where($rewardAsinTableAlias . Constant::LINKER . Constant::DB_TABLE_EXT_TYPE, '=', static::getModelAlias())
                                ->where($rewardAsinTableAlias . Constant::LINKER . Constant::DB_TABLE_STATUS, '=', 1);
                    }),
        ];
        $with = Constant::PARAMETER_ARRAY_DEFAULT;
        $unset = Constant::PARAMETER_ARRAY_DEFAULT;
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getNamespaceClass(), $from, $select, $where, [], null, null, false, [], false, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
        ];

        $dataStructure = 'list';
        $flatten = false;
        $data = collect(FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure));

        $reward = null;
        if ($data) {
            $typeData = [0, 1, 2, 5, 3]; //礼品类型 0:其他 1:礼品卡 2:coupon 3:实物 5:积分
            foreach ($typeData as $type) {

                $reward = $data->firstWhere(Constant::DB_TABLE_TYPE, $type);

                if ($reward && !in_array($type, [1, 2])) {
                    break;
                }

                if ($reward && in_array($type, [1, 2])) {
                    $rewardId = data_get($reward, Constant::DB_TABLE_EXT_ID, Constant::PARAMETER_INT_DEFAULT); //奖励id
                    $name = data_get($reward, Constant::DB_TABLE_NAME, Constant::PARAMETER_STRING_DEFAULT); //礼品名称
                    $couponData = CouponService::getRewardCoupon($storeId, $rewardId, static::getModelAlias(), 0, $name, '');
                    if ($couponData) {
                        break;
                    } else {
                        $reward = null;
                    }
                }
            }
        }

        if (empty($reward)) {
            $reward = static::getDefaultReward($storeId, $businessType);
            if ($reward) {
                data_set($reward, Constant::DB_TABLE_EXT_ID, data_get($reward, Constant::DB_TABLE_PRIMARY, 0), false);
            }
        }

        return $reward;
    }

    /**
     * 获取订单索评奖励
     * @param int $storeId 商城id
     * @param string $orderno 订单id
     * @return array $rs 订单索评奖励
     */
    public static function getOrderReviewReward($storeId, $orderno) {
        $orderData = OrderService::getOrderData($orderno, '', Constant::PLATFORM_SERVICE_AMAZON, $storeId);
        if (data_get($orderData, Constant::RESPONSE_CODE_KEY, 0) != 1) {
            data_set($orderData, Constant::RESPONSE_CODE_KEY, 30002); //订单不存在
            return $orderData;
        }

        $_orderItemData = data_get($orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . 'items', []);
        if (empty($_orderItemData)) {
            data_set($orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . 'reward', null); //订单延保奖励
            return $orderData;
        }

        $currency = data_get($orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_CURRENCY, '');

        $_orderItemData = collect($_orderItemData);
        $orderItemData = $_orderItemData->sortByDesc(Constant::DB_TABLE_AMOUNT)->values()->all();

        $amazonHostData = DictService::getListByType('amazon_host', 'dict_key', 'dict_value');
        foreach ($orderItemData as $key => $item) {
            $country = data_get($item, Constant::DB_TABLE_PRODUCT_COUNTRY, '');
            $country = $country ? $country : data_get($item, Constant::DB_TABLE_ORDER_COUNTRY, '');
            $country = $country ? $country : 'US';
            $amazonHost = data_get($amazonHostData, $country, data_get($amazonHostData, 'US', ''));

            data_set($item, 'amazon', $amazonHost);

            $field = FunctionHelper::getExePlanHandleData('amazon{connection}asin', Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, 'string', Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, '/review/create-review/?asin=');
            $reviewUrl = FunctionHelper::handleData($item, $field); //亚马逊 review url

            data_set($orderItemData, $key . '.reviewUrl', $reviewUrl);
        }
        data_set($orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . 'items', $orderItemData); //订单item

        $orderItem = current($orderItemData);
        $asin = data_get($orderItem, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT); //产品asin
        $country = data_get($orderItem, Constant::DB_TABLE_ORDER_COUNTRY, Constant::PARAMETER_STRING_DEFAULT); //订单国家
        $country = $country ? $country : data_get($orderItem, Constant::DB_TABLE_PRODUCT_COUNTRY, Constant::PARAMETER_STRING_DEFAULT); //订单国家
        $sku = data_get($orderItem, Constant::DB_TABLE_SELLER_SKU, Constant::PARAMETER_STRING_DEFAULT); //产品店铺sku
        $rewardData = static::getRewardFromAsin($storeId, $asin, $country);
        if ($rewardData) {

            if (in_array(data_get($rewardData, Constant::DB_TABLE_TYPE, ''), [1])) {//礼品类型 0:其他 1:礼品卡 2:coupon 3:实物 5:积分
                $currencyData = static::getConfig($storeId, 'currency');
                data_set($rewardData, Constant::DB_TABLE_NAME, (data_get($currencyData, $currency, '') . ' ' . data_get($rewardData, Constant::DB_TABLE_NAME, '')));
            }

            data_set($rewardData, Constant::DB_TABLE_ASIN, $asin);
            data_set($rewardData, Constant::DB_TABLE_PRODUCT_COUNTRY, $country);
            data_set($rewardData, Constant::DB_TABLE_SELLER_SKU, $sku);
        }

        data_set($orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . 'reward', $rewardData); //订单延保奖励

        return $orderData;
    }

    /**
     * 获取订单索评奖励
     * @param int $storeId 商城id
     * @param string $orderno 订单id
     * @return array $rs 订单索评奖励
     */
    public static function handleOrderReviewReward($storeId, $customerId = 0, $orderReviewId = 0, $orderno = '', $account = '') {

        $rs = Response::getDefaultResponseData(30004);
        if (is_integer($orderReviewId)) {
            $where = [
                Constant::DB_TABLE_PRIMARY => $orderReviewId,
            ];
            $orderReviewId = OrderReviewService::existsOrFirst($storeId, '', $where, true, [Constant::DB_TABLE_PRIMARY, Constant::AUDIT_STATUS]);
        }

        $auditStatus = data_get($orderReviewId, Constant::AUDIT_STATUS, Constant::ORDER_STATUS_DEFAULT);
        if ($auditStatus > -1) {//奖励审核中
            data_set($rs, Constant::RESPONSE_CODE_KEY, 1);
            return $rs;
        }

        $orderReviewReward = static::getOrderReviewReward($storeId, $orderno);
        //dd($orderReviewReward);
        $reward = data_get($orderReviewReward, Constant::RESPONSE_DATA_KEY . Constant::LINKER . 'reward', Constant::PARAMETER_ARRAY_DEFAULT);
        if (empty($reward)) {
            return $rs;
        }

        //获取奖励数据
        $rewardId = data_get($reward, Constant::DB_TABLE_EXT_ID, Constant::PARAMETER_INT_DEFAULT); //奖励id
        $where = [
            Constant::DB_TABLE_PRIMARY => $rewardId,
        ];
        $select = [
            Constant::DB_TABLE_PRIMARY,
            Constant::PRODUCT_TYPE, //产品类型（1：普通产品，2：重点产品，3：新品，4：清仓）
            Constant::DB_TABLE_NAME,
            Constant::DB_TABLE_TYPE,
            Constant::DB_TABLE_TYPE_VALUE,
            Constant::BUSINESS_TYPE,
        ];
        $rewardData = static::existsOrFirst($storeId, '', $where, true, $select);
        if (empty($rewardData)) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 30005);
            return $rs;
        }

        $asin = data_get($reward, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT); //产品asin
        $country = data_get($reward, Constant::DB_TABLE_PRODUCT_COUNTRY, Constant::PARAMETER_STRING_DEFAULT); //产品国家
        $sku = data_get($reward, Constant::DB_TABLE_SELLER_SKU, Constant::PARAMETER_STRING_DEFAULT); //产品店铺sku
        $name = data_get($reward, Constant::DB_TABLE_NAME, Constant::PARAMETER_STRING_DEFAULT); //奖励名称

        $productType = data_get($rewardData, Constant::PRODUCT_TYPE, Constant::PARAMETER_INT_DEFAULT); //产品类型（1：普通产品，2：重点产品，3：新品，4：清仓）
        //$name = data_get($rewardData, Constant::DB_TABLE_NAME, Constant::PARAMETER_STRING_DEFAULT); //奖励名称
        $type = data_get($rewardData, Constant::DB_TABLE_TYPE, Constant::PARAMETER_INT_DEFAULT); //礼品类型: 0:其他 1:礼品卡 2:coupon 3:实物 5:积分 6:现金
        $typeValue = data_get($rewardData, Constant::DB_TABLE_TYPE_VALUE, Constant::PARAMETER_STRING_DEFAULT); //礼品值
        $businessType = data_get($rewardData, Constant::BUSINESS_TYPE, Constant::PARAMETER_INT_DEFAULT); //业务类型,1:订单延保,2其他

        $startAt = null; //coupon有效开始时间
        $endAt = null; //coupon有效结束时间

        $connection = static::getModel($storeId, '')->getConnection();
        $connection->beginTransaction();

        //会员延保体系礼品模块升级3.0 202010201113
        /**
         * 审核规则
         * 原普通产品奖励即折扣码，积分奖励系统自动审核，审核逻辑不变。
         * 原重点产品奖励在2.0版本改为礼品卡，实物奖励以及其他(去掉重点普通产品类型之分，直接以奖励类型为准)将由人工审核发送奖励。
         * 以及延保成功通知邮件，系统审核结果的各类邮件逻辑不变
         */
        try {
            switch ($type) {

                case 5://积分
                    //折扣码，积分奖励系统自动审核
                    $orderReviewId = data_get($orderReviewId, Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
                    $creditData = [
                        Constant::DB_TABLE_STORE_ID => $storeId,
                        Constant::DB_TABLE_VALUE => $typeValue,
                        Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                        Constant::DB_TABLE_ADD_TYPE => 1,
                        Constant::DB_TABLE_ACTION => 'order_review',
                        Constant::DB_TABLE_EXT_ID => $orderReviewId, //订单索评关联id
                        Constant::DB_TABLE_EXT_TYPE => OrderReviewService::getModelAlias(), //订单索评关联模型
                    ];

                    $creditWhere = [
                        Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                        Constant::DB_TABLE_ADD_TYPE => 1,
                        Constant::DB_TABLE_ACTION => 'order_review',
                        Constant::DB_TABLE_EXT_ID => $orderReviewId, //订单索评关联id
                        Constant::DB_TABLE_EXT_TYPE => OrderReviewService::getModelAlias(), //订单索评关联模型
                    ];
                    $isExists = CreditService::exists($storeId, $creditWhere);
                    if (empty($isExists)) {//加积分
                        CreditService::handle($creditData);
                    }

                    break;

                case 1://礼品卡
                case 2://coupon
                    //获取未使用的 奖励coupon
                    $couponData = CouponService::getRewardCoupon($storeId, $rewardId, static::getModelAlias(), $customerId, $name, $account);
                    $typeValue = data_get($couponData, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_STRING_DEFAULT);
                    $startAt = data_get($couponData, Constant::DB_TABLE_START_TIME, $startAt);
                    $endAt = data_get($couponData, Constant::DB_TABLE_END_TIME, $endAt);
                    break;

                default:
                    break;
            }

            if (empty($typeValue)) {
                $connection->rollBack();
                data_set($rs, Constant::RESPONSE_CODE_KEY, 30007);
                return $rs;
            }

            $where = [
                Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                Constant::DB_TABLE_ORDER_NO => $orderno,
            ];

            $data = [
                Constant::DB_TABLE_SKU => $sku,
                Constant::DB_TABLE_ASIN => $asin,
                Constant::DB_TABLE_COUNTRY => $country,
                Constant::DB_TABLE_EXT_ID => $rewardId, //订单索评奖励关联id
                Constant::DB_TABLE_EXT_TYPE => static::getModelAlias(), //订单索评奖励关联模型
                Constant::BUSINESS_TYPE => $businessType, //业务类型,1:订单延保,2其他
                Constant::DB_TABLE_REWARD_NAME => $name, //奖励名称
                Constant::PRODUCT_TYPE => $productType, //产品类型（1：普通产品，2：重点产品，3：新品，4：清仓）
                Constant::DB_TABLE_TYPE => $type, //礼品类型: 0:其他 1:礼品卡 2:coupon 3:实物 5:积分
                Constant::DB_TABLE_TYPE_VALUE => $typeValue, //礼品值
                Constant::AUDIT_STATUS => Constant::PARAMETER_INT_DEFAULT, //-1 未提交 0 审核中 1 成功 2 失败 3 其他
            ];

            if ($type == 2) {//coupon,更新有效时间
                data_set($data, Constant::DB_TABLE_START_AT, $startAt);
                data_set($data, Constant::DB_TABLE_END_AT, $endAt);
            }

            $orderReviewData = OrderReviewService::updateOrCreate($storeId, $where, $data);

            $connection->commit();

            //审核并发送邮件
            if (in_array($type, [2, 5])) {//折扣码，积分奖励 自动审核通过 礼品类型: 0:其他 1:礼品卡 2:coupon 3:实物 5:积分
                $reviewId = data_get($orderReviewData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_PRIMARY, -1);
                OrderReviewService::audit($storeId, $reviewId, 1, '系统自动审核', '折扣码，积分奖励 自动审核通过', true);
            }

            data_set($rs, Constant::RESPONSE_CODE_KEY, 1);
            return $rs;
        } catch (\Exception $exc) {
            $connection->rollBack();
            $excMsg = ExceptionHandler::getMessage($exc, config('app.debug'));
            return Response::getDefaultResponseData(data_get($excMsg, 'exception_code', Constant::PARAMETER_INT_DEFAULT), data_get($excMsg, 'message', Constant::PARAMETER_INT_DEFAULT), $excMsg);
        }
    }

    /**
     * 编辑礼品状态
     * @param int $storeId 商城id
     * @param int $rewardId 礼品id
     * @param int $rewardStatus 礼品状态 1:有效 0:无效
     * @param array|null $asins 要删除的asion  null:礼品所有asin全部删除  'B07VN6SN3T','B07VN6SN3T1':删除指定的asin
     * @return type
     */
    public static function updateRewardStatus($storeId, $rewardId, $rewardStatus, $asins = null) {

        if (!in_array($rewardStatus, [0, 1])) {
            return Response::getDefaultResponseData(1);
        }

        $rewardAsinWhere = [
            Constant::DB_TABLE_EXT_ID => $rewardId,
            Constant::DB_TABLE_EXT_TYPE => static::getModelAlias(),
        ];

        $_rewardAsinWhere = [
            Constant::DB_TABLE_EXT_ID => $rewardId,
            Constant::DB_TABLE_EXT_TYPE => static::getModelAlias(),
        ];

        switch ($rewardStatus) {
            case 0:
                if ($asins !== null) {
                    $asinArr = array_filter(array_unique(explode(",", $asins)));
                    if (empty($asinArr)) {
                        return Response::getDefaultResponseData(1);
                    }

                    data_set($rewardAsinWhere, Constant::DB_TABLE_ASIN, $asinArr);

                    //判断删除的asin是否存在
                    $asinData = RewardAsinService::getModel($storeId)->buildWhere($rewardAsinWhere)->pluck(Constant::DB_TABLE_ASIN);

                    $asinDiff = collect($asinArr)->diff($asinData)->all();
                    if (!empty($asinDiff)) {
                        return Response::getDefaultResponseData(0, '产品asin:' . implode(' , ', $asinDiff) . ' 不存在');
                    }
                }

                //清空asin状态变更标识
                RewardAsinService::getModel($storeId, '')->buildWhere($_rewardAsinWhere)->withTrashed()->update([Constant::REWARD_STATUS_NO => 0]);

                //设置asin状态变更标识
                RewardAsinService::update($storeId, $rewardAsinWhere, [Constant::REWARD_STATUS_NO => DB::raw(Constant::DB_TABLE_EXT_ID)]);

                //设置asin无效
                RewardAsinService::delete($storeId, $rewardAsinWhere);

                $isExists = RewardAsinService::getModel($storeId, '')->buildWhere($_rewardAsinWhere)->count();
                if ($isExists) {
                    $rewardStatus = 1;
                }

                break;

            case 1:
                //还原最近无效的asin
                RewardAsinService::getModel($storeId)->buildWhere(Arr::collapse([$_rewardAsinWhere, [Constant::REWARD_STATUS_NO => $rewardId]]))->restore();

                //清空asin状态变更标识
                RewardAsinService::update($storeId, $_rewardAsinWhere, [Constant::REWARD_STATUS_NO => 0]);

                break;

            default:
                break;
        }

        //更新礼品状态
        $rewardWhere = [
            Constant::DB_TABLE_PRIMARY => $rewardId,
        ];
        static::update($storeId, $rewardWhere, [Constant::REWARD_STATUS => $rewardStatus]);

        return Response::getDefaultResponseData(1);
    }

    /**
     * 删除礼品
     * @param int $storeId 官网id
     * @param array $ids 需要删除的礼品主键id数组
     * @return bool
     */
    public static function deleteRewards($storeId, $ids) {
        if (empty($ids) || empty($storeId)) {
            return false;
        }

        $where = [
            Constant::DB_TABLE_PRIMARY => $ids
        ];

        return static::getModel($storeId)->buildWhere($where)->delete();
    }

    /**
     * 获取订单索评奖励_V2
     * @param int $storeId 商城id
     * @param int $customerId 会员id
     * @param string $orderno 订单id
     * @param array $requestData 请求参数
     * @return array $rs 订单索评奖励
     */
    public static function getOrderReviewRewardV2($storeId, $customerId, $orderno, $requestData) {
        $rs = Response::getDefaultResponseData(Constant::RESPONSE_SUCCESS_CODE);

        $orderData = data_get($requestData, 'order_data', Constant::PARAMETER_ARRAY_DEFAULT);
        $orderItemData = data_get($requestData, 'order_data.data.items', Constant::PARAMETER_ARRAY_DEFAULT);
        if (empty($orderData) || empty($orderItemData)) {
            $orderData = OrderService::getOrderData($orderno, Constant::PARAMETER_STRING_DEFAULT, Constant::PLATFORM_SERVICE_AMAZON, $storeId);
            if (data_get($orderData, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_INT_DEFAULT) != Constant::RESPONSE_SUCCESS_CODE) {
                data_set($rs, Constant::RESPONSE_CODE_KEY, 30002);
                return $rs;
            }

            $orderItemData = data_get($orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . 'items', []);
            if (empty($orderItemData)) {
                return $orderData;
            }
        }

        $rewardGiftCardRs = BusGiftCardApplyService::rewardWarrantyHandle($storeId, $orderno);
        if (data_get($rewardGiftCardRs, Constant::RESPONSE_CODE_KEY) != Constant::RESPONSE_SUCCESS_CODE) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 50002);
            return $rs;
        }

        $currency = data_get($orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_CURRENCY, Constant::PARAMETER_STRING_DEFAULT);

        $totalScore = 0;
        $rewardList = [];
        $rewardTypes = [];
        $amazonHostData = DictService::getListByType('amazon_host', 'dict_key', 'dict_value');
        $isMatchReward = DictStoreService::getByTypeAndKey($storeId, 'review', 'is_match_reward', true); //是否匹配奖励

        //reviews数据
        $where = [Constant::DB_TABLE_ORDER_NO => $orderno, Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId];
        $select = [Constant::DB_TABLE_SKU, Constant::DB_TABLE_ASIN, Constant::DB_TABLE_REVIEW_LINK, Constant::DB_TABLE_REVIEW_IMG_URL, Constant::DB_TABLE_ORDER_NO];
        $reviews = OrderReviewService::getModel($storeId)->buildWhere($where)->select($select)->get();
        $reviewsMap = [];
        foreach ($reviews as $review) {
            $asin = data_get($review, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT);
            $sku = data_get($review, Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT);
            $reviewsMap["{$sku}_{$asin}"] = $review;
        }

        if (in_array($storeId, [2])) {
            OrderReviewService::vtOldReviewsDataHandle($storeId, $customerId, $orderno, $orderItemData);
        } else {
            //历史数据处理
            OrderReviewService::oldReviewsDataHandle($storeId, $customerId, $orderno, $orderItemData);
        }

        $orderItems = [];
        $diffMaps = [];
        $orderCountry = data_get($orderItemData, '0.' . Constant::DB_TABLE_ORDER_COUNTRY, Constant::PARAMETER_STRING_DEFAULT);
        foreach ($orderItemData as $key => $item) {
            $orderStatus = data_get($item, Constant::DB_TABLE_ORDER_STATUS);
            if (strtolower($orderStatus) != 'shipped') { //shipped状态的order_item数据才能匹配奖励
                continue;
            }

            $asin = data_get($item, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT); //产品asin
            $sku = data_get($item, Constant::DB_TABLE_SELLER_SKU, Constant::PARAMETER_STRING_DEFAULT); //产品店铺sku
            $country = data_get($item, Constant::DB_TABLE_ORDER_COUNTRY, Constant::PARAMETER_STRING_DEFAULT); //订单国家
            $country = $country ? $country : data_get($item, Constant::DB_TABLE_PRODUCT_COUNTRY, Constant::PARAMETER_STRING_DEFAULT); //订单国家
            if (isset($diffMaps["{$sku}_{$asin}"])) { //去重
                continue;
            }

            $amazonCountry = data_get($item, Constant::DB_TABLE_PRODUCT_COUNTRY, Constant::PARAMETER_STRING_DEFAULT);
            $amazonCountry = $amazonCountry ? $amazonCountry : data_get($item, Constant::DB_TABLE_ORDER_COUNTRY, Constant::PARAMETER_STRING_DEFAULT);
            $amazonCountry = $amazonCountry ? $amazonCountry : 'US';
            $amazonHost = data_get($amazonHostData, $amazonCountry, data_get($amazonHostData, 'US', Constant::PARAMETER_STRING_DEFAULT));

            data_set($item, 'amazon', $amazonHost);

            $field = FunctionHelper::getExePlanHandleData('amazon{connection}asin', Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, 'string', Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, '/review/create-review/?asin=');
            $reviewUrl = FunctionHelper::handleData($item, $field); //亚马逊 review url

            data_set($item, 'reviewUrl', $reviewUrl);

            data_set($item, Constant::DB_TABLE_REVIEW_LINK, Constant::PARAMETER_STRING_DEFAULT);
            data_set($item, Constant::DB_TABLE_REVIEW_IMG_URL, Constant::PARAMETER_STRING_DEFAULT);
            if (isset($reviewsMap["{$sku}_{$asin}"])) {
                data_set($item, Constant::DB_TABLE_REVIEW_LINK, data_get($reviewsMap["{$sku}_{$asin}"], Constant::DB_TABLE_REVIEW_LINK, Constant::PARAMETER_STRING_DEFAULT));
                data_set($item, Constant::DB_TABLE_REVIEW_IMG_URL, data_get($reviewsMap["{$sku}_{$asin}"], Constant::DB_TABLE_REVIEW_IMG_URL, Constant::PARAMETER_STRING_DEFAULT));
            }

            $rewardData = [];
            if ($isMatchReward) {
                $rewardData = static::getRewardFromAsin($storeId, $asin, $country);
                if ($rewardData) {
                    if (in_array(data_get($rewardData, Constant::DB_TABLE_TYPE, Constant::PARAMETER_STRING_DEFAULT), [1])) {//礼品类型 0:其他 1:礼品卡 2:coupon 3:实物 5:积分
                        $currencyData = static::getConfig($storeId, 'currency');
                        data_set($rewardData, Constant::DB_TABLE_NAME, (data_get($currencyData, $currency, '') . ' ' . data_get($rewardData, Constant::DB_TABLE_NAME, '')));
                    }

                    data_set($rewardData, Constant::DB_TABLE_ASIN, $asin);
                    data_set($rewardData, Constant::DB_TABLE_PRODUCT_COUNTRY, $country);
                    data_set($rewardData, Constant::DB_TABLE_SELLER_SKU, $sku);

                    if (in_array(data_get($rewardData, Constant::DB_TABLE_TYPE, Constant::PARAMETER_STRING_DEFAULT), [5])) {//礼品类型 0:其他 1:礼品卡 2:coupon 3:实物 5:积分
                        $totalScore += data_get($rewardData, Constant::DB_TABLE_TYPE_VALUE, Constant::PARAMETER_INT_DEFAULT);
                    } else {
                        $rewardList[] = data_get($rewardData, Constant::DB_TABLE_NAME);
                    }
                    $rewardTypes[data_get($rewardData, Constant::DB_TABLE_TYPE)] = data_get($rewardData, Constant::DB_TABLE_TYPE);
                }
            }
            data_set($item,'reward', $rewardData); //订单延保奖励
            $orderItems[] = $item;
            $diffMaps["{$sku}_{$asin}"] = true;
        }

        $isMatchReward && $totalScore && $rewardList[] = "$totalScore " . OrderReviewService::pointRemark($storeId, $orderCountry);
        $isMatchReward && $rewardTypes && $rewardTypes = array_values($rewardTypes);
        $orderWarranty = OrderReviewService::orderWarrantyCredit($storeId, $customerId, $orderno, $orderCountry);

        data_set($orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . 'items', $orderItems); //订单item
        data_set($orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . 'rewards', $rewardList); //$rewardList
        data_set($orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . 'reward_types', $rewardTypes); //$rewardTypes
        data_set($orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . 'order_warranty', $orderWarranty); //延保积分

        return $orderData;
    }


    /**
     * 索评奖励匹配及发放
     * @param int $storeId 官网id
     * @param int $customerId 会员id
     * @param array $orderReview 索评数据
     * @param string $account 账号
     * @param array $orderData 订单数据
     * @return array
     */
    public static function handleOrderReviewRewardV2($storeId, $customerId, $orderReview, $account, $orderData) {

        $rs = Response::getDefaultResponseData(30004);

        $auditStatus = data_get($orderReview, Constant::AUDIT_STATUS, Constant::ORDER_STATUS_DEFAULT);
        if ($auditStatus > -1) {//奖励审核中
            data_set($rs, Constant::RESPONSE_CODE_KEY, 1);
            return $rs;
        }

        $sku = data_get($orderReview, Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT);
        $asin = data_get($orderReview, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT);
        $country = data_get($orderReview, Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT);

        $reward = static::getRewardFromAsin($storeId, $asin, $country);
        if (empty($reward)) {
            return $rs;
        }

        $currency = data_get($orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_CURRENCY, Constant::PARAMETER_STRING_DEFAULT);
        if (in_array(data_get($reward, Constant::DB_TABLE_TYPE, ''), [1])) {//礼品类型 0:其他 1:礼品卡 2:coupon 3:实物 5:积分
            $currencyData = static::getConfig($storeId, 'currency');
            data_set($reward, Constant::DB_TABLE_NAME, (data_get($currencyData, $currency, '') . ' ' . data_get($reward, Constant::DB_TABLE_NAME, '')));
        }

        //获取奖励数据
        $rewardId = data_get($reward, Constant::DB_TABLE_EXT_ID, Constant::PARAMETER_INT_DEFAULT); //奖励id
        $where = [
            Constant::DB_TABLE_PRIMARY => $rewardId,
        ];
        $select = [
            Constant::DB_TABLE_PRIMARY,
            Constant::PRODUCT_TYPE, //产品类型（1：普通产品，2：重点产品，3：新品，4：清仓）
            Constant::DB_TABLE_NAME,
            Constant::DB_TABLE_TYPE,
            Constant::DB_TABLE_TYPE_VALUE,
            Constant::BUSINESS_TYPE,
        ];
        $rewardData = static::existsOrFirst($storeId, '', $where, true, $select);
        if (empty($rewardData)) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 30005);
            return $rs;
        }

        $name = data_get($reward, Constant::DB_TABLE_NAME, Constant::PARAMETER_STRING_DEFAULT); //奖励名称

        $productType = data_get($rewardData, Constant::PRODUCT_TYPE, Constant::PARAMETER_INT_DEFAULT); //产品类型（1：普通产品，2：重点产品，3：新品，4：清仓）
        $type = data_get($rewardData, Constant::DB_TABLE_TYPE, Constant::PARAMETER_INT_DEFAULT); //礼品类型: 0:其他 1:礼品卡 2:coupon 3:实物 5:积分 6:现金
        $typeValue = data_get($rewardData, Constant::DB_TABLE_TYPE_VALUE, Constant::PARAMETER_STRING_DEFAULT); //礼品值
        $businessType = data_get($rewardData, Constant::BUSINESS_TYPE, Constant::PARAMETER_INT_DEFAULT); //业务类型,1:订单延保,2其他

        $startAt = null; //coupon有效开始时间
        $endAt = null; //coupon有效结束时间

        $connection = static::getModel($storeId, '')->getConnection();
        $connection->beginTransaction();

        //会员延保体系礼品模块升级3.0 202010201113
        /**
         * 审核规则
         * 原普通产品奖励即折扣码，积分奖励系统自动审核，审核逻辑不变。
         * 原重点产品奖励在2.0版本改为礼品卡，实物奖励以及其他(去掉重点普通产品类型之分，直接以奖励类型为准)将由人工审核发送奖励。
         * 以及延保成功通知邮件，系统审核结果的各类邮件逻辑不变
         */

        $orderReviewId = data_get($orderReview, Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
        try {
            switch ($type) {

                case 5://积分
                    //折扣码，积分奖励系统自动审核
                    $creditData = [
                        Constant::DB_TABLE_STORE_ID => $storeId,
                        Constant::DB_TABLE_VALUE => $typeValue,
                        Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                        Constant::DB_TABLE_ADD_TYPE => 1,
                        Constant::DB_TABLE_ACTION => 'order_review',
                        Constant::DB_TABLE_EXT_ID => $orderReviewId, //订单索评关联id
                        Constant::DB_TABLE_EXT_TYPE => OrderReviewService::getModelAlias(), //订单索评关联模型
                    ];

                    $creditWhere = [
                        Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                        Constant::DB_TABLE_ADD_TYPE => 1,
                        Constant::DB_TABLE_ACTION => 'order_review',
                        Constant::DB_TABLE_EXT_ID => $orderReviewId, //订单索评关联id
                        Constant::DB_TABLE_EXT_TYPE => OrderReviewService::getModelAlias(), //订单索评关联模型
                    ];
                    $isExists = CreditService::exists($storeId, $creditWhere);
                    if (empty($isExists)) {//加积分
                        CreditService::handle($creditData);
                    }

                    break;

                case 1://礼品卡
                case 2://coupon
                    //获取未使用的 奖励coupon
                    $couponData = CouponService::getRewardCoupon($storeId, $rewardId, static::getModelAlias(), $customerId, $name, $account);
                    $typeValue = data_get($couponData, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_STRING_DEFAULT);
                    $startAt = data_get($couponData, Constant::DB_TABLE_START_TIME, $startAt);
                    $endAt = data_get($couponData, Constant::DB_TABLE_END_TIME, $endAt);
                    break;

                default:
                    break;
            }

            if (empty($typeValue)) {
                $connection->rollBack();
                data_set($rs, Constant::RESPONSE_CODE_KEY, 30007);
                return $rs;
            }

            $where = [
                Constant::DB_TABLE_PRIMARY => $orderReviewId,
            ];

            $data = [
                Constant::DB_TABLE_SKU => $sku,
                Constant::DB_TABLE_ASIN => $asin,
                Constant::DB_TABLE_COUNTRY => $country,
                Constant::DB_TABLE_EXT_ID => $rewardId, //订单索评奖励关联id
                Constant::DB_TABLE_EXT_TYPE => static::getModelAlias(), //订单索评奖励关联模型
                Constant::BUSINESS_TYPE => $businessType, //业务类型,1:订单延保,2其他
                Constant::DB_TABLE_REWARD_NAME => $name, //奖励名称
                Constant::PRODUCT_TYPE => $productType, //产品类型（1：普通产品，2：重点产品，3：新品，4：清仓）
                Constant::DB_TABLE_TYPE => $type, //礼品类型: 0:其他 1:礼品卡 2:coupon 3:实物 5:积分
                Constant::DB_TABLE_TYPE_VALUE => $typeValue, //礼品值
                Constant::AUDIT_STATUS => Constant::PARAMETER_INT_DEFAULT, //-1 未提交 0 审核中 1 成功 2 失败 3 其他
            ];

            if ($type == 2) {//coupon,更新有效时间
                data_set($data, Constant::DB_TABLE_START_AT, $startAt);
                data_set($data, Constant::DB_TABLE_END_AT, $endAt);
            }

            if (in_array($type, [0, 1, 3])) {//0:其他 1:礼品卡 3:实物 默认时间3个月
                data_set($data, Constant::DB_TABLE_START_AT, Carbon::now()->toDateTimeString());
                data_set($data, Constant::DB_TABLE_END_AT, Carbon::parse('+3 months')->toDateTimeString());
            }

            $orderReviewData = OrderReviewService::updateOrCreate($storeId, $where, $data);

            $connection->commit();

            //审核并发送邮件
            if (in_array($type, [2, 5])) {//折扣码，积分奖励 自动审核通过 礼品类型: 0:其他 1:礼品卡 2:coupon 3:实物 5:积分
                $reviewId = data_get($orderReviewData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_PRIMARY, -1);
                OrderReviewService::audit($storeId, $reviewId, 1, '系统自动审核', '折扣码，积分奖励 自动审核通过', true);
            }

            data_set($rs, Constant::RESPONSE_CODE_KEY, 1);
            return $rs;
        } catch (\Exception $exc) {
            $connection->rollBack();
            $excMsg = ExceptionHandler::getMessage($exc, config('app.debug'));
            return Response::getDefaultResponseData(data_get($excMsg, 'exception_code', Constant::PARAMETER_INT_DEFAULT), data_get($excMsg, 'message', Constant::PARAMETER_INT_DEFAULT), $excMsg);
        }
    }
}
