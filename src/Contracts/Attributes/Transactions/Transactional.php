<?php

namespace TheCorps\LaravelCqrs\Contracts\Attributes\Transactions;

use Attribute;

/**
 * Configures the database transaction TransactionBehavior opens for this command.
 *
 * Unset options fall back to cqrs.transactions in the config file. The attribute
 * is inherited by subclasses.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Transactional
{
    /**
     * @param string|null $connection Database connection name, or null for the configured default.
     * @param int|null    $attempts   How many times to run the command when a deadlock occurs, or null for the configured default.
     */
    public function __construct(
        public readonly ?string $connection = null,
        public readonly ?int $attempts = null,
    ) {}
}
