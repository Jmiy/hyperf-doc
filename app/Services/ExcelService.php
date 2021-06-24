<?php

/**
 * 邮件服务
 * User: Jmiy
 * Date: 2019-12-04
 * Time: 17:55
 */

namespace App\Services;

use App\Utils\Support\Facades\Queue;
use Hyperf\Utils\Arr;
use App\Utils\FunctionHelper;
use App\Jobs\CreateExcelJob;
use App\Utils\Support\Facades\Redis;
use App\Constants\Constant;
use Hyperf\DbConnection\Db as DB;
use App\Mail\PublicMail;
use App\Utils\PublicValidator;
use App\Utils\Response;

class ExcelService extends BaseService {

    /**
     * 获取时区
     * @param mix $data 数据
     * @param string|null $timezone 时区 默认：null
     * @return string|null 时区
     */
    public static function getTimezone($data, $timezone = null) {

        if ($timezone) {
            return $timezone;
        }

        $timeValue = strtotime($data);
        if ($timeValue !== false) {//如果是一个合格的时间串，就直接使用当前系统默认的时区即可
            return null;
        }

        return 'UTC';
    }

    /**
     * 处理数据
     * @param array $data 源数据
     * @param array $typeData 数据类型
     * @return array 处理后的数据
     */
    public static function handleData($data, $typeData = [], $index = null) {

        $timeIndex = [];
        foreach ($typeData as $key => $value) {
            if ($value == \Vtiful\Kernel\Excel::TYPE_TIMESTAMP) {
                $timeIndex[] = $key;
            }
        }

        if (empty($timeIndex)) {
            return $data;
        }

        if (count($data) != count($data, 1)) {//如果是多维数组，就递归处理
            foreach ($data as $key => $_value) {
                data_set($data, $key, static::handleData($_value, $typeData));
            }
            return $data;
        }

        $format = 'Y-m-d H:i:s';
        $attributes = 'date'; //'timestamp';
        $timezone = null;

        if (!is_array($data) && $index !== null && in_array($index, $timeIndex)) {
            $timezone = static::getTimezone($data);
            return FunctionHelper::handleDatetime($data, $format, $attributes, $timezone); //'UTC'  'Y-m-d H:i:s'
        }

        foreach ($timeIndex as $index) {
            $dateTime = data_get($data, $index, null);
            $timezone = static::getTimezone($dateTime);
            $dateTime = FunctionHelper::handleDatetime($dateTime, $format, $attributes, $timezone);
            data_set($data, $index, $dateTime);
        }

        return $data;
    }

    /**
     * @param $fileFullPath
     * @param array $typeData
     * @param null $nextRowCallback
     * @param null $nextCellCallback
     * @param string $sheetIdxName
     * @return array
     */
    public static function parseExcelFile($fileFullPath, $typeData = [], $nextRowCallback = null, $nextCellCallback = null, $sheetIdxName = '') {

        $pathInfo = pathinfo($fileFullPath);
        $path = data_get($pathInfo, 'dirname', Constant::PARAMETER_STRING_DEFAULT);
        $fileName = data_get($pathInfo, 'basename', Constant::PARAMETER_STRING_DEFAULT);

        $data = [];
        if (empty($path) || empty($fileName)) {
            return $data;
        }

        $config = ['path' => $path];
        $excel = new \Vtiful\Kernel\Excel($config);

        $sheetList = $excel->openFile($fileName)->sheetList();
        $data = [];
        foreach ($sheetList as $sheetName) {

            // 通过工作表名称获取工作表数据
            $excel->openSheet($sheetName)
                    ->setType($typeData)
            ;

            //全量读取
            if ($nextRowCallback === null && $nextCellCallback === null) {
                $_data = $excel->getSheetData();
                $data = Arr::collapse([$data, static::handleData($_data, $typeData)]);
                if ($sheetIdxName == $sheetName) {
                    break;
                }
                continue;
            }

            if ($nextRowCallback !== null) {
                while ($row = $excel->nextRow($typeData)) {

                    if (empty(array_filter($row))) {
                        continue;
                    }

                    $_row = static::handleData($row, $typeData);
                    $nextRowCallback($_row);
                    //$data[$sheetName][] = $_row;
                }
            }

            if ($nextCellCallback !== null) {
                $excel->nextCellCallback(function ($row, $cell, $cellData) use($nextCellCallback, $sheetName, &$data) {
                    $cellData = static::handleData($cellData, $typeData, $cell);
                    $nextCellCallback($row, $cell, $cellData, $sheetName);
                    //$data[$sheetName][$row][$cell] = $cellData;
                }, $sheetName);
            }

            if ($sheetIdxName == $sheetName) {
                break;
            }
        }

        unset($excel);

        return $data;

//        $spreadsheet = IOFactory::load($fileFullPath);
//        $worksheet = $spreadsheet->getActiveSheet();
//        $data = $worksheet->toArray();
//
//        $spreadsheet->disconnectWorksheets();
//        unset($spreadsheet);
//
//        return $data;
    }

