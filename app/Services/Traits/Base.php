<?php

/**
 * Base trait
 * User: Jmiy
 * Date: 2020-09-03
 * Time: 09:27
 */

namespace App\Services\Traits;

use App\Services\LogService;
use App\Constants\Constant;
use App\Utils\Context;
use App\Utils\Curl;
use App\Utils\FunctionHelper;
use Hyperf\Utils\Arr;

trait Base
{

    /**
     * 获取类名
     * @return string
     */
    public static function getCustomClassName()
    {
        $class = explode('\\', get_called_class());
        $trans = array("Service" => "");
        return strtr(end($class), $trans);
    }

    /**
     * 获取当前类的绝对路径
     * @return string
     */
    public static function getNamespaceClass()
    {
        return implode('', ['\\', static::class]);
    }

    /**
     * 获取服务提供者
     * @param string $platform
     * @param string|array $serviceProvider
     * @return string
     */
    public static function getServiceProvider($platform = '', $serviceProvider = [])
    {

        $class = explode('\\', get_called_class());
        $trans = [
            '\\' . end($class) => ""
        ];

        $serviceData = Arr::collapse([[strtr(static::getNamespaceClass(), $trans), $platform], (is_array($serviceProvider) ? $serviceProvider : [$serviceProvider])]);
        $serviceData = array_filter($serviceData);

        return implode('\\', array_filter($serviceData));
    }

    /**
     * 执行服务
     * @param string $platform 平台
     * @param string|array $serviceProvider 服务提供者
     * @param string $method 执行方法
     * @param array $parameters 参数
     * @return mixed|null
     */
    public static function managerHandle($platform = '', $serviceProvider = '', $method = '', $parameters = [])
    {
        $service = static::getServiceProvider($platform, $serviceProvider);
        if (!($service && $method && method_exists($service, $method))) {
            return null;
        }

        return $service::{$method}(...$parameters);
    }

    /**
     * 请求服务
     * @param $url
     * @param array $requestData 请求参数
     * @param string $username 账号
     * @param string $password 密码
     * @param string $requestMethod 请求方式
     * @param array $headers 请求头
     * @param array $extData 扩展参数
     * @return array|string 响应数据
     */
    public static function requestService($url, $requestData = [], $username = '', $password = '', $requestMethod = 'POST', $headers = [], $extData = [])
    {

        $headers = $headers ? $headers : [
            'Content-Type: application/json; charset=utf-8', //设置请求内容为 json  这个时候post数据必须是json串 否则请求参数会解析失败
        ];

        $curlOptions = [
            CURLOPT_CONNECTTIMEOUT_MS => 1000 * data_get($extData, 'requestConnectTimeout', 100),
            CURLOPT_TIMEOUT_MS => 1000 * data_get($extData, 'requestTimeout', 100),
            CURLOPT_USERNAME => $username, //设置账号
            CURLOPT_PASSWORD => $password, //设置密码
        ];

        $responseData = Curl::request($url, $headers, $curlOptions, $requestData, $requestMethod);

        if (data_get($responseData, 'responseText', false) === false) {//如果请求失败，就重复请求一次
            $responseData = Curl::request($url, $headers, $curlOptions, $requestData, $requestMethod);
        }

        $level = data_get($extData, 'logLevel', 'log');
        $type = data_get($extData, 'logType', Constant::PLATFORM_SERVICE_LOCALHOST);
        $subtype = data_get($extData, 'logSubtype', get_called_class()); //子类型
        $keyinfo = data_get($extData, 'keyInfo', data_get($responseData, 'curlInfo.total_time', ''));
        $content = $requestData;
        $subkeyinfo = $url;
        $dataKey = data_get($extData, 'dataKey', null);

        if (data_get($responseData, Constant::RESPONSE_TEXT, false) === false) {//如果接口请求异常，就记录异常日志
            $level = 'exception';
            static::logs($level, $type, $subtype, $keyinfo, $content, $subkeyinfo, $responseData);
        }

        static::logs($level, $type, $subtype, $keyinfo, $content, $subkeyinfo, $responseData, $dataKey);

        return $responseData;
    }

    /**
     * 使用消息队列异步记录日志
     * @param string $level 日志级别
     * @param string $type 日志类型
     * @param string $subtype 日志子类型
     * @param string $keyinfo 日志简要描述
     * @param array $content 日志内容
     * @param string $subkeyinfo 子日志简要描述
     * @param array $extData 日志扩展参数
     * @param null $dataKey 数据key
     * @return bool|mixed
     */
    public static function logs($level = '', $type = Constant::PLATFORM_SHOPIFY, $subtype = '', $keyinfo = '', $content = [], $subkeyinfo = '', $extData = [], $dataKey = null)
    {
        $service = LogService::getNamespaceClass();
        $method = 'addSystemLog'; //记录请求日志
        $parameters = [$level, $type, $subtype, $keyinfo, $content, $subkeyinfo, $extData];

        if ($dataKey === null) {
            return FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters));
        }

        $data = data_get($extData, Constant::RESPONSE_TEXT . Constant::LINKER . $dataKey, []);
        if (empty($data)) {
            return FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters));
        }

        foreach ($data as $item) {
            $parameters = [$level, $type, $subtype, $keyinfo, $content, $subkeyinfo, $item];
            FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters));
        }

        return true;
    }

    /**
     * 强制转换为字符串
     * @param mix $value
     * @return string $value
     */
    public static function castToString($value) {
        return (string) $value;
    }

    /**
     * @param int $storeId
     * @return mixed|null
     */
    public static function getServiceEnv($storeId = 2)
    {
        $request = app('request');
        $appEnv = $request->input('app_env', null); //开发环境 $request->route('app_env', null)
        return $appEnv === null ? $storeId : ($appEnv . '_' . $storeId);
    }

}
