<?php

/**
 * Created by Patazon.
 * @desc   :
 * @author : Roy_qiu
 * @email  : Roy_qiu@patazon.net
 * @date   : 2021/1/11 17:10
 */

namespace App\Services;

use App\Constants\Constant;
use App\Utils\FunctionHelper;
use App\Utils\Response;
use Hyperf\Utils\Arr;
use Hyperf\DbConnection\Db as DB;

class FileUploadRecordService extends BaseService {

    public static $fileUploadRecordName = 'fur';
    public static $fileDownloadRecordName = 'fdr';

    public static function addFile($storeId, $requestData) {
        $rs = Response::getDefaultResponseData(1);
        $fileOriginName = data_get($requestData, 'originalName', Constant::PARAMETER_STRING_DEFAULT);
        $fileUrl = data_get($requestData, 'url', Constant::PARAMETER_STRING_DEFAULT);
        if (empty($fileUrl) || empty($fileOriginName)) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 2);
            return $rs;
        }

        $data = [
            'file_origin_name' => $fileOriginName,
            'file_url' => $fileUrl,
        ];

        static::getModel($storeId)->insert($data);

        return $rs;
    }

    public static function deleteFile($storeId, $requestData) {
        $rs = Response::getDefaultResponseData(1);
        $ids = data_get($requestData, Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_ARRAY_DEFAULT);
        if (empty($ids)) {
            return $rs;
        }

        !is_array($ids) && $ids = [$ids];

        static::delete($storeId, [Constant::DB_TABLE_PRIMARY => $ids]);

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
        $_data = static::getPublicData($params, []);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, null);
        $order = data_get($params, Constant::ORDER_BY, data_get($_data, Constant::ORDER, []));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, Constant::ACT_LIMIT_KEY, data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, Constant::PARAMETER_INT_DEFAULT));
        $storeId = Arr::get($params, Constant::DB_TABLE_STORE_ID, Constant::PARAMETER_INT_DEFAULT);
        $groupBy = ['file_id'];

        $select = $select ? $select : [
            static::$fileUploadRecordName . Constant::LINKER . Constant::DB_TABLE_PRIMARY,
            'file_origin_name',
            'file_url',
            static::$fileUploadRecordName . Constant::LINKER . Constant::DB_TABLE_CREATED_AT,
        ];

        $isExport = data_get($params, 'is_export', data_get($params, 'srcParameters.0.is_export', Constant::PARAMETER_INT_DEFAULT));

        $only = Constant::PARAMETER_ARRAY_DEFAULT;
        $handleData = [];

        $joinData = [];

        $fileDownloadRecordsSelect = [
            'file_id',
            DB::raw("count(*) as download_num"),
        ];

        $with = [
            'downloads' => FunctionHelper::getExePlan(
                $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $fileDownloadRecordsSelect, [], [], null, null, false,
                [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany',
                false, Constant::PARAMETER_ARRAY_DEFAULT, $groupBy),
        ];

        $unset = ['downloads'];
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getNamespaceClass(), 'file_upload_records as ' . static::$fileUploadRecordName, $select, $where, $order,
            $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset, '', true, []);

        if (data_get($params, Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_PRIMARY, false)) {//如果仅仅获取主键id，就不需要处理数据，不关联
            $dbExecutionPlan = [
                Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            ];
        } else {
            $handleData = [];
            $exePlan[Constant::DB_EXECUTION_PLAN_HANDLE_DATA] = $handleData;

            $itemHandleDataCallback = [
                'download_num' => function($item) {
                    return data_get($item,'downloads.0.download_num', Constant::PARAMETER_INT_DEFAULT);
                },
            ];

            if ($isExport) {}

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
        $fileName = $params['file_name'] ?? Constant::PARAMETER_STRING_DEFAULT; //文件名
        $uploadStartTime = data_get($params, 'upload_start_time', Constant::PARAMETER_STRING_DEFAULT); //上传开始时间
        $uploadEndTime = data_get($params, 'upload_end_time', Constant::PARAMETER_STRING_DEFAULT); //上传结束时间
        $downloadStartTime = data_get($params, 'download_start_time', Constant::PARAMETER_STRING_DEFAULT); //下载开始时间
        $downloadEndTime = data_get($params, 'download_end_time', Constant::PARAMETER_STRING_DEFAULT); //下载结束时间

        if ($fileName !== '') {
            $where[] = ['file_origin_name', 'like', "%$fileName%"];
        }

        if ($uploadStartTime !== '') {
            $where[] = [static::$fileUploadRecordName . Constant::LINKER . Constant::DB_TABLE_CREATED_AT, '>=', $uploadStartTime];
        }

        if ($uploadEndTime !== '') {
            $where[] = [static::$fileUploadRecordName . Constant::LINKER . Constant::DB_TABLE_CREATED_AT, '<=', $uploadEndTime];
        }

        $customizeWhere = [];
        if (!empty($downloadStartTime) && !empty($downloadEndTime)) {
            $_customizeWhere = [
                [
                    Constant::METHOD_KEY => Constant::DB_EXECUTION_PLAN_WHERE,
                    Constant::PARAMETERS_KEY => [function ($query) use ($downloadStartTime, $downloadEndTime) {
                        $query->whereExists(function ($query) use($downloadStartTime, $downloadEndTime) {
                            $query->select(DB::raw(1))
                                ->from('file_download_records as fdr')
                                ->where('fdr.created_at', '>=', $downloadStartTime)
                                ->where('fdr.created_at', '<=', $downloadEndTime);
                        });
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

        $order = $order ? $order : [[static::$fileUploadRecordName . Constant::LINKER . Constant::DB_TABLE_PRIMARY, Constant::ORDER_DESC]];
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
            '文件名' => 'file_origin_name',
            '文件链接(URL)' => 'file_url',
            '下载次数' => 'download_num',
            '上传时间' => 'created_at',
            Constant::EXPORT_DISTINCT_FIELD => [
                Constant::EXPORT_PRIMARY_KEY => Constant::DB_TABLE_PRIMARY,
                Constant::EXPORT_PRIMARY_VALUE_KEY => Constant::DB_TABLE_PRIMARY,
                Constant::DB_EXECUTION_PLAN_SELECT => [static::$fileUploadRecordName . Constant::LINKER . Constant::DB_TABLE_PRIMARY]
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
}
