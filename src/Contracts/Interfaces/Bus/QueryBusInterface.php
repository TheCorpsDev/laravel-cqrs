<?php

namespace TheCorps\LaravelCqrs\Contracts\Interfaces\Bus;

use TheCorps\LaravelCqrs\Contracts\Interfaces\Queries\QueryInterface;

/**
 * Dispatches queries. Type-hint this interface to inject the query bus.
 */
interface QueryBusInterface
{
    /**
     * Dispatches a query and returns what its handler returns.
     *
     * @param QueryInterface $query The query to dispatch.
     *
     * @return mixed
     */
    public function dispatch(QueryInterface $query): mixed;
}
