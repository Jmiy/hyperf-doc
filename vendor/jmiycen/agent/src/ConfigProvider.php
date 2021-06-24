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
namespace Jenssegers\Agent;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                'agent' => AgentServiceProvider::class,
            ],
            'commands' => [
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for agent.',
                    'source' => __DIR__ . '/../publish/agent.php',
                    'destination' => BASE_PATH . '/config/autoload/agent.php',
                ],
            ],
        ];
    }
}
