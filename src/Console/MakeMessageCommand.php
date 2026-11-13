<?php

namespace TheCorps\LaravelCqrs\Console;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Shared implementation of make:cqrs-command and make:cqrs-query.
 *
 * Given "Billing/CreateInvoice" it creates, under the configured namespace
 * (App\Domain by default):
 *
 *     Billing/Commands/CreateInvoice/CreateInvoiceCommand.php
 *     Billing/Commands/CreateInvoice/CreateInvoiceHandler.php
 *     Billing/Commands/CreateInvoice/CreateInvoiceValidator.php   (with --validator)
 */
abstract class MakeMessageCommand extends Command
{
    /**
     * "Command" or "Query".
     */
    abstract protected function kind(): string;

    /**
     * Creates the files of the use case.
     */
    public function handle(Filesystem $files): int
    {
        $name = $this->argument('name');
        $parsed = is_string($name) ? $this->parseName($name) : null;

        if ($parsed === null) {
            $this->error('The name must use the Feature/UseCase format, for example Billing/CreateInvoice.');

            return self::FAILURE;
        }

        [$feature, $useCase] = $parsed;

        $kind = $this->kind();
        $relativeNamespace = implode('\\', [...$feature, Str::plural($kind), $useCase]);
        $namespace = $this->rootNamespace() . '\\' . $relativeNamespace;
        $directory = app_path(str_replace('\\', '/', $this->generatorNamespace() . '\\' . $relativeNamespace));

        $message = $useCase . $kind;
        $handler = $useCase . 'Handler';
        $validator = $this->option('validator') ? $useCase . 'Validator' : null;

        $targets = [
            $message => strtolower($kind),
            $handler => strtolower($kind) . '-handler',
        ];

        if ($validator !== null) {
            $targets[$validator] = strtolower($kind) . '-validator';
        }

        $existing = array_filter(
            array_keys($targets),
            static fn(string $class): bool => $files->exists("{$directory}/{$class}.php"),
        );

        if ($existing !== [] && !$this->option('force')) {
            foreach ($existing as $class) {
                $this->error("{$directory}/{$class}.php already exists. Use --force to overwrite it.");
            }

            return self::FAILURE;
        }

        $files->ensureDirectoryExists($directory);

        foreach ($targets as $class => $stub) {
            $path = "{$directory}/{$class}.php";

            $files->put($path, $this->render($files->get($this->stubPath($stub)), [
                'namespace' => $namespace,
                'class' => $class,
                'message' => $message,
                'handler' => $handler,
                'validatorImport' => $validator === null ? '' : "use TheCorps\\LaravelCqrs\\Contracts\\Attributes\\{$this->attributeNamespace()}\\{$kind}Validator;\n",
                'validatorAttribute' => $validator === null ? '' : "#[{$kind}Validator({$validator}::class)]\n",
            ]));

            $this->components->info("Created [{$path}].");
        }

        return self::SUCCESS;
    }

    /**
     * Splits "Billing/CreateInvoice" into (["Billing"], "CreateInvoice").
     *
     * A trailing "Command" or "Query" on the use case is dropped, so
     * "Billing/CreateInvoiceCommand" gives the same result.
     *
     * @return array{list<string>, string}|null
     */
    private function parseName(string $name): ?array
    {
        $segments = array_values(array_filter(
            explode('/', str_replace('\\', '/', trim($name))),
            static fn(string $segment): bool => $segment !== '',
        ));

        if (count($segments) < 2) {
            return null;
        }

        foreach ($segments as $segment) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $segment) !== 1) {
                return null;
            }
        }

        $segments = array_map(static fn(string $segment): string => Str::studly($segment), $segments);
        $useCase = (string) array_pop($segments);

        if (str_ends_with($useCase, $this->kind()) && $useCase !== $this->kind()) {
            $useCase = substr($useCase, 0, -strlen($this->kind()));
        }

        return [$segments, $useCase];
    }

    /**
     * Full namespace under which use cases are generated, e.g. App\Domain.
     */
    private function rootNamespace(): string
    {
        return rtrim($this->laravel->getNamespace(), '\\') . '\\' . $this->generatorNamespace();
    }

    /**
     * Configured namespace relative to the application namespace, e.g. Domain.
     */
    private function generatorNamespace(): string
    {
        $namespace = config('cqrs.generators.namespace', 'Domain');

        return trim(is_string($namespace) ? $namespace : 'Domain', '\\');
    }

    private function attributeNamespace(): string
    {
        return Str::plural($this->kind());
    }

    /**
     * Uses the application's published stub when present.
     */
    private function stubPath(string $name): string
    {
        $published = $this->laravel->basePath("stubs/cqrs/{$name}.stub");

        return is_file($published) ? $published : dirname(__DIR__, 2) . "/stubs/{$name}.stub";
    }

    /**
     * @param array<string, string> $replacements
     */
    private function render(string $stub, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $stub = str_replace('{{ ' . $key . ' }}', $value, $stub);
        }

        return $stub;
    }
}
