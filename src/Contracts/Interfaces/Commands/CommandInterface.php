<?php

namespace TheCorps\LaravelCqrs\Contracts\Interfaces\Commands;

/**
 * Marker interface for all CQRS bus commands.
 *
 * TResult is the type returned by the command's handler. Declaring it lets static
 * analysers and IDEs infer the return type of CommandBus::dispatch(). Declare it
 * with an `@implements CommandInterface<int>` tag in the docblock of the class.
 *
 * It defaults to mixed, so commands that do not declare it keep working unchanged.
 *
 * @template-covariant TResult = mixed
 */
interface CommandInterface {}
