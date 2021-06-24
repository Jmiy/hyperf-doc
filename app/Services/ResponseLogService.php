<?php

/**
 * log服务
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services;

use Hyperf\Utils\Arr;
use App\Services\Traits\GetDefaultConnectionModel;

class ResponseLogService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 添加响应流水
     * @param string $action 用户行为
     * @param int $storeId  商城id
     * @param int $actId    活动id
     * @param string $fromUrl 来源地址
     * @param string $account 账号
     * @param string $cookies cookies
     * @param string $ip  ip
     * @param string $apiUrl  接口地址
     * @param string $createdAt 创建时间
     * @param int $extId  关联id
     * @param string $extType 关联模型
     * @param array $requestData 请求参数
     * @return boolean true:成功  false:失败
     */
    public static function addResponseLog($action = '', $storeId = 0, $actId = 0, $fromUrl = '', $account = '', $cookies = '', $ip = '', $apiUrl = '', $createdAt = '', $extId = 0, $extType = '', $requestData = []) {

        $responseData = data_get($requestData, 'responseData.data', []);
        $responseHeaders = data_get($requestData, 'responseData.headers', []);
        $logStructureData = LogService::getAccessLogStructure($action, $storeId, $actId, $fromUrl, $account, $cookies, $ip, $apiUrl, $createdAt, $extId, $extType, $requestData);
        $data = Arr::collapse([$logStructureData, [
                        'code' => data_get($requestData, 'responseData.code', 0), //响应状态码
                        'msg' => data_get($requestData, 'responseData.msg', ''), //响应提示
                        'response_data' => is_array($responseData) ? json_encode($responseData, JSON_UNESCAPED_UNICODE) : $responseData, //响应数据
                        'response_status_code' => data_get($requestData, 'responseData.status', 200), //http响应状态码 默认：200
                        'exe_time' => intval(data_get($requestData, 'responseData.exeTime', 0)), //响应时间
                        'response_headers' => is_array($responseHeaders) ? json_encode($responseHeaders, JSON_UNESCAPED_UNICODE) : $responseHeaders, //响应头
                        'response_options' => data_get($requestData, 'responseData.options', ''), //响应options
        ]]);

        return static::getModel($storeId, '', [])->insert($data);
    }

}
