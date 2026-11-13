<?php

namespace TheCorps\LaravelCqrs\Facades;

use Illuminate\Support\Facades\Facade;
use TheCorps\LaravelCqrs\Testing\QueryBusFake;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Bus\QueryBusInterface;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Queries\QueryInterface;

/**
 * Facade for the CQRS query bus.
 *
 * @method static TResult dispatch<TResult>(QueryInterface<TResult> $query)
 * @method static QueryBusFake respondWith(class-string $class, mixed $response)
 * @method static void assertDispatched(class-string|\Closure $class, callable|int|null $callback = null)
 * @method static void assertDispatchedTimes(class-string $class, int $times = 1)
 * @method static void assertNotDispatched(class-string|\Closure $class, callable|null $callback = null)
 * @method static void assertNothingDispatched()
 * @method static list<object> dispatched(class-string $class, callable|null $callback = null)
 * @method static bool hasDispatched(class-string $class)
 *
 * @see \TheCorps\LaravelCqrs\Bus\QueryBus
 * @see \TheCorps\LaravelCqrs\Testing\QueryBusFake
 */
class QueryBus extends Facade
{
    /**
     * Replaces the bus with a fake that records queries instead of handling them.
     *
     * @param array<class-string, mixed> $responses What dispatch() returns per query class;
     *                                              a closure is called with the query.
     */
    public static function fake(array $responses = []): QueryBusFake
    {
        static::swap($fake = new QueryBusFake($responses));

        return $fake;
    }

    /**
     * Returns the binding key for the underlying service in the container.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return QueryBusInterface::class;
    }
}
