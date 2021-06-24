<?php

namespace Torann\GeoIP;

use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Hyperf\Utils\ApplicationContext;
use Illuminate\Cache\CacheManager;

class GeoIPServiceProvider
{
    // 实现一个 __invoke() 方法来完成对象的生产，方法参数会自动注入一个当前的容器实例
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get(ConfigInterface::class);

        $manager = ApplicationContext::getContainer()->get(CacheManager::class);

        return make(GeoIP::class, ['config' => $config->get('geoip', []), 'cache' => $manager]);
    }
}
