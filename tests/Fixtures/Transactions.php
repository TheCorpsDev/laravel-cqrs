<?php

namespace TheCorps\LaravelCqrs\Tests\Fixtures;

use Exception;
use Illuminate\Support\Facades\DB;
use TheCorps\LaravelCqrs\Contracts\Attributes\Commands\CommandHandler;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\CommandInterface;
use TheCorps\LaravelCqrs\Contracts\Attributes\Transactions\Transactional;
use TheCorps\LaravelCqrs\Contracts\Attributes\Transactions\WithoutTransaction;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\CommandHandlerInterface;

#[CommandHandler(TransactionLevelHandler::class)]
final class TransactionLevelCommand implements CommandInterface {}

#[CommandHandler(TransactionLevelHandler::class)]
#[WithoutTransaction]
final class NoTransactionCommand implements CommandInterface {}

#[CommandHandler(TransactionLevelHandler::class)]
#[Transactional(connection: 'secondary')]
final class SecondaryConnectionCommand implements CommandInterface {}

final class TransactionLevelHandler implements CommandHandlerInterface
{
    /** @return array{default: int, secondary: int} */
    public function handle(object $command): array
    {
        return [
            'default' => DB::connection()->transactionLevel(),
            'secondary' => DB::connection('secondary')->transactionLevel(),
        ];
    }
}

#[CommandHandler(DeadlockOnceHandler::class)]
#[Transactional(attempts: 3)]
final class RetriedCommand implements CommandInterface {}

#[CommandHandler(DeadlockOnceHandler::class)]
final class NotRetriedCommand implements CommandInterface {}

final class DeadlockOnceHandler implements CommandHandlerInterface
{
    public static int $calls = 0;

    public function handle(object $command): int
    {
        if (++self::$calls === 1) {
            throw new Exception('Deadlock found when trying to get lock; try restarting transaction');
        }

        return self::$calls;
    }
}
