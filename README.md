# thecorps/laravel-cqrs

> [🇮🇹 Leggi in italiano](README.it.md)

Lightweight CQRS pipeline infrastructure for Laravel applications, built on PHP 8 attributes.

Provides a `CommandBus` and a `QueryBus` that route your commands and queries through a fully configurable pipeline of behaviors — logging, validation, database transactions, and handler execution — with zero boilerplate in the consuming code.

---

## Requirements

| Dependency | Version            |
| ---------- | ------------------ |
| PHP        | `^8.2`             |
| Laravel    | `^11.0`, `^12.0` or `^13.0` |

---

## Installation

```bash
composer require thecorps/laravel-cqrs
```

Laravel's auto-discovery registers the `CqrsServiceProvider` and the `CommandBus` / `QueryBus` facades automatically. No manual registration needed.

### Publishing the configuration file

```bash
php artisan vendor:publish --tag=cqrs-config
```

This creates `config/cqrs.php` in your application where you can customize the pipeline and logging settings.

---

## How it works

```
CommandBus::dispatch(new CreateUserCommand(...))
    │
    ▼
LoggingBehavior           → logs "[CQRS] CreateUserCommand dispatched"
    │
    ▼
ValidationBehavior        → resolves #[CommandValidator] and calls validate()
    │
    ▼
TransactionBehavior       → wraps everything below in DB::transaction()
    │
    ▼
HandlerExecutionBehavior  → resolves #[CommandHandler] and calls handle()
    │
    ▼
CreateUserCommandHandler::handle(CreateUserCommand $command)
```

Queries follow the same pattern but skip `LoggingBehavior` and `TransactionBehavior` by default.

---

## Usage

### 1 — Command

A command is an immutable DTO that implements `CommandInterface` and declares its handler via the `#[CommandHandler]` attribute. Attributes are also inherited: a command extending a base class that declares `#[CommandHandler]` uses that handler unless it declares its own.

```php
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\CommandInterface;
use TheCorps\LaravelCqrs\Contracts\Attributes\Commands\CommandHandler;

#[CommandHandler(CreateUserCommandHandler::class)]
final class CreateUserCommand implements CommandInterface
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {}
}
```

### 2 — Command Handler

```php
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\CommandHandlerInterface;

class CreateUserCommandHandler implements CommandHandlerInterface
{
    public function handle(CreateUserCommand $command): User
    {
        return User::create([
            'name'  => $command->name,
            'email' => $command->email,
        ]);
    }
}
```

### 3 — Dispatch

```php
use TheCorps\LaravelCqrs\Facades\CommandBus;

$user = CommandBus::dispatch(new CreateUserCommand(
    name:  $request->validated('name'),
    email: $request->validated('email'),
));
```

---

### 4 — Query

```php
use TheCorps\LaravelCqrs\Contracts\Interfaces\Queries\QueryInterface;
use TheCorps\LaravelCqrs\Contracts\Attributes\Queries\QueryHandler;

#[QueryHandler(GetUserQueryHandler::class)]
final class GetUserQuery implements QueryInterface
{
    public function __construct(
        public readonly int $userId,
    ) {}
}
```

```php
use TheCorps\LaravelCqrs\Contracts\Interfaces\Queries\QueryHandlerInterface;

class GetUserQueryHandler implements QueryHandlerInterface
{
    public function handle(GetUserQuery $query): ?User
    {
        return User::find($query->userId);
    }
}
```

```php
use TheCorps\LaravelCqrs\Facades\QueryBus;

$user = QueryBus::dispatch(new GetUserQuery(userId: $id));
```

---

### 5 — Validator (optional)

Validators contain domain rules that go beyond HTTP input validation. They are opt-in: without `#[CommandValidator]`, the `ValidationBehavior` is a no-op.

```php
#[CommandHandler(CreateUserCommandHandler::class)]
#[CommandValidator(CreateUserCommandValidator::class)]
final class CreateUserCommand implements CommandInterface { ... }
```

```php
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\ValidatesCommandInterface;

class CreateUserCommandValidator implements ValidatesCommandInterface
{
    public function validate(object $command): void
    {
        if (User::where('email', $command->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email is already registered.',
            ]);
        }
    }
}
```

In the default command pipeline the validator runs **before** `TransactionBehavior`: a failed validation stops the command without opening a database transaction.

> **Note:** a check like the one above is not atomic. Two concurrent requests can both pass it before either one inserts, even inside a transaction. Keep a unique index on the column as the real guarantee and use the validator for a friendly error message.

---

### 6 — Transactions

