<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.(用于管理路由)
 * https://hyperf.wiki/2.0/#/zh-cn/router?id=%e6%b3%a8%e8%a7%a3%e5%8f%82%e6%95%b0
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
use Hyperf\HttpServer\Router\Router;

Router::addRoute(['GET', 'POST', 'HEAD'], '/', 'App\Controller\IndexController@index');

Router::get('/favicon.ico', function () {
    return '';
});

Router::get('/hello-hyperf', function () {
    return 'Hello Hyperf.';
});

Router::get('/user/{id}', 'App\Controller\DocController::route');

// 该 Group 下的所有路由都将应用配置的中间件
Router::addGroup(
    '/api/shop',
    function () {
        //Router::get('/index', [\App\Controller\DocController::class, 'index']);
        Router::addRoute(
            ['GET', 'POST','PUT','DELETE'],
            '/index',
            [\App\Controller\DocController::class, 'index'],
            [
                'as' => 'test_user',
                'uses' => 'DocController@user',
                'validator' => [
                    'type' => 'test',
                    'messages' => [],
                    'rules' => [],
                ]
            ]);


        Router::addRoute(
            ['GET', 'POST','PUT','DELETE'],
            '/route[/{id:\d+}]',
            [\App\Controller\DocController::class, 'route'],
            [
                'as' => 'test_user',
                'validator' => [
                    'type' => 'test',
                    'messages' => [],
                    'rules' => [],
                ]
            ]);

        Router::addRoute(
            ['GET', 'POST','PUT','DELETE'],
            '/encrypt[/{id:\d+}]',
            [\App\Controller\DocController::class, 'encrypt'],
            [
                'as' => 'test_user',
                'validator' => [
                    'type' => 'test',
                    'messages' => [],
                    'rules' => [],
                ],
                'nolog' => 'test_nolog',
            ]);
    },
    ['middleware' => []]//App\Middleware\Auth\FooMiddleware::class
);

Router::get('/metrics', function(){
    $registry = Hyperf\Utils\ApplicationContext::getContainer()->get(Prometheus\CollectorRegistry::class);
    $renderer = new Prometheus\RenderTextFormat();
    return $renderer->render($registry->getMetricFamilySamples());
});
