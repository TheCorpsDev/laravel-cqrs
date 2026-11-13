<?php

namespace TheCorps\LaravelCqrs\Testing;

use TheCorps\LaravelCqrs\Bus\QueryBus;
use TheCorps\LaravelCqrs\Pipeline\Pipeline;
use TheCorps\LaravelCqrs\Testing\Concerns\RecordsMessages;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Queries\QueryInterface;

/**
 * Query bus test double: records queries instead of handling them.
 *
 * Installed by QueryBus::fake(). It extends the real bus, so code that
 * type-hints QueryBus or QueryBusInterface receives it.
 */
class QueryBusFake extends QueryBus
{
    use RecordsMessages;

    /**
     * @param array<class-string, mixed> $responses What dispatch() returns per query class.
     */
    public function __construct(array $responses = [])
    {
        parent::__construct(new Pipeline([]));

        $this->responses = $responses;
    }

    /**
     * Records the query and returns the configured response, or null.
     *
     * @template TResult
     *
     * @param QueryInterface<TResult> $query The query to dispatch.
     *
     * @return TResult
     */
    public function dispatch(QueryInterface $query): mixed
    {
        /** @var TResult */
        return $this->record($query);
    }
}