    /**
     * 创建excel文件
     * @param array $header excel表头
     * @param \Hyperf\Database\Model\Builder  $query
     * @param int  $count  分页每次获取记录条数
     * @param string $fileName 文件名
     * @param string $path 文件保存文件夹
     * @return string 文件下载地址
     */
    public static function createVtifulExcel($header, $query, $count = 100, $fileName = Constant::PARAMETER_STRING_DEFAULT, $path = '/public/file/download/excel') {

        $_path = config(Constant::APP . Constant::LINKER . Constant::EXPORT_PATH);
        $realPath = storage_path($_path . $path);

        if (!is_dir($realPath)) {
            mkdir($realPath, 0777, true);
        }

        $config = ['path' => $realPath];
        $excel = new \Vtiful\Kernel\Excel($config);

        if (empty($fileName)) {
            $fileName = date('YmdHis') . '_' . mt_rand(1000, 9999) . '.xlsx';
        }

        $textFile = $excel->fileName($fileName)
                ->header(array_keys($header));

        $row = 0;
        $_header = array_values($header);

        $query->chunk($count, function ($data) use($textFile, $_header, &$row) {
            if ($data) {
                foreach ($data as $item) {
                    $row = $row + 1;
                    foreach ($_header as $key => $field) {
                        $value = FunctionHelper::handleData($item, $field);
                        $textFile->insertText($row, $key, $value . Constant::PARAMETER_STRING_DEFAULT);
                    }
                }
            }
        });

        $textFile->output();

        return $path . '/' . $fileName;
    }

