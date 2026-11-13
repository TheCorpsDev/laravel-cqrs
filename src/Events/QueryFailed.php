<?php

namespace TheCorps\LaravelCqrs\Events;

use Throwable;

/**
 * Fired when the pipeline of a query throws. The exception is re-thrown afterwards.
 */
final class QueryFailed extends MessageEvent
{
    /**
     * @param object    $message    The query.
     * @param Throwable $exception  What the pipeline threw.
     * @param float     $durationMs Time spent in the pipeline, in milliseconds.
     */
    public function __construct(
        object $message,
        public readonly Throwable $exception,
        public readonly float $durationMs,
    ) {
        parent::__construct($message);
    }
}
