<?php

namespace TheCorps\LaravelCqrs\Exceptions;

/**
 * Thrown when no handler is declared for a command or query.
 */
final class MissingHandlerException extends CqrsException
{
    /**
     * @param object $message        The command or query that was dispatched.
     * @param string $attributeClass The handler attribute that was expected.
     */
    public static function for(object $message, string $attributeClass): self
    {
        return new self(sprintf(
            'No #[%s] attribute found on [%s] or its parent classes.',
            class_basename($attributeClass),
            $message::class,
        ));
    }
}
