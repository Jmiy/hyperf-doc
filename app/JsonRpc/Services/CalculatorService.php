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

namespace App\JsonRpc\Services;

use Hyperf\RpcServer\Annotation\RpcService;
use App\JsonRpc\Contracts\CalculatorServiceInterface;

/**
 * 注意，如希望通过服务中心来管理服务，需在注解内增加 publishTo 属性 protocol="jsonrpc-http", server="jsonrpc-http", publishTo="consul"
 * @RpcService(name="CalculatorService", protocol="jsonrpc", server="jsonrpc", publishTo="consul")
 * @RpcService(name="CalculatorService", protocol="jsonrpc-http", server="jsonrpc-http", publishTo="consul")
 */
class CalculatorService implements CalculatorServiceInterface
{
    // 实现一个加法方法，这里简单的认为参数都是 int 类型
    public function add(int $a, int $b): int
    {
        // 这里是服务方法的具体实现
        return $a + $b;
    }

    // 实现一个加法方法，这里简单的认为参数都是 int 类型
//    public function add(int $a, int $b)
//    {
//        // 这里是服务方法的具体实现
//        return func_get_args();
//    }
}

