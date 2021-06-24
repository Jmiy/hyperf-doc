<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.(用于管理注解)
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

use Hyperf\Database\Model\Builder;

return [
    'scan' => [
        'paths' => [
            BASE_PATH . '/app',
        ],
        // ignore_annotations 数组内的注解都会被注解扫描器忽略
        'ignore_annotations' => [
            'mixin',
        ],
        'class_map' => [
            // 需要映射的类名 => 类所在的文件地址
            Builder::class => BASE_PATH . '/class_map/Hyperf/Database/Model/Builder.php',
        ],
    ],
];
