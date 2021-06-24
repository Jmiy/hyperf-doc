<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.(Aliyun ACM 配置中心)
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    // 是否开启配置中心的接入流程，为 true 时会自动启动一个 ConfigFetcherProcess 进程用于更新配置
    'enable' => false,
    // 是否使用独立进程来拉取config，如果否则将在worker内以协程方式拉取
    'use_standalone_process' => true,
    // 配置更新间隔（秒）
    'interval' => 5,
    // 阿里云 ACM 断点地址，取决于您的可用区
    'endpoint' => env('ALIYUN_ACM_ENDPOINT', 'acm.aliyun.com'),
    // 当前应用需要接入的 Namespace
    'namespace' => env('ALIYUN_ACM_NAMESPACE', ''),
    // 您的配置对应的 Data ID
    'data_id' => env('ALIYUN_ACM_DATA_ID', ''),
    // 您的配置对应的 Group
    'group' => env('ALIYUN_ACM_GROUP', 'DEFAULT_GROUP'),
    // 您的阿里云账号的 Access Key
    'access_key' => env('ALIYUN_ACM_AK', ''),
    // 您的阿里云账号的 Secret Key
    'secret_key' => env('ALIYUN_ACM_SK', ''),
];

