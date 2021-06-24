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

namespace App\Processes;

use Hyperf\Process\AbstractProcess;
use Hyperf\Process\Annotation\Process;
use Hyperf\Process\ProcessCollector;
use Hyperf\Utils\Arr;
use Swoole\Server;
use App\Messages\Pipe\UserProcessPipeMessage;
use Hyperf\Utils\Coroutine;

/**
 * @Process(nums=1, name="custom-process", redirectStdinStdout=false, pipeType=2, enableCoroutine=true)
 */
class CustomProcess extends AbstractProcess
{

    /**
     * @var Server
     */
    private $server;

    public function bind($server): void
    {
        $this->server = $server;
        parent::bind($server);
    }

    public function handle(): void
    {

        while (true) {
            sleep(10);
        }

        // 进程运行的代码，不能退出，一旦退出Manager进程会自动再次创建该进程。
        /**
         * 从管道中读取数据。https://wiki.swoole.com/wiki/page/217.html
         * $buffer_size是缓冲区的大小，默认为8192，最大不超过64K
         * 管道类型为DGRAM数据报时，read可以读取完整的一个数据包
         * 管道类型为STREAM时，read是流式的，需要自行处理包完整性问题
         * 读取成功返回二进制数据字符串，读取失败返回false
         */
//        while (true) {
//            try {
//
//
//                /** @var \Swoole\Coroutine\Socket $sock */
//                //$sock = $this->process->exportSocket();
//                //$data = $sock->recv(65535, 10.0);
//
//                while ($data = $this->process->read(65535)) {//同步阻塞读取
//                //while ($data = $sock->recv(65535, 10.0)) {//同步阻塞读取
//
//                    var_dump(__METHOD__,$data);
//
//                    try {
//
//                        $data = json_decode($data, true);
//
//                        $service = data_get($data, Constant::SERVICE_KEY, '');
//                        $method = data_get($data, Constant::METHOD_KEY, '');
//                        $parameters = data_get($data, Constant::PARAMETERS_KEY, []);
//
//                        if ($service && $method && method_exists($service, $method)) {
//                            $service::{$method}(...$parameters);
//                        }
//
//                    } catch (\Exception $exc) {
//                        $parameters = [
//                            'parameters' => $data,
//                            //'exc' => ExceptionHandler::getMessage($exc),
//                        ];
//                        LogService::addSystemLog('error', $service, $method, 'CustomProcess--执行失败', $parameters); //添加系统日志
//                    }
//                }
//
//            } catch (\Exception $exc) {
//            }
//        }

    }

    /**
     * @return mixed
     */
    public static function write($data)
    {

        Coroutine::create(function () use ($data) {
            /**
             * 向管道内写入数据。https://wiki.swoole.com/wiki/page/216.html
             * 在子进程内调用write，父进程可以调用read接收此数据
             * 在父进程内调用write，子进程可以调用read接收此数据
             * Swoole底层使用Unix Socket实现通信，Unix Socket是内核实现的全内存通信，无任何IO消耗。在1进程write，1进程read，每次读写1024字节数据的测试中，100万次通信仅需1.02秒。
             * 管道通信默认的方式是流式，write写入的数据在read可能会被底层合并。可以设置swoole_process构造函数的第三个参数为2改变为数据报式。
             */
            $pipeMessage = new UserProcessPipeMessage($data);
            return Arr::random(ProcessCollector::get('custom-process'))->exportSocket()->send(serialize($pipeMessage), 10);
        });

        /**
         * 向管道内写入数据。https://wiki.swoole.com/wiki/page/216.html
         * 在子进程内调用write，父进程可以调用read接收此数据
         * 在父进程内调用write，子进程可以调用read接收此数据
         * Swoole底层使用Unix Socket实现通信，Unix Socket是内核实现的全内存通信，无任何IO消耗。在1进程write，1进程read，每次读写1024字节数据的测试中，100万次通信仅需1.02秒。
         * 管道通信默认的方式是流式，write写入的数据在read可能会被底层合并。可以设置swoole_process构造函数的第三个参数为2改变为数据报式。
         */
        //return Arr::random(ProcessCollector::get('custom-process'))->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        //return Arr::random(ProcessCollector::get('custom-process'))->exportSocket()->send(json_encode($data, JSON_UNESCAPED_UNICODE), 10);


    }
}
