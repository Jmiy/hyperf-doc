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

use Hyperf\ModelCache\Redis\LuaManager as HyperfLuaManager;
use Hyperf\ModelCache\Redis\OperatorInterface;

class LuaManager extends HyperfLuaManager
{

    public function getOperator(string $key): OperatorInterface
    {
        if (! isset($this->operators[$key])) {
            $this->operators[$key] = make($key);
        }

        return parent::getOperator($key);
    }

}
