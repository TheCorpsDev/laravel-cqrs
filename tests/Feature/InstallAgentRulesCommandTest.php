<?php

namespace TheCorps\LaravelCqrs\Tests\Feature;

use Illuminate\Console\Command;
use PHPUnit\Framework\Attributes\Test;
use TheCorps\LaravelCqrs\Tests\TestCase;
use TheCorps\LaravelCqrs\Support\AgentRulesInstaller;

final class InstallAgentRulesCommandTest extends TestCase
{
    private const START = '<!-- laravel-cqrs-agent-rules:start -->';

    private string $projectPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectPath = sys_get_temp_dir() . '/laravel-cqrs-agent-rules-' . uniqid('', true);
        mkdir($this->projectPath, 0755, true);
        $this->app->setBasePath($this->projectPath);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->projectPath);

        parent::tearDown();
    }

    #[Test]
    public function it_installs_agent_rules_for_a_clean_project(): void
    {
        $this->artisan('cqrs:install-agent-rules')->assertExitCode(Command::SUCCESS);

        $this->assertFileExists($this->projectPath . '/AGENTS.md');
        $this->assertFileExists($this->projectPath . '/CLAUDE.md');

        $source = file_get_contents(AgentRulesInstaller::skillSourcePath());

        $this->assertSame($source, file_get_contents($this->projectPath . '/.agents/skills/laravel-cqrs/SKILL.md'));
        $this->assertSame($source, file_get_contents($this->projectPath . '/.claude/skills/laravel-cqrs/SKILL.md'));

        $agents = (string) file_get_contents($this->projectPath . '/AGENTS.md');

        $this->assertStringStartsWith(self::START, $agents);
        $this->assertStringContainsString('`.agents/skills/laravel-cqrs/SKILL.md`', $agents);
        $this->assertStringContainsString('`.claude/skills/laravel-cqrs/SKILL.md`', $agents);
        $this->assertSame($agents, file_get_contents($this->projectPath . '/CLAUDE.md'));
    }

    #[Test]
    public function it_preserves_existing_instructions_and_is_idempotent(): void
    {
        file_put_contents($this->projectPath . '/AGENTS.md', "# Existing rules\nKeep this instruction.\n");
        file_put_contents($this->projectPath . '/CLAUDE.md', "# Claude rules\nKeep this too.\n");

        $this->artisan('cqrs:install-agent-rules')->assertExitCode(Command::SUCCESS);

        $firstInstall = (string) file_get_contents($this->projectPath . '/AGENTS.md');

        $this->artisan('cqrs:install-agent-rules')->assertExitCode(Command::SUCCESS);

        $this->assertStringStartsWith("# Existing rules\nKeep this instruction.\n\n" . self::START, $firstInstall);
        $this->assertStringStartsWith("# Claude rules\nKeep this too.\n\n" . self::START, (string) file_get_contents($this->projectPath . '/CLAUDE.md'));
        $this->assertSame($firstInstall, file_get_contents($this->projectPath . '/AGENTS.md'));
        $this->assertSame(1, substr_count($firstInstall, self::START));
    }

    #[Test]
    public function content_written_after_the_block_is_left_untouched_on_reinstall(): void
    {
        $this->artisan('cqrs:install-agent-rules')->assertExitCode(Command::SUCCESS);

        $path = $this->projectPath . '/AGENTS.md';
        $edited = file_get_contents($path) . "\n## Added later by the team\nKeep me.\n";
        file_put_contents($path, $edited);

        $this->artisan('cqrs:install-agent-rules')->assertExitCode(Command::SUCCESS);

        $this->assertSame($edited, file_get_contents($path));
    }

    #[Test]
    public function an_outdated_block_is_replaced(): void
    {
        file_put_contents(
            $this->projectPath . '/AGENTS.md',
            "# Rules\n\n" . self::START . "\nOld text\n<!-- laravel-cqrs-agent-rules:end -->\n\n## After\n",
        );

        $this->artisan('cqrs:install-agent-rules')->assertExitCode(Command::SUCCESS);

        $contents = (string) file_get_contents($this->projectPath . '/AGENTS.md');

        $this->assertStringNotContainsString('Old text', $contents);
        $this->assertStringStartsWith("# Rules\n\n" . self::START . "\n## Laravel CQRS", $contents);
        $this->assertStringEndsWith("<!-- laravel-cqrs-agent-rules:end -->\n\n## After\n", $contents);
    }

    #[Test]
    public function windows_line_endings_are_kept(): void
    {
        file_put_contents($this->projectPath . '/AGENTS.md', "# Rules\r\nKeep.\r\n");

        $this->artisan('cqrs:install-agent-rules')->assertExitCode(Command::SUCCESS);

        $contents = (string) file_get_contents($this->projectPath . '/AGENTS.md');

        $this->assertSame(0, preg_match("/(?<!\r)\n/", $contents), 'Every line ending must be CRLF.');
    }

    #[Test]
    public function a_symlinked_claude_md_receives_the_same_block_once(): void
    {
        if (!$this->canSymlink()) {
            $this->markTestSkipped('Symlinks are not available.');
        }

        file_put_contents($this->projectPath . '/AGENTS.md', "# Team rules\n");
        symlink('AGENTS.md', $this->projectPath . '/CLAUDE.md');

        $this->artisan('cqrs:install-agent-rules')->assertExitCode(Command::SUCCESS);

        $contents = (string) file_get_contents($this->projectPath . '/AGENTS.md');

        $this->assertTrue(is_link($this->projectPath . '/CLAUDE.md'));
        $this->assertSame(1, substr_count($contents, self::START));
        $this->assertStringContainsString('`.agents/skills/laravel-cqrs/SKILL.md`', $contents);
        $this->assertStringContainsString('`.claude/skills/laravel-cqrs/SKILL.md`', $contents);
    }

    #[Test]
    public function a_symlinked_skills_directory_is_written_once(): void
    {
        if (!$this->canSymlink()) {
            $this->markTestSkipped('Symlinks are not available.');
        }

        mkdir($this->projectPath . '/.agents/skills', 0755, true);
        mkdir($this->projectPath . '/.claude', 0755, true);
        symlink('../.agents/skills', $this->projectPath . '/.claude/skills');

        $this->artisan('cqrs:install-agent-rules')
            ->expectsOutputToContain('.agents/skills/laravel-cqrs/SKILL.md')
            ->doesntExpectOutputToContain('.claude/skills/laravel-cqrs/SKILL.md')
            ->assertExitCode(Command::SUCCESS);

        $this->assertFileExists($this->projectPath . '/.claude/skills/laravel-cqrs/SKILL.md');
    }

    #[Test]
    public function it_refuses_to_overwrite_an_unmanaged_skill_without_changing_root_files(): void
    {
        $path = $this->projectPath . '/.claude/skills/laravel-cqrs';
        mkdir($path, 0755, true);
        file_put_contents($path . '/SKILL.md', "---\ndescription: A custom skill\n---\n");

        $this->artisan('cqrs:install-agent-rules')
            ->expectsOutputToContain('Refusing to overwrite unmanaged skill')
            ->assertExitCode(Command::FAILURE);

        $this->assertFileDoesNotExist($this->projectPath . '/AGENTS.md');
        $this->assertFileDoesNotExist($this->projectPath . '/CLAUDE.md');
        $this->assertFileDoesNotExist($this->projectPath . '/.agents/skills/laravel-cqrs/SKILL.md');
        $this->assertSame("---\ndescription: A custom skill\n---\n", file_get_contents($path . '/SKILL.md'));
    }

    #[Test]
    public function the_shipped_skill_and_boost_guideline_are_well_formed(): void
    {
        $skill = (string) file_get_contents(AgentRulesInstaller::skillSourcePath());

        $this->assertMatchesRegularExpression('/\A---\nname: laravel-cqrs\ndescription: .+\n---\n/', $skill);
        $this->assertStringContainsString('<!-- laravel-cqrs-managed-skill -->', $skill);
        $this->assertFileExists(dirname(__DIR__, 2) . '/resources/boost/guidelines/core.blade.php');
    }

    private function canSymlink(): bool
    {
        return function_exists('symlink') && PHP_OS_FAMILY !== 'Windows';
    }

    private function deleteDirectory(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->deleteDirectory($path . DIRECTORY_SEPARATOR . $entry);
            }
        }

        rmdir($path);
    }
}
