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
return [
    // Etcd Client
    'uri' => 'http://192.168.152.128:2379',
    'version' => 'v3beta',//beta
    'options' => [
        'timeout' => 10,
    ],
];
