<?php

namespace App\Services\Store\Amazon\Erp;

use App\Services\BaseService as AppBaseService;
use App\Utils\Curl;
use App\Utils\FunctionHelper;
use App\Constants\Constant;

class BaseService extends AppBaseService {

    protected static $apiKey; //apiKey
    protected static $password; //api密码
    protected static $storeUrl; //api url
    protected static $storeId; //商城id

    /**
     * 设置配置信息
     * @param int $storeId 商城id
     * @param array $configData 配置数据
     */

    public static function setConf($storeId = 1, $configData = []) {

        $storeId = static::castToString($storeId);

        //设置时区
        FunctionHelper::setTimezone($storeId);

        $config = $configData ? $configData : config('app.patozon_app');

        static::$storeUrl = data_get($config, 'baseUrl', '');
        static::$apiKey = data_get($config, 'username', '');
        static::$password = data_get($config, 'secretKey', '');
        static::$storeId = $storeId;

        return true;
    }

    /**
     * 获得签名
     * @author Jason
     * @param $path
     * @param $requestTime
     * @param $postFields
     * @param $nonce
     * @return bool|string
     */
    public static function createSignature($path, $requestTime, $postFields, $nonce) {

        // 检查组成参数
        if (empty($path) || !is_string($path) || empty($requestTime) || empty($postFields) || !is_string($postFields) || empty($nonce) || !is_string($nonce)) {
            return false;
        }

        // 将参与签名的所有参数用'&'连接起来
        $baseString = implode('&', array(
            $path,
            static::$apiKey,
            static::$password,
            $requestTime,
            $postFields,
            $nonce,
        ));

        // 用md5加密算法生成长度为32位的签名
        return md5($baseString);
    }

    public static function request($url, $requestData = [], $username = '', $password = '', $requestMethod = 'POST', $headers = [], $extData = []) {

        $parts = parse_url($url);
        $path = $parts['path']; // 请求路径
        $time = time(); // 当前时间，作为请求时间，5分钟后超时
        $requestData = json_encode($requestData); // 请求参数用JSON格式封装
        $nonce = substr(md5($time . uniqid()), 0, 16); // 生成随机字符串
        // 生成签名
        $signature = static::createSignature($path, $time, $requestData, $nonce);

        if (empty($signature)) {
            return [];
        }

        $headers = $headers ? $headers : [
            'Content-Type: application/json; charset=utf-8', //设置请求内容为 json  这个时候post数据必须是json串 否则请求参数会解析失败
            'X-Orion-Username: ' . static::$apiKey,
            'X-Orion-Signature: ' . $signature,
            'X-Orion-Request-Time: ' . $time,
            'X-Orion-Nonce: ' . $nonce,
        ];

        $curlOptions = [
            CURLOPT_CONNECTTIMEOUT_MS => 1000 * 100,
            CURLOPT_TIMEOUT_MS => 1000 * 100,
            CURLOPT_USERNAME => $username ? $username : static::$apiKey, //设置账号
            CURLOPT_PASSWORD => $password ? $password : static::$password, //设置密码
        ];

        $responseData = Curl::request($url, $headers, $curlOptions, $requestData, $requestMethod);

        if (data_get($responseData, 'responseText', false) === false) {//如果请求失败，就重复请求一次
            $responseData = Curl::request($url, $headers, $curlOptions, $requestData, $requestMethod);
        }

        $class = explode('\\', get_called_class());

        $level = data_get($extData, 'logLevel', 'log');
        $type = data_get($extData, 'logType', Constant::PLATFORM_SHOPIFY);
        $subtype = data_get($extData, 'logSubtype', end($class)); //子类型
        $keyinfo = data_get($extData, 'keyInfo', static::$storeId);
        $content = $requestData;
        $subkeyinfo = $url;
        $dataKey = data_get($extData, 'dataKey', null);

        if (data_get($responseData, 'responseText', false) === false) {//如果接口请求异常，就记录异常日志
            $level = 'exception';
            static::logs($level, $type, $subtype, $keyinfo, $content, $subkeyinfo, $responseData);
        }

        static::logs($level, $type, $subtype, $keyinfo, $content, $subkeyinfo, $responseData, $dataKey);

        return $responseData;
    }

}
