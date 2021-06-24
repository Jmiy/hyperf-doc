<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.(用于管理异常处理器)
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    'handler' => [
        'http' => [
            Hyperf\HttpServer\Exception\Handler\HttpExceptionHandler::class,
            App\Exception\Handler\AppExceptionHandler::class,//应用异常处理程序
            App\Exception\Handler\FooExceptionHandler::class,//自定义异常处理程序
            Hyperf\Validation\ValidationExceptionHandler::class,//验证异常
        ],
    ],
];
