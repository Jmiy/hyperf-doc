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

namespace App\JsonRpc\Consumers;

use Hyperf\RpcClient\AbstractServiceClient;
use App\JsonRpc\Contracts\CalculatorServiceInterface;
use Hyperf\Retry\Annotation\Retry;//服务重试:https://hyperf.wiki/2.0/#/zh-cn/retry
use Hyperf\CircuitBreaker\Annotation\CircuitBreaker;//服务熔断及降级

class CalculatorServiceConsumer extends AbstractServiceClient implements CalculatorServiceInterface
{
    /**
     * 定义对应服务提供者的服务名称
     * @var string
     */
    protected $serviceName = 'CalculatorService';

    /**
     * 定义对应服务提供者的服务协议
     * @var string
     */
    protected $protocol = 'jsonrpc-http';//  jsonrpc

    /**
     * 异常时重试该方法 服务熔断及降级:https://hyperf.wiki/2.0/#/zh-cn/circuit-breaker
     * @Retry(maxAttempts=3)
     * @CircuitBreaker(timeout=0.200, failCounter=1, successCounter=1, fallback="App\JsonRpc\Consumers\CalculatorServiceConsumer::addFallback")
     */
    public function add(int $a, int $b)//: int
    {
        //var_dump(__METHOD__);
        return $this->__request(__FUNCTION__, compact('a', 'b'));
    }

    public function addFallback(int $a, int $b): int
    {
        return 555;
    }
}