    /**
     * 创建excel文件
     * @param array $header excel表头
     * @param \Hyperf\Database\Model\Builder  $query
     * @param int  $count  分页每次获取记录条数
     * @param string $fileName 文件名
     * @param string $path 文件保存文件夹
     * @return string 文件下载地址
     */
    public static function createCsvFile($header, $service, $countMethod, $countParameters, $method, $parameters, $fileName = Constant::PARAMETER_STRING_DEFAULT, $path = '/public/file/download/excel') {

        $isFromEmailExport = data_get($parameters, '0.is_from_email_export');
        if ($isFromEmailExport) {
            return static::createExcelByQueue($header, $service, $countMethod, $countParameters, $method, $parameters, $fileName, $path);
        }

        ini_set('max_execution_time', 6000); // 设置PHP超时时间
        ini_set('memory_limit', '2048M'); // 设置PHP临时允许内存大小

        $srcParameters = $parameters;

        data_set($parameters, '0.limit', null); //设置全量查询
        data_set($parameters, '0.offset', null); //设置全量查询
        data_set($parameters, '0.isOnlyGetPrimary', true); //设置仅仅获取主键id

        data_set($parameters, 2, false);
        $select = data_get($parameters, 3, []);
        $_select = data_get($header, 'distinctField.select', ['id']);
        data_set($parameters, 3, $_select);
        $isGetQuery = data_get($parameters, '0.isGetQuery', true);
        data_set($parameters, 5, $isGetQuery);

        $primaryKey = data_get($header, 'distinctField.primaryKey', 'id');
        $primaryValueKey = data_get($header, 'distinctField.primaryValueKey', 'id');

        $data = $service::{$method}(...$parameters);
        if (is_array($data)) {
            $data = collect($data);
        }
        $data = $data->pluck($primaryValueKey);
        $_path = config(Constant::APP . Constant::LINKER . Constant::EXPORT_PATH);
        $realPath = storage_path($_path . $path);

        if (!is_dir($realPath)) {
            mkdir($realPath, 0777, true);
        }

        if (empty($fileName)) {
            $fileName = date('YmdHis') . '_' . mt_rand(1000, 9999) . '.csv';
        }

        if (isset($header[Constant::EXPORT_DISTINCT_FIELD])) {
            unset($header[Constant::EXPORT_DISTINCT_FIELD]);
        }

        $file = fopen($realPath . '/' . $fileName, 'a'); //w+

        if (!empty($header)) {
            $colHeaders = [];
            foreach (array_keys($header) as $value) {
                $colHeaders[] = iconv('UTF-8', 'GB2312//IGNORE', $value);
            }

            fputcsv($file, $colHeaders);
        }

        $limit = 100;
        $page = 1;
        $lineNumber = 1;
        while ($ids = $data->slice((($page - 1) * $limit), $limit)->all()) {
            $page++;
            if (empty($ids)) {
                break;
            }

            data_set($parameters, 0, [
                'store_id' => data_get($parameters, '0.store_id', 0),
                'orderBy' => [],
                $primaryKey => $ids,
                'limit' => null,
                'offset' => null,
                'srcParameters' => $srcParameters,
            ]);

            $source = data_get($srcParameters, (Constant::PARAMETER_INT_DEFAULT . Constant::LINKER . Constant::DB_TABLE_SOURCE), Constant::PARAMETER_STRING_DEFAULT);
            if ($source) {
                data_set($parameters, (Constant::PARAMETER_INT_DEFAULT . Constant::LINKER . Constant::DB_TABLE_SOURCE), $source);
            }

            data_set($parameters, 3, $select);
            data_set($parameters, 5, false);

            $_data = $service::{$method}(...$parameters);
            $_data = collect(Arr::get($_data, 'data', $_data))->keyBy($primaryKey)->all();
            foreach ($ids as $id) {
                $rowData = [];

                foreach ($header as $field) {
                    $_value = data_get($_data, $id . Constant::LINKER . $field, Constant::PARAMETER_STRING_DEFAULT) . '';
                    $rowData[] = $_value !== null ? iconv('UTF-8', 'GB2312//IGNORE', $_value) : '';
                }

                if ($rowData) {
                    fputcsv($file, $rowData);
                    unset($rowData);
                }

                if ($lineNumber == $limit) { //每次写入1000条数据清除内存
                    ob_flush(); //将本来存在输出缓存中的内容取出来，调用ob_flush()之后缓冲区内容将被丢弃。
                    flush();   //待输出的内容立即发送。
                    $lineNumber = 0;
                }

                $lineNumber++;
            }
        }

        ob_flush();
        fclose($file);

        return $path . '/' . $fileName;



//                $row = 1;
//                foreach ($data as $_fileName) {
//                    $file = fopen($_fileName, "r");
//                    while (!feof($file)) {
//                        $dd = fgetcsv($file);
//                        if ($dd) {
//                            foreach ($dd as $key => $value) {
//                                $textFile->insertText($row, $key, $value . Constant::PARAMETER_STRING_DEFAULT); //
//                            }
//                            $row += 1;
//                        }
//                    }
//                    fclose($file);
//                    unlink($_fileName);
//                }
//
//        if (empty($fileName)) {
//            $fileName = date('YmdHis') . '_' . mt_rand(1000, 9999) . '.csv';
//        }
//        $file = fopen($realPath . '/' . $fileName, 'w+');
//            foreach ($data as $rowData => $row) {
//                $rowData = json_decode($rowData, true);
//                fputcsv($file, $rowData);
//            }
//fclose($file);
    }

