<?php

namespace TheCorps\LaravelCqrs\Tests\Feature;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\ExpectationFailedException;
use TheCorps\LaravelCqrs\Bus\CommandBus as ConcreteCommandBus;
use TheCorps\LaravelCqrs\Facades\QueryBus;
use TheCorps\LaravelCqrs\Facades\CommandBus;
use TheCorps\LaravelCqrs\Tests\TestCase;
use TheCorps\LaravelCqrs\Testing\CommandBusFake;
use TheCorps\LaravelCqrs\Tests\Fixtures\EchoCommand;
use TheCorps\LaravelCqrs\Tests\Fixtures\InvoiceService;
use TheCorps\LaravelCqrs\Tests\Fixtures\FailingCommand;
use TheCorps\LaravelCqrs\Tests\Fixtures\FindNoteQuery;
use TheCorps\LaravelCqrs\Tests\Fixtures\RegisterUserCommand;

final class FakesTest extends TestCase
{
    #[Test]
    public function a_faked_bus_records_commands_without_handling_them(): void
    {
        CommandBus::fake();

        $this->assertNull(CommandBus::dispatch(new FailingCommand()));

        CommandBus::assertDispatched(FailingCommand::class);
        CommandBus::assertDispatchedTimes(FailingCommand::class, 1);
        CommandBus::assertNotDispatched(EchoCommand::class);
    }

    #[Test]
    public function responses_can_be_configured_per_class(): void
    {
        CommandBus::fake([
            EchoCommand::class => 'stubbed',
            RegisterUserCommand::class => fn(RegisterUserCommand $command): string => "fake {$command->email}",
        ]);

        $this->assertSame('stubbed', CommandBus::dispatch(new EchoCommand()));
        $this->assertSame('fake b@example.com', CommandBus::dispatch(new RegisterUserCommand('b@example.com')));

        QueryBus::fake()->respondWith(FindNoteQuery::class, 'a note');

        $this->assertSame('a note', QueryBus::dispatch(new FindNoteQuery(1)));
        QueryBus::assertDispatched(fn(FindNoteQuery $query): bool => $query->id === 1);
    }

    #[Test]
    public function assertions_accept_callbacks_counts_and_typed_closures(): void
    {
        CommandBus::fake();

        CommandBus::dispatch(new EchoCommand('a'));
        CommandBus::dispatch(new EchoCommand('b'));

        CommandBus::assertDispatched(EchoCommand::class, 2);
        CommandBus::assertDispatched(EchoCommand::class, fn(EchoCommand $command): bool => $command->value === 'b');
        CommandBus::assertDispatched(fn(EchoCommand $command): bool => $command->value === 'a');
        CommandBus::assertNotDispatched(fn(EchoCommand $command): bool => $command->value === 'c');

        $this->assertCount(1, CommandBus::dispatched(EchoCommand::class, fn(EchoCommand $command): bool => $command->value === 'a'));
        $this->assertTrue(CommandBus::hasDispatched(EchoCommand::class));
    }

    #[Test]
    public function failed_assertions_report_the_class(): void
    {
        CommandBus::fake();

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('The expected [' . EchoCommand::class . '] was not dispatched.');

        CommandBus::assertDispatched(EchoCommand::class);
    }

    #[Test]
    public function assert_nothing_dispatched_lists_what_was_dispatched(): void
    {
        CommandBus::fake();
        CommandBus::assertNothingDispatched();

        CommandBus::dispatch(new EchoCommand());

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('Unexpected messages were dispatched: [' . EchoCommand::class . ']');

        CommandBus::assertNothingDispatched();
    }

    #[Test]
    public function the_fake_replaces_the_bus_for_injected_dependencies_too(): void
    {
        $fake = CommandBus::fake([RegisterUserCommand::class => 'from fake']);

        $this->assertSame('from fake', $this->app->make(InvoiceService::class)->register());
        $this->assertSame($fake, $this->app->make(ConcreteCommandBus::class));
        $this->assertInstanceOf(CommandBusFake::class, $this->app->make(ConcreteCommandBus::class));

        $fake->assertDispatched(fn(RegisterUserCommand $command): bool => $command->email === 'service@example.com');
    }

    #[Test]
    public function a_closure_without_a_class_type_is_rejected(): void
    {
        CommandBus::fake();

        $this->expectException(InvalidArgumentException::class);

        CommandBus::assertDispatched(fn($command): bool => true);
    }
}
