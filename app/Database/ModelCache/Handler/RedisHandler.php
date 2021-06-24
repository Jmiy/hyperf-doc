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
namespace App\Database\ModelCache\Handler;

use Hyperf\ModelCache\Config;
use Psr\Container\ContainerInterface;

use Hyperf\ModelCache\Handler\RedisHandler as HyperfRedisHandler;
use App\Database\ModelCache\Redis\LuaManager;

class RedisHandler extends HyperfRedisHandler
{

    public function __construct(ContainerInterface $container, Config $config){
        parent::__construct($container, $config);
        $this->manager = make(LuaManager::class, [$config]);
    }

    public function handle(string $key, array $keys, ?int $num = null)
    {
        return $this->manager->handle($key, $keys, $num);
    }
}