    /**
     * 创建excel文件
     * @param array $header excel表头
     * @param \Hyperf\Database\Model\Builder  $query
     * @param int  $count  分页每次获取记录条数
     * @param string $fileName 文件名
     * @param string $path 文件保存文件夹
     * @return string 文件下载地址
     */
    public static function createExcelByQueue($header, $service, $countMethod, $countParameters, $method, $parameters, $fileName = Constant::PARAMETER_STRING_DEFAULT, $path = '/public/file/download/excel') {

        FunctionHelper::pushQueue(FunctionHelper::getJobData(static::getNamespaceClass(), 'handleTask', func_get_args()), null, '{data-import}');
        $exportEmail = data_get($parameters, Constant::PARAMETER_INT_DEFAULT . Constant::LINKER . 'export_email');

        return Response::getDefaultResponseData(1, '数据正在导出，导出成功以后将发送邮件到 ' . $exportEmail . ',请点击邮件中的文件链接，下载文件', []);
    }

    public static function handleTask($header, $service, $countMethod, $countParameters, $method, $parameters, $fileName = Constant::PARAMETER_STRING_DEFAULT, $path = '/public/file/download/excel') {

        ini_set('max_execution_time', 6000); // 设置PHP超时时间
        ini_set('memory_limit', '2048M'); // 设置PHP临时允许内存大小

        $srcParameters = $parameters;

        data_set($parameters, '0.limit', null); //设置全量查询
        data_set($parameters, '0.offset', null); //设置全量查询
        data_set($parameters, '0.isOnlyGetPrimary', true); //设置仅仅获取主键id

        data_set($parameters, 2, false);
        $select = data_get($parameters, 3, []);
        $_select = data_get($header, 'distinctField.select', ['id']);
        data_set($parameters, 3, $_select);
        $isGetQuery = data_get($parameters, '0.isGetQuery', true);
        data_set($parameters, 5, $isGetQuery);

        $primaryKey = data_get($header, 'distinctField.primaryKey', 'id');
        $primaryValueKey = data_get($header, 'distinctField.primaryValueKey', 'id');

        $data = $service::{$method}(...$parameters);
        if (is_array($data)) {
            $data = collect($data);
        }
        $data = $data->pluck($primaryValueKey);

        $limit = 2000;
        $page = 1;
        $lineNumber = 1;

        $sum = ceil($data->count() / $limit);
        $taskData = [
            'sum' => ceil($data->count() / $limit),
        ];
        $taskId = TaskService::getModel(1)->insertGetId($taskData);

        while ($ids = $data->slice((($page - 1) * $limit), $limit)->all()) {
            $page++;
            if (empty($ids)) {
                break;
            }

            data_set($parameters, 0, [
                'store_id' => data_get($parameters, '0.store_id', 0),
                'orderBy' => [],
                $primaryKey => $ids,
                'limit' => null,
                'offset' => null,
                'srcParameters' => $srcParameters,
                'sort' => $page - 1,
                'taskId' => $taskId,
                'sum' => $sum,
            ]);

            $source = data_get($srcParameters, (Constant::PARAMETER_INT_DEFAULT . Constant::LINKER . Constant::DB_TABLE_SOURCE), Constant::PARAMETER_STRING_DEFAULT);
            if ($source) {
                data_set($parameters, (Constant::PARAMETER_INT_DEFAULT . Constant::LINKER . Constant::DB_TABLE_SOURCE), $source);
            }

            data_set($parameters, 3, $select);
            data_set($parameters, 5, false);

            FunctionHelper::pushQueue(FunctionHelper::getJobData(static::getNamespaceClass(), 'handleExcel', [$header, $service, $countMethod, $countParameters, $method, $parameters, $fileName, $path]), null, '{data-import}');
        }
    }

