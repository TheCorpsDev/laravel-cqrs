<?php

namespace TheCorps\LaravelCqrs\Exceptions;

/**
 * Thrown when the validator declared for a command or query cannot be used.
 */
final class InvalidValidatorException extends CqrsException
{
    public static function classNotFound(object $message, string $validatorClass): self
    {
        return new self(sprintf(
            'The validator [%s] declared on [%s] does not exist.',
            $validatorClass,
            $message::class,
        ));
    }

    public static function missingMethod(object $message, string $validatorClass): self
    {
        return new self(sprintf(
            'The validator [%s] declared on [%s] has no validate() method.',
            $validatorClass,
            $message::class,
        ));
    }
}
