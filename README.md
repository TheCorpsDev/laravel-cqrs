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

The facades are optional. To inject the bus, type-hint `CommandBusInterface` (or `QueryBusInterface`); the container resolves it to the same singleton the facade uses, and `CommandBus::fake()` replaces both.

```php
use TheCorps\LaravelCqrs\Contracts\Interfaces\Bus\CommandBusInterface;

final class RegistrationController
{
    public function __construct(private readonly CommandBusInterface $commands) {}

    public function store(RegisterRequest $request): RedirectResponse
    {
        $this->commands->dispatch(new CreateUserCommand(...));

        return redirect('/');
    }
}
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

To change the transaction of a single command, use an attribute on the command:

```php
use TheCorps\LaravelCqrs\Contracts\Attributes\Transactions\Transactional;
use TheCorps\LaravelCqrs\Contracts\Attributes\Transactions\WithoutTransaction;

#[CommandHandler(SendNewsletterHandler::class)]
#[WithoutTransaction]                       // no database work, or the handler manages its own
final class SendNewsletterCommand implements CommandInterface { ... }

#[CommandHandler(SettleLedgerHandler::class)]
#[Transactional(connection: 'ledger', attempts: 3)]   // other connection, retry up to 3 times on deadlock
final class SettleLedgerCommand implements CommandInterface { ... }
```

Options left unset fall back to `cqrs.transactions` (`connection` defaults to the default connection, `attempts` to `1`). Both attributes are inherited by subclasses.

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

### 8 — Typed results

`dispatch()` returns whatever the handler returns. Declare that type on the command or query and PHPStan, Psalm and IDEs infer it at every call site, through the facade, the bus interface or the fake:

```php
/** @implements QueryInterface<User|null> */
#[QueryHandler(GetUserQueryHandler::class)]
final class GetUserQuery implements QueryInterface { ... }

$user = QueryBus::dispatch(new GetUserQuery(userId: $id)); // User|null
```

The type parameter defaults to `mixed`, so commands and queries without the annotation keep working and raise no new static-analysis errors.

---

### 9 — Naming conventions

A command or query without a handler attribute is handled by the class named after it in the same namespace:

| Message | Handler | Validator |
| --- | --- | --- |
| `CreateUserCommand` | `CreateUserCommandHandler` or `CreateUserHandler` | `CreateUserCommandValidator` or `CreateUserValidator` |
| `GetUserQuery` | `GetUserQueryHandler` or `GetUserHandler` | `GetUserQueryValidator` or `GetUserValidator` |

A class found this way is used only if it implements the matching interface (`CommandHandlerInterface`, `ValidatesCommandInterface`, and the query equivalents). Attributes always win, and validators are looked up by name only for messages that declare no handler attribute, so existing commands never start running an undeclared validator. Set `'conventions' => false` in `config/cqrs.php` to require attributes everywhere.

---

### 10 — Events

Both buses fire Laravel events around every dispatch:

| Command | Query | Properties |
| --- | --- | --- |
| `CommandDispatching` | `QueryDispatching` | `message` |
| `CommandHandled` | `QueryHandled` | `message`, `result`, `durationMs` |
| `CommandFailed` | `QueryFailed` | `message`, `exception`, `durationMs` |

They live in `TheCorps\LaravelCqrs\Events` and are a good hook for metrics, auditing or Telescope/Pulse recorders:

```php
use Illuminate\Support\Facades\Event;
use TheCorps\LaravelCqrs\Events\CommandHandled;

Event::listen(function (CommandHandled $event) {
    logger()->info('Command handled', [
        'command' => $event->message::class,
        'duration_ms' => $event->durationMs,
    ]);
});
```

Events nobody listens to are not even created, so they cost about a microsecond per dispatch. Set `'events' => false` to turn them off. `CommandFailed` is fired before the exception is re-thrown.

---

## Testing

Replace a bus with a fake to assert what your code dispatches without running the handlers:

```php
use TheCorps\LaravelCqrs\Facades\CommandBus;
use TheCorps\LaravelCqrs\Facades\QueryBus;

CommandBus::fake();

$this->post('/users', [...]);

CommandBus::assertDispatched(CreateUserCommand::class);
CommandBus::assertDispatched(fn (CreateUserCommand $command) => $command->email === 'ada@example.com');
CommandBus::assertDispatched(CreateUserCommand::class, 1);       // exactly once
CommandBus::assertNotDispatched(DeleteUserCommand::class);
CommandBus::assertNothingDispatched();
```

A faked `dispatch()` returns `null`, or the response you configure per class; a closure receives the message:

```php
QueryBus::fake([
    GetUserQuery::class => fn (GetUserQuery $query) => User::factory()->make(['id' => $query->userId]),
]);

