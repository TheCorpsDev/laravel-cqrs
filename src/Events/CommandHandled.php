<?php

namespace TheCorps\LaravelCqrs\Events;

/**
 * Fired after a command went through the pipeline successfully.
 */
final class CommandHandled extends MessageEvent
{
    /**
     * @param object $message    The command.
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
