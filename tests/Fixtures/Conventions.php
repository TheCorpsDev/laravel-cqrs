<?php

namespace TheCorps\LaravelCqrs\Tests\Fixtures;

use TheCorps\LaravelCqrs\Contracts\Attributes\Commands\CommandHandler;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Queries\QueryInterface;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\CommandInterface;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Queries\QueryHandlerInterface;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\CommandHandlerInterface;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\ValidatesCommandInterface;

/** Handled by RegisterUserCommandHandler, found by convention. */
final class RegisterUserCommand implements CommandInterface
{
    public function __construct(public readonly string $email = 'a@example.com') {}
}

final class RegisterUserCommandHandler implements CommandHandlerInterface
{
    public function handle(RegisterUserCommand $command): string
    {
        return 'registered ' . $command->email;
    }
}

/** Handled by ArchiveUserHandler (suffix stripped), validated by ArchiveUserValidator. */
final class ArchiveUserCommand implements CommandInterface
{
    public function __construct(public readonly int $id = 1) {}
}

final class ArchiveUserHandler implements CommandHandlerInterface
{
    public function handle(ArchiveUserCommand $command): string
    {
        return 'archived ' . $command->id;
    }
}

final class ArchiveUserValidator implements ValidatesCommandInterface
{
    public function validate(object $command): void
    {
        if ($command instanceof ArchiveUserCommand && $command->id < 1) {
            throw new \InvalidArgumentException('Invalid id.');
        }
    }
}

/** Declares its handler explicitly; the same-named validator must not run. */
#[CommandHandler(ExplicitHandler::class)]
final class ExplicitCommand implements CommandInterface {}

final class ExplicitHandler implements CommandHandlerInterface
{
    public function handle(ExplicitCommand $command): string
    {
        return 'explicit';
    }
}

final class ExplicitCommandValidator implements ValidatesCommandInterface
{
    public function validate(object $command): void
    {
        throw new \LogicException('This validator is not declared and must not run.');
    }
}

/** The class named like a handler does not implement the handler interface. */
final class UnmarkedCommand implements CommandInterface {}

final class UnmarkedCommandHandler
{
    public function handle(UnmarkedCommand $command): string
    {
        return 'should not be used';
    }
}

final class CountUsersQuery implements QueryInterface {}

final class CountUsersHandler implements QueryHandlerInterface
{
    public function handle(CountUsersQuery $query): int
    {
        return 42;
    }
}