    /**
     * 创建excel文件
     * @param array $header excel表头
     * @param \Hyperf\Database\Model\Builder  $query
     * @param int  $count  分页每次获取记录条数
     * @param string $fileName 文件名
     * @param string $path 文件保存文件夹
     * @return string 文件下载地址
     */
    public static function handleExcel($header, $service, $countMethod, $countParameters, $method, $parameters, $fileName = Constant::PARAMETER_STRING_DEFAULT, $path = '/public/file/download/excel') {

        $lockParameters = [
            function () use($header, $service, $countMethod, $countParameters, $method, $parameters, $fileName, $path) {

                $primaryKey = data_get($header, Constant::EXPORT_DISTINCT_FIELD . '.primaryKey', 'id');
                $ids = data_get($parameters, '0.' . $primaryKey, []);
                $sort = data_get($parameters, '0.sort', 1);
                $taskId = data_get($parameters, '0.taskId', 0);
                $sum = data_get($parameters, '0.sum', 0);
                $storeId = data_get($parameters, '0.' . Constant::DB_TABLE_STORE_ID, 0); //store_id

                $taskWhere = [
                    'task_no' => $taskId,
                    'sort' => $sort,
                ];
                $isExists = TaskService::existsOrFirst($storeId, '', $taskWhere);
                if ($isExists) {
                    return true;
                }

                $data = [
                    'task_no' => $taskId,
                    'sort' => $sort,
                ];
                $_taskId = TaskService::getModel($storeId)->insertGetId($data);

                ini_set('max_execution_time', 6000); // 设置PHP超时时间
                ini_set('memory_limit', '2048M'); // 设置PHP临时允许内存大小

                $_data = $service::{$method}(...$parameters);
                $_data = collect(Arr::get($_data, 'data', $_data))->keyBy($primaryKey)->all();

                if (isset($header[Constant::EXPORT_DISTINCT_FIELD])) {
                    unset($header[Constant::EXPORT_DISTINCT_FIELD]);
                }

                $_path = config(Constant::APP . Constant::LINKER . Constant::EXPORT_PATH);
                $realPath = storage_path($_path . $path . '/' . $taskId);

                if (!is_dir($realPath)) {
                    mkdir($realPath, 0777, true);
                }

                $config = ['path' => $realPath];
                $excel = new \Vtiful\Kernel\Excel($config);

                if (empty($fileName)) {
                    $fileName = date('YmdHis') . '_' . mt_rand(1000, 9999) . '_' . $_taskId . '.xlsx';
                }

                $textFile = $excel->fileName($fileName)
                        ->header(array_keys($header));

                $row = 1;
                $_header = array_values($header);
                foreach ($ids as $id) {
                    foreach ($_header as $_key => $field) {
                        $_value = data_get($_data, $id . Constant::LINKER . $field, Constant::PARAMETER_STRING_DEFAULT) . '';
                        $textFile->insertText($row, $_key, $_value . ''); //
                    }
                    $row++;
                }
                $textFile->output();

                $data = [
                    'file_name' => ($realPath . '/' . $fileName),
                    'sum' => count($_data),
                    'row_sum' => $row - 1
                ];
                $offset = TaskService::update($storeId, [Constant::DB_TABLE_PRIMARY => $_taskId], $data);

                if ($offset) {
                    $where = [
                        Constant::DB_TABLE_PRIMARY => $taskId,
                    ];
                    $_offset = TaskService::update($storeId, $where, ['executed_sum' => DB::raw('executed_sum+1')]);

                    if ($_offset) {
                        FunctionHelper::pushQueue(FunctionHelper::getJobData(static::getNamespaceClass(), 'createExcel', [$header, $service, $countMethod, $countParameters, $method, $parameters]), null, '{data-import}');
                    }
                }

                return true;
            }
        ];

        $taskId = data_get($parameters, '0.taskId', 0);
        $sort = data_get($parameters, '0.sort', 1);

        return static::handleLock([md5(__METHOD__), $taskId, $sort], $lockParameters);
    }

