<?php

namespace TheCorps\LaravelCqrs\Pipeline\Behaviors;

use Closure;
use Throwable;
use Psr\Log\LoggerInterface;
use Illuminate\Log\LogManager;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Pipeline\PipelineBehaviorInterface;

/**
 * Behavior that logs the dispatch, completion and failure of every command or query.
 *
 * The log channel and levels are read from the cqrs.logging config key on every
 * dispatch. Set cqrs.logging.channel to null to use the default Laravel log channel.
 *
 * Every entry carries the fully qualified class name in its context; completion and
 * failure entries also carry the duration in milliseconds. Failures are logged and
 * the exception is re-thrown untouched, so Laravel's exception handler still reports it.
 */
class LoggingBehavior implements PipelineBehaviorInterface
{
    /**
     * Logs a message before and after executing the next behavior in the chain.
     *
     * @param object  $command The command or query passing through the pipeline.
     * @param Closure $next    The next behavior in the chain.
     *
     * @return mixed
     *
     * @throws Throwable Re-throws whatever the rest of the pipeline throws.
     */
    public function handle(object $command, Closure $next): mixed
    {
        $name = class_basename($command);
        $context = ['class' => $command::class];
        $level = $this->level('level', 'debug');

        $this->logger()->log($level, "[CQRS] {$name} dispatched", $context);

        $startedAt = hrtime(true);

        try {
            $result = $next($command);
        } catch (Throwable $exception) {
            $this->logger()->log($this->level('failure_level', 'warning'), "[CQRS] {$name} failed", $context + [
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
                'exception' => $exception::class,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $this->logger()->log($level, "[CQRS] {$name} completed", $context + [
            'duration_ms' => $this->elapsedMilliseconds($startedAt),
        ]);

        return $result;
    }

    /**
     * Returns the logger for the configured channel.
     *
     * @return LoggerInterface
     */
    private function logger(): LoggerInterface
    {
        $channel = config('cqrs.logging.channel');
        $manager = app(LogManager::class);

        return is_string($channel) && $channel !== ''
            ? $manager->channel($channel)
            : $manager;
    }

    /**
     * Reads a log level from the cqrs.logging config.
     *
     * @param string $key     The config key under cqrs.logging.
     * @param string $default The level to use when the key is missing.
     *
     * @return string
     */
    private function level(string $key, string $default): string
    {
        $level = config("cqrs.logging.{$key}", $default);

        return is_string($level) ? $level : $default;
    }

    /**
     * Returns the milliseconds elapsed since the given hrtime() value.
     *
     * @param int $startedAt Value returned by hrtime(true).
     *
     * @return float
     */
    private function elapsedMilliseconds(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1_000_000, 2);
    }
}
