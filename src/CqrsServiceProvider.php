<?php

namespace TheCorps\LaravelCqrs;

use ReflectionClass;
use ReflectionNamedType;
use TheCorps\LaravelCqrs\Bus\QueryBus;
use Illuminate\Support\ServiceProvider;
use TheCorps\LaravelCqrs\Bus\CommandBus;
use TheCorps\LaravelCqrs\Pipeline\Pipeline;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Config\Repository;
use TheCorps\LaravelCqrs\Pipeline\MetadataResolver;
use TheCorps\LaravelCqrs\Console\InstallAgentRulesCommand;
use TheCorps\LaravelCqrs\Exceptions\InvalidPipelineException;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Pipeline\PipelineBehaviorInterface;

/**
 * Service provider that registers the CommandBus and QueryBus with their
 * respective pipelines in the application container.
 *
 * Pipelines are built lazily, the first time a bus is resolved, so that
 * behaviors can depend on bindings registered by any other service provider.
 */
class CqrsServiceProvider extends ServiceProvider
{
    /**
     * Registers the CQRS bindings in the application container.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/cqrs.php', 'cqrs');

        $this->app->singleton(
            CommandBus::class,
            fn(Container $app): CommandBus => new CommandBus($this->makePipeline(
                $app,
                'cqrs.command_pipeline',
                MetadataResolver::forCommands($this->conventions($app)),
            ))
        );

        $this->app->singleton(
            QueryBus::class,
            fn(Container $app): QueryBus => new QueryBus($this->makePipeline(
                $app,
                'cqrs.query_pipeline',
                MetadataResolver::forQueries($this->conventions($app)),
            ))
        );
    }

    /**
     * Bootstraps package services and publishes the configuration file.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallAgentRulesCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/cqrs.php' => $this->app->configPath('cqrs.php'),
            ], 'cqrs-config');
        }
    }

    /**
     * Whether handlers and validators may be found by naming convention.
     */
    private function conventions(Container $app): bool
    {
        return (bool) $this->config($app)->get('cqrs.conventions', true);
    }

    private function config(Container $app): Repository
    {
        $config = $app->make('config');

        if (!$config instanceof Repository) {
            throw new \LogicException('The config repository is not available.');
        }

        return $config;
    }

    /**
     * Builds a pipeline from the behavior classes listed under the given config key.
     *
     * Every behavior is resolved from the container. A behavior whose constructor
     * asks for a MetadataResolver receives the resolver of this pipeline, so the
     * built-in behaviors and their subclasses work for both commands and queries.
     *
     * @param Container        $app       The application container.
     * @param string           $configKey The config key holding the behavior list.
     * @param MetadataResolver $resolver  The resolver of this pipeline.
     *
     * @return Pipeline
     *
     * @throws InvalidPipelineException When the configuration is not a valid list of behaviors.
     */
    private function makePipeline(Container $app, string $configKey, MetadataResolver $resolver): Pipeline
    {
        $classes = $this->config($app)->get($configKey);

        if (!is_array($classes) || !array_is_list($classes)) {
            throw InvalidPipelineException::notAList($configKey);
        }

        $behaviors = [];

        foreach ($classes as $class) {
            if (!is_string($class) || !class_exists($class)) {
                throw InvalidPipelineException::invalidBehavior($configKey, $class);
            }

            $behavior = $app->make($class, $this->resolverParameter($class, $resolver));

            if (!$behavior instanceof PipelineBehaviorInterface) {
                throw InvalidPipelineException::invalidBehavior($configKey, $class);
            }

            $behaviors[] = $behavior;
        }

        return new Pipeline($behaviors);
    }

    /**
     * Returns the container parameter override that injects the pipeline's
     * MetadataResolver into the behavior constructor, if it asks for one.
     *
     * @param class-string     $class    The behavior class.
     * @param MetadataResolver $resolver The resolver of the pipeline.
     *
     * @return array<string, MetadataResolver>
     */
    private function resolverParameter(string $class, MetadataResolver $resolver): array
    {
        $constructor = (new ReflectionClass($class))->getConstructor();

        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && is_a($type->getName(), MetadataResolver::class, true)) {
                return [$parameter->getName() => $resolver];
            }
        }

        return [];
    }
}
