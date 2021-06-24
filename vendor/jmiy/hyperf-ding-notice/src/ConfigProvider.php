<?php

namespace DingNotice;

use DingNotice\Contracts\FactoryInterface;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                FactoryInterface::class  => DingNoticeFactory::class,
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
                    'description' => 'The config for ding.',
                    'source' => __DIR__ . '/../publish/ding.php',
                    'destination' => BASE_PATH . '/config/autoload/ding.php',
                ],
            ],
        ];
    }

}
