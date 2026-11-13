<?php

namespace TheCorps\LaravelCqrs\Bus;

use TheCorps\LaravelCqrs\Events\QueryFailed;
use TheCorps\LaravelCqrs\Events\QueryHandled;
use TheCorps\LaravelCqrs\Events\QueryDispatching;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Bus\QueryBusInterface;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Queries\QueryInterface;

/**
 * CQRS query bus: routes queries through the configured pipeline.
 */
class QueryBus extends Bus implements QueryBusInterface
{
    /**
     * Dispatches a query through the pipeline and returns the result.
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
        return $this->send($query, QueryDispatching::class, QueryHandled::class, QueryFailed::class);
    }
}
