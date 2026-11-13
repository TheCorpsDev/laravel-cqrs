<?php

namespace TheCorps\LaravelCqrs\Testing\Concerns;

use Closure;
use ReflectionFunction;
use ReflectionNamedType;
use InvalidArgumentException;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Records dispatched messages and provides assertions on them.
 *
 * Shared by CommandBusFake and QueryBusFake.
 */
trait RecordsMessages
{
    /** @var list<object> */
    private array $recorded = [];

    /** @var array<class-string, mixed> */
    private array $responses = [];

    /**
     * Sets what dispatch() returns for a message class. A closure is called with
     * the message and its return value is used.
     *
     * @param class-string $class    The command or query class.
     * @param mixed        $response The value to return, or a closure producing it.
     *
     * @return $this
     */
    public function respondWith(string $class, mixed $response): static
    {
        $this->responses[$class] = $response;

        return $this;
    }

    /**
     * Returns the dispatched messages of the given class, optionally filtered.
     *
     * @template T of object
     *
     * @param class-string<T>             $class    The command or query class.
     * @param (callable(T): bool)|null    $callback Filter on the message.
     *
     * @return list<T>
     */
    public function dispatched(string $class, ?callable $callback = null): array
    {
        $messages = array_values(array_filter(
            $this->recorded,
            static fn(object $message): bool => $message instanceof $class,
        ));

        if ($callback !== null) {
            $messages = array_values(array_filter($messages, static fn(object $message): bool => (bool) $callback($message)));
        }

        /** @var list<T> */
        return $messages;
    }

    /**
     * Whether a message of the given class was dispatched.
     *
     * @param class-string $class The command or query class.
     */
    public function hasDispatched(string $class): bool
    {
        return $this->dispatched($class) !== [];
    }

    /**
     * Asserts that a message was dispatched.
     *
     * Accepts a class name, optionally with a filter callback or an exact number
     * of times, or a closure whose first parameter type names the class.
     *
     * @param class-string|Closure          $class    The class, or a typed closure.
     * @param (callable(object): bool)|int|null $callback Filter, or expected number of dispatches.
     */
    public function assertDispatched(string|Closure $class, callable|int|null $callback = null): void
    {
        [$class, $callback] = $this->normalizeAssertion($class, $callback);

        if (is_int($callback)) {
            $this->assertDispatchedTimes($class, $callback);

            return;
        }

        PHPUnit::assertTrue(
            $this->dispatched($class, $callback) !== [],
            "The expected [{$class}] was not dispatched.",
        );
    }

    /**
     * Asserts that a message was dispatched exactly the given number of times.
     *
     * @param class-string $class The command or query class.
     */
    public function assertDispatchedTimes(string $class, int $times = 1): void
    {
        $count = count($this->dispatched($class));

        PHPUnit::assertSame(
            $times,
            $count,
            "The expected [{$class}] was dispatched {$count} times instead of {$times} times.",
        );
    }

    /**
     * Asserts that a message was not dispatched.
     *
     * @param class-string|Closure     $class    The class, or a typed closure.
     * @param (callable(object): bool)|null $callback Filter on the message.
     */
    public function assertNotDispatched(string|Closure $class, ?callable $callback = null): void
    {
        [$class, $callback] = $this->normalizeAssertion($class, $callback);

        PHPUnit::assertTrue(
            $this->dispatched($class, is_callable($callback) ? $callback : null) === [],
            "The unexpected [{$class}] was dispatched.",
        );
    }

    /**
     * Asserts that nothing was dispatched.
     */
    public function assertNothingDispatched(): void
    {
        $classes = implode(', ', array_unique(array_map(static fn(object $message): string => $message::class, $this->recorded)));

        PHPUnit::assertSame([], $this->recorded, "Unexpected messages were dispatched: [{$classes}].");
    }

    /**
     * Records the message and returns the configured response.
     */
    private function record(object $message): mixed
    {
        $this->recorded[] = $message;

        $response = $this->responses[$message::class] ?? null;

        return $response instanceof Closure ? $response($message) : $response;
    }

    /**
     * @param class-string|Closure              $class
     * @param (callable(object): bool)|int|null $callback
     *
     * @return array{class-string, (callable(object): bool)|int|null}
     */
    private function normalizeAssertion(string|Closure $class, callable|int|null $callback): array
    {
        if ($class instanceof Closure) {
            $parameters = (new ReflectionFunction($class))->getParameters();
            $type = isset($parameters[0]) ? $parameters[0]->getType() : null;

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin() || !class_exists($type->getName())) {
                throw new InvalidArgumentException('The closure must type-hint the command or query class as its first parameter.');
            }

            return [$type->getName(), $class];
        }

        return [$class, $callback];
    }
}
