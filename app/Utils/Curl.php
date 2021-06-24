<?php

namespace App\Utils;

class Curl {

    /**
     * 获取COOKIE的存储临时文件
     * @return type
     */
    public static function getTemporaryCookieFileName($cookieFile = '.cobe_http_cookie') {
        return $cookieFile ? (sys_get_temp_dir() . '/' . $cookieFile) : tempnam('', 'tmp');
    }

    /**
     * 获取请求来源
     * @return string
     */
    public static function getReferer() {
        if (isset($_SERVER['HTTP_REFERER'])) {
            return $_SERVER['HTTP_REFERER'];
        }
        return '';
    }

    /**
     * 通过 curl 发送请求并且获取相应 结果
     * @param array $curlOptions
     * @param boolean $getCurlInfo  是否获取响应头部信息 true：获取 false：不获取  默认：true
     * @return string
     */
    public static function handle($curlOptions, $getCurlInfo = true) {
        /* 设置CURLOPT_RETURNTRANSFER为true */
        if (!isset($curlOptions[CURLOPT_RETURNTRANSFER]) || $curlOptions[CURLOPT_RETURNTRANSFER] == false) {
            $curlOptions[CURLOPT_RETURNTRANSFER] = true;
        }

        /* 设置CURLOPT_FOLLOWLOCATION为true */
        if (!isset($curlOptions[CURLOPT_FOLLOWLOCATION])) {
            $curlOptions[CURLOPT_FOLLOWLOCATION] = true;
        }

        /* 设置CURLOPT_FOLLOWLOCATION为false */
        if (!isset($curlOptions[CURLOPT_HEADER])) {
            $curlOptions[CURLOPT_HEADER] = false; //获取返回头信息
        }

        /* 设置CURLOPT_SSL_VERIFYPEER为false */
        if (!isset($curlOptions[CURLOPT_SSL_VERIFYPEER])) {
            $curlOptions[CURLOPT_SSL_VERIFYPEER] = false; // 是否对认证证书来源的检查 1：检查 0：不检查
        }

        /* 设置CURLOPT_SSL_VERIFYHOST */
        if (!isset($curlOptions[CURLOPT_SSL_VERIFYHOST])) {
            $curlOptions[CURLOPT_SSL_VERIFYHOST] = false; //从证书中检查SSL加密算法是否存在
        }

        /* 设置CURLOPT_CONNECTTIMEOUT_MS */
        if (!isset($curlOptions[CURLOPT_CONNECTTIMEOUT_MS])) {
            $curlOptions[CURLOPT_CONNECTTIMEOUT_MS] = 1000 * 10; //尝试连接等待的时间，以毫秒为单位。如果设置为0，则无限等待。 	在cURL 7.16.2中被加入。从PHP 5.2.3开始可用。
        }

        /* 设置CURLOPT_TIMEOUT_MS */
        if (!isset($curlOptions[CURLOPT_TIMEOUT_MS])) {
            $curlOptions[CURLOPT_TIMEOUT_MS] = 1000 * 10; //设置cURL允许执行的最长毫秒数。 	在cURL 7.16.2中被加入。从PHP 5.2.3起可使用。
        }

        /* 设置请求头 */
        $headers = [
            "Expect: ",
            'Referer: ' . static::getReferer(),
        ]; //加了这样的请求头以后响应速度快 2 倍以上

        $ip = FunctionHelper::getClientIP();
        $remotesKeys = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'X_FORWARDED_FOR',
            'CLIENT_IP',
            'X_FORWARDED',
            'FORWARDED_FOR',
            'FORWARDED',
            'ADDR',
            'X_CLUSTER_CLIENT_IP',
            'X-FORWARDED-FOR',
            'CLIENT-IP',
            'X-FORWARDED',
            'FORWARDED-FOR',
            'FORWARDED',
            'REMOTE-ADDR',
            'X-CLUSTER-CLIENT-IP',
        ];
        foreach ($remotesKeys as $remotesKey) {
            $_header = implode(': ', [$remotesKey, $ip]);
            $headers[] = $_header;
        }

        if (isset($curlOptions[CURLOPT_HTTPHEADER])) {
            $headers = array_merge($curlOptions[CURLOPT_HTTPHEADER], $headers);
        }
        $curlOptions[CURLOPT_HTTPHEADER] = $headers; //设置HTTP请求头的数组。使用如下的形式的数组进行设置： array('Content-type: text/plain', 'Content-length: 100')

