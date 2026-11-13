## thecorps/laravel-cqrs

This application uses `thecorps/laravel-cqrs`: commands and queries are immutable DTOs dispatched through `CommandBus` and `QueryBus`, which run them through a configurable pipeline (logging, validation, database transaction, handler execution).

- Every state change is a command; query handlers never write or cause side effects.
- A command implements `CommandInterface` and names its handler with `#[CommandHandler(Handler::class)]`; a query implements `QueryInterface` with `#[QueryHandler(Handler::class)]`. Validators are optional (`#[CommandValidator]`, `#[QueryValidator]`).
- Dispatch only through the bus (facades, or inject `CommandBusInterface` / `QueryBusInterface`), never by calling a handler directly.
- Generate new use cases with `php artisan make:cqrs-command Feature/UseCase` or `make:cqrs-query`, and check them with `php artisan cqrs:list`.
- In tests, `CommandBus::fake()` / `QueryBus::fake()` record dispatches for `assertDispatched()`.
- Keep `HandlerExecutionBehavior` last in `config/cqrs.php` and `TransactionBehavior` out of the query pipeline.
- Before creating or changing CQRS code, activate the `laravel-cqrs` skill for the full conventions.

@verbatim
<code-snippet name="Dispatch a command and a query" lang="php">
use TheCorps\LaravelCqrs\Facades\CommandBus;
use TheCorps\LaravelCqrs\Facades\QueryBus;

$invoiceId = CommandBus::dispatch(new CreateInvoiceCommand(customerId: $customer->id, amountInCents: 1500));
$invoice = QueryBus::dispatch(new GetInvoiceQuery(invoiceId: $invoiceId));
</code-snippet>
@endverbatim
