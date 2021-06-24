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

use Hyperf\AsyncQueue\Process\ConsumerProcess;
use Hyperf\Process\Annotation\Process;

/**
 * @Process
 */
class LogQueueConsumer extends ConsumerProcess
{
    /**
     * @var string
     */
    protected $queue = 'log';

    /**
     * 任务执行流转流程主要包括以下几个队列:
     * 队列名	备注
     * waiting	等待消费的队列
     * reserved	正在消费的队列
     * delayed	延迟消费的队列
     * failed	消费失败的队列
     * timeout	消费超时的队列 (虽然超时，但可能执行成功)
     */
}