    public static function createExcel($header, $service, $countMethod, $countParameters, $method, $parameters, $fileName = Constant::PARAMETER_STRING_DEFAULT, $path = '/public/file/download/excel') {

        $lockParameters = [
            function () use($header, $service, $countMethod, $countParameters, $method, $parameters, $fileName, $path) {

                ini_set('max_execution_time', 6000); // 设置PHP超时时间
                ini_set('memory_limit', '2048M'); // 设置PHP临时允许内存大小

                $taskId = data_get($parameters, '0.taskId', 0);
                $sum = data_get($parameters, '0.sum', 0);
                $storeId = data_get($parameters, '0.' . Constant::DB_TABLE_STORE_ID, 0);

                $where = [
                    Constant::DB_TABLE_PRIMARY => $taskId,
                ];
                $taskData = TaskService::existsOrFirst($storeId, '', $where, true, ['sum', 'task_status']);
                if (!(data_get($taskData, 'sum', 0) >= $sum && data_get($taskData, 'task_status', 0) == 0)) {
                    return false;
                }

                $where['task_status'] = 0;
                $offset = TaskService::update($storeId, $where, ['task_status' => 1]);
                if (empty($offset)) {
                    return false;
                }

                $where = [
                    'task_no' => $taskId,
                ];
                $data = TaskService::getModel($storeId, '')->select(['file_name', Constant::DB_TABLE_PRIMARY])->buildWhere($where)->orderBy('sort', 'ASC')->get()->pluck('file_name', Constant::DB_TABLE_PRIMARY);
                foreach ($data as $primaryId => $_fileName) {

                    if (empty($_fileName)) {
                        TaskService::update($storeId, [Constant::DB_TABLE_PRIMARY => $taskId], ['task_status' => 0]);
                        return false;
                    }
                }

                if (isset($header[Constant::EXPORT_DISTINCT_FIELD])) {
                    unset($header[Constant::EXPORT_DISTINCT_FIELD]);
                }

                $_path = config(Constant::APP . Constant::LINKER . Constant::EXPORT_PATH);
                $realPath = storage_path($_path . $path);

                if (!is_dir($realPath)) {
                    mkdir($realPath, 0777, true);
                }

                $config = ['path' => $realPath];
                $excel = new \Vtiful\Kernel\Excel($config);

                if (empty($fileName)) {
                    $fileName = date('YmdHis') . '_' . mt_rand(1000, 9999) . '_' . $taskId . '.xlsx';
                }

//                $textFile = $excel->fileName($fileName)
//                        ->header(array_keys($header));

                $excelData = [];
                foreach ($data as $primaryId => $_fileName) {

                    $_data = static::parseExcelFile($_fileName);
                    unset($_data[0]);

                    foreach ($_data as $key => $item) {
                        $excelData[] = $item;
                    }
                }

                $fileObject = $excel->constMemory($fileName);
//                $fileHandle = $fileObject->getHandle();
//
//                $format = new \Vtiful\Kernel\Format($fileHandle);
//                $boldStyle = $format->bold()->toResource();
                //$fileObject->setRow('A1', 10, $boldStyle) // 写入数据前设置行样式
                $fileObject->header(array_keys($header))
                        ->data($excelData)
                        ->output();

//                $row = 1;
//                foreach ($data as $_fileName) {
//                    $_data = static::parseExcelFile($_fileName);
//                    unset($_data[0]);
//                    foreach ($_data as $key => $item) {
//                        foreach ($item as $_key => $_value) {
//                            $textFile->insertText($row, $_key, $_value . ''); //
//                        }
//                        $row++;
//                    }
//                }
//                $textFile->output();

                $where = [
                    Constant::DB_TABLE_PRIMARY => $taskId,
                    'task_status' => 1,
                ];
                $host = [
                    'dev' => 'https://devbrand.patozon.net',
                    'test' => 'https://testbrand.patozon.net',
                    'pre-release' => 'https://brandwtest.patozon.net',
                    'production' => 'https://brand.patozon.net',
                ];
                $fileUrl = data_get($host, config('app.env', 'production'), data_get($host, 'production', '')) . $path . '/' . $fileName;
                TaskService::update($storeId, $where, ['task_status' => 2, 'file_name' => $fileUrl, 'row_sum' => count($excelData)]);

                $exportFileName = data_get($parameters, '0.srcParameters.0.export_file_name');
                static::sendEmail($storeId, '导出' . $exportFileName, '请点击链接下载' . $exportFileName . '：<a href="' . $fileUrl . '"  target="_blank" rel="noopener noreferrer">' . $fileUrl . '</a>', $parameters);

                return $fileUrl;
            }
        ];

        $taskId = data_get($parameters, '0.taskId', 0);
        return static::handleLock([md5(__METHOD__), $taskId], $lockParameters);
    }

