<?php

namespace App\Services\Psc\Patozon;

use App\Services\BaseService as AppBaseService;
use App\Constants\Constant;

class BaseService extends AppBaseService
{

    public static function getApiUrl($url)
    {

        $env = config('app.env', 'production');
        return config('services.psc.' . $env, config('services.psc.production')) . $url;
    }

    public static function request($url, $requestData = [], $username = '', $password = '', $requestMethod = 'POST', $headers = [], $extData = [])
    {

        $headers = $headers ? $headers : [
            'Content-Type: application/json; charset=utf-8', //设置请求内容为 json  这个时候post数据必须是json串 否则请求参数会解析失败
            'Authorization: Bearer ' . $password,
        ];

        data_set($extData, 'logType', Constant::PLATFORM_SERVICE_PATOZON, false);

        return static::requestService($url, $requestData, $username, $password, $requestMethod, $headers, $extData);
    }

}