        /* 初始化curl模块 */
        $curl = curl_init();
        /* 设置curl选项 */
        curl_setopt_array($curl, $curlOptions);

        /* 发送请求并获取响应信息 */
        $responseText = '';
        $errmsg = '';
        try {
            $responseText = curl_exec($curl);
            if (($errno = curl_errno($curl)) != CURLM_OK) {
                $errmsg = curl_error($curl);
                $responseText = false;
            }
        } catch (\Exception $e) {
            $responseText = false;
        }

        $responseData = [
            'responseText' => $responseText,
            'errmsg' => $errmsg,
        ];

        $curlInfo = curl_getinfo($curl);
        if ($getCurlInfo) {
            $responseData['curlInfo'] = $curlInfo;
        }

        /* 关闭curl模块 */
        curl_close($curl);
        /* 返回结果 */

        return $responseData;
    }

    /**
     *
     * @param string $url  api接口
     * @param array $requestData 请求报文
     * @param string $username  账号
     * @param string $password  密码
     * @param string $requestMethod 请求方法
     * @return array $responseData 响应报文
     */
    public static function request($url, $headers = [], $curlOptions = [], $requestData = [], $requestMethod = 'POST') {

        $_curlOptions = [
            CURLOPT_URL => $url, //访问URL
            CURLOPT_HTTPHEADER => $headers, //一个用来设置HTTP头字段的数组。使用如下的形式的数组进行设置： array('Content-type: text/plain', 'Content-length: 100')
            CURLOPT_HEADER => false, //获取返回头信息
                //CURLOPT_CUSTOMREQUEST => $requestMethod, //自定义的请求方式
        ];

        $requestMethod = strtoupper($requestMethod);
        switch ($requestMethod) {
            case 'GET':
                $_curlOptions[CURLOPT_URL] .= ($requestData ? ('?' . http_build_query($requestData)) : '');
                break;

            case 'POST':
                $_curlOptions[CURLOPT_POST] = true;
                $_curlOptions[CURLOPT_POSTFIELDS] = $requestData;
                break;

            default:
                $_curlOptions[CURLOPT_CUSTOMREQUEST] = $requestMethod; //自定义的请求方式
                $_curlOptions[CURLOPT_POSTFIELDS] = $requestData;
                break;
        }

        foreach ($_curlOptions as $key => $value) {
            if (!isset($curlOptions[$key])) {
                $curlOptions[$key] = $value;
            }
        }


        /* 获取响应信息并验证结果 */
        $responseData = static::handle($curlOptions, true);

        if (isset($responseData['responseText']) && $responseData['responseText']) {
            $responseData['responseText'] = json_decode($responseData['responseText'], true) ?? $responseData['responseText'];
        }

        return $responseData;
    }

    /**
     * POST 请求封装
     * @param string $url  请求地址
     * @param array $data  POST 参数
     * @param int $timeout 超时时间
     * @param string $method 请求方式 默认post
     * @return boolean|string
     */
    public static function requestPost($url, $data = array(), $timeout = 4, $method = "post") {

        if (!is_array($data) || empty($data)) {
            return false;
        }

        $str = "";
        foreach ($data as $k => $v) {
            $nv = rawurlencode($v);
            if ($nv === null) {
                continue;
            }
            if (empty($str)) {
                $str = $k . "=" . $nv;
            } else {
                $str .= "&" . $k . "=" . $nv;
            }
        }

        $curlOptions = array(
            CURLOPT_URL => $url, //访问URL
            CURLOPT_RETURNTRANSFER => true, //获取结果作为字符串返回
            CURLOPT_FOLLOWLOCATION => true,
            //CURLOPT_HEADER => false, //获取返回头信息
            CURLOPT_POST => true, //发送时带有POST参数
            CURLOPT_POSTFIELDS => $str, //请求的POST参数字符串
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0,
            CURLOPT_TIMEOUT => $timeout, //设置cURL允许执行的最长秒数。
        );

        if ($method == "get") {
            $curlOptions[CURLOPT_URL] = $url . "?" . $str;
            unset($curlOptions[CURLOPT_POSTFIELDS]);
        }

        $result = self::handle($curlOptions, false);

        return $result;
    }

}
