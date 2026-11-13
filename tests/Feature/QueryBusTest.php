<?php

namespace TheCorps\LaravelCqrs\Tests\Feature;

use InvalidArgumentException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use TheCorps\LaravelCqrs\Bus\QueryBus;
use TheCorps\LaravelCqrs\Facades\QueryBus as QueryBusFacade;
use TheCorps\LaravelCqrs\Tests\Fixtures\FindNoteQuery;
use TheCorps\LaravelCqrs\Tests\TestCase;

final class QueryBusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            $table->string('body');
        });

        DB::table('notes')->insert(['body' => 'first note']);
    }

    #[Test]
    public function it_dispatches_a_query_to_its_handler_and_returns_the_result(): void
    {
        $this->assertSame('first note', $this->app->make(QueryBus::class)->dispatch(new FindNoteQuery(1)));
        $this->assertNull($this->app->make(QueryBus::class)->dispatch(new FindNoteQuery(99)));
    }

    #[Test]
    public function the_facade_dispatches_queries(): void
    {
        $this->assertSame('first note', QueryBusFacade::dispatch(new FindNoteQuery(1)));
    }

    #[Test]
    public function the_query_validator_runs_before_the_handler(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The id must be positive.');

        $this->app->make(QueryBus::class)->dispatch(new FindNoteQuery(0));
    }

    #[Test]
    public function queries_are_not_logged_by_default(): void
    {
        $this->app->make(QueryBus::class)->dispatch(new FindNoteQuery(1));

        $this->assertSame([], $this->logMessages());
    }
}
