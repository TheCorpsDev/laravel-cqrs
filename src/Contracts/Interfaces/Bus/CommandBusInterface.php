<?php

namespace TheCorps\LaravelCqrs\Contracts\Interfaces\Bus;

use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\CommandInterface;

/**
 * Dispatches commands. Type-hint this interface to inject the command bus.
 */
interface CommandBusInterface
{
    /**
     * Dispatches a command and returns what its handler returns.
     *
     * @template TResult
     *
     * @param CommandInterface<TResult> $command The command to dispatch.
     *
     * @return TResult
     */
    public function dispatch(CommandInterface $command): mixed;
}
