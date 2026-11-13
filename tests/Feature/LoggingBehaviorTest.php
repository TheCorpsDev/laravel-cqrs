<?php

namespace TheCorps\LaravelCqrs\Tests\Feature;

use DomainException;
use PHPUnit\Framework\Attributes\Test;
use TheCorps\LaravelCqrs\Bus\CommandBus;
use TheCorps\LaravelCqrs\Tests\Fixtures\EchoCommand;
use TheCorps\LaravelCqrs\Tests\Fixtures\FailingCommand;
use TheCorps\LaravelCqrs\Tests\TestCase;

final class LoggingBehaviorTest extends TestCase
{
    #[Test]
    public function it_logs_dispatch_and_completion_with_class_and_duration(): void
    {
        $this->app->make(CommandBus::class)->dispatch(new EchoCommand());

        $this->assertSame([
            'DEBUG [CQRS] EchoCommand dispatched',
            'DEBUG [CQRS] EchoCommand completed',
        ], $this->logMessages());

        [$dispatched, $completed] = $this->logs->getRecords();

        $this->assertSame(['class' => EchoCommand::class], $dispatched->context);
        $this->assertSame(EchoCommand::class, $completed->context['class']);
        $this->assertIsFloat($completed->context['duration_ms']);
    }

    #[Test]
    public function it_logs_failures_and_rethrows_the_exception(): void
    {
        try {
            $this->app->make(CommandBus::class)->dispatch(new FailingCommand());
            $this->fail('A DomainException was expected.');
        } catch (DomainException $exception) {
            $this->assertSame('Boom.', $exception->getMessage());
        }

        $this->assertSame([
            'DEBUG [CQRS] FailingCommand dispatched',
            'WARNING [CQRS] FailingCommand failed',
        ], $this->logMessages());

        $failed = $this->logs->getRecords()[1];

        $this->assertSame(FailingCommand::class, $failed->context['class']);
        $this->assertSame(DomainException::class, $failed->context['exception']);
        $this->assertSame('Boom.', $failed->context['error']);
        $this->assertIsFloat($failed->context['duration_ms']);
    }

    #[Test]
    public function the_levels_are_configurable(): void
    {
        config([
            'cqrs.logging.level' => 'info',
            'cqrs.logging.failure_level' => 'error',
        ]);

        $bus = $this->app->make(CommandBus::class);
        $bus->dispatch(new EchoCommand());

        try {
            $bus->dispatch(new FailingCommand());
        } catch (DomainException) {
        }

        $this->assertSame([
            'INFO [CQRS] EchoCommand dispatched',
            'INFO [CQRS] EchoCommand completed',
            'INFO [CQRS] FailingCommand dispatched',
            'ERROR [CQRS] FailingCommand failed',
        ], $this->logMessages());
    }
}
