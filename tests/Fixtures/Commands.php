<?php

namespace TheCorps\LaravelCqrs\Tests\Fixtures;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use TheCorps\LaravelCqrs\Facades\CommandBus;
use TheCorps\LaravelCqrs\Contracts\Attributes\Commands\CommandHandler;
use TheCorps\LaravelCqrs\Contracts\Attributes\Commands\CommandValidator;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\CommandInterface;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\CommandHandlerInterface;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\ValidatesCommandInterface;

#[CommandHandler(CreateNoteHandler::class)]
#[CommandValidator(CreateNoteValidator::class)]
final class CreateNoteCommand implements CommandInterface
{
    public function __construct(
        public readonly string $body,
        public readonly bool $failAfterInsert = false,
    ) {}
}

final class CreateNoteHandler implements CommandHandlerInterface
{
    public function handle(CreateNoteCommand $command): int
    {
        $id = (int) DB::table('notes')->insertGetId(['body' => $command->body]);

        if ($command->failAfterInsert) {
            throw new DomainException('Failed after insert.');
        }

        return $id;
    }
}

final class CreateNoteValidator implements ValidatesCommandInterface
{
    /** @var list<int> Transaction level observed by each validate() call. */
    public static array $transactionLevels = [];

    public function validate(object $command): void
    {
        self::$transactionLevels[] = DB::transactionLevel();

        if ($command instanceof CreateNoteCommand && $command->body === '') {
            throw ValidationException::withMessages(['body' => 'The body is required.']);
        }
    }
}

#[CommandHandler(EchoHandler::class)]
final class EchoCommand implements CommandInterface
{
    public function __construct(public readonly string $value = 'ok') {}
}

final class EchoHandler implements CommandHandlerInterface
{
    public function handle(EchoCommand $command): string
    {
        return $command->value;
    }
}

#[CommandHandler(FailingHandler::class)]
final class FailingCommand implements CommandInterface {}

final class FailingHandler implements CommandHandlerInterface
{
    public function handle(FailingCommand $command): never
    {
        throw new DomainException('Boom.');
    }
}

#[CommandHandler(OuterHandler::class)]
final class OuterCommand implements CommandInterface {}

final class OuterHandler implements CommandHandlerInterface
{
    public function handle(OuterCommand $command): string
    {
        return 'outer+' . CommandBus::dispatch(new EchoCommand('inner'));
    }
}

#[CommandHandler(ParentHandler::class)]
class ParentCommand implements CommandInterface {}

final class ChildCommand extends ParentCommand {}

final class ParentHandler implements CommandHandlerInterface
{
    public function handle(ParentCommand $command): string
    {
        return $command::class;
    }
}

final class CommandWithoutHandler implements CommandInterface {}

#[CommandHandler('App\\Handlers\\DoesNotExist')]
final class CommandWithMissingHandlerClass implements CommandInterface {}

#[CommandHandler(HandlerWithoutHandleMethod::class)]
final class CommandWithInvalidHandler implements CommandInterface {}

final class HandlerWithoutHandleMethod {}

#[CommandHandler(EchoHandler::class)]
#[CommandValidator('App\\Validators\\DoesNotExist')]
final class CommandWithMissingValidatorClass implements CommandInterface {}

#[CommandHandler(EchoHandler::class)]
#[CommandValidator(HandlerWithoutHandleMethod::class)]
final class CommandWithInvalidValidator implements CommandInterface {}
