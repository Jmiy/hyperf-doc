<?php

declare(strict_types=1);

namespace App\Middleware\Auth;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;

use Hyperf\Di\Annotation\Inject;

class FooMiddleware implements MiddlewareInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @Inject(lazy=true)
     * @var RequestInterface
     */
    protected $request;

    /**
     * @Inject(lazy=true)
     * @var HttpResponse
     */
    protected $response;

    public function __construct(ContainerInterface $container)//, HttpResponse $response, RequestInterface $request
    {
        $this->container = $container;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        //var_dump(__METHOD__);

        // 根据具体业务判断逻辑走向，这里假设用户携带的token有效
        $isValidToken = true;
        if ($isValidToken) {
            return $handler->handle($request);
        }

        return $this->response->json(
            [
                'code' => -1,
                'data' => [
                    'error' => '中间件验证token无效，阻止继续向下执行',
                ],
            ]
        );


    }
}