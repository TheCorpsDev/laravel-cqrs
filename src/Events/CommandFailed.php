<?php

namespace TheCorps\LaravelCqrs\Events;

use Throwable;

/**
 * Fired when the pipeline of a command throws. The exception is re-thrown afterwards.
 */
final class CommandFailed extends MessageEvent
{
    /**
     * @param object    $message    The command.
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
