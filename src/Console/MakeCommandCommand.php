<?php

namespace TheCorps\LaravelCqrs\Console;

/**
 * php artisan make:cqrs-command Billing/CreateInvoice [--validator] [--force]
 */
class MakeCommandCommand extends MakeMessageCommand
{
    /** @var string */
    protected $signature = 'make:cqrs-command
        {name : The feature and use case, for example Billing/CreateInvoice}
        {--validator : Also create a validator}
        {--force : Overwrite existing files}';

    /** @var string */
    protected $description = 'Create a CQRS command with its handler and, optionally, its validator';

    protected function kind(): string
    {
        return 'Command';
    }
}
