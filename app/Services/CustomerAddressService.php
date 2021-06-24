<?php

/**
 * 会员收件地址服务
 * User: Jmiy
 * Date: 2019-11-13
 * Time: 16:50
 */

namespace App\Services;

use App\Services\Traits\GetDefaultConnectionModel;
use Carbon\Carbon;
use App\Constants\Constant;
use Hyperf\DbConnection\Db as DB;

class CustomerAddressService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 编辑地址
     * @param int $storeId
     * @param int $customerId
     * @param array $data
     * @return boolean
     */
    public static function edit($storeId, $customerId, $data) {

        if (!empty($data[Constant::DB_TABLE_ADDRESS]) && is_array($data[Constant::DB_TABLE_ADDRESS])) {
            $data = array_merge($data, $data[Constant::DB_TABLE_ADDRESS]);
        }

        $address = [];
        if (isset($data[Constant::DB_TABLE_COUNTRY])) {
            $address[Constant::DB_TABLE_COUNTRY] = $data[Constant::DB_TABLE_COUNTRY] ?? '';
        }

        if (isset($data[Constant::DB_TABLE_REGION])) {
            $address[Constant::DB_TABLE_REGION] = $data[Constant::DB_TABLE_REGION] ?? '';
        }

        if (isset($data[Constant::DB_TABLE_CITY])) {
            $address[Constant::DB_TABLE_CITY] = $data[Constant::DB_TABLE_CITY] ?? '';
        }

        if (isset($data[Constant::DB_TABLE_STREET])) {
            $address[Constant::DB_TABLE_STREET] = $data[Constant::DB_TABLE_STREET] ?? '';
        }

        if (isset($data[Constant::DB_TABLE_ADDR])) {
            $address[Constant::DB_TABLE_ADDR] = $data[Constant::DB_TABLE_ADDR] ?? '';
        }

        if (empty($address)) {
            return true;
        }

        $nowTime = Carbon::now()->toDateTimeString();

        $createdAt = data_get($data, Constant::DB_TABLE_CREATED_AT, '');
        $updatedAt = data_get($data, Constant::DB_TABLE_UPDATED_AT, '');
        if ($createdAt) {
            data_set($address, Constant::DB_TABLE_OLD_CREATED_AT, $createdAt, false);
        }
        if ($updatedAt) {
            data_set($address, Constant::DB_TABLE_OLD_UPDATED_AT, $updatedAt, false);
        }

        $type = $data[Constant::DB_TABLE_TYPE] ?? 'home';
        $store_id = $data[Constant::DB_TABLE_STORE_ID] ?? 0;
        $where = [
            Constant::DB_TABLE_TYPE => $type,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::DB_TABLE_STORE_ID => $store_id,
        ];

        $source = data_get($data, Constant::DB_TABLE_SOURCE, 1);
        if (
                !in_array($storeId, Constant::RULES_NOT_APPLY_STORE) && in_array($source, Constant::RULES_APPLY_SOURCE)
        ) {//如果是定时任务同步的账号并且不是 holife和ikich，就根据shopify 账号状态 state和accepts_marketing 设置 status 的值 
//            if (data_get($data, 'accepts_marketing', 0) == 0 && data_get($data, 'status', 0) == 0) {
//                data_set($address, 'status', 0);
//                data_set($address, 'deleted_at', $nowTime);
//            }
            $address = [];
            $replacePairs = ["'" => "\'"];
            if (isset($data[Constant::DB_TABLE_COUNTRY])) {
                $country = strtr(($data[Constant::DB_TABLE_COUNTRY] ?? ''), $replacePairs);
                $address[Constant::DB_TABLE_COUNTRY] = DB::raw("IF(" . Constant::DB_TABLE_COUNTRY . "='', '$country', " . Constant::DB_TABLE_COUNTRY . ")");
            }

            if (isset($data[Constant::DB_TABLE_REGION])) {
                $region = strtr(($data[Constant::DB_TABLE_REGION] ?? ''), $replacePairs);
                $address[Constant::DB_TABLE_REGION] = DB::raw("IF(" . Constant::DB_TABLE_REGION . "='', '$region', " . Constant::DB_TABLE_REGION . ")");
            }

            if (isset($data[Constant::DB_TABLE_CITY])) {
                $city = strtr(($data[Constant::DB_TABLE_CITY] ?? ''), $replacePairs);
                $address[Constant::DB_TABLE_CITY] = DB::raw("IF(" . Constant::DB_TABLE_CITY . "='', '$city', " . Constant::DB_TABLE_CITY . ")");
            }

            if (isset($data[Constant::DB_TABLE_STREET])) {
                $street = strtr(($data[Constant::DB_TABLE_STREET] ?? ''), $replacePairs);
                $address[Constant::DB_TABLE_STREET] = DB::raw("IF(" . Constant::DB_TABLE_STREET . "='', '$street', " . Constant::DB_TABLE_STREET . ")");
            }

            if (isset($data[Constant::DB_TABLE_ADDR])) {
                $addr = $data[Constant::DB_TABLE_ADDR] ?? '';
                $address[Constant::DB_TABLE_ADDR] = $addr;
            }

            if (isset($data[Constant::DB_TABLE_ADDRESSES])) {
                $addresses = $data[Constant::DB_TABLE_ADDRESSES] ?? '';
                $address[Constant::DB_TABLE_PLATFORM_ADDRESSES] = $addresses;
            }

            return static::getModel($storeId, '')->withTrashed()->updateOrCreate($where, $address); //updateOrCreate：不可以添加主键id的值  updateOrInsert：可以添加主键id的值
        }

        return static::updateOrCreate($storeId, $where, $address); //updateOrCreate：不可以添加主键id的值  updateOrInsert：可以添加主键id的值
    }

}
