<?php

namespace TheCorps\LaravelCqrs\Exceptions;

use TheCorps\LaravelCqrs\Pipeline\Behaviors\HandlerExecutionBehavior;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Pipeline\PipelineBehaviorInterface;

/**
 * Thrown when a pipeline is misconfigured.
 */
final class InvalidPipelineException extends CqrsException
{
    public static function notAList(string $configKey): self
    {
        return new self(sprintf(
            'The [%s] configuration must be a list of behavior class names.',
            $configKey,
        ));
    }

    public static function invalidBehavior(string $configKey, mixed $behavior): self
    {
        $name = is_string($behavior) ? $behavior : get_debug_type($behavior);

        return new self(sprintf(
            'The [%s] entry [%s] must be the name of a class implementing %s.',
            $configKey,
            $name,
            PipelineBehaviorInterface::class,
        ));
    }

    public static function reachedEnd(object $message): self
    {
        return new self(sprintf(
            '[%s] reached the end of the pipeline without being handled. Make sure %s is the last behavior of the pipeline.',
            $message::class,
            class_basename(HandlerExecutionBehavior::class),
        ));
    }
}
