<?php

namespace TheCorps\LaravelCqrs\Bus;

use TheCorps\LaravelCqrs\Events\CommandFailed;
use TheCorps\LaravelCqrs\Events\CommandHandled;
use TheCorps\LaravelCqrs\Events\CommandDispatching;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Bus\CommandBusInterface;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\CommandInterface;

/**
 * CQRS command bus: routes commands through the configured pipeline.
 */
class CommandBus extends Bus implements CommandBusInterface
{
    /**
     * Dispatches a command through the pipeline and returns the result.
     *
     * @template TResult
     *
     * @param CommandInterface<TResult> $command The command to dispatch.
     *
     * @return TResult
     */
    public function dispatch(CommandInterface $command): mixed
    {
        /** @var TResult */
        return $this->send($command, CommandDispatching::class, CommandHandled::class, CommandFailed::class);
    }
}
