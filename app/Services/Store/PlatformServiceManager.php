<?php

namespace App\Services\Store;

//use Exception;
//use App\Services\LogService;
use Hyperf\Utils\Arr;

class PlatformServiceManager {

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

        $_service = '';
        switch ($serviceName) {
            case 'Customer':
                $_service = 'Customers';
                break;

            case 'Fulfillment':
                $_service = 'Fulfillments';
                break;

            case 'Page':
            case 'Theme':
            case 'Asset':
                $_service = 'OnlineStore';
                break;

            case 'Order':
            case 'Refund':
            case 'Transaction':
                $_service = 'Orders';

                break;

            case 'Product':
                $_service = 'Products';
                break;

            case 'Metafield':
                $_service = 'Metafield';
                break;

            case 'Base':
                $_service = '';
                $serviceName = 'BaseService';
                break;

            default:
                break;
        }

        $service = static::getService($platform, (is_array($serviceName) ? $serviceName : [$_service, $serviceName]));
        if (!($service && $method && method_exists($service, $method))) {
            return null;
        }

//        try {
//
//        } catch (Exception $exc) {
//            $parameters = [
//                'parameters' => $parameters,
//                'exc' => ExceptionHandler::getMessage($exc),
//            ];
//            LogService::addSystemLog('error', $service, $method, 'PlatformServiceManager--执行失败', $parameters); //添加系统日志
//        }

        return $service::{$method}(...$parameters);
    }

}
