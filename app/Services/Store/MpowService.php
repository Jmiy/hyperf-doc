<?php

namespace App\Services\Store;

use App\Utils\Curl;
use Carbon\Carbon;

class MpowService {

    public static function request($url, $requestData = [], $requestMethod = 'POST') {

        $headers = array(
            'Content-Type: application/json; charset=utf-8', //设置请求内容为 json  这个时候post数据必须是json串 否则请求参数会解析失败
        );

        $curlOptions = [
            CURLOPT_CONNECTTIMEOUT_MS => 1000 * 100,
            CURLOPT_TIMEOUT_MS => 1000 * 100,
        ];

        $responseText = Curl::request($url, $headers, $curlOptions, json_encode($requestData), $requestMethod);

        return $responseText;
    }

    /**
     * 同步mpow会员
     * @param $params
     * @return array|mixed
     */
    public static function syncCustomer($storeId = 1, $createdAtMin = '', $createdAtMax = '', $limit = 1000, $source = 5) {

        $timeDiff = 7 * 60 * 60;
        $requestData = [
            'start_time' => Carbon::createFromTimestamp(strtotime($createdAtMin) + $timeDiff)->toDateTimeString(),
            'end_time' => Carbon::createFromTimestamp(strtotime($createdAtMax) + $timeDiff)->toDateTimeString(),
            'limit' => $limit,
        ];

        $url = config('app.sync.1.syncCustomerUrl');
        $data = static::request($url, $requestData);

        if (empty($data['responseText'])) {
            return [];
        }

        return static::getCustomerData($data['responseText'], $source);
    }

    /**
     * 获取统一的会员数据
     * @param array $data mpow的会员数据
     * @return array
     */
    public static function getCustomerData($data, $source = 5) {
        $result = [];
        foreach ($data as $k => $row) {
            $result[$k] = [
                'store_id' => 1,
                'store_customer_id' => $row['customer_id'] ?? 0,
                'account' => $row['email'] ?? '',
                'first_name' => $row['first_name'] ?? '',
                'last_name' => $row['last_name'] ?? '',
                'currency' => $row['currency'] ?? '',
                'gender' => $row['gender'] ?? 0,
                'brithday' => $row['dob'] ?? '',
                'country' => $row['country'] ?? '',
                'phone' => $row['phone'] ?? '',
                'source' => $source, //注册方式：1自然注册，2常规活动，3大型活动,4非官方页面 5:后台同步
            ];

            if (isset($row['created_at']) && $row['created_at']) {
                $result[$k]['ctime'] = Carbon::parse($row['created_at'])->toDateTimeString();
            }

            $address = [];
            if (isset($row['country']) && isset($row['city']) && isset($row['region'])) {
                $address = [
                    'country' => $row['country'] ?? '',
                    'type' => 'home',
                    'city' => $row['city'] ?? '',
                    'region' => $row['region'] ?? '',
                    'street' => $row['street'],
                ];
            }
            $result[$k]['address'] = $address;
        }
        return $result;
    }

    public static function getCustomer($params) {
        return false;
    }

}
