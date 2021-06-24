<?php

namespace App\Services\Store\Amazon\Customers;

use App\Services\Store\Amazon\BaseService;
use Carbon\Carbon;
use Hyperf\Utils\Arr;
use App\Constants\Constant;

class Customer extends BaseService {

    /**
     * 获取统一的会员数据
     * @param array $data shopify会员数据
     * @param int $storeId 商店id
     * @param int $source 会员来源
     * @return array 统一的会员数据
     */
    public static function getCustomerData($data, $storeId = 2, $source = 5) {
        $result = [];
        foreach ($data as $k => $row) {

            if (empty($row)) {
                continue;
            }

            $result[$k] = [
                'store_id' => $storeId,
                'store_customer_id' => $row['id'] ?? 0,
                'account' => $row['email'] ?? '',
                'first_name' => $row['first_name'] ?? '',
                'last_name' => $row['last_name'] ?? '',
                'currency' => $row['currency'] ?? '',
                'phone' => $row['phone'] ?? '',
                'address' => [],
                'source' => $source,
                'accepts_marketing' => data_get($row, 'accepts_marketing', 0) ? 1 : 0, //是否订阅 true：订阅  false：不订阅
                'accepts_marketing_updated_at' => Carbon::parse($row['accepts_marketing_updated_at'])->toDateTimeString(), //订阅时间
                'state' => data_get($row, 'state', 'disabled'), //账号状态 disabled/invited/enabled/declined
                'status' => 1, //账号状态 disabled/invited/enabled/declined
                'platformData' => $row,
                Constant::DB_TABLE_IP => data_get($row, Constant::DB_TABLE_IP, ''),
                Constant::DB_TABLE_COUNTRY => data_get($row, Constant::DB_TABLE_COUNTRY, ''),
            ];

            if (isset($row['created_at']) && $row['created_at']) {//注册时间
                $createdAt = Carbon::parse($row['created_at'])->toDateTimeString();
                data_set($result, $k . '.ctime', $createdAt);
                data_set($result, $k . '.platform_created_at', $createdAt);
            }

            if (isset($row['updated_at']) && $row['updated_at']) {//用户信息更新时间
                $updatedAt = Carbon::parse($row['updated_at'])->toDateTimeString();
                data_set($result, $k . '.mtime', $updatedAt);
                data_set($result, $k . '.lastlogin', $updatedAt);
                data_set($result, $k . '.platform_updated_at', $updatedAt);
            }

            $defaultAddress = data_get($row, 'default_address', []);
            if ($defaultAddress) {

                if (empty($result[$k][Constant::DB_TABLE_COUNTRY]) && $defaultAddress['country_code']) {
                    $result[$k][Constant::DB_TABLE_COUNTRY] = $defaultAddress['country_code'];
                }

                if (empty($result[$k]['phone'])) {
                    $result[$k]['phone'] = $defaultAddress['phone'] ?? $result[$k]['phone'];
                }

                $result[$k]['address'] = [
                    'type' => 'home',
                    'city' => $defaultAddress['city'] ?? '',
                    'region' => $defaultAddress['province_code'] ?? '',
                    'street' => $defaultAddress['address1'] . $defaultAddress['address2'],
                    'addr' => json_encode($defaultAddress),
                    'addresses' => json_encode(data_get($row, 'addresses', [])),
                ];
            }
        }

        return $result;
    }

    /**
     * 获取会员数据 https://help.shopify.com/en/api/reference/customers/customer#index-2019-07
     * @param int $storeId 商城id
     * @param string $createdAtMin 最小创建时间
     * @param string $createdAtMax 最大创建时间
     * @param array $ids shopify会员id
     * @param string $sinceId shopify会员id
     * @param int $limit 记录条数
     * @param int $source 会员来源
     * @param array $extData 扩展数据
     * @return array
     */
    public static function getCustomer($storeId = 2, $createdAtMin = '', $createdAtMax = '', $ids = [], $sinceId = '', $limit = 250, $source = 5, $extData = []) {

        static::setConf($storeId);

        return [];
    }

}
