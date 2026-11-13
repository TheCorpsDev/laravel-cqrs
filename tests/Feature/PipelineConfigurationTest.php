<?php

namespace TheCorps\LaravelCqrs\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use TheCorps\LaravelCqrs\Bus\QueryBus;
use TheCorps\LaravelCqrs\Bus\CommandBus;
use TheCorps\LaravelCqrs\Tests\TestCase;
use TheCorps\LaravelCqrs\CqrsServiceProvider;
use TheCorps\LaravelCqrs\Tests\Fixtures\EchoCommand;
use TheCorps\LaravelCqrs\Tests\Fixtures\EchoHandler;
use TheCorps\LaravelCqrs\Tests\Fixtures\NotABehavior;
use TheCorps\LaravelCqrs\Tests\Fixtures\ClockBehavior;
use TheCorps\LaravelCqrs\Tests\Fixtures\CountingBehavior;
use TheCorps\LaravelCqrs\Exceptions\InvalidPipelineException;
use TheCorps\LaravelCqrs\Tests\Fixtures\HandlerNameBehavior;
use TheCorps\LaravelCqrs\Tests\Fixtures\AppBindingsServiceProvider;
use TheCorps\LaravelCqrs\Tests\Fixtures\AuditedValidationBehavior;
use TheCorps\LaravelCqrs\Pipeline\Behaviors\LoggingBehavior;
use TheCorps\LaravelCqrs\Pipeline\Behaviors\ValidationBehavior;
use TheCorps\LaravelCqrs\Pipeline\Behaviors\HandlerExecutionBehavior;

final class PipelineConfigurationTest extends TestCase
{
    protected function setUp(): void
    {
        ClockBehavior::$seen = [];
        CountingBehavior::$instances = 0;
        AuditedValidationBehavior::$calls = 0;
        HandlerNameBehavior::$handlers = [];

        parent::setUp();
    }

    /**
     * The application provider is registered after the package provider, as
     * Laravel does with package discovery.
     */
    protected function getPackageProviders($app): array
    {
        return [CqrsServiceProvider::class, AppBindingsServiceProvider::class];
    }

    /**
     * In a real application config/cqrs.php is loaded before any provider is
     * registered, so the pipeline is configured here rather than in
     * defineEnvironment(), which Testbench runs after registration.
     */
    protected function resolveApplicationConfiguration($app): void
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('cqrs.command_pipeline', [
            CountingBehavior::class,
            ClockBehavior::class,
            ValidationBehavior::class,
            HandlerExecutionBehavior::class,
        ]);
    }

    #[Test]
    public function behaviors_may_depend_on_bindings_registered_by_later_providers(): void
    {
        $this->assertSame('ok', $this->app->make(CommandBus::class)->dispatch(new EchoCommand()));
        $this->assertSame(['2026-01-01'], ClockBehavior::$seen);
    }

    #[Test]
    public function behaviors_are_not_built_until_the_bus_is_used(): void
    {
        $this->assertSame(0, CountingBehavior::$instances);

        $bus = $this->app->make(CommandBus::class);
        $bus->dispatch(new EchoCommand());
        $bus->dispatch(new EchoCommand());

        $this->assertSame(1, CountingBehavior::$instances);
    }

    #[Test]
    public function configuration_changed_before_the_first_dispatch_is_honoured(): void
    {
        config(['cqrs.command_pipeline' => [LoggingBehavior::class, HandlerExecutionBehavior::class]]);

        $this->app->make(CommandBus::class)->dispatch(new EchoCommand());

        $this->assertSame(0, CountingBehavior::$instances);
        $this->assertSame([], ClockBehavior::$seen);
        $this->assertCount(2, $this->logMessages());
    }

    #[Test]
    public function a_pipeline_without_handler_execution_throws_instead_of_returning_null(): void
    {
        config(['cqrs.command_pipeline' => [LoggingBehavior::class, ValidationBehavior::class]]);

        $this->expectException(InvalidPipelineException::class);
        $this->expectExceptionMessage(EchoCommand::class . '] reached the end of the pipeline without being handled');

        $this->app->make(CommandBus::class)->dispatch(new EchoCommand());
    }

    #[Test]
    public function built_in_behaviors_can_be_extended(): void
    {
        config(['cqrs.command_pipeline' => [AuditedValidationBehavior::class, HandlerExecutionBehavior::class]]);

        $this->assertSame('ok', $this->app->make(CommandBus::class)->dispatch(new EchoCommand()));
        $this->assertSame(1, AuditedValidationBehavior::$calls);
    }

    #[Test]
    public function custom_behaviors_receive_the_metadata_resolver_of_their_pipeline(): void
    {
        config(['cqrs.command_pipeline' => [HandlerNameBehavior::class, HandlerExecutionBehavior::class]]);

        $this->app->make(CommandBus::class)->dispatch(new EchoCommand());

        $this->assertSame([EchoHandler::class], HandlerNameBehavior::$handlers);
    }

    #[Test]
    public function a_class_that_is_not_a_behavior_is_rejected(): void
    {
        config(['cqrs.command_pipeline' => [NotABehavior::class, HandlerExecutionBehavior::class]]);

        $this->expectException(InvalidPipelineException::class);
        $this->expectExceptionMessage('[cqrs.command_pipeline] entry [' . NotABehavior::class . ']');

        $this->app->make(CommandBus::class);
    }

    #[Test]
    public function an_unknown_class_is_rejected(): void
    {
        config(['cqrs.query_pipeline' => ['App\\Behaviors\\Missing']]);

        $this->expectException(InvalidPipelineException::class);
        $this->expectExceptionMessage('[cqrs.query_pipeline] entry [App\\Behaviors\\Missing]');

        $this->app->make(QueryBus::class);
    }

    #[Test]
    public function a_pipeline_configuration_that_is_not_a_list_is_rejected(): void
    {
        config(['cqrs.command_pipeline' => null]);

        $this->expectException(InvalidPipelineException::class);
        $this->expectExceptionMessage('The [cqrs.command_pipeline] configuration must be a list');

        $this->app->make(CommandBus::class);
    }
}
