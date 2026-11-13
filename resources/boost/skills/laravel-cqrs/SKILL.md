---
name: laravel-cqrs
description: Apply thecorps/laravel-cqrs conventions when creating or changing commands, queries, handlers, validators, CommandBus or QueryBus dispatching, or the CQRS pipeline configuration of a Laravel application.
---

<!-- laravel-cqrs-managed-skill -->

# Laravel CQRS

Use this skill whenever the project depends on `thecorps/laravel-cqrs` and the task creates or changes CQRS code: commands, queries, handlers, validators, bus dispatching, custom pipeline behaviors or `config/cqrs.php`.

## Classify the use case first

- Every state change is a command. A query handler never creates, updates, deletes, saves, dispatches a command or causes another side effect.
- If a requested "query" needs a write, design a command instead and dispatch it with `CommandBus`.
- One command or query per use case. Do not add nullable catch-all fields that hide missing input.

## Where files go

Follow the structure the project already uses for CQRS classes. Look for existing classes implementing `CommandInterface` or `QueryInterface` before creating new folders.

When the project has no established structure yet, generate the files with Artisan instead of writing them by hand:

```bash
php artisan make:cqrs-command Billing/CreateInvoice --validator
php artisan make:cqrs-query Billing/GetInvoice
```

They create one folder per use case inside its feature:

```text
app/Domain/{Feature}/
  Commands/{UseCase}/
    {UseCase}Command.php
    {UseCase}Handler.php
    {UseCase}Validator.php     # only when domain validation is needed
  Queries/{UseCase}/
    {UseCase}Query.php
    {UseCase}Handler.php
    {UseCase}Validator.php     # only when domain validation is needed
```

## Commands and queries

A command or query is a `final` immutable DTO with `readonly` constructor properties. It implements the matching marker interface and names its handler with an attribute:

```php
use TheCorps\LaravelCqrs\Contracts\Attributes\Commands\CommandHandler;
use TheCorps\LaravelCqrs\Contracts\Attributes\Commands\CommandValidator;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\CommandInterface;

/** @implements CommandInterface<int> */
#[CommandHandler(CreateInvoiceHandler::class)]
#[CommandValidator(CreateInvoiceValidator::class)] // only when needed
final class CreateInvoiceCommand implements CommandInterface
{
    public function __construct(
        public readonly int $customerId,
        public readonly int $amountInCents,
    ) {}
}
```

- Queries use `QueryInterface`, `#[QueryHandler(...)]` and `#[QueryValidator(...)]` from the `Queries` namespaces.
- Declare the handler's return type with `@implements CommandInterface<T>` / `@implements QueryInterface<T>` so `dispatch()` is typed at every call site.
- Attributes are inherited: a class extending a base command uses the base handler unless it declares its own.
- Without a handler attribute, the handler is found by name in the same namespace (`CreateInvoiceCommand` → `CreateInvoiceCommandHandler` or `CreateInvoiceHandler`, which must implement the handler interface), and so is the validator. Follow the project: if its messages declare attributes, declare them too.

## Handlers and validators

- A handler implements `CommandHandlerInterface` or `QueryHandlerInterface` and exposes a public `handle()` method typed with its specific DTO. It is resolved from the container, so it may use constructor injection.
- A validator implements `ValidatesCommandInterface` or `ValidatesQueryInterface` with `public function validate(object $command): void` and throws (typically `ValidationException`) when a domain rule fails.
- Use Laravel `FormRequest` for transport checks: presence, type, format. Use CQRS validators for domain rules: uniqueness, allowed state transitions, cross-aggregate constraints.
- In the default command pipeline the validator runs before the database transaction. A uniqueness check in a validator is not atomic: keep a unique index in the database as the real guarantee.
- Handlers coordinate models, domain services and value objects. Keep business rules out of the handler unless there is nowhere better.

## Dispatching

- Dispatch commands only with `CommandBus::dispatch(...)` and queries only with `QueryBus::dispatch(...)`: the facades in `TheCorps\LaravelCqrs\Facades`, or inject `CommandBusInterface` / `QueryBusInterface` from `TheCorps\LaravelCqrs\Contracts\Interfaces\Bus`. Never call a handler directly.
- `dispatch()` returns whatever the handler returns.
- Every command runs inside `DB::transaction()` by default; an exception in the handler rolls back all its writes.
- Add `#[WithoutTransaction]` to a command that does no database work or manages its own transactions, and `#[Transactional(connection: '...', attempts: 3)]` to choose the connection or retry on deadlock (namespace `TheCorps\LaravelCqrs\Contracts\Attributes\Transactions`).
- For cross-cutting reactions (metrics, auditing), listen to `CommandHandled` / `CommandFailed` / `QueryHandled` / `QueryFailed` from `TheCorps\LaravelCqrs\Events` instead of adding code to handlers.

## Pipeline

- Do not change `config/cqrs.php` unless the task requires it.
- `HandlerExecutionBehavior` must stay last in both pipelines; without it the bus throws `InvalidPipelineException`.
- `TransactionBehavior` belongs only in the command pipeline.
- A custom behavior implements `PipelineBehaviorInterface::handle(object $command, Closure $next): mixed`, calls `$next($command)` and returns its result. It is resolved from the container on first use, so constructor injection works. A constructor parameter typed `MetadataResolver` receives the resolver of its pipeline.

## Errors you may see

All extend `TheCorps\LaravelCqrs\Exceptions\CqrsException`:

- `MissingHandlerException`: the class has no `#[CommandHandler]` / `#[QueryHandler]` and no handler is found by naming convention.
- `InvalidHandlerException` / `InvalidValidatorException`: the declared class does not exist or lacks `handle()` / `validate()`.
- `InvalidPipelineException`: a pipeline entry is not a behavior, or nothing handled the message.

## Testing

- Test a handler through the real bus so the pipeline (validation, transaction) runs.
- To test code that dispatches, fake the bus and assert on what was dispatched:

```php
CommandBus::fake([CreateInvoiceCommand::class => 42]); // optional responses per class
// ... exercise the code ...
CommandBus::assertDispatched(fn (CreateInvoiceCommand $command) => $command->customerId === 7);
CommandBus::assertNotDispatched(VoidInvoiceCommand::class);
QueryBus::fake()->respondWith(GetInvoiceQuery::class, $invoice);
```

## Completion check

Before finishing, verify: correct classification (command vs query), file location, interface, result type and attribute on the DTO, handler interface and `handle()` signature, dispatch through the bus, domain validation kept out of `FormRequest` and vice versa, no writes in query handlers, pipeline invariants untouched. Run `php artisan cqrs:list` to confirm every command and query resolves to its handler.
