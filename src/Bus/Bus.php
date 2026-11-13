<?php

namespace TheCorps\LaravelCqrs\Bus;

use Closure;
use Throwable;
use Illuminate\Support\Testing\Fakes\Fake;
use TheCorps\LaravelCqrs\Pipeline\Pipeline;
use Illuminate\Contracts\Events\Dispatcher;
use TheCorps\LaravelCqrs\Events\QueryFailed;
use TheCorps\LaravelCqrs\Events\QueryHandled;
use TheCorps\LaravelCqrs\Events\CommandFailed;
use TheCorps\LaravelCqrs\Events\CommandHandled;
use TheCorps\LaravelCqrs\Events\QueryDispatching;
use TheCorps\LaravelCqrs\Events\CommandDispatching;

/**
 * Shared implementation of the command and query buses: runs a message through
 * the pipeline and, when an event dispatcher is given, fires the bus events.
 */
abstract class Bus
{
    /**
     * @param Pipeline                                   $pipeline The behavior pipeline to pass messages through.
     * @param Dispatcher|(Closure(): ?Dispatcher)|null   $events   Event dispatcher, a closure returning it
     *                                                             (resolved on every dispatch, so Event::fake()
     *                                                             is honoured), or null to fire no events.
     */
    public function __construct(
        private readonly Pipeline $pipeline,
        private readonly Dispatcher|Closure|null $events = null,
    ) {}

    /**
     * Sends the message through the pipeline, firing the given events around it.
     *
     * Events without listeners are not even instantiated.
     *
     * @param object                                               $message     The command or query.
     * @param class-string<CommandDispatching|QueryDispatching>    $dispatching Event fired before the pipeline.
     * @param class-string<CommandHandled|QueryHandled>            $handled     Event fired after a successful run.
     * @param class-string<CommandFailed|QueryFailed>              $failed      Event fired when the pipeline throws.
     *
     * @return mixed
     *
     * @throws Throwable Whatever the pipeline throws.
     */
    protected function send(object $message, string $dispatching, string $handled, string $failed): mixed
    {
        $events = $this->events instanceof Closure ? ($this->events)() : $this->events;

        if ($events === null) {
            return $this->pipeline->handle($message);
        }

        if ($this->shouldFire($events, $dispatching)) {
            $events->dispatch(new $dispatching($message));
        }

        $startedAt = hrtime(true);

        try {
            $result = $this->pipeline->handle($message);
        } catch (Throwable $exception) {
            if ($this->shouldFire($events, $failed)) {
                $events->dispatch(new $failed($message, $exception, $this->elapsedMilliseconds($startedAt)));
            }

            throw $exception;
        }

        if ($this->shouldFire($events, $handled)) {
            $events->dispatch(new $handled($message, $result, $this->elapsedMilliseconds($startedAt)));
        }

        return $result;
    }

    /**
     * Events nobody listens to are skipped, except on a test fake (Event::fake()),
     * which must record every event to support its assertions.
     */
    private function shouldFire(Dispatcher $events, string $event): bool
    {
        return $events instanceof Fake || $events->hasListeners($event);
    }

    private function elapsedMilliseconds(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1_000_000, 2);
    }
}
