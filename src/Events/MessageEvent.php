<?php

namespace TheCorps\LaravelCqrs\Events;

/**
 * Base class of every event fired by the command and query buses.
 */
abstract class MessageEvent
{
    /**
     * @param object $message The command or query being dispatched.
     */
    public function __construct(
        public readonly object $message,
    ) {}
}
