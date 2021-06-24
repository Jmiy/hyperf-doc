<?php

declare(strict_types=1);
/**
 * Job
 */

namespace App\Jobs;

use App\Services\LogService;
use Hyperf\Utils\Context;
use Hyperf\Utils\Coroutine;

use App\Constants\Constant;

class PublicJob extends Job
{
    public $data;

    /**
     * 任务执行失败后的重试次数，即最大执行次数为 $maxAttempts+1 次
     *
     * @var int
     */
    protected $maxAttempts = 2;

    public function __construct($data)
    {
        // 这里最好是普通数据，不要使用携带 IO 的对象，比如 PDO 对象
        $this->data = $data;
    }

    public function handle()
    {
        //ApplicationContext::getContainer();

        $service = data_get($this->data, Constant::SERVICE_KEY, '');
        $method = data_get($this->data, Constant::METHOD_KEY, '');
        $parameters = data_get($this->data, Constant::PARAMETERS_KEY, []);

        if ($service && $method && method_exists($service, $method)) {
            try {

                //设置 协程上下文请求数据
                Context::set(Constant::CONTEXT_REQUEST_DATA, data_get($this->data, Constant::REQUEST_DATA_KEY, []));

                call([$service, $method], $parameters);//兼容各种调用 $service::{$method}(...$parameters);

            } catch (\Exception $exc) {
                $parameters = [
                    'parameters' => $this->data,
                    //'exc' => ExceptionHandler::getMessage($exc),
                ];
                LogService::addSystemLog('error', $service, $method, 'PublicJob--执行失败', $parameters); //添加系统日志
            }

            // 根据参数处理具体逻辑
            // 通过具体参数获取模型等
            // 这里的逻辑会在 ConsumerProcess 进程中执行
            //var_dump(Coroutine::parentId(), Coroutine::id(), Coroutine::inCoroutine(), $this->data);
        }
    }
}
