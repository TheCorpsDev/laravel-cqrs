<?php

namespace TheCorps\LaravelCqrs\Pipeline\Behaviors;

use Closure;
use TheCorps\LaravelCqrs\Pipeline\MetadataResolver;
use TheCorps\LaravelCqrs\Exceptions\InvalidHandlerException;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Pipeline\PipelineBehaviorInterface;

/**
 * Behavior that resolves and invokes the handler associated with the command or query.
 *
 * This must be the last behavior in any pipeline. It reads the #[CommandHandler]
 * or #[QueryHandler] attribute, resolves the handler class from the container,
 * and calls its handle() method.
 */
class HandlerExecutionBehavior implements PipelineBehaviorInterface
{
    /**
     * Creates a new behavior instance.
     *
     * @param MetadataResolver $resolver The resolver used to retrieve the handler class.
     */
    public function __construct(
        private readonly MetadataResolver $resolver,
    ) {}

    /**
     * Resolves the handler via the resolver and invokes it with the command or query.
     *
     * @param object  $command The command or query to handle.
     * @param Closure $next    The next behavior in the chain (unused in this terminal behavior).
     *
     * @return mixed
     */
    public function handle(object $command, Closure $next): mixed
    {
        $handlerClass = $this->resolver->getHandlerClass($command);
        $handler = app($handlerClass);

        if (!is_object($handler) || !method_exists($handler, 'handle')) {
            throw InvalidHandlerException::missingMethod($command, $handlerClass);
        }

        return $handler->handle($command);
    }
}
