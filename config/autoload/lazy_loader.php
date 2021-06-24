<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.(用于管理 DI 的延迟加载配置)
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    /**
     * 格式为：代理类名 => 原类名
     * 代理类此时是不存在的，Hyperf会在runtime文件夹下自动生成该类。
     * 代理类类名和命名空间可以自由定义。
     */
    //'App\Services\LazyUserService' => \App\Services\UserServiceInterface::class,
    //'UserServiceInterface1' => 'UserServiceInterface1',
];