`TransactionBehavior` is included in the default command pipeline. You do not need to do anything: every command is automatically wrapped in a `DB::transaction()`. If the handler, or any behavior placed after `TransactionBehavior`, throws an exception, the entire transaction is rolled back. Behaviors placed before it, such as `ValidationBehavior` in the default order, run outside the transaction.

```php
class TransferFundsCommandHandler implements CommandHandlerInterface
{
    public function handle(TransferFundsCommand $command): void
    {
        // Both writes run inside the same DB transaction.
        // If the second one throws, the first is rolled back automatically.
        Account::find($command->fromId)->decrement('balance', $command->amount);
        Account::find($command->toId)->increment('balance', $command->amount);
    }
}
```

To disable automatic transactions, remove `TransactionBehavior` from `command_pipeline` in your published `config/cqrs.php`:

```php
'command_pipeline' => [
    LoggingBehavior::class,
    ValidationBehavior::class,
    // TransactionBehavior::class — removed
    HandlerExecutionBehavior::class,
],
```

> **Note:** Never add `TransactionBehavior` to the query pipeline. Read-only queries do not need transactions.

---

### 7 — Logging

`LoggingBehavior` is included in the default command pipeline. It writes one entry when a command is dispatched and one when it completes, or a `failed` entry if an exception is thrown. The exception is then re-thrown untouched, so Laravel's exception handler still reports it.

```
[CQRS] CreateUserCommand dispatched
[CQRS] CreateUserCommand completed
[CQRS] CreateUserCommand failed
```

Every entry carries the fully qualified class name in its context (`class`). The `completed` and `failed` entries also carry `duration_ms`, and the `failed` entry carries the exception class (`exception`) and message (`error`).

By default it uses the application's default log channel, at `debug` level for dispatch and completion and at `warning` level for failures. All three settings are configurable:

```php
// config/cqrs.php
'logging' => [
    'channel'       => 'daily',  // write to a named Laravel log channel
    'level'         => 'info',   // level of the "dispatched" and "completed" entries
    'failure_level' => 'error',  // level of the "failed" entry
],
```

To disable logging entirely, remove `LoggingBehavior` from `command_pipeline`:

```php
'command_pipeline' => [
    // LoggingBehavior::class — removed
    ValidationBehavior::class,
    TransactionBehavior::class,
    HandlerExecutionBehavior::class,
],
```

---

## Configuration

After publishing, `config/cqrs.php` exposes three configuration areas.

### Pipeline composition

```php
'command_pipeline' => [
    LoggingBehavior::class,
    ValidationBehavior::class,
    TransactionBehavior::class,
    HandlerExecutionBehavior::class,  // must always be last
],

'query_pipeline' => [
    ValidationBehavior::class,
    HandlerExecutionBehavior::class,  // must always be last
],
```

You can add, remove, or reorder behaviors freely. The order in the array is the execution order.

If a command or query reaches the end of the pipeline without being handled, for example because `HandlerExecutionBehavior` was removed, the bus throws an `InvalidPipelineException` instead of silently returning `null`.

### Custom behaviors

Any class implementing `PipelineBehaviorInterface` can be inserted into the pipeline. Behaviors are resolved from the Laravel container the first time a bus is used, so they support constructor injection and may depend on bindings registered by any of your service providers.

```php
use Closure;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Pipeline\PipelineBehaviorInterface;

class RateLimitingBehavior implements PipelineBehaviorInterface
{
    public function __construct(private readonly RateLimiter $limiter) {}

    public function handle(object $command, Closure $next): mixed
    {
        // your cross-cutting logic here
        return $next($command);
    }
}
```

```php
// config/cqrs.php
'command_pipeline' => [
    LoggingBehavior::class,
    RateLimitingBehavior::class,   // ← inserted
    ValidationBehavior::class,
    TransactionBehavior::class,
    HandlerExecutionBehavior::class,
],
```

A behavior whose constructor asks for a `MetadataResolver` receives the resolver of its own pipeline, so it can read the handler or validator of the message it is processing. This also means the built-in behaviors can be extended:

```php
use Closure;
use TheCorps\LaravelCqrs\Pipeline\Behaviors\ValidationBehavior;

class AuditedValidationBehavior extends ValidationBehavior
{
    public function handle(object $command, Closure $next): mixed
    {
        // your extra logic here
        return parent::handle($command, $next);
    }
}
```

The pipeline is built once per bus instance and reused. Changes to `cqrs.command_pipeline` or `cqrs.query_pipeline` made at runtime apply only before the bus is first resolved.

