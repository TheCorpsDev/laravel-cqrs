<?php

namespace TheCorps\LaravelCqrs\Tests\Fixtures;

use Closure;
use Illuminate\Support\ServiceProvider;
use TheCorps\LaravelCqrs\Pipeline\MetadataResolver;
use TheCorps\LaravelCqrs\Pipeline\Behaviors\ValidationBehavior;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Pipeline\PipelineBehaviorInterface;

/** A dependency bound by an application provider registered after the package. */
interface Clock
{
    public function now(): string;
}

final class FixedClock implements Clock
{
    public function now(): string
    {
        return '2026-01-01';
    }
}

final class AppBindingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Clock::class, FixedClock::class);
    }
}

/** Custom behavior that depends on a binding from the application. */
final class ClockBehavior implements PipelineBehaviorInterface
{
    /** @var list<string> */
    public static array $seen = [];

    public function __construct(private readonly Clock $clock) {}

    public function handle(object $command, Closure $next): mixed
    {
        self::$seen[] = $this->clock->now();

        return $next($command);
    }
}

/** Counts how many times it is constructed. */
final class CountingBehavior implements PipelineBehaviorInterface
{
    public static int $instances = 0;

    public function __construct()
    {
        self::$instances++;
    }

    public function handle(object $command, Closure $next): mixed
    {
        return $next($command);
    }
}

/** Subclass of a built-in behavior, which needs the pipeline's MetadataResolver. */
final class AuditedValidationBehavior extends ValidationBehavior
{
    public static int $calls = 0;

    public function handle(object $command, Closure $next): mixed
    {
        self::$calls++;

        return parent::handle($command, $next);
    }
}

/** Custom behavior that asks for the resolver under a different parameter name. */
final class HandlerNameBehavior implements PipelineBehaviorInterface
{
    /** @var list<string> */
    public static array $handlers = [];

    public function __construct(private readonly MetadataResolver $metadata) {}

    public function handle(object $command, Closure $next): mixed
    {
        self::$handlers[] = $this->metadata->getHandlerClass($command);

        return $next($command);
    }
}

final class NotABehavior {}
