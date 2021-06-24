<?php

namespace App\Utils;

use App\Processes\CustomProcess;
use App\Utils\Services\QueueService;
use Carbon\Carbon;
use App\Services\ResponseLogService;
use Hyperf\Utils\Arr;
use App\Constants\Constant;
use Hyperf\Utils\Context;

class Response {

    /**
     * 获取统一响应数据结构
     * @param array $data 响应数据
     * @param boolean $isNeedDataKey
     * @param int $status
     * @param array $headers
     * @param int $options
     * @return array 统一响应数据结构
     */
    public static function getResponseData($data = [], $isNeedDataKey = true, $status = 200, array $headers = [], $options = 0) {
        return [
            data_get($data, Constant::RESPONSE_DATA_KEY, Constant::PARAMETER_ARRAY_DEFAULT),
            data_get($data, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_INT_DEFAULT),
            data_get($data, Constant::RESPONSE_MSG_KEY, Constant::PARAMETER_STRING_DEFAULT),
            $isNeedDataKey,
            $status,
            $headers,
            $options,
        ];
    }

    /**
     * 获取  图片 资源地址
     * @param String $imgUrl
     * @param int $is_https 1：强制用https 0:不强制用https
     * @return \Illuminate\Http\JsonResponse
     */
    public static function json($data = [], $code = 1, $msg = 'ok', $isNeedDataKey = true, $status = 200, array $headers = [], $options = 0) {

        $request = app('request');
        $storeId = $request->input('store_id', 0);
        $actId = $request->input('act_id', 0);

        $result = [
            Constant::RESPONSE_EXE_TIME => Constant::PARAMETER_INT_DEFAULT,
            Constant::RESPONSE_CODE_KEY => $code,
            Constant::RESPONSE_MSG_KEY => static::getResponseMsg($storeId, $code, $msg),
            //'cpu_num' => swoole_cpu_num(),
        ];

        if ($isNeedDataKey) {
            $result[Constant::RESPONSE_DATA_KEY] = $data;
        } else {
            $result = array_merge($result, $data);
        }

        $serverParams = Context::get(\Psr\Http\Message\ServerRequestInterface::class)->getServerParams();

        $result[Constant::RESPONSE_EXE_TIME] = (number_format(microtime(true) - data_get($serverParams,'request_time_float', 0), 8, '.', '') * 1000) . ' ms';

        try {

            $requestData = $request->all();

            data_set($requestData, 'responseData', $result);
            data_set($requestData, 'responseData.status', $status);
            data_set($requestData, 'responseData.headers', $headers);
            data_set($requestData, 'responseData.options', $options);

            FunctionHelper::setTimezone($storeId);

            $action = data_get($requestData, 'account_action', '');
            $fromUrl = data_get($requestData, Constant::CLIENT_ACCESS_URL, '');
            $account = data_get($requestData, Constant::DB_TABLE_ACCOUNT, data_get($requestData, 'help_account', data_get($requestData, 'operator', '')));
            $cookies = data_get($requestData, 'account_cookies', '').'';
            $ip = FunctionHelper::getClientIP(data_get($requestData, Constant::DB_TABLE_IP, null));
            $serverParams = $request->getServerParams();
            $uri = data_get($serverParams,'request_uri');//$request->getRequestUri();
            $apiUrl = $uri;
            $createdAt = data_get($requestData, 'created_at', Carbon::now()->toDateTimeString());
            $extId = data_get($requestData, 'id', 0);
            $extType = data_get($requestData, 'ext_type', '');

            $parameters = [$action, $storeId, $actId, $fromUrl, $account, $cookies, $ip, $apiUrl, $createdAt, $extId, $extType, $requestData];

            $queueConnection = config('app.log_queue');
            $extData = [
                'queueConnectionName' => $queueConnection,//Queue Connection
                //'queue' => config('async_queue.' . $queueConnection . '.channel'),//Queue Name
                //'delay' => 1,//任务延迟执行时间  单位：秒
            ];

            $logTaskData = FunctionHelper::getJobData(ResponseLogService::getNamespaceClass(), 'addResponseLog', $parameters, null, $extData);
            $taskData = [
                $logTaskData,
            ];

            //QueueService::pushQueue($taskData);
            $processData = FunctionHelper::getJobData(QueueService::class, 'pushQueue', [$taskData], []);//
            CustomProcess::write($processData);

        } catch (\Exception $exc) {
            //echo $exc->getTraceAsString();
        }

        return response('http_response', $status, $headers)->json($result);
    }

    /**
     * 获取默认的响应数据结构
     * @param int $code 响应状态码
     * @param string $msg 响应提示
     * @param array $data 响应数据
     * @return array $data
     */
    public static function getDefaultResponseData($code = Constant::PARAMETER_INT_DEFAULT, $msg = null, $data = Constant::PARAMETER_ARRAY_DEFAULT) {
        return [
            Constant::RESPONSE_CODE_KEY => $code,
            Constant::RESPONSE_MSG_KEY => $msg,
            Constant::RESPONSE_DATA_KEY => $data,
        ];
    }

    /**
     * 获取响应提示
     * @param int $storeId 品牌商店id
     * @param int $code 响应状态码
     * @param string $msg 响应提示 默认：使用系统提示
     * @return string 响应提示
     */
    public static function getResponseMsg($storeId, $code, $msg = null) {

        if (!empty($msg)) {
            return $msg;
        }

        $field = PublicValidator::getAttributeName($storeId, $code);
        $validatorData = [
            $field => '',
        ];
        $rules = [
            $field => ['api_code_msg'],
        ];

        $validator = getValidatorFactory()->make($validatorData, $rules);
        $errors = $validator->errors();
        foreach ($rules as $key => $value) {
            if ($errors->has($key)) {
                return $errors->first($key);
            }
        }

        return '';
    }

}
