<?php

namespace TheCorps\LaravelCqrs\Support;

use PhpToken;
use SplFileInfo;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Finds the names of the classes declared in the PHP files of a directory tree,
 * by reading tokens. Files are not included or executed.
 */
final class ClassFinder
{
    /**
     * @param list<string> $directories Directories to scan recursively.
     *
     * @return list<string> Fully qualified class names, sorted.
     */
    public static function in(array $directories): array
    {
        $classes = [];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($files as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    $code = file_get_contents($file->getPathname());

                    if ($code !== false) {
                        array_push($classes, ...self::inCode($code));
                    }
                }
            }
        }

        $classes = array_values(array_unique($classes));
        sort($classes);

        return $classes;
    }

    /**
     * @return list<string>
     */
    public static function inCode(string $code): array
    {
        $tokens = array_values(array_filter(
            PhpToken::tokenize($code),
            static fn(PhpToken $token): bool => !$token->isIgnorable(),
        ));

        $namespace = '';
        $classes = [];

        foreach ($tokens as $index => $token) {
            if ($token->is(T_NAMESPACE)) {
                $next = $tokens[$index + 1] ?? null;
                $namespace = $next !== null && $next->is([T_NAME_QUALIFIED, T_STRING]) ? $next->text . '\\' : '';

                continue;
            }

            if (!$token->is(T_CLASS)) {
                continue;
            }

            $previous = $tokens[$index - 1] ?? null;

            // Skip Foo::class and anonymous classes (new class).
            if ($previous !== null && $previous->is([T_DOUBLE_COLON, T_NEW])) {
                continue;
            }

            $name = $tokens[$index + 1] ?? null;

            if ($name !== null && $name->is(T_STRING)) {
                $classes[] = $namespace . $name->text;
            }
        }

        return $classes;
    }
}
