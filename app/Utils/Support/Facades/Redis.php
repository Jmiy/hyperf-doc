<?php

namespace App\Utils\Support\Facades;

use Hyperf\Utils\ApplicationContext;
use Hyperf\Redis\RedisFactory;

class Redis {

    /**
     * 获取redis连接池
     * @param string $poolName
     * @return \Hyperf\Redis\RedisProxy
     */
    public static function getRedis(string $poolName = 'default') {
        $container = ApplicationContext::getContainer();
        $redisFactory = $container->get(RedisFactory::class);
        return $redisFactory->get($poolName);
    }

    /**
     * Handle dynamic, static calls to the object.
     *
     * @param  string  $method
     * @param  array   $args
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public static function __callStatic($method, $args) {
        return static::getRedis()->{$method}(...$args);
    }

}
