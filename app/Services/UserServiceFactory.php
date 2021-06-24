<?php

declare(strict_types=1);

namespace App\Services;

use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

class UserServiceFactory
{
    // 实现一个 __invoke() 方法来完成对象的生产，方法参数会自动注入一个当前的容器实例
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get(ConfigInterface::class);
        // 我们假设对应的配置的 key 为 cache.enable
        $enableCache = $config->get('cache.enable', false);
        // make(string $name, array $parameters = []) 方法等同于 new ，使用 make() 方法是为了允许 AOP 的介入，而直接 new 会导致 AOP 无法正常介入流程
        return make(UserService::class, compact('enableCache'));
        //return make(UserService::class, ['enableCache'=>$enableCache]);
        //return make(UserService::class, [$enableCache]);
    }
}
