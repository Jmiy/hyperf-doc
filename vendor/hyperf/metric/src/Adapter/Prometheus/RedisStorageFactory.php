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
namespace Hyperf\Metric\Adapter\Prometheus;

use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

class RedisStorageFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $redis = $container->get(\Redis::class);
        $appName = $container->get(ConfigInterface::class)->get('app_name', 'skeleton');
        Redis::setPrefix($appName);
        return Redis::fromExistingConnection($redis);
    }
}
