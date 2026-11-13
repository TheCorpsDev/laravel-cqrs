<?php

namespace TheCorps\LaravelCqrs\Support;

use RuntimeException;

/**
 * Installs managed agent instructions into a Laravel application root.
 *
 * It writes a marker-delimited block into AGENTS.md and CLAUDE.md, and a copy of
 * the laravel-cqrs skill into .agents/skills (Codex and other agents that follow
 * the Agent Skills layout) and .claude/skills (Claude Code). The skill source is
 * the same file Laravel Boost installs, so both installation paths stay in sync.
 */
final class AgentRulesInstaller
{
    public const SKILL_NAME = 'laravel-cqrs';

    private const ROOT_BLOCK_START = '<!-- laravel-cqrs-agent-rules:start -->';
    private const ROOT_BLOCK_END = '<!-- laravel-cqrs-agent-rules:end -->';
    private const SKILL_MARKER = '<!-- laravel-cqrs-managed-skill -->';

    /** Root instruction files, relative to the application root. */
    private const ROOT_FILES = ['AGENTS.md', 'CLAUDE.md'];

    /** Skill directories, relative to the application root. */
    private const SKILL_DIRECTORIES = ['.agents/skills', '.claude/skills'];

    /**
     * Installs the root instruction blocks and the project-local skill copies.
     *
     * Files reached through symlinks (for example CLAUDE.md linking to AGENTS.md,
     * or .claude/skills linking to .agents/skills) are written only once.
     */
    public function install(string $basePath): AgentRulesInstallResult
    {
        $basePath = rtrim($basePath, '/\\');
        $skillPaths = array_map(
            static fn(string $directory): string => "{$basePath}/{$directory}/" . self::SKILL_NAME . '/SKILL.md',
            self::SKILL_DIRECTORIES,
        );

        $conflicts = $this->findSkillConflicts($skillPaths);

        if ($conflicts !== []) {
            return new AgentRulesInstallResult([], $conflicts);
        }

        $writtenFiles = [];
        $writtenTargets = [];

        foreach (self::ROOT_FILES as $filename) {
            $path = "{$basePath}/{$filename}";

            if ($this->alreadyWritten($path, $writtenTargets)) {
                continue;
            }

            $this->writeFile($path, $this->withManagedRootBlock($this->readFile($path)));
            $writtenFiles[] = $path;
            $writtenTargets[] = $this->target($path);
        }

        $skill = self::skillContents();

        foreach ($skillPaths as $path) {
            if ($this->alreadyWritten($path, $writtenTargets)) {
                continue;
            }

            $this->writeFile($path, $skill);
            $writtenFiles[] = $path;
            $writtenTargets[] = $this->target($path);
        }

        return new AgentRulesInstallResult($writtenFiles, []);
    }

    /**
     * Returns the absolute path of the skill shipped with the package.
     */
    public static function skillSourcePath(): string
    {
        return dirname(__DIR__, 2) . '/resources/boost/skills/' . self::SKILL_NAME . '/SKILL.md';
    }

    /**
     * @param list<string> $skillPaths
     *
     * @return list<string>
     */
    private function findSkillConflicts(array $skillPaths): array
    {
        $conflicts = [];

        foreach ($skillPaths as $path) {
            if (is_link($path) || is_dir($path)) {
                $conflicts[] = $path;

                continue;
            }

            if (is_file($path) && !str_contains($this->readFile($path), self::SKILL_MARKER)) {
                $conflicts[] = $path;
            }
        }

        return $conflicts;
    }

    /**
     * Adds the managed block to the given contents, or replaces the existing one.
     *
     * Only the text between the markers changes; everything around it, including
     * whitespace, is preserved. The line ending already used by the file is kept.
     */
    private function withManagedRootBlock(string $contents): string
    {
        $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $block = implode($eol, $this->rootBlockLines());

        $pattern = '/' . preg_quote(self::ROOT_BLOCK_START, '/') . '.*?' . preg_quote(self::ROOT_BLOCK_END, '/') . '/s';

        if (preg_match($pattern, $contents) === 1) {
            return (string) preg_replace_callback($pattern, static fn(): string => $block, $contents, 1);
        }

        $existing = rtrim($contents);

        return $existing === ''
            ? $block . $eol
            : $existing . $eol . $eol . $block . $eol;
    }

    /**
     * The managed block is identical in every root file, so a file shared by
     * several agents through a symlink stays correct for all of them.
     *
     * @return list<string>
     */
    private function rootBlockLines(): array
    {
        $agentsSkill = self::SKILL_DIRECTORIES[0] . '/' . self::SKILL_NAME . '/SKILL.md';
        $claudeSkill = self::SKILL_DIRECTORIES[1] . '/' . self::SKILL_NAME . '/SKILL.md';

        return [
            self::ROOT_BLOCK_START,
            '## Laravel CQRS',
            '',
            'This project uses `thecorps/laravel-cqrs`. Before creating or changing commands, queries, handlers, validators, bus dispatching or the CQRS pipeline, read and follow the `' . self::SKILL_NAME . '` skill. Its rules are mandatory for that code.',
            '',
            "- `{$agentsSkill}` (Codex and other agents that read AGENTS.md)",
            "- `{$claudeSkill}` (Claude Code)",
            '',
            'Both files contain the same rules.',
            self::ROOT_BLOCK_END,
        ];
    }

    /**
     * @param list<string> $writtenTargets
     */
    private function alreadyWritten(string $path, array $writtenTargets): bool
    {
        return in_array($this->target($path), $writtenTargets, true);
    }

    /**
     * Returns the real path of an existing file, or the path itself.
     */
    private function target(string $path): string
    {
        $real = realpath($path);

        return $real === false ? $path : $real;
    }

    private static function skillContents(): string
    {
        $path = self::skillSourcePath();
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new RuntimeException("Unable to read required skill resource [{$path}].");
        }

        return $contents;
    }

    private function readFile(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read [{$path}].");
        }

        return $contents;
    }

    private function writeFile(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create [{$directory}].");
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("Unable to write [{$path}].");
        }
    }
}
