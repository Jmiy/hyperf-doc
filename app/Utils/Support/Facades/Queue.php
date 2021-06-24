<?php

namespace App\Utils\Support\Facades;

use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\AsyncQueue\Driver\DriverInterface;
use Hyperf\Utils\ApplicationContext;

class Queue {

    /**
     * @param null|string $queue 消息队列配置名称 默认：null(使用默认消息队列：default)
     * @return
     */
    public static function connection($queue = null): DriverInterface
    {
        //var_dump(__METHOD__,$queue);
        return ApplicationContext::getContainer()->get(DriverFactory::class)->get($queue ?? 'default');
    }

    /**
     * 生产消息.
     * @param $params 数据
     * @param int $delay 延时时间 单位秒
     * @param $queue 消息队列配置名称 默认：null(使用默认消息队列：default)
     * @return bool
     */
    /**
     * @param object|string $job job对象|类
     * @param null|array $data job类 参数
     * @param int $delay 延时执行时间 (单位：秒)
     * @param null|string $connection 消息队列配置名称 默认：null(使用默认消息队列：default)
     * @param null|string $queue 队列名 默认取 $connection 对应的配置的队列名 暂时不支持动态修改
     * @return bool
     */
    public static function push($job, $data = null, int $delay = 0, $connection = null, $queue = null): bool
    {

        if (!is_object($job)) {
            $job = make($job, $data);
        }
        return static::connection($connection)->push($job, $delay);
    }
}
