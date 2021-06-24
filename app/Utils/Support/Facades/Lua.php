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

namespace App\Utils\Support\Facades;

use App\Utils\Exception\OperatorNotFoundException;
use App\Utils\Contracts\Redis\Lua\OperatorInterface;
use App\Utils\Contracts\Redis\Lua\BatchFuzzyDelete;


class Lua
{

    /**
     * @var array<string,OperatorInterface>
     */
    public static $operators = [];

    /**
     * @var array<string,string>
     */
    public static $luaShas = [];

    public static function handle(string $key, array $keys, ?string $poolName = 'default', ?int $num = null)
    {
        $redis = Redis::getRedis($poolName);

        $sha = static::getLuaSha($key);

        $operator = static::getOperator($key);

        if ($num === null) {
            $num = count($keys);
        }

        if (!empty($sha)) {
            $luaData = $redis->evalSha($sha, $keys, $num);
        } else {
            $script = $operator->getScript();
            $luaData = $redis->eval($script, $keys, $num);
        }

        return $operator->parseResponse($luaData);
    }

    public static function getOperator(string $key): OperatorInterface
    {
        if (!isset(static::$operators[$key])) {
            static::$operators[$key] = make($key);
        }

        if (!static::$operators[$key] instanceof OperatorInterface) {
            throw new OperatorNotFoundException(sprintf('The operator %s is not instanceof OperatorInterface.', $key));
        }

        return static::$operators[$key];
    }

    public static function getLuaSha(string $key, string $poolName = 'default'): string
    {
        if (empty(static::$luaShas[$poolName][$key])) {
            static::$luaShas[$poolName][$key] = Redis::getRedis($poolName)->script('load', static::getOperator($key)->getScript());
        }
        return static::$luaShas[$poolName][$key];
    }

    public static function doc(string $key, string $poolName = 'default')
    {
        return static::handle(BatchFuzzyDelete::class, [$key], $poolName);
    }


}
