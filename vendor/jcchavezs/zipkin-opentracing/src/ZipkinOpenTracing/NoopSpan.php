<?php

namespace ZipkinOpenTracing;

use OpenTracing\Span as OTSpan;
use OpenTracing\SpanContext as OTSpanContext;
use Zipkin\Span as ZipkinSpan;
use ZipkinOpenTracing\SpanContext as ZipkinOpenTracingContext;

final class NoopSpan implements OTSpan
{
    /**
     * @var OTSpanContext|SpanContext
     */
    private $context;

    private function __construct(ZipkinSpan $span)
    {
        $this->context = ZipkinOpenTracingContext::fromTraceContext($span->getContext());
    }

    /**
     * @param ZipkinSpan $span
     * @return NoopSpan
     */
    public static function create(ZipkinSpan $span)
    {
        return new self($span);
    }

    /**
     * @return string
     */
    public function getOperationName(): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getContext(): OTSpanContext
    {
        return $this->context;
    }

    /**
     * {@inheritdoc}
     */
    public function finish($finishTime = null, array $logRecords = []): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function overwriteOperationName(string $newOperationName): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function setTag(string $key, $value): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function log(array $fields = [], $timestamp = null): void
    {
    }

    /**
     * Adds a baggage item to the SpanContext which is immutable so it is required to use
     * SpanContext::withBaggageItem to get a new one.
     *
     * If the span is already finished, a warning should be logged.
     *
     * @param string $key
     * @param string $value
     */
    public function addBaggageItem(string $key, string $value): void
    {
    }

    /**
     * @param string $key
     * @return string
     */
    public function getBaggageItem(string $key): ?string
    {
        return '';
    }
}
