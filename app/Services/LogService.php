<?php

/**
 * log服务
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services;

use Hyperf\Utils\Str;
use Hyperf\Utils\Arr;
use Carbon\Carbon;
use App\Services\Traits\GetDefaultConnectionModel;

class LogService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'AccessLog';
    }

    /**
     * 获取请求参数数据结构
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
     * @return array 请求参数数据结构
     */
    public static function getAccessLogStructure($action = '', $storeId = 0, $actId = 0, $fromUrl = '', $account = '', $cookies = '', $ip = '', $apiUrl = '', $createdAt = '', $extId = 0, $extType = '', $requestData = []) {
        return Arr::collapse([[
                'action' => $action,
                'store_id' => intval($storeId),
                'act_id' => $actId,
                'from_url' => Str::substr($fromUrl, 0, 255),
                'account' => $account ? $account : '',
                'cookies' => $cookies ? $cookies : '',
                'ip' => Str::substr($ip, 0, 100),
                'api_url' => Str::substr($apiUrl, 0, 100),
                'request_data' => is_array($requestData) ? json_encode($requestData, JSON_UNESCAPED_UNICODE) : $requestData,
                'created_at' => $createdAt ? $createdAt : Carbon::now()->toDateTimeString(),
                'ext_type' => $extType,
                'ext_id' => intval($extId),
                'orderno' => data_get($requestData, 'orderno', ''), //订单号
                'request_mark' => data_get($requestData, 'request_mark', ''), //请求唯一标识
                    ], data_get($requestData, 'clientData', [])]);
    }

    /**
     * 添加访问流水
     * @param string $action 用户行为
     * @param int|string $storeId  商城id
     * @param int|string $actId    活动id
     * @param string $fromUrl 来源地址
     * @param string $account 账号
     * @param string $cookies cookies
     * @param string $ip  ip
     * @param string $apiUrl  接口地址
     * @param string $createdAt 创建时间
     * @param int|string $extId  关联id
     * @param string $extType 关联模型
     * @param array $requestData 请求参数
     * @return boolean true:成功  false:失败
     */
    public static function addAccessLog($action = '', $storeId = 0, $actId = 0, $fromUrl = '', $account = '', $cookies = '', $ip = '', $apiUrl = '', $createdAt = '', $extId = 0, $extType = '', $requestData = []) {

        $data = static::getAccessLogStructure($action, $storeId, $actId, $fromUrl, $account, $cookies, $ip, $apiUrl, $createdAt, $extId, $extType, $requestData);

        return static::getModel($storeId, '')->insert($data);
    }

    /**
     * 检查会员是否存在
     * @param int $storeId
     * @param int $customerId
     * @param string $account
     * @param int $storeCustomerId
     * @return bool
     */
    public static function existsSystemLog($storeId = 0, $where = [], $getData = false) {

        if (empty($where)) {
            return $getData ? [] : true;
        }

        $query = static::getModel($storeId, '', [], 'SystemLog')->buildWhere($where);
        if ($getData) {
            $rs = $query->first();
        } else {
            $rs = $query->exists();
        }

        return $rs;
    }

    /**
     * 系统日志记录
     * @param string $level 日志等级
     * @param string $type 日志类型
     * @param string $subtype 日志子类型
     * @param string $keyinfo 关键信息
     * @param string $content 日志详情|json存储
     * @param string $subkeyinfo 副关键信息
     * @param array $extData 扩展数据
     * @return int|null 日志id
     */
    public static function addSystemLog($level = 'info', $type = '', $subtype = '', $keyinfo = '', $content = '', $subkeyinfo = '', $extData = []) {

        $data = [
            'level' => Str::substr($level, 0, 20), //日志级别 error,info,fatalerror,excetion
            'type' => Str::substr($type, 0, 100), //日志类型
            'subtype' => Str::substr($subtype, 0, 60), //日志子类型
            'keyinfo' => Str::substr($keyinfo, 0, 80), //关键信息
            'content' => is_array($content) ? json_encode($content, JSON_UNESCAPED_UNICODE) : $content, //日志详情|json存储
            'subkeyinfo' => Str::substr($subkeyinfo, 0, 200), //副关键信息
            'created_at' => Carbon::now()->toDateTimeString(), //错误产生时间
            'ext_data' => is_array($extData) ? json_encode($extData, JSON_UNESCAPED_UNICODE) : $extData,
        ];

        $storeId = data_get($extData, 'storeId', 0);
        return static::getModel($storeId, '', [], 'SystemLog')->insertGetId($data);
    }

}
