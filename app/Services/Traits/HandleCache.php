<?php

/**
 * base trait
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services\Traits;

use App\Utils\Support\Facades\Cache;
use App\Constants\Constant;
use App\Utils\FunctionHelper;
use App\Utils\Response;

trait HandleCache {

    /**
     * 获取要清空的tags
     * @return array
     */
    public static function getClearTags() {
        return [static::getCacheTags()];
    }

    public static function getCacheTags() {
        return 'cacheTags';
    }

    public static function getCacheTtl($key = 'ttl') {
        return config('cache.' . $key, 86400); //认证缓存时间 单位秒
    }

    public static function handleCache($tag = '', $actionData = []) {

        $tags = config('cache.tags.' . $tag, ['{' . $tag . '}']);
        $service = data_get($actionData, Constant::SERVICE_KEY, '');
        $method = data_get($actionData, Constant::METHOD_KEY, '');
        $parameters = data_get($actionData, Constant::PARAMETERS_KEY, []);

        $instance = Cache::tags($tags)->{$method}(...$parameters);

        $serialHandle = data_get($actionData, Constant::SERIAL_HANDLE, []);
        if ($serialHandle) {
//            foreach ($serialHandle as $handleData) {
//                $service = data_get($handleData, 'service', '');
//                $method = data_get($handleData, 'method', '');
//                $parameters = data_get($handleData, 'parameters', []);
//                $instance = $instance->{$method}(...$parameters);
//            }

            foreach ($serialHandle as $handleData) {
                $instance = tap($instance, function (&$instance) use($handleData) {
                    $service = data_get($handleData, Constant::SERVICE_KEY, '');
                    $method = data_get($handleData, Constant::METHOD_KEY, '');
                    $parameters = data_get($handleData, Constant::PARAMETERS_KEY, []);
                    $instance = $instance->{$method}(...$parameters);
                });
            }
        }

        return $instance;
    }

    /**
     * 清空缓存
     */
    public static function clear() {

        $tags = static::getClearTags();
        $rs = false;
        foreach ($tags as $tag) {
            $handleCacheData = [
                Constant::SERVICE_KEY => static::getNamespaceClass(),
                Constant::METHOD_KEY => 'flush',
                Constant::PARAMETERS_KEY => [],
            ];
            $rs = static::handleCache($tag, $handleCacheData);
        }

        return $rs;
    }

    /**
     * 释放分布式锁
     * @param string $cacheKey  key
     * @param string $method    方法
     * @param int $releaseTime 释放边界值 单位秒
     * @return void
     */
    public static function forceReleaseLock($cacheKey, $method = 'forceRelease', $releaseTime = 10) {

        $service = static::getNamespaceClass();
        $tag = static::getCacheTags();

        if ($releaseTime == 0) {
            //释放锁
            $handleCacheData = FunctionHelper::getJobData($service, 'lock', [$cacheKey], null, [
                        Constant::SERIAL_HANDLE => [
                            FunctionHelper::getJobData($service, $method, []),
                        ]
            ]);
            return static::handleCache($tag, $handleCacheData);
        }

        $key = $cacheKey . ':statisticsLock';
        $handleCacheData = FunctionHelper::getJobData($service, 'has', [$key]);
        $has = static::handleCache($tag, $handleCacheData);

        switch ($method) {
            case 'forceRelease'://释放锁
                if ($has) {
                    $handleCacheData = FunctionHelper::getJobData($service, 'get', [$key]);
                    $releaseLockTime = static::handleCache($tag, $handleCacheData);
                    $nowTime = time();
                    if ($nowTime >= $releaseLockTime) {

                        //删除统计
                        $handleCacheData = FunctionHelper::getJobData($service, 'forget', [$key]);
                        static::handleCache($tag, $handleCacheData);

                        //释放锁
                        $handleCacheData = FunctionHelper::getJobData($service, 'lock', [$cacheKey], null, [
                                    Constant::SERIAL_HANDLE => [
                                        FunctionHelper::getJobData($service, $method, []),
                                    ]
                        ]);
                        static::handleCache($tag, $handleCacheData);
                    }
                }

                break;

            case 'statisticsLock'://统计锁
                //increment('key', $amount)
                if (empty($has)) {
                    $time = time() + $releaseTime;
                    $handleCacheData = FunctionHelper::getJobData($service, 'add', [$key, $time]); //, 600
                    static::handleCache($tag, $handleCacheData);
                }
                break;

            default:
                break;
        }

        return true;
    }

    /**
     * 获取缓存时间(单位：秒)
     * @param int|null $ttl 缓存时间(单位：秒)
     * @return int 缓存时间(单位：秒)
     */
    public static function getTtl($ttl = null) {
        return $ttl ? $ttl : config('cache.ttl', 86400);
    }

    /**
     * 使用分布式锁处理
     * @param array $cacheKeyData key
     * @param array $parameters 分布式锁参数
     * @return mix
     */
    public static function handleLock($cacheKeyData, $parameters = []) {
        $tag = static::getCacheTags();
        $cacheKey = implode(':', $cacheKeyData);
        $service = static::getNamespaceClass();
        $handleCacheData = FunctionHelper::getJobData($service, 'lock', [$cacheKey], null, [
                    Constant::SERIAL_HANDLE => [
                        FunctionHelper::getJobData($service, 'get', $parameters),
                    ]
        ]);

        return static::handleCache($tag, $handleCacheData);
    }

}
