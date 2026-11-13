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

When the project has no established structure yet, use one folder per use case inside its feature:

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
- Attributes are inherited: a class extending a base command uses the base handler unless it declares its own.

## Handlers and validators

- A handler implements `CommandHandlerInterface` or `QueryHandlerInterface` and exposes a public `handle()` method typed with its specific DTO. It is resolved from the container, so it may use constructor injection.
- A validator implements `ValidatesCommandInterface` or `ValidatesQueryInterface` with `public function validate(object $command): void` and throws (typically `ValidationException`) when a domain rule fails.
- Use Laravel `FormRequest` for transport checks: presence, type, format. Use CQRS validators for domain rules: uniqueness, allowed state transitions, cross-aggregate constraints.
- In the default command pipeline the validator runs before the database transaction. A uniqueness check in a validator is not atomic: keep a unique index in the database as the real guarantee.
- Handlers coordinate models, domain services and value objects. Keep business rules out of the handler unless there is nowhere better.

## Dispatching

- Dispatch commands only with `CommandBus::dispatch(...)` and queries only with `QueryBus::dispatch(...)` (facades in `TheCorps\LaravelCqrs\Facades`, or inject `TheCorps\LaravelCqrs\Bus\CommandBus` / `QueryBus`). Never call a handler directly.
- `dispatch()` returns whatever the handler returns.
- Every command runs inside `DB::transaction()` by default; an exception in the handler rolls back all its writes.

## Pipeline

- Do not change `config/cqrs.php` unless the task requires it.
- `HandlerExecutionBehavior` must stay last in both pipelines; without it the bus throws `InvalidPipelineException`.
- `TransactionBehavior` belongs only in the command pipeline.
- A custom behavior implements `PipelineBehaviorInterface::handle(object $command, Closure $next): mixed`, calls `$next($command)` and returns its result. It is resolved from the container on first use, so constructor injection works. A constructor parameter typed `MetadataResolver` receives the resolver of its pipeline.

## Errors you may see

All extend `TheCorps\LaravelCqrs\Exceptions\CqrsException`:

- `MissingHandlerException`: the class has no `#[CommandHandler]` / `#[QueryHandler]`.
- `InvalidHandlerException` / `InvalidValidatorException`: the declared class does not exist or lacks `handle()` / `validate()`.
- `InvalidPipelineException`: a pipeline entry is not a behavior, or nothing handled the message.

## Testing

- Test a handler through the bus so the real pipeline (validation, transaction) runs.
- To assert that code dispatches a command without running it, use the facade's Mockery helpers: `CommandBus::spy()` then `CommandBus::shouldHaveReceived('dispatch')`, or `CommandBus::shouldReceive('dispatch')->andReturn(...)`.

## Completion check

Before finishing, verify: correct classification (command vs query), file location, interface and attribute on the DTO, handler interface and `handle()` signature, dispatch through the bus, domain validation kept out of `FormRequest` and vice versa, no writes in query handlers, pipeline invariants untouched.
