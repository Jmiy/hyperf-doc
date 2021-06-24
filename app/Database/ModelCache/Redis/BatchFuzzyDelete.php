<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace App\Database\ModelCache\Redis;

use Hyperf\ModelCache\Redis\OperatorInterface;

class BatchFuzzyDelete implements OperatorInterface
{

    public function getScript(): string
    {
        return <<<'LUA'
    return redis.call('del',unpack(redis.call('keys',KEYS[1])));
LUA;
    }

    public function parseResponse($data)
    {
        return $data;
    }
}
