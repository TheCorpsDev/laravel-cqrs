<?php

namespace TheCorps\LaravelCqrs\Tests\Feature;

use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use TheCorps\LaravelCqrs\Bus\CommandBus;
use TheCorps\LaravelCqrs\Facades\CommandBus as CommandBusFacade;
use TheCorps\LaravelCqrs\Tests\Fixtures\ChildCommand;
use TheCorps\LaravelCqrs\Tests\Fixtures\CreateNoteCommand;
use TheCorps\LaravelCqrs\Tests\Fixtures\CreateNoteValidator;
use TheCorps\LaravelCqrs\Tests\Fixtures\EchoCommand;
use TheCorps\LaravelCqrs\Tests\Fixtures\OuterCommand;
use TheCorps\LaravelCqrs\Tests\TestCase;

final class CommandBusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CreateNoteValidator::$transactionLevels = [];

        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            $table->string('body');
        });
    }

    #[Test]
    public function it_dispatches_a_command_to_its_handler_and_returns_the_result(): void
    {
        $id = $this->app->make(CommandBus::class)->dispatch(new CreateNoteCommand('hello'));

        $this->assertSame(1, $id);
        $this->assertSame('hello', DB::table('notes')->value('body'));
    }

    #[Test]
    public function the_facade_and_the_container_share_the_same_bus(): void
    {
        $this->assertSame('via facade', CommandBusFacade::dispatch(new EchoCommand('via facade')));
        $this->assertSame($this->app->make(CommandBus::class), CommandBusFacade::getFacadeRoot());
    }

    #[Test]
    public function the_validator_runs_before_the_transaction_and_the_handler_inside_it(): void
    {
        $this->app->make(CommandBus::class)->dispatch(new CreateNoteCommand('hello'));

        $this->assertSame([0], CreateNoteValidator::$transactionLevels);
        $this->assertSame(0, DB::transactionLevel());
    }

    #[Test]
    public function a_failed_validation_stops_the_pipeline_before_the_handler(): void
    {
        try {
            $this->app->make(CommandBus::class)->dispatch(new CreateNoteCommand(''));
            $this->fail('A ValidationException was expected.');
        } catch (ValidationException) {
            $this->assertSame(0, DB::table('notes')->count());
        }
    }

    #[Test]
    public function an_exception_in_the_handler_rolls_back_the_transaction(): void
    {
        try {
            $this->app->make(CommandBus::class)->dispatch(new CreateNoteCommand('lost', failAfterInsert: true));
            $this->fail('A DomainException was expected.');
        } catch (DomainException $exception) {
            $this->assertSame('Failed after insert.', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('notes')->count());
        $this->assertSame(0, DB::transactionLevel());
    }

    #[Test]
    public function a_handler_can_dispatch_another_command(): void
    {
        $this->assertSame('outer+inner', $this->app->make(CommandBus::class)->dispatch(new OuterCommand()));
    }

    #[Test]
    public function the_handler_attribute_is_inherited_from_a_parent_class(): void
    {
        $this->assertSame(ChildCommand::class, $this->app->make(CommandBus::class)->dispatch(new ChildCommand()));
    }
}
