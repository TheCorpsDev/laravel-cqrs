<?php

namespace TheCorps\LaravelCqrs\Console;

/**
 * php artisan make:cqrs-query Billing/GetInvoice [--validator] [--force]
 */
class MakeQueryCommand extends MakeMessageCommand
{
    /** @var string */
    protected $signature = 'make:cqrs-query
        {name : The feature and use case, for example Billing/GetInvoice}
        {--validator : Also create a validator}
        {--force : Overwrite existing files}';

    /** @var string */
    protected $description = 'Create a CQRS query with its handler and, optionally, its validator';

    protected function kind(): string
    {
        return 'Query';
    }
}
