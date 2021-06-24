<?php

/**
 * 行为服务
 * User: Jmiy
 * Date: 2019-11-13
 * Time: 16:50
 */

namespace App\Services;

use Hhxsv5\LaravelS\Swoole\WebSocketHandlerInterface;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

/**
 * @see https://wiki.swoole.com/#/start/start_ws_server
 */
class WebSocketService implements WebSocketHandlerInterface {
    /*     * @var \Swoole\Table $wsTable */

    private $wsTable;

    // 声明没有参数的构造函数
    public function __construct() {
        $this->wsTable = app('swoole')->wsTable;
    }

//    public function onOpen(Server $server, Request $request) {
//        
//        var_dump($request->fd, $request->server);
//        
//        // 在触发onOpen事件之前，建立WebSocket的HTTP请求已经经过了Laravel的路由，
//        // 所以Laravel的Request、Auth等信息是可读的，Session是可读写的，但仅限在onOpen事件中。
//        // \Log::info('New WebSocket connection', [$request->fd, request()->all(), session()->getId(), session('xxx'), session(['yyy' => time()])]);
//        $server->push($request->fd, 'Welcome to LaravelS');
//        // throw new \Exception('an exception');// 此时抛出的异常上层会忽略，并记录到Swoole日志，需要开发者try/catch捕获处理
//    }
//
//    public function onMessage(Server $server, Frame $frame) {
//        echo "Message: {$frame->data}\n";
//        // \Log::info('Received message', [$frame->fd, $frame->data, $frame->opcode, $frame->finish]);
//        //$server->push($frame->fd, date('Y-m-d H:i:s'));
//        // throw new \Exception('an exception');// 此时抛出的异常上层会忽略，并记录到Swoole日志，需要开发者try/catch捕获处理
//        
//        $server->push($frame->fd, date('Y-m-d H:i:s').": {$frame->data}");
//    }
//
//    public function onClose(Server $server, $fd, $reactorId) {
//        // throw new \Exception('an exception');// 此时抛出的异常上层会忽略，并记录到Swoole日志，需要开发者try/catch捕获处理
//        echo "client-{$fd} is closed\n";
//    }
    // 场景：WebSocket中UserId与FD绑定
    public function onOpen(Server $server, Request $request) {
        // var_dump(app('swoole') === $server);// 同一实例
        /**
         * 获取当前登录的用户
         * 此特性要求建立WebSocket连接的路径要经过Authenticate之类的中间件。
         * 例如：
         * 浏览器端：var ws = new WebSocket("ws://127.0.0.1:5200/ws");
         * 那么Laravel中/ws路由就需要加上类似Authenticate的中间件。
         * Route::get('/ws', function () {
         *     // 响应状态码200的任意内容
         *     return 'websocket';
         * })->middleware(['auth']);
         */
        // $user = Auth::user();
        // $userId = $user ? $user->id : 0; // 0 表示未登录的访客用户
        $userId = mt_rand(1000, 10000);
        // if (!$userId) {
        //     // 未登录用户直接断开连接
        //     $server->disconnect($request->fd);
        //     return;
        // }
        $this->wsTable->set('uid:' . $userId, ['value' => $request->fd]); // 绑定uid到fd的映射
        $this->wsTable->set('fd:' . $request->fd, ['value' => $userId]); // 绑定fd到uid的映射
        $server->push($request->fd, "Welcome to LaravelS #{$request->fd}");
    }

    public function onMessage(Server $server, Frame $frame) {
        // 广播
        foreach ($this->wsTable as $key => $row) {
            if (strpos($key, 'uid:') === 0 && $server->isEstablished($row['value'])) {
                $content = sprintf('Broadcast: new message "%s" from #%d', $frame->data, $frame->fd);
                $server->push($row['value'], $content);
            }
        }
    }

    public function onClose(Server $server, $fd, $reactorId) {
        $uid = $this->wsTable->get('fd:' . $fd);
        if ($uid !== false) {
            $this->wsTable->del('uid:' . $uid['value']); // 解绑uid映射
        }
        $this->wsTable->del('fd:' . $fd); // 解绑fd映射

        $server->push($fd, "Goodbye #{$fd}");
    }

}
