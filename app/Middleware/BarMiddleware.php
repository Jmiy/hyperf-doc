<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class BarMiddleware implements MiddlewareInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        //var_dump(__METHOD__,$request->getBody());

        //全局更改请求和响应对象 https://hyperf.wiki/2.0/#/zh-cn/middleware/middleware?id=%e5%85%a8%e5%b1%80%e6%9b%b4%e6%94%b9%e8%af%b7%e6%b1%82%e5%92%8c%e5%93%8d%e5%ba%94%e5%af%b9%e8%b1%a1
        /**
         * 首先，在协程上下文内是有存储最原始的 PSR-7 请求对象 和 响应对象 的，且根据 PSR-7 对相关对象所要求的 不可变性(immutable)，
         * 也就意味着我们在调用 $response = $response->with***() 所调用得到的 $response，并非为改写原对象，而是一个 Clone 出来的新对象，也就意味着我们储存在协程上下文内的 请求对象 和 响应对象 是不会改变的，
         * 那么当我们在中间件内的某些逻辑改变了 请求对象 或 响应对象，而且我们希望对后续的 非传递性的 代码再获取改变后的 请求对象 或 响应对象，那么我们便可以在改变对象后，将新的对象设置到上下文中，如代码所示：
         */
        // $request 和 $response 为修改后的对象
//        $request = \Hyperf\Utils\Context::set(ServerRequestInterface::class, $request);
//        $response = \Hyperf\Utils\Context::set(ResponseInterface::class, $response);

        return $handler->handle($request);
    }
}