    /**
     * 给管理员发邮件
     * @param $storeId
     * @param $title
     * @param string $content
     */
    public static function sendEmail($storeId, $title, $content = '', $parameters = []) {

        $to_email = data_get($parameters, '0.srcParameters.0.export_email');

        $validatorData = [
            Constant::TO_EMAIL => $to_email,
        ];
        $rules = [
            Constant::TO_EMAIL => 'required|email',
        ];
        $validator = PublicValidator::handle($validatorData, $rules, Constant::PARAMETER_ARRAY_DEFAULT, 'adminEmail');
        if ($validator !== true) {//如果验证没有通过就提示用户
            return $validator->getData(true);
        }

        $data = [
            Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
            Constant::SUBJECT => $title . '-' . config('app.env', 'production'),
            Constant::DB_TABLE_CONTENT => $content,
        ];
        $message = new PublicMail($data);
        $rs = EmailService::send($to_email, $message, $data);

        if ($rs) {
            $taskId = data_get($parameters, '0.taskId', 0);
            $where = [
                Constant::DB_TABLE_PRIMARY => $taskId,
                'task_status' => 2,
            ];
            TaskService::update($storeId, $where, ['task_status' => 3]);
        }

        return $rs;
    }

    /**
     * 创建excel文件
     * @param array $header excel表头
     * @param \Hyperf\Database\Model\Builder  $query
     * @param int  $count  分页每次获取记录条数
     * @param string $fileName 文件名
     * @param string $path 文件保存文件夹
     * @return string 文件下载地址
     */
    public static function _createExcelByQueue($header, $service, $countMethod, $countParameters, $method, $parameters, $fileName = Constant::PARAMETER_STRING_DEFAULT, $path = '/public/file/download/excel') {

        ini_set('memory_limit', '2048M');

        $data = $service::{$countMethod}(...$countParameters);

        $totalPage = Arr::get($data, 'total_page', 0); //总页数
        $pageSize = Arr::get($data, 'page_size', 100); //每页记录总数
        $total = Arr::get($data, 'total', 0); //总记录条数

        if ($totalPage < 1) {
            return [];
        }
        $zsetKey = 'createVtifulExcel:' . FunctionHelper::randomStr(8);
        $zsetTotalPageKey = 'createVtifulExcel:totalPage:' . FunctionHelper::randomStr(8);

        $allParameter = func_get_args();

        if (isset($allParameter[0][Constant::EXPORT_DISTINCT_FIELD])) {
            unset($allParameter[0][Constant::EXPORT_DISTINCT_FIELD]);
        }
        $header = data_get($allParameter, 0, []);

        array_unshift($allParameter, $zsetKey);
        data_set($allParameter, '3', $zsetTotalPageKey);

        for ($i = 1; $i <= $totalPage; $i++) {
            data_set($allParameter, '6.0.page', $i);
            data_set($allParameter, '6.0.page_size', $pageSize);
            Queue::push(new CreateExcelJob($allParameter));
        }

        $_path = config(Constant::APP . Constant::LINKER . Constant::EXPORT_PATH);
        $realPath = storage_path($_path . $path);

        if (!is_dir($realPath)) {
            mkdir($realPath, 0777, true);
        }

        $config = ['path' => $realPath];
        $excel = new \Vtiful\Kernel\Excel($config);

        if (empty($fileName)) {
            $fileName = date('YmdHis') . '_' . mt_rand(1000, 9999) . '.xlsx';
        }

        $textFile = $excel->fileName($fileName)->header(array_keys($header));
        $count = count($header);
        while (true) {

            $rowData = Redis::RPOP($zsetKey);
            if ($rowData) {
                $rowData = BaseService::getSrcMember($rowData);
                $columnNumber = 0;
                $lineNumber = data_get($rowData, $count, 1);
                unset($rowData[$count]);
                foreach ($rowData as $value) {
                    $textFile->insertText($lineNumber, $columnNumber, ($value . Constant::PARAMETER_STRING_DEFAULT)); //
                    $columnNumber++;
                }
            }

            $taskTotalPage = Redis::zcard($zsetTotalPageKey);
            if ($taskTotalPage >= $totalPage) {
                break;
            }
        }

        while ($rowData = Redis::RPOP($zsetKey)) {
            $rowData = BaseService::getSrcMember($rowData);
            $columnNumber = 0;
            $lineNumber = data_get($rowData, $count, 1);
            unset($rowData[$count]);
            foreach ($rowData as $value) {
                $textFile->insertText($lineNumber, $columnNumber, ($value . Constant::PARAMETER_STRING_DEFAULT)); //
                $columnNumber++;
            }
        }

        $textFile->output();
        BaseService::del($zsetKey);
        BaseService::del($zsetTotalPageKey);

        return $path . '/' . $fileName;

//                $row = 1;
//                foreach ($data as $_fileName) {
//                    $file = fopen($_fileName, "r");
//                    while (!feof($file)) {
//                        $dd = fgetcsv($file);
//                        if ($dd) {
//                            foreach ($dd as $key => $value) {
//                                $textFile->insertText($row, $key, $value . Constant::PARAMETER_STRING_DEFAULT); //
//                            }
//                            $row += 1;
//                        }
//                    }
//                    fclose($file);
//                    unlink($_fileName);
//                }
//
//        if (empty($fileName)) {
//            $fileName = date('YmdHis') . '_' . mt_rand(1000, 9999) . '.csv';
//        }
//        $file = fopen($realPath . '/' . $fileName, 'w+');
//            foreach ($data as $rowData => $row) {
//                $rowData = json_decode($rowData, true);
//                fputcsv($file, $rowData);
//            }
//fclose($file);
    }

