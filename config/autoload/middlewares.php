<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.(用于管理中间件)
 * https://hyperf.wiki/2.0/#/zh-cn/middleware/middleware
 * 中间件的执行顺序: 总共有 3 种级别的中间件，分别为 全局中间件、类级别中间件、方法级别中间件，如果都定义了这些中间件，执行顺序为：全局中间件 -> 类级别中间件 -> 方法级别中间件
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    // http 对应 config/autoload/server.php 内每个 server 的 name 属性对应的值，该配置仅应用在该 Server 中
    'http' => [
        // 数组内配置您的全局中间件，顺序根据该数组的顺序
        //Hyperf\SwooleTracker\Middleware\HttpServerMiddleware::class,//内存泄漏检测工具
        //Hyperf\SwooleTracker\Middleware\HookMallocMiddleware::class,
        App\Middleware\CorsMiddleware::class,//跨域中间件
        App\Middleware\RequestMiddleware::class,//请求中间件
        App\Middleware\Translator\LangMiddleware::class,//国际化中间件
        Hyperf\Validation\Middleware\ValidationMiddleware::class,//验证器中间件

        \Hyperf\Tracer\Middleware\TraceMiddleware::class,//调用链追踪 https://hyperf.wiki/2.1/#/zh-cn/tracer

        //\Hyperf\Metric\Middleware\MetricMiddleware::class,//服务监控 https://hyperf.wiki/2.1/#/zh-cn/metric
    ],

    'jsonrpc-http' => [
        \Hyperf\Tracer\Middleware\TraceMiddleware::class,//调用链追踪 https://hyperf.wiki/2.1/#/zh-cn/tracer
    ],

    'jsonrpc' => [
        \Hyperf\Tracer\Middleware\TraceMiddleware::class,//调用链追踪 https://hyperf.wiki/2.1/#/zh-cn/tracer
    ],
];
