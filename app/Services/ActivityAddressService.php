<?php

/**
 * 活动地址服务
 * User: Jmiy
 * Date: 2019-10-30
 * Time: 14:18
 */

namespace App\Services;
use App\Constants\Constant;

class ActivityAddressService extends BaseService {

    /**
     * 检查是否存在
     * @param int $id 角色id
     * @param bool $getData 是否获取数据 true:是 false:否 默认:false
     * @return mixed|static
     */
    public static function exists($storeId = 0, $country = '', $where = [], $getData = false) {

        if (empty($where)) {
            return $getData ? [] : true;
        }

        $query = static::getModel($storeId, $country)->buildWhere($where);

        if ($getData) {
            $rs = $query->first();
        } else {
            $rs = $query->count();
        }

        return $rs;
    }

    /**
     * 添加记录
     * @param int $storeId 商城id
     * @param array $where where条件
     * @param array $data  数据
     * @return int
     */
    public static function insert($storeId, $where, $data) {
        return static::updateOrCreate($storeId, $where, $data);
    }

    /**
     * 添加记录
     * @param int $storeId 商城id
     * @param array $country 国家
     * @param array $data  数据
     * @return int
     */
    public static function add($storeId, $actId, $customerId, $account, $activityWinningId, $requestData) {

        $extType = data_get($requestData, Constant::DB_TABLE_EXT_TYPE, ''); //申请id 或者 中奖id
        switch ($extType) {
            case 'apply':
                $extType = ActivityApplyService::getModelAlias();

                break;

            default:
                $extType = ActivityWinningService::getModelAlias();
                break;
        }

        $where = [
            'activity_winning_id' => $activityWinningId,
            Constant::DB_TABLE_EXT_TYPE => $extType,
        ];

        $data = [
            'store_id' => $storeId,
            'act_id' => $actId,
            'account' => $account,
            'customer_id' => $customerId,
            Constant::DB_TABLE_COUNTRY => data_get($requestData, Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT),
            'full_name' => data_get($requestData, 'full_name', ''),
            'street' => data_get($requestData, 'street', ''),
            'apartment' => data_get($requestData, 'apartment', ''),
            'city' => data_get($requestData, 'city', ''),
            'state' => data_get($requestData, 'state', ''),
            'zip_code' => data_get($requestData, 'zip_code', ''),
            'phone' => data_get($requestData, 'phone', ''),
            'account_link' => data_get($requestData, 'account_link', ''),
            Constant::DB_TABLE_EXT_TYPE => $extType,
            'ext_id' => $activityWinningId,
        ];

        return static::updateOrCreate($storeId, $where, $data);
    }

}
