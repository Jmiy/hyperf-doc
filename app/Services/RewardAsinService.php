<?php

namespace App\Services;

use App\Constants\Constant;

class RewardAsinService extends BaseService {

    /**
     * 添加asin
     * @param int $storeId 官网id
     * @param int $rewardId 礼品id
     * @param string $name 礼品名称
     * @param int $businessType 业务类型
     * @param array $records asin数据
     * @param array $requestData 请求参数
     * @return bool
     */
    public static function addAsins($storeId, $rewardId, $name, $businessType, $records, $requestData) {
        if (empty($records)) {
            return false;
        }

        foreach ($records as $record) {
            $where = [
                Constant::DB_TABLE_EXT_ID => $rewardId,
                Constant::DB_TABLE_EXT_TYPE => RewardService::getModelAlias(),
                Constant::DB_TABLE_ASIN => $record[Constant::DB_TABLE_ASIN],
                Constant::DB_TABLE_COUNTRY => $record[Constant::DB_TABLE_COUNTRY],
            ];
            $data = [
                Constant::DB_TABLE_NAME => $name,
                Constant::DB_TABLE_TYPE => data_get($requestData, Constant::DB_TABLE_TYPE, Constant::PARAMETER_INT_DEFAULT),
                Constant::DB_TABLE_TYPE_VALUE => data_get($requestData, Constant::DB_TABLE_TYPE_VALUE, Constant::PARAMETER_STRING_DEFAULT),
                Constant::PRODUCT_TYPE => data_get($requestData, Constant::PRODUCT_TYPE, Constant::PARAMETER_INT_DEFAULT),
            ];

            $dbRet = static::updateOrCreate($storeId, $where, $data);
            if (data_get($dbRet, Constant::DB_OPERATION) == Constant::DB_OPERATION_DEFAULT) {
                return false;
            }
        }

        return true;
    }

    /**
     * 逻辑删除asin
     * @param int $storeId
     * @param int $extId 礼品id
     * @param string $extType
     */
    public static function deleteAsins($storeId, $extId, $extType = 'Reward') {
        $where = [
            Constant::DB_TABLE_EXT_ID => $extId,
            Constant::DB_TABLE_EXT_TYPE => $extType,
        ];
        static::delete($storeId, $where);
    }

}
