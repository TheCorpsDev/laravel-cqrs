<?php

use TheCorps\LaravelCqrs\Pipeline\Behaviors\LoggingBehavior;
use TheCorps\LaravelCqrs\Pipeline\Behaviors\ValidationBehavior;
use TheCorps\LaravelCqrs\Pipeline\Behaviors\TransactionBehavior;
use TheCorps\LaravelCqrs\Pipeline\Behaviors\HandlerExecutionBehavior;

return [

    /*
    |--------------------------------------------------------------------------
    | Command Pipeline
    |--------------------------------------------------------------------------
    |
    | The ordered list of behaviors that every command passes through before
    | reaching its handler. You can add, remove, or reorder behaviors here,
    | including your own custom classes that implement PipelineBehaviorInterface.
    |
    | Built-in behaviors:
    |   - LoggingBehavior — logs dispatch, completion and failure
    |   - ValidationBehavior — runs the CommandValidator if present
    |   - TransactionBehavior — wraps execution in a DB transaction
    |   - HandlerExecutionBehavior — resolves and calls the CommandHandler
    |
    | HandlerExecutionBehavior must be the last entry: a command that reaches
    | the end of the pipeline without being handled throws an exception.
    |
    | Behaviors are resolved from the container the first time the bus is
    | used, so they may depend on anything your service providers register.
    |
    */

    'command_pipeline' => [
        LoggingBehavior::class,
        ValidationBehavior::class,
        TransactionBehavior::class,
        HandlerExecutionBehavior::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Pipeline
    |--------------------------------------------------------------------------
    |
    | The ordered list of behaviors that every query passes through before
    | reaching its handler. Queries intentionally skip logging and transactions
    | by default, but you can add them back or insert custom behaviors.
    |
    */

    'query_pipeline' => [
        ValidationBehavior::class,
        HandlerExecutionBehavior::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Controls how the LoggingBehavior writes messages.
    |
    |   channel — the Laravel log channel to use (null = default channel)
    |   level — PSR-3 level for the "dispatched" and "completed" entries
    |   failure_level — PSR-3 level for the "failed" entry
    |
    | PSR-3 levels: debug, info, notice, warning, error, critical, alert, emergency
    |
    */

    'logging' => [
        'channel' => null,
        'level' => 'debug',
        'failure_level' => 'warning',
    ],


    /*
    |--------------------------------------------------------------------------
    | Transactions
    |--------------------------------------------------------------------------
    |
    | Defaults for the transaction TransactionBehavior opens around a command.
    | A command can override them with #[Transactional(connection, attempts)]
    | or skip the transaction entirely with #[WithoutTransaction].
    |
    |   connection — database connection name (null = default connection)
    |   attempts — how many times to run the command when a deadlock occurs
    |
    */

    'transactions' => [
        'connection' => null,
        'attempts' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Naming conventions
    |--------------------------------------------------------------------------
    |
    | When enabled, a command or query without a #[CommandHandler] or
    | #[QueryHandler] attribute is handled by the class in the same namespace
    | named after it: CreateUserCommand → CreateUserCommandHandler or
    | CreateUserHandler. Its validator, if any, is found the same way. Classes
    | found by convention must implement the matching handler or validator
    | interface. Attributes always take precedence.
    |
    */

    'conventions' => true,

];
