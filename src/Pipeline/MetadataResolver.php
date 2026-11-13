<?php

namespace TheCorps\LaravelCqrs\Pipeline;

use TheCorps\LaravelCqrs\Support\AttributeReader;
use TheCorps\LaravelCqrs\Exceptions\InvalidHandlerException;
use TheCorps\LaravelCqrs\Exceptions\MissingHandlerException;
use TheCorps\LaravelCqrs\Exceptions\InvalidValidatorException;
use TheCorps\LaravelCqrs\Contracts\Attributes\Queries\QueryHandler;
use TheCorps\LaravelCqrs\Contracts\Attributes\Queries\QueryValidator;
use TheCorps\LaravelCqrs\Contracts\Attributes\Commands\CommandHandler;
use TheCorps\LaravelCqrs\Contracts\Attributes\Commands\CommandValidator;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Queries\QueryHandlerInterface;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\CommandHandlerInterface;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Queries\ValidatesQueryInterface;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\ValidatesCommandInterface;

/**
 * Resolves the handler and validator associated with a command or query.
 *
 * The handler and validator are read from PHP attributes, on the class or its
 * parents. When conventions are enabled and the class declares no handler
 * attribute, they are looked up by name in the same namespace instead:
 * CreateUserCommand → CreateUserCommandHandler or CreateUserHandler, and
 * CreateUserCommandValidator or CreateUserValidator. A class found by convention
 * is used only if it implements the matching handler or validator interface.
 *
 * Results are cached statically for the lifetime of the process.
 */
class MetadataResolver
{
    /** @var array<string, array{handler: string, validator: string|null}> */
    private static array $cache = [];

    /**
     * Creates a new resolver instance.
     *
     * @param class-string<CommandHandler|QueryHandler>                          $handlerAttributeClass   Handler attribute to look for.
     * @param class-string<CommandValidator|QueryValidator>|null                 $validatorAttributeClass Validator attribute to look for.
     * @param class-string<CommandHandlerInterface|QueryHandlerInterface>|null   $handlerInterface        Interface a handler found by convention must implement.
     * @param class-string<ValidatesCommandInterface|ValidatesQueryInterface>|null $validatorInterface    Interface a validator found by convention must implement.
     * @param bool                                                               $conventions             Whether to look up handlers and validators by name.
     */
    public function __construct(
        private readonly string $handlerAttributeClass,
        private readonly ?string $validatorAttributeClass = null,
        private readonly ?string $handlerInterface = null,
        private readonly ?string $validatorInterface = null,
        private readonly bool $conventions = false,
    ) {}

    /**
     * Creates the resolver used by the command pipeline.
     */
    public static function forCommands(bool $conventions = false): self
    {
        return new self(
            CommandHandler::class,
            CommandValidator::class,
            CommandHandlerInterface::class,
            ValidatesCommandInterface::class,
            $conventions,
        );
    }

    /**
     * Creates the resolver used by the query pipeline.
     */
    public static function forQueries(bool $conventions = false): self
    {
        return new self(
            QueryHandler::class,
            QueryValidator::class,
            QueryHandlerInterface::class,
            ValidatesQueryInterface::class,
            $conventions,
        );
    }

    /**
     * Returns the handler class name associated with the given command or query.
     *
     * @param object $command The command or query to resolve.
     *
     * @return string
     *
     * @throws MissingHandlerException When no handler is declared or found by convention.
     * @throws InvalidHandlerException When the declared handler does not exist or has no handle() method.
     */
    public function getHandlerClass(object $command): string
    {
        return $this->resolve($command)['handler'];
    }

    /**
     * Returns the validator class name associated with the given command or query, or null if none.
     *
     * @param object $command The command or query to resolve.
     *
     * @return string|null
     *
     * @throws InvalidValidatorException When the declared validator does not exist or has no validate() method.
     */
    public function getValidatorClass(object $command): ?string
    {
        return $this->resolve($command)['validator'];
    }

    /**
     * Clears the metadata and attribute caches. Useful in tests that redefine metadata.
     *
     * @return void
     */
    public static function flushCache(): void
    {
        self::$cache = [];
        AttributeReader::flushCache();
    }

    /** @return array{handler: string, validator: string|null} */
    private function resolve(object $command): array
    {
        $cacheKey = $command::class . '|' . $this->handlerAttributeClass . '|' . (int) $this->conventions;

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $handlerClass = AttributeReader::first($command, $this->handlerAttributeClass)?->handlerClass;
        $byConvention = $handlerClass === null && $this->conventions;

        if ($byConvention) {
            $handlerClass = $this->findByConvention($command, 'Handler', $this->handlerInterface);
        }

        if ($handlerClass === null) {
            throw MissingHandlerException::for($command, $this->handlerAttributeClass, $this->conventions);
        }

        if (!class_exists($handlerClass) && !interface_exists($handlerClass)) {
            throw InvalidHandlerException::classNotFound($command, $handlerClass);
        }

        if (!method_exists($handlerClass, 'handle')) {
            throw InvalidHandlerException::missingMethod($command, $handlerClass);
        }

        $validatorClass = $this->validatorAttributeClass === null
            ? null
            : AttributeReader::first($command, $this->validatorAttributeClass)?->validatorClass;

        // Validators are looked up by name only for messages that rely on
        // conventions entirely, so classes with explicit attributes never
        // start running a validator they did not declare.
        if ($validatorClass === null && $byConvention) {
            $validatorClass = $this->findByConvention($command, 'Validator', $this->validatorInterface);
        }

        if ($validatorClass !== null) {
            if (!class_exists($validatorClass) && !interface_exists($validatorClass)) {
                throw InvalidValidatorException::classNotFound($command, $validatorClass);
            }

            if (!method_exists($validatorClass, 'validate')) {
                throw InvalidValidatorException::missingMethod($command, $validatorClass);
            }
        }

        return self::$cache[$cacheKey] = [
            'handler' => $handlerClass,
            'validator' => $validatorClass,
        ];
    }

    /**
     * Looks up a class named after the message in the same namespace.
     *
     * @param object      $command   The command or query.
     * @param string      $suffix    "Handler" or "Validator".
     * @param string|null $interface The interface the candidate must implement.
     *
     * @return class-string|null
     */
    private function findByConvention(object $command, string $suffix, ?string $interface): ?string
    {
        if ($interface === null) {
            return null;
        }

        $class = $command::class;
        $candidates = [$class . $suffix];

        foreach (['Command', 'Query'] as $messageSuffix) {
            if (str_ends_with($class, $messageSuffix)) {
                $candidates[] = substr($class, 0, -strlen($messageSuffix)) . $suffix;
            }
        }

        foreach ($candidates as $candidate) {
            if (class_exists($candidate) && is_subclass_of($candidate, $interface)) {
                return $candidate;
            }
        }

        return null;
    }
}
