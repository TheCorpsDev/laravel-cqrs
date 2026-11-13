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
     * @param CommandInterface $command The command to dispatch.
     *
     * @return mixed
     */
    public function dispatch(CommandInterface $command): mixed;
}
