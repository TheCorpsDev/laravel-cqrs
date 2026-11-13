<?php

namespace TheCorps\LaravelCqrs\Tests\Fixtures;

use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
use TheCorps\LaravelCqrs\Contracts\Attributes\Queries\QueryHandler;
use TheCorps\LaravelCqrs\Contracts\Attributes\Queries\QueryValidator;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Queries\QueryInterface;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Queries\QueryHandlerInterface;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Queries\ValidatesQueryInterface;

#[QueryHandler(FindNoteHandler::class)]
#[QueryValidator(FindNoteValidator::class)]
final class FindNoteQuery implements QueryInterface
{
    public function __construct(public readonly int $id) {}
}

final class FindNoteHandler implements QueryHandlerInterface
{
    public function handle(FindNoteQuery $query): ?string
    {
        $body = DB::table('notes')->where('id', $query->id)->value('body');

        return is_string($body) ? $body : null;
    }
}

final class FindNoteValidator implements ValidatesQueryInterface
{
    public function validate(object $query): void
    {
        if ($query instanceof FindNoteQuery && $query->id < 1) {
            throw new InvalidArgumentException('The id must be positive.');
        }
    }
}

final class QueryWithoutHandler implements QueryInterface {}