### Logging

```php
'logging' => [
    'channel'       => null,       // null = default Laravel log channel; or 'daily', 'slack', etc.
    'level'         => 'debug',    // PSR-3 level of the "dispatched" and "completed" entries
    'failure_level' => 'warning',  // PSR-3 level of the "failed" entry
],
```

---

## Errors

Misconfigurations fail with a dedicated exception that names the class involved. All of them extend `TheCorps\LaravelCqrs\Exceptions\CqrsException`, which extends `RuntimeException`.

| Exception | Thrown when |
| --- | --- |
| `MissingHandlerException` | the command or query, and its parent classes, declare no `#[CommandHandler]` / `#[QueryHandler]` |
| `InvalidHandlerException` | the declared handler class does not exist or has no `handle()` method |
| `InvalidValidatorException` | the declared validator class does not exist or has no `validate()` method |
| `InvalidPipelineException` | a pipeline entry is not a `PipelineBehaviorInterface` class, the pipeline config is not a list, or a message reaches the end of the pipeline unhandled |

---

## Package structure

```
src/
├── Contracts/
│   ├── Attributes/
│   │   ├── Commands/
│   │   │   ├── CommandHandler.php     ← #[CommandHandler(HandlerClass::class)]
│   │   │   └── CommandValidator.php   ← #[CommandValidator(ValidatorClass::class)]
│   │   └── Queries/
│   │       ├── QueryHandler.php       ← #[QueryHandler(HandlerClass::class)]
│   │       └── QueryValidator.php     ← #[QueryValidator(ValidatorClass::class)]
│   └── Interfaces/
│       ├── Commands/
│       │   ├── CommandInterface.php           ← implement on every Command DTO
│       │   ├── CommandHandlerInterface.php    ← implement on every CommandHandler
│       │   └── ValidatesCommandInterface.php  ← implement on every CommandValidator
│       ├── Queries/
│       │   ├── QueryInterface.php             ← implement on every Query DTO
│       │   ├── QueryHandlerInterface.php      ← implement on every QueryHandler
│       │   └── ValidatesQueryInterface.php    ← implement on every QueryValidator
│       └── Pipeline/
│           └── PipelineBehaviorInterface.php  ← implement for custom behaviors
├── Exceptions/                           ← CqrsException and its subclasses
├── Pipeline/
│   ├── Behaviors/
│   │   ├── LoggingBehavior.php           ← logs dispatch, completion and failure
│   │   ├── ValidationBehavior.php        ← runs the validator if declared
│   │   ├── TransactionBehavior.php       ← wraps in DB::transaction()
│   │   └── HandlerExecutionBehavior.php  ← resolves and calls the handler
│   ├── MetadataResolver.php  ← reads PHP attributes via reflection (statically cached)
│   └── Pipeline.php          ← composes and executes the behavior chain
├── Bus/
│   ├── CommandBus.php  ← concrete bus registered in the container
│   └── QueryBus.php
├── Facades/
│   ├── CommandBus.php  ← Laravel facade
│   └── QueryBus.php
└── CqrsServiceProvider.php  ← registers buses, merges config, publishes files
```

---

## Best practices

**Keep Commands and Queries as immutable DTOs.**
Use `readonly` constructor properties. A command must carry exactly what it needs — no optional nullable fields that mask missing data.

**Separate HTTP validation from domain validation.**
Use Laravel `FormRequest` to check input format, presence, and types. Use `CommandValidator` for domain rules: uniqueness, state machine transitions, cross-aggregate constraints.

```
FormRequest       → is the email a valid format? is the field present?
CommandValidator  → is this email already taken? can this subscription be paused?
```

**Handlers must be thin orchestrators.**
Keep business logic in models, domain services, or value objects. The handler's only job is to coordinate them.

**Queries must never write.**
If you find yourself calling `save()` inside a query handler, move that logic to a command.

**`HandlerExecutionBehavior` must always be last.**
Any behavior placed after it will never execute, and a pipeline without it throws an `InvalidPipelineException`.

**Do not add `TransactionBehavior` to the query pipeline.**
Wrapping read-only queries in transactions is unnecessary overhead. The default configuration already reflects this.

---

## Development

```bash
composer install
composer test      # PHPUnit
composer analyse   # PHPStan, level max
```

The test suite runs on GitHub Actions against every supported combination of PHP and Laravel.

---

## License

This package is open-source software released under the [MIT License](LICENSE).

You are free to use, modify, and distribute it in both private and commercial projects. The only requirement is to keep the copyright notice in any copies or substantial portions of the software.
