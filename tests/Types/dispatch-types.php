<?php

// Analysed by PHPStan only (see phpstan.neon.dist): checks the types inferred
// for dispatch() through the buses, their interfaces, and the facades.

namespace TheCorps\LaravelCqrs\Tests\Types;

use function PHPStan\Testing\assertType;

use TheCorps\LaravelCqrs\Bus\QueryBus;
use TheCorps\LaravelCqrs\Bus\CommandBus;
use TheCorps\LaravelCqrs\Facades\QueryBus as QueryBusFacade;
use TheCorps\LaravelCqrs\Facades\CommandBus as CommandBusFacade;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Bus\QueryBusInterface;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Bus\CommandBusInterface;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Queries\QueryInterface;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\CommandInterface;

final class Invoice {}

/** @implements CommandInterface<int> */
final class CreateInvoice implements CommandInterface {}

/** @implements QueryInterface<Invoice|null> */
final class FindInvoice implements QueryInterface {}

/** Written before generics existed: must keep working and infer mixed. */
final class LegacyCommand implements CommandInterface {}

function types(
    CommandBus $commandBus,
    CommandBusInterface $commandBusInterface,
    QueryBus $queryBus,
    QueryBusInterface $queryBusInterface,
): void {
    assertType('int', $commandBus->dispatch(new CreateInvoice()));
    assertType('int', $commandBusInterface->dispatch(new CreateInvoice()));
    assertType('int', CommandBusFacade::dispatch(new CreateInvoice()));
    assertType('mixed', $commandBus->dispatch(new LegacyCommand()));

    assertType('TheCorps\LaravelCqrs\Tests\Types\Invoice|null', $queryBus->dispatch(new FindInvoice()));
    assertType('TheCorps\LaravelCqrs\Tests\Types\Invoice|null', $queryBusInterface->dispatch(new FindInvoice()));
    assertType('TheCorps\LaravelCqrs\Tests\Types\Invoice|null', QueryBusFacade::dispatch(new FindInvoice()));
}
