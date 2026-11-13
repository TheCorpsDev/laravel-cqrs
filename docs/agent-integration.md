# Coding-agent integration

`thecorps/laravel-cqrs` ships the rules a coding agent needs to write CQRS code that fits the package: when to use a command or a query, where files go, which interfaces and attributes to use, how to dispatch, and which pipeline invariants to keep.

The rules live in one skill, [`resources/boost/skills/laravel-cqrs/SKILL.md`](../resources/boost/skills/laravel-cqrs/SKILL.md), in the [Agent Skills](https://agentskills.io) format. There are two ways to install it; use one.

## Option 1: Laravel Boost

If the application uses [Laravel Boost](https://laravel.com/docs/boost), run:

```bash
php artisan boost:install   # or boost:update to pick up newly installed packages
```

Boost detects the package and offers to install:

- its guideline, [`resources/boost/guidelines/core.blade.php`](../resources/boost/guidelines/core.blade.php), a short overview loaded upfront;
- its `laravel-cqrs` skill, loaded on demand.

Boost then writes them in the format of every agent you selected (Claude Code, Codex, Cursor, GitHub Copilot, Junie and others) and keeps them updated with `boost:update`.

## Option 2: the package command

Without Boost, run:

```bash
php artisan cqrs:install-agent-rules
```

It is opt-in: installing the Composer package never edits application files by itself. The command writes, relative to Laravel's base path:

```text
AGENTS.md                              read by Codex, Cursor, GitHub Copilot and most other agents
CLAUDE.md                              read by Claude Code
.agents/skills/laravel-cqrs/SKILL.md   Agent Skills location used by Codex and others
.claude/skills/laravel-cqrs/SKILL.md   skill location used by Claude Code
```

`AGENTS.md` and `CLAUDE.md` receive the same short block that points to the skill, delimited by:

```text
<!-- laravel-cqrs-agent-rules:start -->
<!-- laravel-cqrs-agent-rules:end -->
```

An agent that does not support skills still follows the pointer and reads the file.

Behavior:

- Content outside the markers is never changed, including blank lines and the file's line endings.
- Re-running the command replaces the block and the managed skills with the version shipped by the installed package, so run it again after upgrading.
- Symlinks are respected: if `CLAUDE.md` links to `AGENTS.md`, or `.claude/skills` links to `.agents/skills`, the shared file is written once.
- If either skill path already holds a `SKILL.md` that was not written by this package (it lacks the `<!-- laravel-cqrs-managed-skill -->` marker), the command stops before writing anything.

## What the rules cover

- Classify the use case first: every state change is a command; query handlers never write.
- Follow the project's existing structure for CQRS classes; when there is none, use `app/Domain/{Feature}/Commands/{UseCase}/` and `app/Domain/{Feature}/Queries/{UseCase}/`.
- Commands and queries are `final` immutable DTOs with the matching interface and handler attribute; validators only when a domain rule needs one.
- Dispatch only through `CommandBus` / `QueryBus`; never call handlers directly.
- `FormRequest` for transport validation, CQRS validators for domain rules, a unique index for real uniqueness.
- Keep `HandlerExecutionBehavior` last and `TransactionBehavior` out of the query pipeline.
- How to test code that dispatches commands.

## Enforcement boundary

These files are instructions for agents, not a static analyzer or a runtime check. Keep code review, and add architecture tests if the project needs technical enforcement.
