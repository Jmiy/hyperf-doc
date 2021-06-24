<?php

declare(strict_types=1);

/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

use Hyperf\Contract\ConfigInterface;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Psr\Http\Message\ServerRequestInterface;
use Hyperf\Contract\TranslatorInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;

if (!function_exists('app')) {
    /**
     * Get the available container instance.
     *
     * @param string|null $make
     * @param array $parameters
     * @return mixed|\Psr\Container\ContainerInterface
     */
    function app($make = null, array $parameters = [])
    {
        if (is_null($make)) {
            if (ApplicationContext::hasContainer()) {
                return ApplicationContext::getContainer();
            }
            return null;
        }

        if (empty($parameters)) {
            $container = ApplicationContext::getContainer();
            //var_dump($make, $container->has($make));
            if ($container->has($make)) {
                return $container->get($make);
            }

            $config = $container->get(ConfigInterface::class);
            $_make = $config->get('dependencies.' . $make);
            //var_dump($_make, $container->has($_make));

            if ($container->has($_make)) {
                return $container->get($_make);
            }

            return make($_make, $parameters);
        }

        return make($make, $parameters);


    }
}

if (!function_exists('encrypt')) {
    /**
     * Encrypt the given value.
     *
     * @param string $value
     * @return string
     */
    function encrypt($value)
    {
        return app('encrypter')->encrypt($value);
    }
}

if (!function_exists('decrypt')) {
    /**
     * Decrypt the given value.
     *
     * @param string $value
     * @return string
     */
    function decrypt($value)
    {
        return app('encrypter')->decrypt($value);
    }
}

if (!function_exists('response')) {
    /**
     * Return a new response from the application.
     *
     * @param string $server
     * @param int $status
     * @param array $headers
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    function response($server = 'http_response', $status = 200, array $headers = [])
    {
        $response = app($server)->withStatus($status);

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}

if (!function_exists('getConfig')) {
    /**
     * Return a config object.
     *
     * @return config object
     */
    function getConfig()
    {
        //通过应用容器 获取配置类对象
        return ApplicationContext::getContainer()->get(ConfigInterface::class);
    }
}

if (!function_exists('isValidIp')) {
    /**
     * Checks if the ip is valid.
     *
     * @param string $ip
     *
     * @return bool
     */
    function isValidIp($ip = null)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE)
        ) {
            return false;
        }

        return true;
    }
}

if (!function_exists('getClientIP')) {
    /**
     * Get the client IP address.
     *
     * @return client IP address
     */
    function getClientIP($ip = null)
    {
        if (!empty($ip)) {
            return $ip;
        }

        if (! Context::has('http.request.ipData')) {
            $remotes_keys = [
                'HTTP_X_FORWARDED_FOR',
                'HTTP_CLIENT_IP',
                'HTTP_X_FORWARDED',
                'HTTP_FORWARDED_FOR',
                'HTTP_FORWARDED',
                'REMOTE_ADDR',
                'HTTP_X_CLUSTER_CLIENT_IP',
                'x_forwarded_for',
                'client_ip',
                'x_forwarded',
                'forwarded_for',
                'forwarded',
                'addr',
                'x_cluster_client_ip',
                'x-forwarded-for',
                'client-ip',
                'x-forwarded',
                'forwarded-for',
                'remote-addr',
                'x-cluster-client-ip',
            ];

            $clientIP = '127.0.0.0';
            $requestHeaders = Context::get(ServerRequestInterface::class)->getHeaders();
            //var_dump(__METHOD__, $requestHeaders);
            foreach ($remotes_keys as $key) {
                $address = data_get($requestHeaders, strtolower($key));
                if (empty($address)) {
                    continue;
                }

                $address = is_array($address) ? $address : [$address];

                foreach ($address as $_address) {
                    $ipData = explode(',', $_address);
                    foreach ($ipData as $clientIP) {
                        if (isValidIp($clientIP)) {
                            return $clientIP;
                        }
                    }
                }
            }


            return Context::set('http.request.ipData', $clientIP);
        }

        return Context::get('http.request.ipData');

    }
}

if (!function_exists('getTranslator')) {
    /**
     * Return a config object.
     *
     * @return config object
     */
    function getTranslator()
    {
        //通过应用容器 获取配置类对象
        return ApplicationContext::getContainer()->get(TranslatorInterface::class);
    }
}

if (!function_exists('getValidatorFactory')) {
    /**
     * Return a config object.
     *
     * @return config object
     */
    function getValidatorFactory()
    {
        //通过应用容器 获取配置类对象
        return app(ValidatorFactoryInterface::class);
    }
}


