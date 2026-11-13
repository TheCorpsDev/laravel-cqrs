<?php

namespace TheCorps\LaravelCqrs\Pipeline\Behaviors;

use Closure;
use Illuminate\Database\DatabaseManager;
use TheCorps\LaravelCqrs\Support\AttributeReader;
use TheCorps\LaravelCqrs\Contracts\Attributes\Transactions\Transactional;
use TheCorps\LaravelCqrs\Contracts\Attributes\Transactions\WithoutTransaction;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Pipeline\PipelineBehaviorInterface;

/**
 * Behavior that wraps the next behavior in the chain inside a database transaction.
 *
 * Any exception thrown downstream will automatically roll back the transaction.
 * This behavior should only be included in the command pipeline, not the query pipeline.
 *
 * A command can skip the transaction with #[WithoutTransaction], or choose the
 * connection and the number of deadlock retries with #[Transactional]. The
 * defaults come from cqrs.transactions.
 */
class TransactionBehavior implements PipelineBehaviorInterface
{
    /**
     * Wraps the execution of the next behavior in a database transaction.
     *
     * @param object  $command The command or query passing through the pipeline.
     * @param Closure $next    The next behavior in the chain.
     *
     * @return mixed
     */
    public function handle(object $command, Closure $next): mixed
    {
        if (AttributeReader::first($command, WithoutTransaction::class) !== null) {
            return $next($command);
        }

        $options = AttributeReader::first($command, Transactional::class);

        $connection = $options->connection ?? $this->config('connection');
        $attempts = $options->attempts ?? $this->config('attempts');

        return app(DatabaseManager::class)
            ->connection(is_string($connection) ? $connection : null)
            ->transaction(
                fn(): mixed => $next($command),
                is_int($attempts) && $attempts > 0 ? $attempts : 1,
            );
    }

    /**
     * Reads an option from cqrs.transactions.
     */
    private function config(string $key): mixed
    {
        return config("cqrs.transactions.{$key}");
    }
}
