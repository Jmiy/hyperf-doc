<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.(用于管理 Server 服务)
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
use Hyperf\Server\Server;
use Hyperf\Server\Event;

return [
    'mode' => SWOOLE_PROCESS,
    'servers' => [
        [
            'name' => 'http',
            'type' => Server::SERVER_HTTP,
            'host' => '0.0.0.0',
            'port' => 9501,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                Event::ON_REQUEST => [Hyperf\HttpServer\Server::class, 'onRequest'],
            ],
        ],
        [//TCP Server (适配 jsonrpc 协议)
            'name' => 'jsonrpc',
            'type' => Server::SERVER_BASE,
            'host' => '0.0.0.0',
            'port' => 9503,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                Event::ON_RECEIVE => [\Hyperf\JsonRpc\TcpServer::class, 'onReceive'],
            ],
            'settings' => [
                'open_eof_split' => true,
                'package_eof' => "\r\n",
                'package_max_length' => 1024 * 1024 * 2,
            ],
        ],
        [//HTTP Server (适配 jsonrpc-http 协议)
            'name' => 'jsonrpc-http',
            'type' => Server::SERVER_HTTP,
            'host' => '0.0.0.0',
            'port' => 9504,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                Event::ON_REQUEST => [\Hyperf\JsonRpc\HttpServer::class, 'onRequest'],
            ],
        ],
    ],
    'settings' => [
        'enable_coroutine' => true,// 开启内置协程
        'worker_num' => swoole_cpu_num(),// 设置启动的 Worker 进程数
        'pid_file' => BASE_PATH . '/runtime/hyperf.pid',// master 进程的 PID
        'open_tcp_nodelay' => true,// TCP 连接发送数据时会关闭 Nagle 合并算法，立即发往客户端连接
        'max_coroutine' => 100000,// 设置当前工作进程最大协程数量
        'open_http2_protocol' => true,// 启用 HTTP2 协议解析
        'max_request' => 100000,// 设置 worker 进程的最大任务数
        'socket_buffer_size' => 2 * 1024 * 1024,// 配置客户端连接的缓存区长度
        'buffer_output_size' => 2 * 1024 * 1024,
        // Task Worker 数量，根据您的服务器配置而配置适当的数量
        'task_worker_num' => 1,
        // 因为 `Task` 主要处理无法协程化的方法，所以这里推荐设为 `false`，避免协程下出现数据混淆的情况
        'task_enable_coroutine' => true,
        'max_wait_time' => 3,//设置 Worker 进程收到停止服务通知后最大等待时间【默认值：3】 https://wiki.swoole.com/#/server/setting?id=max_wait_time
        'document_root' => BASE_PATH . '/public',
        'enable_static_handler' => true,
    ],
    'callbacks' => [
        Event::ON_WORKER_START => [Hyperf\Framework\Bootstrap\WorkerStartCallback::class, 'onWorkerStart'],
        Event::ON_PIPE_MESSAGE => [Hyperf\Framework\Bootstrap\PipeMessageCallback::class, 'onPipeMessage'],
        Event::ON_WORKER_EXIT => [Hyperf\Framework\Bootstrap\WorkerExitCallback::class, 'onWorkerExit'],
        // Task callbacks
        Event::ON_TASK => [Hyperf\Framework\Bootstrap\TaskCallback::class, 'onTask'],
        Event::ON_FINISH => [Hyperf\Framework\Bootstrap\FinishCallback::class, 'onFinish'],
    ],
];
