<?php

namespace App\Services\Monitor;

use Hyperf\Utils\Arr;

class MonitorServiceManager {

    /**
     * 获取服务
     * @param string $platform 平台
     * @param string $service  服务
     * @return string 服务
     */
    public static function getService($platform, $service) {

        $serviceData = Arr::collapse([[__NAMESPACE__, $platform], (is_array($service) ? $service : [$service])]);
        $serviceData = array_filter($serviceData);

        return implode('\\', array_filter($serviceData));
    }

    /**
     * 执行服务
     * @param string $platform 平台
     * @param string $serviceName 服务名
     * @param string $method  执行方法
     * @param array $parameters 参数
     * @return boolean|max
     */
    public static function handle($platform, $serviceName, $method, $parameters) {

        switch ($serviceName) {
            case 'Ding':
                $_service = 'Dings';
                break;

            default:
                $_service = '';
                $serviceName = 'BaseService';
                break;
        }

        $service = static::getService($platform, [$_service, $serviceName]);
        if (!($service && $method && method_exists($service, $method))) {
            return null;
        }

        return $service::{$method}(...$parameters);
    }

}
