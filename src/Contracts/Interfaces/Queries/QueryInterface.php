<?php

namespace TheCorps\LaravelCqrs\Contracts\Interfaces\Queries;

/**
 * Marker interface for all CQRS bus queries.
 *
 * TResult is the type returned by the query's handler. Declaring it lets static
 * analysers and IDEs infer the return type of QueryBus::dispatch(). Declare it
 * with an `@implements QueryInterface<User|null>` tag in the docblock of the class.
 *
 * It defaults to mixed, so queries that do not declare it keep working unchanged.
 *
 * @template-covariant TResult = mixed
 */
interface QueryInterface {}