    /**
     * 创建excel文件
     * @param array $header excel表头
     * @param \Hyperf\Database\Model\Builder  $query
     * @param int  $count  分页每次获取记录条数
     * @param string $fileName 文件名
     * @param string $path 文件保存文件夹
     * @return string 文件下载地址
     */
    public static function _handleExcel($zsetKey, $header, $service, $zsetTotalPageKey, $countParameters, $method, $parameters, $fileName = Constant::PARAMETER_STRING_DEFAULT, $path = '/public/file/download/excel') {

        try {

            $_header = array_values($header);
            $data = $service::{$method}(...$parameters);

            $pageSize = Arr::get($parameters, '0.page_size', 100); //每页记录总数
            $page = Arr::get($parameters, '0.page', 1); //当前页码
            $offset = ($page - 1) * $pageSize;

            $offset = $offset + 1;
            $data = Arr::get($data, 'data', []);
            foreach ($data as $item) {
                //Redis::zadd($zsetKey, $offset, BaseService::getZsetMember($item));
                $_item = [];
                foreach ($_header as $fieldName) {
                    $_item[] = data_get($item, $fieldName, Constant::PARAMETER_STRING_DEFAULT);
                }
                $_item[] = $offset;

                //data_set($item, 'excel_line_number', $offset);
                Redis::LPUSH($zsetKey, BaseService::getZsetMember($_item));

                $offset = $offset + 1;
            }

            $ttl = 10 * 60;
            Redis::expire($zsetKey, $ttl);

            Redis::zadd($zsetTotalPageKey, $page, $page);
            Redis::expire($zsetTotalPageKey, $ttl);
        } catch (\Exception $exc) {
            LogService::addSystemLog('error', 'CreateExcel_job', 'handleExcel', '生成excel出错', ['data' => func_get_args(), 'exc' => $exc->getTraceAsString()]); //添加系统日志
        }

        return true;
    }

}
