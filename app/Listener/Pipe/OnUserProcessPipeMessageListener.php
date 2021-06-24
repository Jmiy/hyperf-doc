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
namespace App\Listener\Pipe;

use Hyperf\Event\Annotation\Listener;
use Psr\Container\ContainerInterface;
use App\Messages\Pipe\UserProcessPipeMessage;
use App\Constants\Constant;
use App\Services\LogService;

/**
 * @Listener
 */
class OnUserProcessPipeMessageListener extends AbstractPipeMessageListener
{

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
    }

    /**
     * Handle the Event when the event is triggered, all listeners will
     * complete before the event is returned to the EventDispatcher.
     */
    public function process(object $event)
    {

        $this->logger->info(sprintf('[%s] event=== [%s]', static::class,get_class($event)));

        if (property_exists($event, 'data') && $event->data instanceof UserProcessPipeMessage) {
            /** @var PipeMessage $data */
            $userProcessPipeMessage = $event->data;

            try {

                $data = $userProcessPipeMessage->data;

                $service = data_get($data, Constant::SERVICE_KEY, '');
                $method = data_get($data, Constant::METHOD_KEY, '');
                $parameters = data_get($data, Constant::PARAMETERS_KEY, []);

                if ($service && $method && method_exists($service, $method)) {
                    $service::{$method}(...$parameters);
                }

            } catch (\Exception $exc) {
                $parameters = [
                    'parameters' => $data,
                    //'exc' => ExceptionHandler::getMessage($exc),
                ];
                LogService::addSystemLog('error', $service, $method, 'CustomProcess--执行失败', $parameters); //添加系统日志
            }

            $this->logger->debug(sprintf('OnUserProcessPipeMessageListener [%s] is executed', 'process'));
        }
    }
}
