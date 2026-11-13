<?php

namespace TheCorps\LaravelCqrs\Tests\Feature;

use DomainException;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use TheCorps\LaravelCqrs\Bus\QueryBus;
use TheCorps\LaravelCqrs\Bus\CommandBus;
use TheCorps\LaravelCqrs\Tests\TestCase;
use TheCorps\LaravelCqrs\Events\QueryHandled;
use TheCorps\LaravelCqrs\Events\CommandFailed;
use TheCorps\LaravelCqrs\Events\CommandHandled;
use TheCorps\LaravelCqrs\Events\QueryDispatching;
use TheCorps\LaravelCqrs\Events\CommandDispatching;
use TheCorps\LaravelCqrs\Tests\Fixtures\EchoCommand;
use TheCorps\LaravelCqrs\Tests\Fixtures\FailingCommand;
use TheCorps\LaravelCqrs\Tests\Fixtures\CountUsersQuery;

final class EventsTest extends TestCase
{
    #[Test]
    public function listeners_receive_dispatching_and_handled_events(): void
    {
        $received = [];

        Event::listen(CommandDispatching::class, function (CommandDispatching $event) use (&$received): void {
            $received[] = ['dispatching', $event->message];
        });
        Event::listen(CommandHandled::class, function (CommandHandled $event) use (&$received): void {
            $received[] = ['handled', $event->message, $event->result, $event->durationMs];
        });

        $command = new EchoCommand('done');
        $this->app->make(CommandBus::class)->dispatch($command);

        $this->assertCount(2, $received);
        $this->assertSame(['dispatching', $command], $received[0]);
        $this->assertSame(['handled', $command, 'done'], array_slice($received[1], 0, 3));
        $this->assertIsFloat($received[1][3]);
    }

    #[Test]
    public function a_failed_event_is_fired_and_the_exception_is_rethrown(): void
    {
        $failures = [];

        Event::listen(CommandFailed::class, function (CommandFailed $event) use (&$failures): void {
            $failures[] = $event->exception;
        });

        try {
            $this->app->make(CommandBus::class)->dispatch(new FailingCommand());
            $this->fail('A DomainException was expected.');
        } catch (DomainException $exception) {
            $this->assertSame([$exception], $failures);
        }
    }

    #[Test]
    public function event_fake_records_events_even_after_the_bus_was_used(): void
    {
        $bus = $this->app->make(QueryBus::class);
        $bus->dispatch(new CountUsersQuery());

        Event::fake();

        $bus->dispatch(new CountUsersQuery());

        Event::assertDispatched(QueryDispatching::class);
        Event::assertDispatched(QueryHandled::class, fn(QueryHandled $event): bool => $event->result === 42);
    }

    #[Test]
    public function events_can_be_disabled(): void
    {
        config(['cqrs.events' => false]);
        Event::fake([CommandDispatching::class, CommandHandled::class, CommandFailed::class]);

        $this->app->make(CommandBus::class)->dispatch(new EchoCommand());

        Event::assertNotDispatched(CommandDispatching::class);
        Event::assertNotDispatched(CommandHandled::class);
    }
}
