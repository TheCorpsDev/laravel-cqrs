<?php

namespace TheCorps\LaravelCqrs\Tests\Fixtures;

use TheCorps\LaravelCqrs\Contracts\Interfaces\Bus\CommandBusInterface;

final class InvoiceService
{
    public function __construct(private readonly CommandBusInterface $bus) {}

    public function register(): mixed
    {
        return $this->bus->dispatch(new RegisterUserCommand('service@example.com'));
    }
}
