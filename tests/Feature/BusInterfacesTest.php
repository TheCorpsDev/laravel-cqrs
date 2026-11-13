<?php

namespace TheCorps\LaravelCqrs\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use TheCorps\LaravelCqrs\Bus\QueryBus;
use TheCorps\LaravelCqrs\Bus\CommandBus;
use TheCorps\LaravelCqrs\Tests\TestCase;
use TheCorps\LaravelCqrs\Tests\Fixtures\InvoiceService;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Bus\QueryBusInterface;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Bus\CommandBusInterface;
use TheCorps\LaravelCqrs\Facades\CommandBus as CommandBusFacade;

final class BusInterfacesTest extends TestCase
{
    #[Test]
    public function the_interfaces_and_the_concrete_classes_resolve_to_the_same_singletons(): void
    {
        $this->assertInstanceOf(CommandBus::class, $this->app->make(CommandBusInterface::class));
        $this->assertSame($this->app->make(CommandBusInterface::class), $this->app->make(CommandBus::class));
        $this->assertSame($this->app->make(CommandBusInterface::class), CommandBusFacade::getFacadeRoot());

        $this->assertInstanceOf(QueryBus::class, $this->app->make(QueryBusInterface::class));
        $this->assertSame($this->app->make(QueryBusInterface::class), $this->app->make(QueryBus::class));
    }

    #[Test]
    public function the_command_bus_interface_can_be_injected(): void
    {
        $this->assertSame('registered service@example.com', $this->app->make(InvoiceService::class)->register());
    }
}
