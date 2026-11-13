<?php

namespace TheCorps\LaravelCqrs\Tests\Feature;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use TheCorps\LaravelCqrs\Bus\QueryBus;
use TheCorps\LaravelCqrs\Bus\CommandBus;
use TheCorps\LaravelCqrs\Tests\TestCase;
use TheCorps\LaravelCqrs\Tests\Fixtures\ExplicitCommand;
use TheCorps\LaravelCqrs\Tests\Fixtures\CountUsersQuery;
use TheCorps\LaravelCqrs\Tests\Fixtures\UnmarkedCommand;
use TheCorps\LaravelCqrs\Tests\Fixtures\ArchiveUserCommand;
use TheCorps\LaravelCqrs\Exceptions\MissingHandlerException;
use TheCorps\LaravelCqrs\Tests\Fixtures\RegisterUserCommand;

final class ConventionsTest extends TestCase
{
    #[Test]
    public function a_handler_named_after_the_command_is_used_when_no_attribute_is_declared(): void
    {
        $this->assertSame('registered a@example.com', $this->app->make(CommandBus::class)->dispatch(new RegisterUserCommand()));
    }

    #[Test]
    public function the_command_suffix_may_be_dropped_from_the_handler_name(): void
    {
        $this->assertSame('archived 1', $this->app->make(CommandBus::class)->dispatch(new ArchiveUserCommand()));
    }

    #[Test]
    public function a_validator_is_found_by_convention_too(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid id.');

        $this->app->make(CommandBus::class)->dispatch(new ArchiveUserCommand(0));
    }

    #[Test]
    public function queries_are_resolved_by_convention(): void
    {
        $this->assertSame(42, $this->app->make(QueryBus::class)->dispatch(new CountUsersQuery()));
    }

    #[Test]
    public function messages_with_attributes_never_pick_up_an_undeclared_validator(): void
    {
        $this->assertSame('explicit', $this->app->make(CommandBus::class)->dispatch(new ExplicitCommand()));
    }

    #[Test]
    public function a_class_that_does_not_implement_the_handler_interface_is_ignored(): void
    {
        $this->expectException(MissingHandlerException::class);
        $this->expectExceptionMessage('No handler was found by naming convention either.');

        $this->app->make(CommandBus::class)->dispatch(new UnmarkedCommand());
    }

    #[Test]
    public function conventions_can_be_disabled(): void
    {
        config(['cqrs.conventions' => false]);

        $this->expectException(MissingHandlerException::class);
        $this->expectExceptionMessage('No #[CommandHandler] attribute found on [' . RegisterUserCommand::class . '] or its parent classes.');

        $this->app->make(CommandBus::class)->dispatch(new RegisterUserCommand());
    }
}
