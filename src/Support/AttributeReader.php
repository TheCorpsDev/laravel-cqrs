<?php

namespace TheCorps\LaravelCqrs\Support;

use ReflectionClass;

/**
 * Reads class attributes, looking at the class first and then at its parents.
 *
 * Results are cached statically for the lifetime of the process.
 */
final class AttributeReader
{
    /** @var array<string, object|null> */
    private static array $cache = [];

    /**
     * Returns the first instance of the attribute declared on the class or its parents.
     *
     * @template T of object
     *
     * @param object|class-string $class          The object or class name to inspect.
     * @param class-string<T>     $attributeClass The attribute to look for.
     *
     * @return T|null
     */
    public static function first(object|string $class, string $attributeClass): ?object
    {
        $className = is_object($class) ? $class::class : $class;
        $key = $className . '|' . $attributeClass;

        if (!array_key_exists($key, self::$cache)) {
            self::$cache[$key] = self::find($className, $attributeClass);
        }

        /** @var T|null */
        return self::$cache[$key];
    }

    /**
     * Clears the attribute cache.
     */
    public static function flushCache(): void
    {
        self::$cache = [];
    }

    /**
     * @template T of object
     *
     * @param class-string    $className
     * @param class-string<T> $attributeClass
     *
     * @return T|null
     */
    private static function find(string $className, string $attributeClass): ?object
    {
        for ($reflection = new ReflectionClass($className); $reflection !== false; $reflection = $reflection->getParentClass()) {
            $attributes = $reflection->getAttributes($attributeClass);

            if ($attributes !== []) {
                return $attributes[0]->newInstance();
            }
        }

        return null;
    }
}
