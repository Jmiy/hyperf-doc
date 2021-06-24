<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.(用于管理 AOP 切面)
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    //内存泄漏检测工具
    //Hyperf\SwooleTracker\Aspect\CoroutineHandlerAspect::class,
    Hyperf\Tracer\Aspect\JsonRpcAspect::class,//rpc 调用链追踪
];
