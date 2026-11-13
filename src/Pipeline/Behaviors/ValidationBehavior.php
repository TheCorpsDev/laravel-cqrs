<?php

namespace TheCorps\LaravelCqrs\Pipeline\Behaviors;

use Closure;
use TheCorps\LaravelCqrs\Pipeline\MetadataResolver;
use TheCorps\LaravelCqrs\Exceptions\InvalidValidatorException;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Pipeline\PipelineBehaviorInterface;

/**
 * Behavior that validates the command or query before continuing through the pipeline.
 *
 * If no validator is bound to the command/query (i.e. no #[CommandValidator] attribute),
 * this behavior is a no-op and passes control to the next step immediately.
 *
 * In the default command pipeline this behavior runs before TransactionBehavior, so a
 * failed validation never opens a database transaction.
 */
class ValidationBehavior implements PipelineBehaviorInterface
{
    /**
     * Creates a new behavior instance.
     *
     * @param MetadataResolver $resolver The resolver used to retrieve the validator class.
     */
    public function __construct(
        private readonly MetadataResolver $resolver,
    ) {}

    /**
     * Resolves the validator via the resolver, executes it if present, then delegates to the next behavior.
     *
     * @param object  $command The command or query to validate.
     * @param Closure $next    The next behavior in the chain.
     *
     * @return mixed
     */
    public function handle(object $command, Closure $next): mixed
    {
        $validatorClass = $this->resolver->getValidatorClass($command);

        if ($validatorClass !== null) {
            $validator = app($validatorClass);

            if (!is_object($validator) || !method_exists($validator, 'validate')) {
                throw InvalidValidatorException::missingMethod($command, $validatorClass);
            }

            $validator->validate($command);
        }

        return $next($command);
    }
}
