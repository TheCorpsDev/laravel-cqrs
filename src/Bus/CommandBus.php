<?php

namespace TheCorps\LaravelCqrs\Bus;

use TheCorps\LaravelCqrs\Pipeline\Pipeline;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Bus\CommandBusInterface;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\CommandInterface;

/**
 * CQRS command bus: routes commands through the configured pipeline.
 */
class CommandBus implements CommandBusInterface
{
    /**
     * Creates a new command bus instance.
     *
     * @param Pipeline $pipeline The behavior pipeline to pass commands through.
     */
    public function __construct(
        private readonly Pipeline $pipeline,
    ) {}

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
        return $this->pipeline->handle($command);
    }
}
