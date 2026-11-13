<?php

namespace TheCorps\LaravelCqrs\Pipeline;

use Closure;
use TheCorps\LaravelCqrs\Exceptions\InvalidPipelineException;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Pipeline\PipelineBehaviorInterface;

/**
 * CQRS pipeline: composes behaviors into a chain and executes them in sequence.
 *
 * The chain of closures is built once, when the pipeline is created, and reused
 * for every dispatch.
 */
class Pipeline
{
    /** @var Closure(object): mixed */
    private readonly Closure $chain;

    /** @param list<PipelineBehaviorInterface> $behaviors */
    public function __construct(array $behaviors)
    {
        $end = static function (object $command): never {
            throw InvalidPipelineException::reachedEnd($command);
        };

        $this->chain = array_reduce(
            array_reverse($behaviors),
            static fn(Closure $next, PipelineBehaviorInterface $behavior): Closure =>
                static fn(object $command): mixed => $behavior->handle($command, $next),
            $end,
        );
    }

    /**
     * Executes the command or query through the entire behavior chain.
     *
     * @param object $command The command or query to process.
     *
     * @return mixed
     *
     * @throws InvalidPipelineException When no behavior handles the message.
     */
    public function handle(object $command): mixed
    {
        return ($this->chain)($command);
    }
}
