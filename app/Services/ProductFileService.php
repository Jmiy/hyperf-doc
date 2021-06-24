<?php

/**
 * Created by Patazon.
 * @desc   :
 * @author : Roy_qiu
 * @email  : Roy_qiu@patazon.net
 * @date   : 2021/1/9 14:11
 */

namespace App\Services;

use App\Models\FileItem;
use App\Utils\Cdn\CdnManager;
use App\Constants\Constant;
use App\Utils\FunctionHelper;
use App\Utils\Response;
use Hyperf\Utils\Arr;
use Hyperf\DbConnection\Db as DB;

class ProductFileService extends BaseService {

    public static $productFilesName = 'pf';
    public static $productFileCategoriesName = 'pfc';
    public static $fileItemName = 'fi';

    /**
     * 添加
     * @param $storeId
     * @param $requestData
     * @return array
     */
    public static function addFile($storeId, $requestData) {
        $rs = Response::getDefaultResponseData(1);
        $oneCategoryName = data_get($requestData, Constant::ONE_CATEGORY_NAME, Constant::PARAMETER_STRING_DEFAULT);
        $twoCategoryName = data_get($requestData, Constant::TWO_CATEGORY_NAME, Constant::PARAMETER_STRING_DEFAULT);
        $threeCategoryName = data_get($requestData, Constant::THREE_CATEGORY_NAME, Constant::PARAMETER_STRING_DEFAULT);
        $productName = data_get($requestData, Constant::PRODUCT_NAME, Constant::PARAMETER_STRING_DEFAULT);
        $sku = data_get($requestData, Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT);
        $imgUrl = data_get($requestData, Constant::DB_TABLE_IMG_URL, Constant::PARAMETER_STRING_DEFAULT);
        $files = data_get($requestData, 'files', Constant::PARAMETER_ARRAY_DEFAULT);
        if (empty($productName) || empty($sku) || empty($imgUrl) || empty($files) || !is_array($files) || empty($oneCategoryName)) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 9999999998);
            return $rs;
        }

        $where = [
            Constant::PRODUCT_NAME => $productName,
            Constant::DB_TABLE_SKU => $sku,
        ];
        $exists = static::existsOrFirst($storeId, '', $where);
        if ($exists) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 2);
            data_set($rs, Constant::RESPONSE_MSG_KEY, '数据已经存在');
            return $rs;
        }

        $where = [
            Constant::ONE_CATEGORY_NAME => $oneCategoryName,
            Constant::TWO_CATEGORY_NAME => $twoCategoryName,
            Constant::THREE_CATEGORY_NAME => $threeCategoryName,
        ];
        $categoryItem = ProductFileCategoryService::existsOrFirst($storeId, '', $where, true, [Constant::DB_TABLE_PRIMARY]);
        $categoryId = data_get($categoryItem, Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
        if (empty($categoryId)) {
            $_result = ProductFileCategoryService::addCategory($storeId, $oneCategoryName, $twoCategoryName, $threeCategoryName);
            $categoryId = data_get($_result, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
            if (empty($categoryId)) {
                data_set($rs, Constant::RESPONSE_CODE_KEY, 3);
                return $rs;
            }
        }

        $data = [
            Constant::PRODUCT_NAME => $productName,
            Constant::DB_TABLE_SKU => $sku,
            Constant::DB_TABLE_IMG_URL => $imgUrl,
            Constant::CATEGORY_ID => $categoryId
        ];
        $insertId = static::getModel($storeId)->insertGetId($data);
        if (empty($insertId)) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 4);
            return $rs;
        }

        foreach ($files as $file) {
            $fileName = data_get($file, Constant::FILE_NAME, Constant::PARAMETER_STRING_DEFAULT);
            $fileUrl = data_get($file, Constant::NEW_FILE_URL, Constant::PARAMETER_STRING_DEFAULT);
            if (empty($fileName) || empty($fileUrl)) {
                continue;
            }

            $data = [
                Constant::FILE_NAME => $fileName,
                Constant::NEW_FILE_URL => $fileUrl,
                Constant::DB_TABLE_EXT_ID => $insertId,
                Constant::DB_TABLE_EXT_TYPE => static::getModelAlias(),
            ];
            static::createModel($storeId,FileItem::class)->insert($data);
        }

        return $rs;
    }

    /**
     * 编辑
     * @param $storeId
     * @param $requestData
     * @return array
     */
    public static function edit($storeId, $requestData) {
        $rs = Response::getDefaultResponseData(1);
        $id = data_get($requestData, Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
        $oneCategoryName = data_get($requestData, Constant::ONE_CATEGORY_NAME, Constant::PARAMETER_STRING_DEFAULT);
        $twoCategoryName = data_get($requestData, Constant::TWO_CATEGORY_NAME, Constant::PARAMETER_STRING_DEFAULT);
        $threeCategoryName = data_get($requestData, Constant::THREE_CATEGORY_NAME, Constant::PARAMETER_STRING_DEFAULT);
        $productName = data_get($requestData, Constant::PRODUCT_NAME, Constant::PARAMETER_STRING_DEFAULT);
        $sku = data_get($requestData, Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT);
        $imgUrl = data_get($requestData, Constant::DB_TABLE_IMG_URL, Constant::PARAMETER_STRING_DEFAULT);
        $files = data_get($requestData, 'files', Constant::PARAMETER_ARRAY_DEFAULT);
        if (empty($id) || empty($productName) || empty($sku) || empty($imgUrl) || empty($files) || !is_array($files) || empty($oneCategoryName)) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 9999999998);
            return $rs;
        }

        $where = [
            Constant::PRODUCT_NAME => $productName,
            Constant::DB_TABLE_SKU => $sku,
        ];
        $exists = static::existsOrFirst($storeId, '', $where, true, [Constant::DB_TABLE_PRIMARY]);
        $existsId = data_get($exists, Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
        if (!empty($existsId) && $existsId != $id) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 2);
            data_set($rs, Constant::RESPONSE_MSG_KEY, '数据已经存在');
            return $rs;
        }

        $where = [
            Constant::ONE_CATEGORY_NAME => $oneCategoryName,
            Constant::TWO_CATEGORY_NAME => $twoCategoryName,
            Constant::THREE_CATEGORY_NAME => $threeCategoryName,
        ];
        $categoryItem = ProductFileCategoryService::existsOrFirst($storeId, '', $where, true, [Constant::DB_TABLE_PRIMARY]);
        $categoryId = data_get($categoryItem, Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
        if (empty($categoryId)) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 3);
            data_set($rs, Constant::RESPONSE_MSG_KEY, '标题不存在，请先添加');
            return $rs;
        }

        $data = [];
        !empty($productName) && $data[Constant::PRODUCT_NAME] = $productName;
        !empty($sku) && $data[Constant::DB_TABLE_SKU] = $sku;
        !empty($imgUrl) && $data[Constant::DB_TABLE_IMG_URL] = $imgUrl;
        !empty($categoryId) && $data[Constant::CATEGORY_ID] = $categoryId;
        if (empty($data)) {
            return $rs;
        }

        $where = [
            Constant::DB_TABLE_PRIMARY => $id,
        ];
        static::update($storeId, $where, $data);

        $where = [
            Constant::DB_TABLE_EXT_ID => $id,
            Constant::DB_TABLE_EXT_TYPE => static::getModelAlias(),
        ];
        static::createModel($storeId,FileItem::class)->buildWhere($where)->delete();
        foreach ($files as $file) {
            $fileName = data_get($file, Constant::FILE_NAME, Constant::PARAMETER_STRING_DEFAULT);
            $fileUrl = data_get($file, Constant::NEW_FILE_URL, Constant::PARAMETER_STRING_DEFAULT);
            if (empty($fileName) || empty($fileUrl)) {
                continue;
            }

            $data = [
                Constant::FILE_NAME => $fileName,
                Constant::NEW_FILE_URL => $fileUrl,
                Constant::DB_TABLE_EXT_ID => $id,
                Constant::DB_TABLE_EXT_TYPE => static::getModelAlias(),
            ];
            static::createModel($storeId,FileItem::class)->insert($data);
        }

        return $rs;
    }

    /**
     * 删除
     * @param $storeId
     * @param $requestData
     * @return array|bool
     */
    public static function deleteFile($storeId, $requestData) {
        $rs = Response::getDefaultResponseData(1);
        $id = data_get($requestData, Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
        if (empty($id)) {
            return $rs;
        }

        static::delete($storeId, [Constant::DB_TABLE_PRIMARY => $id]);

        $where = [
            Constant::DB_TABLE_EXT_ID => $id,
            Constant::DB_TABLE_EXT_TYPE => static::getModelAlias(),
        ];
        static::createModel($storeId,FileItem::class)->buildWhere($where)->delete();

        return $rs;
    }

    /**
     * 列表
     * @param array $params 请求参数
     * @param boolean $toArray 是否转化为数组 true:是 false:否 默认:true
     * @param boolean $isPage  是否分页 true:是 false:否 默认:true
     * @param array $select  查询字段
     * @param boolean $isRaw 是否原始 select true:是 false:否 默认:false
     * @param boolean $isGetQuery 是否获取 query
     * @param boolean $isOnlyGetCount 是否仅仅获取总记录数
     * @return array|\Hyperf\Database\Model\Builder 列表数据|Builder
     */
    public static function getListData($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {
        $_data = static::getPublicData($params, [[static::$productFilesName . Constant::LINKER . Constant::DB_TABLE_SKU, Constant::DB_EXECUTION_PLAN_ORDER_DESC]]);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, null);
        $order = data_get($params, Constant::ORDER_BY, data_get($_data, Constant::ORDER, []));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, Constant::ACT_LIMIT_KEY, data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, Constant::PARAMETER_INT_DEFAULT));
        $storeId = Arr::get($params, Constant::DB_TABLE_STORE_ID, Constant::PARAMETER_INT_DEFAULT);

        $select = $select ? $select : [
            static::$productFilesName . Constant::LINKER . Constant::DB_TABLE_PRIMARY,
            Constant::PRODUCT_NAME,
            Constant::DB_TABLE_SKU,
            Constant::DB_TABLE_IMG_URL,
            static::$productFileCategoriesName . Constant::LINKER . Constant::ONE_CATEGORY_NAME,
            static::$productFileCategoriesName . Constant::LINKER . Constant::TWO_CATEGORY_NAME,
            static::$productFileCategoriesName . Constant::LINKER . Constant::THREE_CATEGORY_NAME,
            static::$productFilesName . Constant::LINKER . Constant::DB_TABLE_CREATED_AT,
        ];

        $isExport = data_get($params, 'is_export', data_get($params, 'srcParameters.0.is_export', Constant::PARAMETER_INT_DEFAULT));

        $only = Constant::PARAMETER_ARRAY_DEFAULT;
        $handleData = [];

        $joinData = [
            FunctionHelper::getExePlanJoinData("product_file_categories as pfc", function ($join) {
                $join->on([[static::$productFileCategoriesName . Constant::LINKER . Constant::DB_TABLE_PRIMARY, '=', static::$productFilesName . Constant::LINKER . 'category_id']])
                    ->where(static::$productFileCategoriesName . Constant::LINKER . Constant::DB_TABLE_STATUS, '=', 1)
                ;
            }),
        ];

        $fileItemSelect = [
            Constant::DB_TABLE_EXT_ID,
            Constant::DB_TABLE_EXT_TYPE,
            Constant::FILE_NAME,
            Constant::NEW_FILE_URL,
        ];
        $with = [
            'files' => FunctionHelper::getExePlan(
                $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $fileItemSelect, [], [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany', false, Constant::PARAMETER_ARRAY_DEFAULT),
        ];

        $unset = [];
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getNamespaceClass(), 'product_files as ' . static::$productFilesName, $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        if (data_get($params, Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_PRIMARY, false)) {//如果仅仅获取主键id，就不需要处理数据，不关联
            $dbExecutionPlan = [
                Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            ];
        } else {

            $handleData = [];
            $exePlan[Constant::DB_EXECUTION_PLAN_HANDLE_DATA] = $handleData;

            $itemHandleDataCallback = [];

            if ($isExport) {
                $itemHandleDataCallback = [
                    'file_names' => function($item) {
                        $files = data_get($item, 'files', Constant::PARAMETER_STRING_DEFAULT);
                        return $files && is_array($files) ? implode(';', array_column($files, Constant::FILE_NAME)) : '';
                    }
                ];
            }

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
     * 获取公共参数
     * @param array $params 请求参数
     * @param array $order 排序
     * @return array
     */
    public static function getPublicData($params, $order = []) {

        $_where = Constant::PARAMETER_ARRAY_DEFAULT;
        $where = Constant::PARAMETER_ARRAY_DEFAULT;
        $productName = $params[Constant::PRODUCT_NAME] ?? Constant::PARAMETER_STRING_DEFAULT; //产品名
        $sku = $params[Constant::DB_TABLE_SKU] ?? Constant::PARAMETER_STRING_DEFAULT; //产品sku
        $oneCategoryName = $params[Constant::ONE_CATEGORY_NAME] ?? Constant::PARAMETER_STRING_DEFAULT; //一级标题
        $twoCategoryName = $params[Constant::TWO_CATEGORY_NAME] ?? Constant::PARAMETER_STRING_DEFAULT; //二级标题
        $threeCategoryName = $params[Constant::THREE_CATEGORY_NAME] ?? Constant::PARAMETER_STRING_DEFAULT; //三级标题
        $startTime = data_get($params, Constant::START_TIME, Constant::PARAMETER_STRING_DEFAULT); //开始时间
        $endTime = data_get($params, Constant::DB_TABLE_END_TIME, Constant::PARAMETER_STRING_DEFAULT); //结束时间
        $searchText = data_get($params, 'search_text', Constant::PARAMETER_STRING_DEFAULT);
        $categoryKey = data_get($params, 'category_key', Constant::PARAMETER_STRING_DEFAULT);
        if (!empty($categoryKey)) {
            $categoryKeyArr = explode('{#}', $categoryKey);
            $oneCategoryName = data_get($categoryKeyArr, '0', Constant::PARAMETER_STRING_DEFAULT);
            $twoCategoryName = data_get($categoryKeyArr, '1', Constant::PARAMETER_STRING_DEFAULT);
            $threeCategoryName = data_get($categoryKeyArr, '2', Constant::PARAMETER_STRING_DEFAULT);
        }

        if ($productName !== '') {
            $where[] = [Constant::PRODUCT_NAME, 'like', "%$productName%"];
        }

        if ($sku !== '') {
            $where[] = [Constant::DB_TABLE_SKU, 'like', "%$sku%"];
        }

        if ($oneCategoryName !== '') {
            $where[] = [Constant::ONE_CATEGORY_NAME, '=', "$oneCategoryName"];
        }

        if ($twoCategoryName !== '') {
            $where[] = [Constant::TWO_CATEGORY_NAME, '=', "$twoCategoryName"];
        }

        if ($threeCategoryName !== '') {
            $where[] = [Constant::THREE_CATEGORY_NAME, '=', "$threeCategoryName"];
        }

        if ($startTime !== '') {
            $where[] = [static::$productFilesName . Constant::LINKER . Constant::DB_TABLE_CREATED_AT, '>=', $startTime];
        }

        if ($endTime !== '') {
            $where[] = [static::$productFilesName . Constant::LINKER . Constant::DB_TABLE_CREATED_AT, '<=', $endTime];
        }

        $customizeWhere = [];
        if (!empty($searchText)) {
            $_customizeWhere = [
                [
                    Constant::METHOD_KEY => Constant::DB_EXECUTION_PLAN_WHERE,
                    Constant::PARAMETERS_KEY => [function ($query) use ($searchText) {
                        $query->orWhere(Constant::PRODUCT_NAME, 'like', "%$searchText%")
                            ->orWhere(Constant::DB_TABLE_SKU, 'like', "%$searchText%");
                    }],
                ]
            ];
            $customizeWhere = Arr::collapse([$customizeWhere, $_customizeWhere]);
        }

        if ($where) {
            $_where[] = $where;
        }

        if ($customizeWhere) {
            $_where['{customizeWhere}'] = $customizeWhere;
        }

        $order = $order ? $order : [[static::$productFilesName . Constant::LINKER . Constant::DB_TABLE_PRIMARY, Constant::ORDER_DESC]];
        return Arr::collapse([parent::getPublicData($params, $order), [
            Constant::DB_EXECUTION_PLAN_WHERE => $_where,
        ]]);
    }

    /**
     * 导出
     * @param array $requestData 请求参数
     * @return array
     */
    public static function export($requestData) {
        $header = [
            '产品名' => Constant::PRODUCT_NAME,
            '产品sku' => Constant::DB_TABLE_SKU,
            '产品图片' => Constant::DB_TABLE_IMG_URL,
            '一级类目' => Constant::ONE_CATEGORY_NAME,
            '二级类目' => Constant::TWO_CATEGORY_NAME,
            '三级类目' => Constant::THREE_CATEGORY_NAME,
            '下载文件名' => 'file_names',
            '创建时间' => Constant::DB_TABLE_CREATED_AT,
            Constant::EXPORT_DISTINCT_FIELD => [
                Constant::EXPORT_PRIMARY_KEY => Constant::DB_TABLE_PRIMARY,
                Constant::EXPORT_PRIMARY_VALUE_KEY => Constant::DB_TABLE_PRIMARY,
                Constant::DB_EXECUTION_PLAN_SELECT => [static::$productFilesName . Constant::LINKER . Constant::DB_TABLE_PRIMARY]
            ],
        ];

        $service = static::getNamespaceClass();
        $method = 'getListData';
        $select = [];
        $parameters = [$requestData, true, true, $select, false, false];
        $countMethod = $method;
        $countParameters = Arr::collapse([$parameters, [true]]);
        $file = ExcelService::createCsvFile($header, $service, $countMethod, $countParameters, $method, $parameters);

        return [Constant::FILE_URL => $file];
    }

    public static function fileDownload($storeId, $requestData) {
        $rs = Response::getDefaultResponseData(1);
        $fileName = data_get($requestData, Constant::FILE_NAME, Constant::PARAMETER_STRING_DEFAULT);
        $fileUrl = data_get($requestData, Constant::NEW_FILE_URL, Constant::PARAMETER_STRING_DEFAULT);
        if (empty($fileName) || empty($fileUrl)) {
            return $rs;
        }

        FileDownloadRecordService::add($storeId, $fileName, $fileUrl);

        return $rs;
    }

    /**
     * 导入
     * @param int $storeId 商城id
     * @param array $requestData
     * @return array 导入结果
     */
    public static function importProductFiles($storeId, $requestData = []) {
        $rs = Response::getDefaultResponseData(1);
        $productFiles = static::getProductFilesData($requestData);
        if (empty($productFiles)) {
            return [];
        }

        $productFileData = [];
        $errorMsg = [];
        foreach ($productFiles as $key => $productFile) {
            $productName = data_get($productFile, Constant::PRODUCT_NAME);
            $sku = data_get($productFile, Constant::DB_TABLE_SKU);
            $imgUrl = data_get($productFile, Constant::DB_TABLE_IMG_URL);
            $fileName = data_get($productFile, Constant::FILE_NAME);
            $fileUrl = data_get($productFile, Constant::NEW_FILE_URL);
            $oneCategoryName = data_get($productFile, Constant::ONE_CATEGORY_NAME, Constant::PARAMETER_STRING_DEFAULT);
            $twoCategoryName =  data_get($productFile, Constant::TWO_CATEGORY_NAME, Constant::PARAMETER_STRING_DEFAULT);
            $threeCategoryName = data_get($productFile, Constant::THREE_CATEGORY_NAME, Constant::PARAMETER_STRING_DEFAULT);
            $msg = '';
            empty(trim($productName)) && $msg .= "第 $key 行产品名不能为空";
            empty(trim($sku)) && $msg .= "第 $key 行 sku名不能为空";
            empty(trim($imgUrl)) && $msg .= "第 $key 行 图片地址名不能为空";
            empty(trim($oneCategoryName)) && $msg .= "第 $key 行 一级类目名不能为空";
            empty(trim($fileName)) && $msg .= "第 $key 行 文件名不能为空";
            empty(trim($fileUrl)) && $msg .= "第 $key 行 文件URL不能为空";
            if (!empty($msg)) {
                $errorMsg[] = $msg;
                continue;
            }

            $key = "$productName.$sku.$oneCategoryName.$twoCategoryName.$threeCategoryName";
            $productFileData[$key][Constant::PRODUCT_NAME] = $productName;
            $productFileData[$key][Constant::DB_TABLE_SKU] = $sku;
            $productFileData[$key][Constant::DB_TABLE_IMG_URL] = $imgUrl;
            $productFileData[$key][Constant::ONE_CATEGORY_NAME] = $oneCategoryName;
            $productFileData[$key][Constant::TWO_CATEGORY_NAME] = $twoCategoryName;
            $productFileData[$key][Constant::THREE_CATEGORY_NAME] = $threeCategoryName;
            $productFileData[$key]['files'][] = [
                Constant::FILE_NAME => $fileName,
                Constant::NEW_FILE_URL => $fileUrl,
            ];
        }

        $_rsData = [];
        foreach ($productFileData as $item) {
            $_result = static::addFile($storeId, $item);
            if (data_get($_result, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_INT_DEFAULT) != 1) {
                $_rsData[] = $item;
            }
        }
        data_set($rs, Constant::RESPONSE_DATA_KEY, $_rsData);
        if (!empty($errorMsg)) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 9999999998);
            data_set($rs, Constant::RESPONSE_MSG_KEY, implode("<br>", $errorMsg));
        }

        return $rs;
    }

    /**
     * 获取导入的数据
     * @param $requestData
     * @return array
     */
    public static function getProductFilesData($requestData) {
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
            \Vtiful\Kernel\Excel::TYPE_STRING,
        ];

        try {
            $data = ExcelService::parseExcelFile(data_get($fileData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::FILE_FULL_PATH, ''), $typeData, null, null, 'Sheet1');
        } catch (\Exception $exception) {
            return $data;
        }

        return static::convertProductFiles($data);
    }

    /**
     * 模板表头
     * @var array
     */
    public static $productFilesHeaderMap = [
        '驱动产品名(必填)' => 'product_name',
        '产品sku(必填/排序)' => Constant::DB_TABLE_SKU,
        '产品图片链接(必填)' => Constant::DB_TABLE_IMG_URL,
        '下载文件名(必填)' => 'file_name',
        '下载链接(必填)' => 'file_url',
        '一级类目(必填)' => 'one_category_name',
        '二级类目' => 'two_category_name',
        '三级类目' => 'three_category_name',
    ];

    /**
     * 导入的数据
     * @param $excelData
     * @return array
     */
    public static function convertProductFiles($excelData) {
        if (empty($excelData)) {
            return [];
        }

        $tableData = [];
        $header = [];
        $isHeader = true;
        foreach ($excelData as $k => $row) {
            if ($isHeader) {
                $temp = array_flip($row);
                foreach (static::$productFilesHeaderMap as $key => $value) {
                    data_set($header, $value, data_get($temp, $key));
                }
                $isHeader = false;
                continue;
            }

            $productName = trim(data_get($row, data_get($header, Constant::PRODUCT_NAME), Constant::PARAMETER_STRING_DEFAULT));
            $sku = trim(data_get($row, data_get($header, Constant::DB_TABLE_SKU), Constant::PARAMETER_STRING_DEFAULT));
            $imgUrl = trim(data_get($row, data_get($header, Constant::DB_TABLE_IMG_URL), Constant::PARAMETER_STRING_DEFAULT));
            $fileName = trim(data_get($row, data_get($header, 'file_name'), Constant::PARAMETER_STRING_DEFAULT));
            $fileUrl = trim(data_get($row, data_get($header, 'file_url'), Constant::PARAMETER_STRING_DEFAULT));
            $oneCategoryName = trim(data_get($row, data_get($header, 'one_category_name'), Constant::PARAMETER_STRING_DEFAULT));
            $twoCategoryName = trim(data_get($row, data_get($header, 'two_category_name'), Constant::PARAMETER_STRING_DEFAULT));
            $threeCategoryName = trim(data_get($row, data_get($header, 'three_category_name'), Constant::PARAMETER_STRING_DEFAULT));
//            if (empty($productName) || empty($sku) || empty($imgUrl) || empty($oneCategoryName) || empty($fileUrl)) {
//                continue;
//            }

            data_set($tableData, $k . '.product_name', $productName);
            data_set($tableData, $k . '.sku', $sku);
            data_set($tableData, $k . '.img_url', $imgUrl);
            data_set($tableData, $k . '.file_name', $fileName);
            data_set($tableData, $k . '.file_url', $fileUrl);
            data_set($tableData, $k . '.one_category_name', $oneCategoryName);
            data_set($tableData, $k . '.two_category_name', $twoCategoryName);
            data_set($tableData, $k . '.three_category_name', $threeCategoryName);
        }

        return $tableData;
    }
}
