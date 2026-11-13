<?php

namespace TheCorps\LaravelCqrs\Facades;

use Illuminate\Support\Facades\Facade;
use TheCorps\LaravelCqrs\Testing\CommandBusFake;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Bus\CommandBusInterface;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\CommandInterface;

/**
 * Facade for the CQRS command bus.
 *
 * @method static TResult dispatch<TResult>(CommandInterface<TResult> $command)
 * @method static CommandBusFake respondWith(class-string $class, mixed $response)
 * @method static void assertDispatched(class-string|\Closure $class, callable|int|null $callback = null)
 * @method static void assertDispatchedTimes(class-string $class, int $times = 1)
 * @method static void assertNotDispatched(class-string|\Closure $class, callable|null $callback = null)
 * @method static void assertNothingDispatched()
 * @method static list<object> dispatched(class-string $class, callable|null $callback = null)
 * @method static bool hasDispatched(class-string $class)
 *
 * @see \TheCorps\LaravelCqrs\Bus\CommandBus
 * @see \TheCorps\LaravelCqrs\Testing\CommandBusFake
 */
class CommandBus extends Facade
{
    /**
     * Replaces the bus with a fake that records commands instead of handling them.
     *
     * @param array<class-string, mixed> $responses What dispatch() returns per command class;
     *                                              a closure is called with the command.
     */
    public static function fake(array $responses = []): CommandBusFake
    {
        static::swap($fake = new CommandBusFake($responses));

        return $fake;
    }

    /**
     * Returns the binding key for the underlying service in the container.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return CommandBusInterface::class;
    }
}
