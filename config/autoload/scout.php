<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.(Elasticsearch)
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    'default' => env('SCOUT_ENGINE', 'elasticsearch'),
    'chunk' => [
        'searchable' => 500,
        'unsearchable' => 500,
    ],
    'prefix' => env('SCOUT_PREFIX', ''),
    'soft_delete' => false,
    'concurrency' => 100,
    'engine' => [
        'elasticsearch' => [
            'driver' => Hyperf\Scout\Provider\ElasticsearchProvider::class,
            'index' => null,// 如果 index 设置为 null，则每个模型会对应一个索引，反之每个模型对应一个类型
            'hosts' => [
                env('ELASTICSEARCH_HOST', 'http://192.168.152.128:9200'),
            ],
        ],
    ],
];
