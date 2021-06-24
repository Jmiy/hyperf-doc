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
namespace Hyperf\Metric\Listener;

use Hyperf\Command\Event\AfterExecute;
use Hyperf\Command\Event\BeforeHandle;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Metric\Contract\MetricFactoryInterface;
use Hyperf\Metric\Event\MetricFactoryReady;
use Hyperf\Metric\MetricFactoryPicker;
use Hyperf\Metric\MetricSetter;
use Hyperf\Utils\Coordinator\Constants;
use Hyperf\Utils\Coordinator\CoordinatorManager;
use Hyperf\Utils\Coroutine;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Swoole\Timer;

/**
 * Collect and handle metrics before command start.
 */
class OnBeforeHandle implements ListenerInterface
{
    use MetricSetter;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var MetricFactoryInterface
     */
    protected $factory;

    protected static $exits = __CLASS__ . ' exited';

    /**
     * @var ConfigInterface
     */
    private $config;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->config = $container->get(ConfigInterface::class);
    }

    public function listen(): array
    {
        return [
            BeforeHandle::class,
            AfterExecute::class,
        ];
    }

    public function process(object $event)
    {
        if ($event instanceof AfterExecute) {
            CoordinatorManager::until(Constants::WORKER_EXIT)->resume();
            return;
        }

        MetricFactoryPicker::$isCommand = true;

        if ($this->config->get('metric.use_standalone_process', true)) {
            if ($this->container->has(StdoutLoggerInterface::class)) {
                $logger = $this->container->get(StdoutLoggerInterface::class);
                $logger->warning('The use_standalone_process is set to true, but the command is not running in a server context. The current process is used instead.');
            }
        }

        $this->factory = $this->container->get(MetricFactoryInterface::class);
        $this->spawnHandle();

        $eventDispatcher = $this->container->get(EventDispatcherInterface::class);
        $eventDispatcher->dispatch(new MetricFactoryReady($this->factory));

        if (! $this->config->get('metric.enable_default_metric', false)) {
            return;
        }

        // The following metrics can be collected in command.
        $metrics = $this->factoryMetrics(
            ['worker' => (string) 'N/A'],
            'memory_usage',
            'memory_peak_usage',
            'gc_runs',
            'gc_collected',
            'gc_threshold',
            'gc_roots',
            'ru_oublock',
            'ru_inblock',
            'ru_msgsnd',
            'ru_msgrcv',
            'ru_maxrss',
            'ru_ixrss',
            'ru_idrss',
            'ru_minflt',
            'ru_majflt',
            'ru_nsignals',
            'ru_nvcsw',
            'ru_nivcsw',
            'ru_nswap',
            'ru_utime_tv_usec',
            'ru_utime_tv_sec',
            'ru_stime_tv_usec',
            'ru_stime_tv_sec'
        );

        $timerInterval = $this->config->get('metric.default_metric_interval', 5);
        $timerId = Timer::tick($timerInterval * 1000, function () use ($metrics) {
            $this->trySet('gc_', $metrics, gc_status());
            $this->trySet('', $metrics, getrusage());
            $metrics['memory_usage']->set(memory_get_usage());
            $metrics['memory_peak_usage']->set(memory_get_peak_usage());
        });
        // Clean up timer on worker exit;
        Coroutine::create(function () use ($timerId) {
            CoordinatorManager::until(Constants::WORKER_EXIT)->yield();
            Timer::clear($timerId);
        });
    }
}
