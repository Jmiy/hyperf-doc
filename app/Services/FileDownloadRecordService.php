<?php

/**
 * Created by Patazon.
 * @desc   :
 * @author : Roy_qiu
 * @email  : Roy_qiu@patazon.net
 * @date   : 2021/1/13 14:34
 */

namespace App\Services;

use App\Constants\Constant;
use App\Utils\Response;

class FileDownloadRecordService extends BaseService {

    /**
     * @param $storeId
     * @param $fileOriginName
     * @param $fileUrl
     * @return array
     */
    public static function add($storeId, $fileOriginName, $fileUrl) {
        $rs = Response::getDefaultResponseData(1);
        $where = [
            'file_url' => $fileUrl,
        ];

        $fileRecord = FileUploadRecordService::existsOrFirst($storeId, '', $where, true, [Constant::DB_TABLE_PRIMARY]);
        $fileId = data_get($fileRecord, Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
        if (empty($fileId)) {
            return $rs;
        }

        $data = [
            'file_id' => $fileId,
        ];
        return static::getModel($storeId)->insert($data);
    }
}
