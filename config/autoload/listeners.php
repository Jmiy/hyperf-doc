<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.(用于管理事件监听者)
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    \Hyperf\ExceptionHandler\Listener\ErrorExceptionHandler::class,//error_reporting() 错误级别的监听器: https://hyperf.wiki/2.0/#/zh-cn/exception-handler?id=error-%e7%9b%91%e5%90%ac%e5%99%a8

    Hyperf\AsyncQueue\Listener\QueueLengthListener::class,//记录队列长度的监听器，默认不开启，您如果需要，可以自行添加到 listeners 配置中。
    Hyperf\AsyncQueue\Listener\ReloadChannelListener::class,//当消息执行超时，或项目重启导致消息执行被中断，最终都会被移动到 timeout 队列中，只要您可以保证消息执行的原子性（同一个消息执行一次，或执行多次，最终表现一致）， 就可以开启以下监听器，框架会自动将 timeout 队列中消息移动到 waiting 队列中，等待下次消费。

    //Hyperf\DbConnection\Listener\InitTableCollectorListener::class,//模型缓存 使用默认值:https://hyperf.wiki/2.0/#/zh-cn/db/model-cache?id=%e4%bd%bf%e7%94%a8%e9%bb%98%e8%ae%a4%e5%80%bc
    Hyperf\ModelCache\Listener\EagerLoadListener::class,//模型关系缓存
];
