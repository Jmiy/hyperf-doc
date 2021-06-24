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
namespace Hyperf\Retry;

use Hyperf\Utils\Fluent;

/**
 * @property mixed $lastResult
 * @property null|\Throwable $lastThrowable
 * @property null|bool $retryExhausted
 * @property null|StrategyInterface $strategy
 * @property null|float $startTime
 * @property null|int $attempt
 * @property null|ProceedingJoinPoint $proceedingJoinPoint
 */
class RetryContext extends Fluent
{
    public function isFirstTry(): bool
    {
        return ! array_key_exists('lastResult', $this->attributes);
    }
}
