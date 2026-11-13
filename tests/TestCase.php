<?php

namespace TheCorps\LaravelCqrs\Tests;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Orchestra\Testbench\TestCase as Orchestra;
use TheCorps\LaravelCqrs\CqrsServiceProvider;
use TheCorps\LaravelCqrs\Pipeline\MetadataResolver;

abstract class TestCase extends Orchestra
{
    protected TestHandler $logs;

    protected function setUp(): void
    {
        MetadataResolver::flushCache();

        parent::setUp();
    }

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [CqrsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $this->logs = new TestHandler();

        $app['config']->set('logging.channels.cqrs-test', [
            'driver' => 'custom',
            'via' => fn(): Logger => new Logger('cqrs-test', [$this->logs]),
        ]);
        $app['config']->set('cqrs.logging.channel', 'cqrs-test');

        $app['config']->set('database.default', 'testing');
    }

    /** @return list<string> */
    protected function logMessages(): array
    {
        return array_map(
            static fn($record): string => strtoupper($record->level->toPsrLogLevel()) . ' ' . $record->message,
            $this->logs->getRecords(),
        );
    }
}
