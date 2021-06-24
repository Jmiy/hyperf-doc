<?php declare(strict_types=1);

namespace Domnikl\Statsd\Connection;

use Domnikl\Statsd\Connection as Connection;

/**
 * connection implementation which drops all requests, useful for dev environments
 * and for disabling sending metrics entirely
 */
class Blackhole implements Connection
{
    public function send(string $message): void
    {
        // do nothing
    }

    public function sendMessages(array $messages): void
    {
        // do nothing
    }

    public function close(): void
    {
        // do nothing
    }
}
