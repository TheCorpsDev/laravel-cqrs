<?php

namespace TheCorps\LaravelCqrs\Pipeline;

use ReflectionClass;
use TheCorps\LaravelCqrs\Exceptions\InvalidHandlerException;
use TheCorps\LaravelCqrs\Exceptions\MissingHandlerException;
use TheCorps\LaravelCqrs\Exceptions\InvalidValidatorException;
use TheCorps\LaravelCqrs\Contracts\Attributes\Queries\QueryHandler;
use TheCorps\LaravelCqrs\Contracts\Attributes\Queries\QueryValidator;
use TheCorps\LaravelCqrs\Contracts\Attributes\Commands\CommandHandler;
use TheCorps\LaravelCqrs\Contracts\Attributes\Commands\CommandValidator;

/**
 * Resolves the handler and validator associated with a command or query via PHP attributes.
 *
 * Attributes are looked up on the class first, then on its parent classes.
 * Results are cached statically for the lifetime of the process to avoid
 * repeated reflection on the same class.
 */
class MetadataResolver
{
    /** @var array<string, array{handler: string, validator: string|null}> */
    private static array $cache = [];

    /**
     * Creates a new resolver instance.
     *
     * @param class-string<CommandHandler|QueryHandler>          $handlerAttributeClass   Handler attribute to look for.
     * @param class-string<CommandValidator|QueryValidator>|null $validatorAttributeClass Validator attribute to look for.
     */
    public function __construct(
        private readonly string $handlerAttributeClass,
        private readonly ?string $validatorAttributeClass = null,
    ) {}

    /**
     * Returns the handler class name associated with the given command or query.
     *
     * @param object $command The command or query to resolve.
     *
     * @return string
     *
     * @throws MissingHandlerException When no handler attribute is declared.
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
     * Clears the reflection cache. Useful in tests that redefine metadata.
     *
     * @return void
     */
    public static function flushCache(): void
    {
        self::$cache = [];
    }

    /** @return array{handler: string, validator: string|null} */
    private function resolve(object $command): array
    {
        $cacheKey = $command::class . '|' . $this->handlerAttributeClass;

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $reflection = new ReflectionClass($command);

        $handlerClass = $this->findAttribute($reflection, $this->handlerAttributeClass)?->handlerClass;

        if ($handlerClass === null) {
            throw MissingHandlerException::for($command, $this->handlerAttributeClass);
        }

        if (!class_exists($handlerClass) && !interface_exists($handlerClass)) {
            throw InvalidHandlerException::classNotFound($command, $handlerClass);
        }

        if (!method_exists($handlerClass, 'handle')) {
            throw InvalidHandlerException::missingMethod($command, $handlerClass);
        }

        $validatorClass = $this->validatorAttributeClass === null
            ? null
            : $this->findAttribute($reflection, $this->validatorAttributeClass)?->validatorClass;

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
     * Returns the first instance of the attribute found on the class or its parents.
     *
     * @template T of object
     *
     * @param ReflectionClass<object> $reflection
     * @param class-string<T>         $attributeClass
     *
     * @return T|null
     */
    private function findAttribute(ReflectionClass $reflection, string $attributeClass): ?object
    {
        for ($class = $reflection; $class !== false; $class = $class->getParentClass()) {
            $attributes = $class->getAttributes($attributeClass);

            if ($attributes !== []) {
                return $attributes[0]->newInstance();
            }
        }

        return null;
    }
}
