<?php

namespace TheCorps\LaravelCqrs\Contracts\Attributes\Transactions;

use Attribute;

/**
 * Tells TransactionBehavior not to open a database transaction for this command,
 * for example because it does not touch the database or manages transactions itself.
 *
 * It takes precedence over #[Transactional] and is inherited by subclasses.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class WithoutTransaction {}