CommandBus::fake()->respondWith(CreateUserCommand::class, 42);
```

The fake also replaces the bus injected through `CommandBusInterface` / `QueryBusInterface` or the concrete classes. `dispatched()` and `hasDispatched()` return the recorded messages for custom assertions.

To test a handler, dispatch through the real bus so validation and the transaction run as in production.

---

## Artisan commands

| Command | Purpose |
| --- | --- |
| `make:cqrs-command Billing/CreateInvoice [--validator] [--force]` | Creates the command and its handler (and validator) in `app/Domain/Billing/Commands/CreateInvoice/` |
| `make:cqrs-query Billing/GetInvoice [--validator] [--force]` | Creates the query and its handler (and validator) in `app/Domain/Billing/Queries/GetInvoice/` |
| `cqrs:list [--path=app]` | Lists every command and query with its handler and validator; exits with an error if one cannot be resolved, which makes it useful in CI |
| `cqrs:install-agent-rules` | Installs the coding-agent rules (see below) |

Generated classes are named `{UseCase}Command`, `{UseCase}Query`, `{UseCase}Handler` and `{UseCase}Validator`. Change the root namespace with `cqrs.generators.namespace` (default `Domain`, under the application namespace), and customise the generated code with:

```bash
php artisan vendor:publish --tag=cqrs-stubs
```

---

## Configuration

After publishing, `config/cqrs.php` exposes the pipelines and the options below.

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

### Other options

```php
'transactions' => [
    'connection' => null,  // default connection of TransactionBehavior; null = database.default
    'attempts'   => 1,     // how many times a command runs when a deadlock occurs
],

'events' => true,          // fire CommandDispatching/Handled/Failed and the Query equivalents

'conventions' => true,     // find handlers and validators by name when no attribute is declared

'generators' => [
    'namespace' => 'Domain',  // make:cqrs-* create classes under App\Domain
],
```

---

## Errors

Misconfigurations fail with a dedicated exception that names the class involved. All of them extend `TheCorps\LaravelCqrs\Exceptions\CqrsException`, which extends `RuntimeException`.

| Exception | Thrown when |
| --- | --- |
| `MissingHandlerException` | the command or query, and its parent classes, declare no `#[CommandHandler]` / `#[QueryHandler]` and no handler is found by naming convention |
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
│   │   ├── Queries/
│   │   │   ├── QueryHandler.php       ← #[QueryHandler(HandlerClass::class)]
│   │   │   └── QueryValidator.php     ← #[QueryValidator(ValidatorClass::class)]
│   │   └── Transactions/
│   │       ├── Transactional.php      ← #[Transactional(connection, attempts)]
│   │       └── WithoutTransaction.php ← #[WithoutTransaction]
│   └── Interfaces/
│       ├── Bus/
│       │   ├── CommandBusInterface.php        ← type-hint to inject the command bus
│       │   └── QueryBusInterface.php          ← type-hint to inject the query bus
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
├── Console/                              ← make:cqrs-command, make:cqrs-query, cqrs:list, cqrs:install-agent-rules
├── Events/                               ← Command/Query Dispatching, Handled, Failed
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
│   ├── Bus.php         ← shared dispatch logic and events
│   ├── CommandBus.php  ← concrete bus registered in the container
│   └── QueryBus.php
├── Facades/
│   ├── CommandBus.php  ← Laravel facade, with fake()
│   └── QueryBus.php
├── Support/                              ← attribute reader, class finder, agent rules installer
├── Testing/
│   ├── CommandBusFake.php  ← installed by CommandBus::fake()
│   └── QueryBusFake.php    ← installed by QueryBus::fake()
└── CqrsServiceProvider.php  ← registers buses, merges config, publishes files and stubs
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

## Coding-agent integration

The package ships the rules a coding agent needs to write CQRS code that fits it, as a `laravel-cqrs` skill plus a short guideline. Install them in one of two ways.

**With [Laravel Boost](https://laravel.com/docs/boost)**: run `php artisan boost:install` (or `boost:update`). Boost detects the package and installs its guideline and skill for every agent you selected: Claude Code, Codex, Cursor, GitHub Copilot, Junie and others.

**Without Boost**: run the package command.

```bash
php artisan cqrs:install-agent-rules
```

It creates or updates four files in the application root:

```text
AGENTS.md                              read by Codex, Cursor, GitHub Copilot and most other agents
CLAUDE.md                              read by Claude Code
.agents/skills/laravel-cqrs/SKILL.md
.claude/skills/laravel-cqrs/SKILL.md
```

`AGENTS.md` and `CLAUDE.md` get the same marker-delimited block pointing to the skill; nothing outside the markers is changed. Symlinked files are written once. Re-run the command after upgrading the package to refresh the rules. It refuses to overwrite a `SKILL.md` that the package did not write.

The rules guide agents; they do not replace code review or architecture tests. See the [agent integration guide](docs/agent-integration.md) for details.

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
