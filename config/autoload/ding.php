<?php

return [

    // 默认发送的机器人

    'default' => [
        // 是否要开启机器人，关闭则不再发送消息
        'enabled' => env('DING_ENABLED',true),
        // 机器人的access_token
        'token' => env('DING_TOKEN','685645852fc15942b6f09f553ad634702d3e5b21141cefed895807ff1b89b8f6'),
        // 钉钉请求的超时时间
        'timeout' => env('DING_TIME_OUT',2.0),
        // 是否开启ss认证
        'ssl_verify' => env('DING_SSL_VERIFY',true),
        // 开启安全配置
        'secret' => env('DING_SECRET',true),
    ],

    'other' => [
        'enabled' => env('OTHER_DING_ENABLED',true),

        'token' => env('OTHER_DING_TOKEN',''),

        'timeout' => env('OTHER_DING_TIME_OUT',2.0),

        'ssl_verify' => env('DING_SSL_VERIFY',true),

        'secret' => env('DING_SECRET',true),
    ]
];
