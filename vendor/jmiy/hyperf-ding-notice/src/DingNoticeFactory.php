<?php

namespace DingNotice;

use Psr\Container\ContainerInterface;
use Hyperf\Contract\ConfigInterface;

class DingNoticeFactory
{
    // 实现一个 __invoke() 方法来完成对象的生产，方法参数会自动注入一个当前的容器实例
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get(ConfigInterface::class);

        return make(DingTalk::class, [
            'config' => $config->get('ding', [])
        ]);
    }

}
