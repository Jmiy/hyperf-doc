<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.(用于管理数据库客户端)
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    'default' => [
        'read' => [
            'host' => [env('DB_HOST_READ', '127.0.0.1')],
        ],
        'write' => [
            'host' => [env('DB_HOST', '127.0.0.1')],
        ],
        'sticky' => true,
        'driver' => env('DB_DRIVER', 'mysql'),
        //'host' => env('DB_HOST', 'localhost'),
        'port' => env('DB_PORT', 3306),
        'database' => env('DB_DATABASE', 'hyperf'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
        'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
        'prefix' => env('DB_PREFIX', ''),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('DB_MAX_IDLE_TIME', 60),
        ],
        'cache' => [
            'handler' => App\Database\ModelCache\Handler\RedisHandler::class,//env('DB_MODEL_CACHE_HANDLER', Hyperf\ModelCache\Handler\RedisHandler::class),
            'cache_key' => '{mc:%s:m:%s}:%s:%s',//缓存key 格式 {mc:数据库连接:m:表名}:主键key:主键id
            'prefix' => 'default',//缓存key 前缀
            'ttl' => 3600 * 24,//数据存在时 缓存时间 单位(s)
            'empty_model_ttl' => 600,//数据不存在时 缓存时间 单位(s)
            'load_script' => true,//是否加载脚本
            'pool' => env('DB_MODEL_CACHE_POOL', 'default'),//redis 连接池
            'increment_limit' => 10,//批量聚合记录限制 null：不可批量聚合  10：可以批量聚合记录总数 默认：null
        ],
        'commands' => [
            'gen:model' => [
                'path' => 'app/Models',
                'force_casts' => true,
                'inheritance' => 'BaseModel',
                'uses' => '',
                'table_mapping' => [],
            ],
        ],
    ],
    'statistical_analysis' => [
        'read' => [
            'host' => [env('LOG_DB_HOST_READ', env('DB_HOST_READ', '127.0.0.1'))],
        ],
        'write' => [
            'host' => [env('LOG_DB_HOST', env('DB_HOST', '127.0.0.1'))],
        ],
        'sticky' => true,
        'driver' => env('LOG_DB_DRIVER', env('DB_DRIVER', 'mysql')),
        'port' => env('LOG_DB_PORT', env('DB_PORT', 3306)),
        'database' => env('LOG_DB_DATABASE', 'ptx_statistical_analysis'),
        'username' => env('LOG_DB_USERNAME', env('DB_USERNAME', 'forge')),
        'password' => env('LOG_DB_PASSWORD', env('DB_PASSWORD', '')),
        'unix_socket' => env('LOG_DB_SOCKET', env('DB_SOCKET', '')),
        'charset' => env('LOG_DB_CHARSET', env('DB_CHARSET', 'utf8mb4')),
        'collation' => env('LOG_DB_COLLATION', env('DB_COLLATION', 'utf8mb4_unicode_ci')),
        'prefix' => env('LOG_DB_PREFIX', env('DB_PREFIX', '')),
        'strict' => env('LOG_DB_STRICT_MODE', env('DB_STRICT_MODE', false)),
        'engine' => env('LOG_DB_ENGINE', env('DB_ENGINE', null)),
        'timezone' => env('LOG_DB_TIMEZONE', env('DB_TIMEZONE', '+00:00')),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('DB_MAX_IDLE_TIME', 60),
        ],
        'cache' => [
            'handler' => App\Database\ModelCache\Handler\RedisHandler::class,//env('DB_MODEL_CACHE_HANDLER', Hyperf\ModelCache\Handler\RedisHandler::class),
            'cache_key' => '{mc:%s:m:%s}:%s:%s',
            'prefix' => 'default',
            'ttl' => 3600 * 24,
            'empty_model_ttl' => 600,
            'load_script' => true,//是否加载脚本
            'pool' => env('DB_MODEL_CACHE_POOL', 'default'),//redis 连接池
        ],
        'commands' => [
            'gen:model' => [
                'path' => 'app/Models',
                'force_casts' => true,
                'inheritance' => 'BaseModel',
                'uses' => '',
                'table_mapping' => [],
            ],
        ],
    ],
    'redman' => [
        'read' => [
            'host' => [env('DB_HOST_READ', '127.0.0.1')],
        ],
        'write' => [
            'host' => [env('DB_HOST', '127.0.0.1')],
        ],
        'sticky' => true,
        'driver' => 'mysql',
        'port' => env('DB_PORT', 3306),
        'database' => 'ptx_redman',
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        'unix_socket' => env('DB_SOCKET', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
        'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
        'prefix' => env('DB_PREFIX', ''),
        'strict' => env('DB_STRICT_MODE', false),
        'engine' => env('DB_ENGINE', null),
        'timezone' => env('DB_TIMEZONE', '+00:00'),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('DB_MAX_IDLE_TIME', 60),
        ],
        'cache' => [
            'handler' => App\Database\ModelCache\Handler\RedisHandler::class,//env('DB_MODEL_CACHE_HANDLER', Hyperf\ModelCache\Handler\RedisHandler::class),
            'cache_key' => '{mc:%s:m:%s}:%s:%s',
            'prefix' => 'default',
            'ttl' => 3600 * 24,
            'empty_model_ttl' => 600,
            'load_script' => true,//是否加载脚本
            'pool' => env('DB_MODEL_CACHE_POOL', 'default'),//redis 连接池
        ],
        'commands' => [
            'gen:model' => [
                'path' => 'app/Models',
                'force_casts' => true,
                'inheritance' => 'BaseModel',
                'uses' => '',
                'table_mapping' => [],
            ],
        ],
    ],
    'survey' => [
        'read' => [
            'host' => [env('DB_HOST_READ', '127.0.0.1')],
        ],
        'write' => [
            'host' => [env('DB_HOST', '127.0.0.1')],
        ],
        'sticky' => true,
        'driver' => 'mysql',
        'port' => env('DB_PORT', 3306),
        'database' => 'ptx_survey',
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        'unix_socket' => env('DB_SOCKET', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
        'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
        'prefix' => env('DB_PREFIX', ''),
        'strict' => env('DB_STRICT_MODE', false),
        'engine' => env('DB_ENGINE', null),
        'timezone' => env('DB_TIMEZONE', '+00:00'),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('DB_MAX_IDLE_TIME', 60),
        ],
        'cache' => [
            'handler' => App\Database\ModelCache\Handler\RedisHandler::class,//env('DB_MODEL_CACHE_HANDLER', Hyperf\ModelCache\Handler\RedisHandler::class),
            'cache_key' => '{mc:%s:m:%s}:%s:%s',
            'prefix' => 'default',
            'ttl' => 3600 * 24,
            'empty_model_ttl' => 600,
            'load_script' => true,//是否加载脚本
            'pool' => env('DB_MODEL_CACHE_POOL', 'default'),//redis 连接池
        ],
        'commands' => [
            'gen:model' => [
                'path' => 'app/Models',
                'force_casts' => true,
                'inheritance' => 'BaseModel',
                'uses' => '',
                'table_mapping' => [],
            ],
        ],
    ],
    'db_permission' => [
        'read' => [
            'host' => [env('DB_HOST_READ', '127.0.0.1')],
        ],
        'write' => [
            'host' => [env('DB_HOST', '127.0.0.1')],
        ],
        'sticky' => true,
        'driver' => 'mysql',
        'port' => env('DB_PORT', 3306),
        'database' => 'ptx_permission',
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        'unix_socket' => env('DB_SOCKET', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
        'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
        'prefix' => env('DB_PREFIX', ''),
        'strict' => env('DB_STRICT_MODE', false),
        'engine' => env('DB_ENGINE', null),
        'timezone' => env('DB_TIMEZONE', '+08:00'),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('DB_MAX_IDLE_TIME', 60),
        ],
        'cache' => [
            'handler' => App\Database\ModelCache\Handler\RedisHandler::class,//env('DB_MODEL_CACHE_HANDLER', Hyperf\ModelCache\Handler\RedisHandler::class),
            'cache_key' => '{mc:%s:m:%s}:%s:%s',
            'prefix' => 'default',
            'ttl' => 3600 * 24,
            'empty_model_ttl' => 600,
            'load_script' => true,//是否加载脚本
            'pool' => env('DB_MODEL_CACHE_POOL', 'default'),//redis 连接池
        ],
        'commands' => [
            'gen:model' => [
                'path' => 'app/Models',
                'force_casts' => true,
                'inheritance' => 'BaseModel',
                'uses' => '',
                'table_mapping' => [],
            ],
        ],
    ],
    'db_order' => [
        'read' => [
            'host' => [env('DB_HOST_READ', '127.0.0.1')],
        ],
        'write' => [
            'host' => [env('DB_HOST', '127.0.0.1')],
        ],
        'sticky' => true,
        'driver' => 'mysql',
        //'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', 3306),
        'database' => 'ptx_order',
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        'unix_socket' => env('DB_SOCKET', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
        'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
        'prefix' => env('DB_PREFIX', ''),
        'strict' => env('DB_STRICT_MODE', false),
        'engine' => env('DB_ENGINE', null),
        'timezone' => env('DB_TIMEZONE', '+00:00'),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('DB_MAX_IDLE_TIME', 60),
        ],
        'cache' => [
            'handler' => App\Database\ModelCache\Handler\RedisHandler::class,//env('DB_MODEL_CACHE_HANDLER', Hyperf\ModelCache\Handler\RedisHandler::class),
            'cache_key' => '{mc:%s:m:%s}:%s:%s',
            'prefix' => 'default',
            'ttl' => 3600 * 24,
            'empty_model_ttl' => 600,
            'load_script' => true,//是否加载脚本
            'pool' => env('DB_MODEL_CACHE_POOL', 'default'),//redis 连接池
        ],
        'commands' => [
            'gen:model' => [
                'path' => 'app/Models',
                'force_casts' => true,
                'inheritance' => 'BaseModel',
                'uses' => '',
                'table_mapping' => [],
            ],
        ],
    ],
    'online_store' => [
        'read' => [
            'host' => [env('DB_HOST_READ', '127.0.0.1')],
        ],
        'write' => [
            'host' => [env('DB_HOST', '127.0.0.1')],
        ],
        'sticky' => true,
        'driver' => 'mysql',
        'port' => env('DB_PORT', 3306),
        'database' => 'ptx_online_store',
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        'unix_socket' => env('DB_SOCKET', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
        'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
        'prefix' => '',
        'strict' => env('DB_STRICT_MODE', false),
        'engine' => env('DB_ENGINE', null),
        'timezone' => env('DB_TIMEZONE', '+00:00'),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('DB_MAX_IDLE_TIME', 60),
        ],
        'cache' => [
            'handler' => App\Database\ModelCache\Handler\RedisHandler::class,//env('DB_MODEL_CACHE_HANDLER', Hyperf\ModelCache\Handler\RedisHandler::class),
            'cache_key' => '{mc:%s:m:%s}:%s:%s',
            'prefix' => 'default',
            'ttl' => 3600 * 24,
            'empty_model_ttl' => 600,
            'load_script' => true,//是否加载脚本
            'pool' => env('DB_MODEL_CACHE_POOL', 'default'),//redis 连接池
        ],
        'commands' => [
            'gen:model' => [
                'path' => 'app/Models',
                'force_casts' => true,
                'inheritance' => 'BaseModel',
                'uses' => '',
                'table_mapping' => [],
            ],
        ],
    ],

    'db_xc' => [//销参产品价格库  239
        'read' => [
            'host' => [env('XC_DB_HOST', '127.0.0.1')],
        ],
        'write' => [
            'host' => [env('XC_DB_HOST', '127.0.0.1')],
        ],
        'sticky' => true,
        'driver' => 'mysql',
        'port' => env('XC_DB_PORT', 3306),
        'database' => env('XC_DB_DATABASE', 'forge'),
        'username' => env('XC_DB_USERNAME', 'forge'),
        'password' => env('XC_DB_PASSWORD', ''),
        'unix_socket' => env('XC_DB_SOCKET', ''),
        'charset' => env('XC_DB_CHARSET', 'utf8mb4'),
        'collation' => env('XC_DB_COLLATION', 'utf8mb4_unicode_ci'),
        'prefix' => env('XC_DB_PREFIX', ''),
        'strict' => env('XC_DB_STRICT_MODE', false),
        'engine' => env('XC_DB_ENGINE', null),
        'timezone' => env('XC_DB_TIMEZONE', '+00:00'),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('DB_MAX_IDLE_TIME', 60),
        ],
        'cache' => [
            'handler' => App\Database\ModelCache\Handler\RedisHandler::class,//env('DB_MODEL_CACHE_HANDLER', Hyperf\ModelCache\Handler\RedisHandler::class),
            'cache_key' => '{mc:%s:m:%s}:%s:%s',
            'prefix' => 'default',
            'ttl' => 3600 * 24,
            'empty_model_ttl' => 600,
            'load_script' => true,//是否加载脚本
            'pool' => env('DB_MODEL_CACHE_POOL', 'default'),//redis 连接池
        ],
        'commands' => [
            'gen:model' => [
                'path' => 'app/Models',
                'force_casts' => true,
                'inheritance' => 'BaseModel',
                'uses' => '',
                'table_mapping' => [],
            ],
        ],
    ],
    'db_xc_order' => [//销参订单库 223
        'read' => [
            'host' => [env('XC_ORDER_DB_HOST', '127.0.0.1')],
        ],
        'write' => [
            'host' => [env('XC_ORDER_DB_HOST', '127.0.0.1')],
        ],
        'sticky' => true,
        'driver' => 'mysql',
        'port' => env('XC_ORDER_DB_PORT', 3306),
        'database' => env('XC_ORDER_DB_DATABASE', 'forge'),
        'username' => env('XC_ORDER_DB_USERNAME', 'forge'),
        'password' => env('XC_ORDER_DB_PASSWORD', ''),
        'unix_socket' => env('XC_ORDER_DB_SOCKET', ''),
        'charset' => env('XC_ORDER_DB_CHARSET', 'utf8mb4'),
        'collation' => env('XC_ORDER_DB_COLLATION', 'utf8mb4_unicode_ci'),
        'prefix' => env('XC_ORDER_DB_PREFIX', ''),
        'strict' => env('XC_ORDER_DB_STRICT_MODE', false),
        'engine' => env('XC_ORDER_DB_ENGINE', null),
        'timezone' => env('XC_ORDER_DB_TIMEZONE', '+00:00'),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('DB_MAX_IDLE_TIME', 60),
        ],
        'cache' => [
            'handler' => App\Database\ModelCache\Handler\RedisHandler::class,//env('DB_MODEL_CACHE_HANDLER', Hyperf\ModelCache\Handler\RedisHandler::class),
            'cache_key' => '{mc:%s:m:%s}:%s:%s',
            'prefix' => 'default',
            'ttl' => 3600 * 24,
            'empty_model_ttl' => 600,
            'load_script' => true,//是否加载脚本
            'pool' => env('DB_MODEL_CACHE_POOL', 'default'),//redis 连接池
        ],
        'commands' => [
            'gen:model' => [
                'path' => 'app/Models',
                'force_casts' => true,
                'inheritance' => 'BaseModel',
                'uses' => '',
                'table_mapping' => [],
            ],
        ],
    ],
    'db_xc_cleanout' => [//销参价格爬虫库 223
        'read' => [
            'host' => [env('XC_CLEANOUT_DB_HOST', '127.0.0.1')],
        ],
        'write' => [
            'host' => [env('XC_CLEANOUT_DB_HOST', '127.0.0.1')],
        ],
        'sticky' => true,
        'driver' => 'mysql',
        'port' => env('XC_CLEANOUT_DB_PORT', 3306),
        'database' => env('XC_CLEANOUT_DB_DATABASE', 'forge'),
        'username' => env('XC_CLEANOUT_DB_USERNAME', 'forge'),
        'password' => env('XC_CLEANOUT_DB_PASSWORD', ''),
        'unix_socket' => env('XC_CLEANOUT_DB_SOCKET', ''),
        'charset' => env('XC_CLEANOUT_DB_CHARSET', 'utf8mb4'),
        'collation' => env('XC_CLEANOUT_DB_COLLATION', 'utf8mb4_unicode_ci'),
        'prefix' => env('XC_CLEANOUT_DB_PREFIX', ''),
        'strict' => env('XC_CLEANOUT_DB_STRICT_MODE', false),
        'engine' => env('XC_CLEANOUT_DB_ENGINE', null),
        'timezone' => env('XC_CLEANOUT_DB_TIMEZONE', '+00:00'),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('DB_MAX_IDLE_TIME', 60),
        ],
        'cache' => [
            'handler' => App\Database\ModelCache\Handler\RedisHandler::class,//env('DB_MODEL_CACHE_HANDLER', Hyperf\ModelCache\Handler\RedisHandler::class),
            'cache_key' => '{mc:%s:m:%s}:%s:%s',
            'prefix' => 'default',
            'ttl' => 3600 * 24,
            'empty_model_ttl' => 600,
            'load_script' => true,//是否加载脚本
            'pool' => env('DB_MODEL_CACHE_POOL', 'default'),//redis 连接池
        ],
        'commands' => [
            'gen:model' => [
                'path' => 'app/Models',
                'force_casts' => true,
                'inheritance' => 'BaseModel',
                'uses' => '',
                'table_mapping' => [],
            ],
        ],
    ],
    'db_xc_single_product' => [//231
        'read' => [
            'host' => [env('XC_SINGLE_PRODUCT_DB_HOST', '127.0.0.1')],
        ],
        'write' => [
            'host' => [env('XC_SINGLE_PRODUCT_DB_HOST', '127.0.0.1')],
        ],
        'sticky' => true,
        'driver' => 'mysql',
        'port' => env('XC_SINGLE_PRODUCT_DB_PORT', 3306),
        'database' => env('XC_SINGLE_PRODUCT_DB_DATABASE', 'forge'),
        'username' => env('XC_SINGLE_PRODUCT_DB_USERNAME', 'forge'),
        'password' => env('XC_SINGLE_PRODUCT_DB_PASSWORD', ''),
        'unix_socket' => env('XC_SINGLE_PRODUCT_DB_SOCKET', ''),
        'charset' => env('XC_SINGLE_PRODUCT_DB_CHARSET', 'utf8mb4'),
        'collation' => env('XC_SINGLE_PRODUCT_DB_COLLATION', 'utf8mb4_unicode_ci'),
        'prefix' => env('XC_SINGLE_PRODUCT_DB_PREFIX', ''),
        'strict' => env('XC_SINGLE_PRODUCT_DB_STRICT_MODE', false),
        'engine' => env('XC_SINGLE_PRODUCT_DB_ENGINE', null),
        'timezone' => env('XC_SINGLE_PRODUCT_DB_TIMEZONE', '+00:00'),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('DB_MAX_IDLE_TIME', 60),
        ],
        'cache' => [
            'handler' => App\Database\ModelCache\Handler\RedisHandler::class,//env('DB_MODEL_CACHE_HANDLER', Hyperf\ModelCache\Handler\RedisHandler::class),
            'cache_key' => '{mc:%s:m:%s}:%s:%s',
            'prefix' => 'default',
            'ttl' => 3600 * 24,
            'empty_model_ttl' => 600,
            'load_script' => true,//是否加载脚本
            'pool' => env('DB_MODEL_CACHE_POOL', 'default'),//redis 连接池
        ],
        'commands' => [
            'gen:model' => [
                'path' => 'app/Models',
                'force_casts' => true,
                'inheritance' => 'BaseModel',
                'uses' => '',
                'table_mapping' => [],
            ],
        ],
    ],
    'sync' => [//231
        'read' => [
            'host' => [env('SYNC_DB_HOST_READ', '127.0.0.1')],
        ],
        'write' => [
            'host' => [env('SYNC_DB_HOST', '127.0.0.1')],
        ],
        'sticky' => true,
        'driver' => env('SYNC_DB_DRIVER', 'mysql'),
        'port' => env('SYNC_DB_PORT', 3306),
        'database' => env('SYNC_DB_DATABASE', 'ptx_sync'),
        'username' => env('SYNC_DB_USERNAME', 'forge'),
        'password' => env('SYNC_DB_PASSWORD', ''),
        'unix_socket' => env('SYNC_DB_SOCKET', ''),
        'charset' => env('SYNC_DB_CHARSET', 'utf8mb4'),
        'collation' => env('SYNC_DB_COLLATION', 'utf8mb4_unicode_ci'),
        'prefix' => env('SYNC_DB_PREFIX', env('DB_PREFIX', '')),
        'strict' => env('SYNC_DB_STRICT_MODE', false),
        'engine' => env('SYNC_DB_ENGINE', null),
        'timezone' => env('SYNC_DB_TIMEZONE', '+00:00'),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('DB_MAX_IDLE_TIME', 60),
        ],
        'cache' => [
            'handler' => App\Database\ModelCache\Handler\RedisHandler::class,//env('DB_MODEL_CACHE_HANDLER', Hyperf\ModelCache\Handler\RedisHandler::class),
            'cache_key' => '{mc:%s:m:%s}:%s:%s',
            'prefix' => 'default',
            'ttl' => 3600 * 24,
            'empty_model_ttl' => 600,
            'load_script' => true,//是否加载脚本
            'pool' => env('DB_MODEL_CACHE_POOL', 'default'),//redis 连接池
        ],
        'commands' => [
            'gen:model' => [
                'path' => 'app/Models',
                'force_casts' => true,
                'inheritance' => 'BaseModel',
                'uses' => '',
                'table_mapping' => [],
            ],
        ],
    ],
    'db_xc_ptx_db' => [//193
        'read' => [
            'host' => [env('XC_PTX_DB_HOST', '127.0.0.1')],
        ],
        'write' => [
            'host' => [env('XC_PTX_DB_HOST', '127.0.0.1')],
        ],
        'sticky' => true,
        'driver' => 'mysql',
        'port' => env('XC_PTX_DB_PORT', 3306),
        'database' => env('XC_PTX_DB_DATABASE', 'forge'),
        'username' => env('XC_PTX_DB_USERNAME', 'forge'),
        'password' => env('XC_PTX_DB_PASSWORD', ''),
        'unix_socket' => env('XC_PTX_DB_SOCKET', ''),
        'charset' => env('XC_PTX_DB_CHARSET', 'utf8mb4'),
        'collation' => env('XC_PTX_DB_COLLATION', 'utf8mb4_unicode_ci'),
        'prefix' => env('XC_PTX_DB_PREFIX', ''),
        'strict' => env('XC_PTX_DB_STRICT_MODE', false),
        'engine' => env('XC_PTX_DB_ENGINE', null),
        'timezone' => env('XC_PTX_DB_TIMEZONE', '+00:00'),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('DB_MAX_IDLE_TIME', 60),
        ],
        'cache' => [
            'handler' => App\Database\ModelCache\Handler\RedisHandler::class,//env('DB_MODEL_CACHE_HANDLER', Hyperf\ModelCache\Handler\RedisHandler::class),
            'cache_key' => '{mc:%s:m:%s}:%s:%s',
            'prefix' => 'default',
            'ttl' => 3600 * 24,
            'empty_model_ttl' => 600,
            'load_script' => true,//是否加载脚本
            'pool' => env('DB_MODEL_CACHE_POOL', 'default'),//redis 连接池
        ],
        'commands' => [
            'gen:model' => [
                'path' => 'app/Models',
                'force_casts' => true,
                'inheritance' => 'BaseModel',
                'uses' => '',
                'table_mapping' => [],
            ],
        ],
    ],

];
