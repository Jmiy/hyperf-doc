<?php

namespace Illuminate\Mail;

use Psr\Container\ContainerInterface;
use Hyperf\Contract\ConfigInterface;

/**
 * @mixin \Illuminate\Mail\Markdown
 */
class MarkdownFactory
{
    // 实现一个 __invoke() 方法来完成对象的生产，方法参数会自动注入一个当前的容器实例
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get(ConfigInterface::class);

        return make(Markdown::class, [
            'options' => [
                'theme' => $config->get('mail.markdown.theme', 'default'),
                'paths' => $config->get('mail.markdown.paths', []),
            ]
        ]);
    }
}
