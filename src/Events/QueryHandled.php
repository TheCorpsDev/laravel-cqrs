<?php

namespace TheCorps\LaravelCqrs\Events;

/**
 * Fired after a query went through the pipeline successfully.
 */
final class QueryHandled extends MessageEvent
{
    /**
     * @param object $message    The query.
     * @param mixed  $result     What the handler returned.
     * @param float  $durationMs Time spent in the pipeline, in milliseconds.
     */
    public function __construct(
        object $message,
        public readonly mixed $result,
        public readonly float $durationMs,
    ) {
        parent::__construct($message);
    }
}
