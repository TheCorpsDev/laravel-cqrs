<?php

namespace TheCorps\LaravelCqrs\Testing;

use TheCorps\LaravelCqrs\Bus\CommandBus;
use TheCorps\LaravelCqrs\Pipeline\Pipeline;
use TheCorps\LaravelCqrs\Testing\Concerns\RecordsMessages;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\CommandInterface;

/**
 * Command bus test double: records commands instead of handling them.
 *
 * Installed by CommandBus::fake(). It extends the real bus, so code that
 * type-hints CommandBus or CommandBusInterface receives it.
 */
class CommandBusFake extends CommandBus
{
    use RecordsMessages;

    /**
     * @param array<class-string, mixed> $responses What dispatch() returns per command class.
     */
    public function __construct(array $responses = [])
    {
        parent::__construct(new Pipeline([]));

        $this->responses = $responses;
    }

    /**
     * Records the command and returns the configured response, or null.
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
        return $this->record($command);
    }
}
