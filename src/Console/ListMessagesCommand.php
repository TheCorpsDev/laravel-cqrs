<?php

namespace TheCorps\LaravelCqrs\Console;

use Throwable;
use ReflectionClass;
use Illuminate\Console\Command;
use TheCorps\LaravelCqrs\Support\ClassFinder;
use TheCorps\LaravelCqrs\Pipeline\MetadataResolver;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Queries\QueryInterface;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\CommandInterface;

/**
 * Lists every command and query of the application with its handler and
 * validator, and fails when one of them cannot be resolved.
 *
 * Useful in CI to catch a missing or misspelled handler before it reaches
 * production.
 */
class ListMessagesCommand extends Command
{
    /** @var string */
    protected $signature = 'cqrs:list
        {--path=* : Directories to scan, relative to the application root (default: app)}';

    /** @var string */
    protected $description = 'List CQRS commands and queries with their handlers, and check that each one resolves';

    public function handle(): int
    {
        $conventions = (bool) config('cqrs.conventions', true);
        $resolvers = [
            'Command' => [CommandInterface::class, MetadataResolver::forCommands($conventions)],
            'Query' => [QueryInterface::class, MetadataResolver::forQueries($conventions)],
        ];

        $rows = [];
        $errors = [];

        foreach (ClassFinder::in($this->directories()) as $class) {
            try {
                if (!class_exists($class)) {
                    continue;
                }
            } catch (Throwable) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }

            foreach ($resolvers as $type => [$interface, $resolver]) {
                if (!$reflection->implementsInterface($interface)) {
                    continue;
                }

                $message = $reflection->newInstanceWithoutConstructor();

                try {
                    $rows[] = [$type, $class, $resolver->getHandlerClass($message), $resolver->getValidatorClass($message) ?? '-'];
                } catch (Throwable $exception) {
                    $rows[] = [$type, $class, '<fg=red>error</>', '-'];
                    $errors[] = $exception->getMessage();
                }
            }
        }

        if ($rows === []) {
            $this->components->info('No commands or queries found.');

            return self::SUCCESS;
        }

        $this->table(['Type', 'Class', 'Handler', 'Validator'], $rows);

        foreach ($errors as $error) {
            $this->components->error($error);
        }

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function directories(): array
    {
        $paths = array_values(array_filter(
            (array) $this->option('path'),
            static fn(mixed $path): bool => is_string($path) && $path !== '',
        ));

        if ($paths === []) {
            return [app_path()];
        }

        return array_map(fn(string $path): string => $this->laravel->basePath($path), $paths);
    }
}
