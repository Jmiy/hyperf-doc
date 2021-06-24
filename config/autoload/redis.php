<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.(用于管理 Redis 客户端)
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    'default' => [
        'host' => env('REDIS_HOST', 'localhost'),
        'auth' => env('REDIS_AUTH', null),
        'port' => (int) env('REDIS_PORT', 6379),
        'db' => (int) env('REDIS_DB', 0),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('REDIS_MAX_IDLE_TIME', 60),
        ],
    ],
    'cache' => [
        'host' => env('REDIS_HOST', 'localhost'),
        'auth' => env('REDIS_AUTH', null),
        'port' => (int) env('REDIS_PORT', 6379),
        'db' => (int) env('REDIS_CACHE_DB', 0),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('REDIS_MAX_IDLE_TIME', 60),
        ],
    ],
    env('DB_MODEL_CACHE_POOL', 'default') => [//db model queue
        'host' => env('DB_MODEL_CACHE_POOL_HOST', 'localhost'),
        'auth' => env('DB_MODEL_CACHE_POOL_AUTH', null),
        'port' => (int) env('DB_MODEL_CACHE_POOL_PORT', 6379),
        'db' => (int) env('DB_MODEL_CACHE_POOL_DB', 0),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('DB_MODEL_CACHE_POOL_MAX_IDLE_TIME', 60),
        ],
    ],
    env('DEFAULT_QUEUE_DRIVER_REDIS_POOL', 'default_queue') => [//default queue
        'host' => env('DEFAULT_QUEUE_DRIVER_REDIS_POOL_HOST', 'localhost'),
        'auth' => env('DEFAULT_QUEUE_DRIVER_REDIS_POOL_AUTH', null),
        'port' => (int) env('DEFAULT_QUEUE_DRIVER_REDIS_POOL_PORT', 6379),
        'db' => (int) env('DEFAULT_QUEUE_DRIVER_REDIS_POOL_DB', 0),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('DEFAULT_QUEUE_DRIVER_REDIS_POOL_MAX_IDLE_TIME', 60),
        ],
    ],
    env('LOG_QUEUE_DRIVER_REDIS_POOL','log_queue') => [//log queue
        'host' => env('LOG_QUEUE_DRIVER_REDIS_POOL_HOST', 'localhost'),
        'auth' => env('LOG_QUEUE_DRIVER_REDIS_POOL_AUTH', null),
        'port' => (int) env('LOG_QUEUE_DRIVER_REDIS_POOL_PORT', 6379),
        'db' => (int) env('LOG_QUEUE_DRIVER_REDIS_POOL_DB', 0),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('LOG_QUEUE_DRIVER_REDIS_POOL_MAX_IDLE_TIME', 60),
        ],
    ],
    env('MAIL_QUEUE_DRIVER_REDIS_POOL','mail_queue') => [//mail queue
        'host' => env('MAIL_QUEUE_DRIVER_REDIS_POOL_HOST', 'localhost'),
        'auth' => env('MAIL_QUEUE_DRIVER_REDIS_POOL_AUTH', null),
        'port' => (int) env('MAIL_QUEUE_DRIVER_REDIS_POOL_PORT', 6379),
        'db' => (int) env('MAIL_QUEUE_DRIVER_REDIS_POOL_DB', 0),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('LOG_QUEUE_DRIVER_REDIS_POOL_MAX_IDLE_TIME', 60),
        ],
    ],
];
