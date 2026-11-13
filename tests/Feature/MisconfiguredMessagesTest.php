<?php

namespace TheCorps\LaravelCqrs\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheCorps\LaravelCqrs\Bus\QueryBus;
use TheCorps\LaravelCqrs\Bus\CommandBus;
use TheCorps\LaravelCqrs\Tests\TestCase;
use TheCorps\LaravelCqrs\Exceptions\CqrsException;
use TheCorps\LaravelCqrs\Tests\Fixtures\QueryWithoutHandler;
use TheCorps\LaravelCqrs\Exceptions\InvalidHandlerException;
use TheCorps\LaravelCqrs\Exceptions\MissingHandlerException;
use TheCorps\LaravelCqrs\Tests\Fixtures\CommandWithoutHandler;
use TheCorps\LaravelCqrs\Exceptions\InvalidValidatorException;
use TheCorps\LaravelCqrs\Tests\Fixtures\CommandWithInvalidHandler;
use TheCorps\LaravelCqrs\Tests\Fixtures\CommandWithInvalidValidator;
use TheCorps\LaravelCqrs\Tests\Fixtures\CommandWithMissingHandlerClass;
use TheCorps\LaravelCqrs\Tests\Fixtures\CommandWithMissingValidatorClass;

final class MisconfiguredMessagesTest extends TestCase
{
    /** @return iterable<string, array{object, class-string<CqrsException>, string}> */
    public static function misconfiguredCommands(): iterable
    {
        yield 'no handler attribute' => [
            new CommandWithoutHandler(),
            MissingHandlerException::class,
            'No #[CommandHandler] attribute found on [' . CommandWithoutHandler::class . '] or its parent classes.',
        ];

        yield 'handler class does not exist' => [
            new CommandWithMissingHandlerClass(),
            InvalidHandlerException::class,
            'The handler [App\\Handlers\\DoesNotExist] declared on [' . CommandWithMissingHandlerClass::class . '] does not exist.',
        ];

        yield 'handler without handle()' => [
            new CommandWithInvalidHandler(),
            InvalidHandlerException::class,
            'has no handle() method.',
        ];

        yield 'validator class does not exist' => [
            new CommandWithMissingValidatorClass(),
            InvalidValidatorException::class,
            'The validator [App\\Validators\\DoesNotExist] declared on [' . CommandWithMissingValidatorClass::class . '] does not exist.',
        ];

        yield 'validator without validate()' => [
            new CommandWithInvalidValidator(),
            InvalidValidatorException::class,
            'has no validate() method.',
        ];
    }

    /** @param class-string<CqrsException> $exception */
    #[Test]
    #[DataProvider('misconfiguredCommands')]
    public function misconfigured_commands_fail_with_a_clear_exception(object $command, string $exception, string $message): void
    {
        $this->expectException($exception);
        $this->expectExceptionMessage($message);

        $this->app->make(CommandBus::class)->dispatch($command);
    }

    #[Test]
    public function a_query_without_handler_fails_with_a_clear_exception(): void
    {
        $this->expectException(MissingHandlerException::class);
        $this->expectExceptionMessage('No #[QueryHandler] attribute found on [' . QueryWithoutHandler::class . ']');

        $this->app->make(QueryBus::class)->dispatch(new QueryWithoutHandler());
    }

    #[Test]
    public function package_exceptions_remain_runtime_exceptions(): void
    {
        $this->assertTrue(is_subclass_of(CqrsException::class, \RuntimeException::class));
    }
}
