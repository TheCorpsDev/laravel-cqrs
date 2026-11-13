<?php

namespace TheCorps\LaravelCqrs\Tests\Feature;

use Exception;
use PHPUnit\Framework\Attributes\Test;
use TheCorps\LaravelCqrs\Bus\CommandBus;
use TheCorps\LaravelCqrs\Tests\TestCase;
use TheCorps\LaravelCqrs\Tests\Fixtures\RetriedCommand;
use TheCorps\LaravelCqrs\Tests\Fixtures\NotRetriedCommand;
use TheCorps\LaravelCqrs\Tests\Fixtures\DeadlockOnceHandler;
use TheCorps\LaravelCqrs\Tests\Fixtures\NoTransactionCommand;
use TheCorps\LaravelCqrs\Tests\Fixtures\TransactionLevelCommand;
use TheCorps\LaravelCqrs\Tests\Fixtures\SecondaryConnectionCommand;

final class TransactionAttributesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DeadlockOnceHandler::$calls = 0;
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.connections.secondary', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    #[Test]
    public function commands_run_in_a_transaction_on_the_default_connection(): void
    {
        $this->assertSame(
            ['default' => 1, 'secondary' => 0],
            $this->app->make(CommandBus::class)->dispatch(new TransactionLevelCommand()),
        );
    }

    #[Test]
    public function without_transaction_skips_the_transaction(): void
    {
        $this->assertSame(
            ['default' => 0, 'secondary' => 0],
            $this->app->make(CommandBus::class)->dispatch(new NoTransactionCommand()),
        );
    }

    #[Test]
    public function transactional_selects_the_connection(): void
    {
        $this->assertSame(
            ['default' => 0, 'secondary' => 1],
            $this->app->make(CommandBus::class)->dispatch(new SecondaryConnectionCommand()),
        );
    }

    #[Test]
    public function the_default_connection_can_be_configured(): void
    {
        config(['cqrs.transactions.connection' => 'secondary']);

        $this->assertSame(
            ['default' => 0, 'secondary' => 1],
            $this->app->make(CommandBus::class)->dispatch(new TransactionLevelCommand()),
        );
    }

    #[Test]
    public function transactional_attempts_retry_the_command_after_a_deadlock(): void
    {
        $this->assertSame(2, $this->app->make(CommandBus::class)->dispatch(new RetriedCommand()));
    }

    #[Test]
    public function commands_are_not_retried_by_default(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Deadlock found');

        $this->app->make(CommandBus::class)->dispatch(new NotRetriedCommand());
    }
}
