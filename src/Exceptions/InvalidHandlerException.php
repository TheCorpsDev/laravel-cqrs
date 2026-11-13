<?php

namespace TheCorps\LaravelCqrs\Exceptions;

/**
 * Thrown when the handler declared for a command or query cannot be used.
 */
final class InvalidHandlerException extends CqrsException
{
    public static function classNotFound(object $message, string $handlerClass): self
    {
        return new self(sprintf(
            'The handler [%s] declared on [%s] does not exist.',
            $handlerClass,
            $message::class,
        ));
    }

    public static function missingMethod(object $message, string $handlerClass): self
    {
        return new self(sprintf(
            'The handler [%s] declared on [%s] has no handle() method.',
            $handlerClass,
            $message::class,
        ));
    }
}
