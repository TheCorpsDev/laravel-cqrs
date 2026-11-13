# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and the project follows [Semantic Versioning](https://semver.org/).

## [1.3.0] - Unreleased

### Added

- `CommandBusInterface` and `QueryBusInterface`. Both resolve to the same singletons as the concrete buses and the facades, which now use the interfaces as their container keys.
- Typed results: `CommandInterface` and `QueryInterface` take a `TResult` template (default `mixed`), and `dispatch()` returns it through the buses, the interfaces, the facades and the fakes. Existing classes without the annotation raise no new static-analysis errors.
- `CommandBus::fake()` and `QueryBus::fake()` with `assertDispatched()`, `assertDispatchedTimes()`, `assertNotDispatched()`, `assertNothingDispatched()`, `dispatched()`, `hasDispatched()` and per-class responses (`fake([Class::class => $value])`, `respondWith()`).
- `#[WithoutTransaction]` and `#[Transactional(connection, attempts)]` to skip or configure the transaction of a single command, plus the `cqrs.transactions` defaults.
- `CommandDispatching`, `CommandHandled`, `CommandFailed`, `QueryDispatching`, `QueryHandled` and `QueryFailed` events, with the result or exception and the duration. Events without listeners are not instantiated; disable them with `cqrs.events`.
- Handler and validator resolution by naming convention for messages without a handler attribute (`CreateUserCommand` → `CreateUserCommandHandler` or `CreateUserHandler`). Only classes implementing the matching interface are used. Disable with `cqrs.conventions`.
- `make:cqrs-command`, `make:cqrs-query` (with publishable stubs, `--tag=cqrs-stubs`) and `cqrs:list`, which lists every command and query with its handler and fails when one does not resolve.
- `MetadataResolver::forCommands()` and `MetadataResolver::forQueries()`.

### Changed

- `TransactionBehavior` opens the transaction through the database manager on the configured connection instead of the `DB` facade's default connection; with the default configuration the behavior is the same.
- The agent skill and the Boost guideline cover the new features.

## [1.2.0] - Unreleased

### Added

- Coding-agent integration. The package ships a `laravel-cqrs` skill and a guideline at the paths read by [Laravel Boost](https://laravel.com/docs/boost) (`resources/boost`), so `boost:install` sets them up for every agent Boost supports.
- `php artisan cqrs:install-agent-rules` installs the same skill without Boost, into `.agents/skills` and `.claude/skills`, and adds a marker-delimited pointer block to `AGENTS.md` and `CLAUDE.md`. Content outside the markers, line endings and symlinked files are preserved.

## [1.1.0] - Unreleased

### Fixed

- Pipeline behaviors are now built the first time a bus is resolved instead of during service provider registration. Custom behaviors can depend on bindings registered by application service providers, which previously made the application fail to boot.
- A command or query that reaches the end of the pipeline without being handled (for example when `HandlerExecutionBehavior` is missing) now throws `InvalidPipelineException` instead of silently returning `null`.
- Subclasses of `ValidationBehavior` and `HandlerExecutionBehavior` can be used in the pipeline. Any behavior whose constructor asks for a `MetadataResolver` receives the resolver of its pipeline.
- `LoggingBehavior` logs a `failed` entry when the pipeline throws, and every entry carries the fully qualified class name; completion and failure entries carry the duration.
- `#[CommandHandler]`, `#[CommandValidator]`, `#[QueryHandler]` and `#[QueryValidator]` are inherited from parent classes.
- The README no longer claims that a validator exception rolls back the transaction: in the default pipeline the validator runs before the transaction is opened.

### Added

- Dedicated exceptions, all extending `CqrsException` (itself a `RuntimeException`): `MissingHandlerException`, `InvalidHandlerException`, `InvalidValidatorException`, `InvalidPipelineException`. Handlers and validators that do not exist or lack `handle()` / `validate()` are reported with the class involved instead of a generic PHP error.
- `cqrs.logging.failure_level` configuration option (default `warning`).
- `MetadataResolver::flushCache()`.
- Context on every `LoggingBehavior` entry (`class`, and `duration_ms` / `exception` / `error` where relevant). The message text of the existing entries is unchanged.
- PHPUnit test suite, PHPStan at level max and a GitHub Actions workflow covering PHP 8.2–8.5 and Laravel 11–13.

### Changed

- The closure chain of each pipeline is built once and reused for every dispatch.
- Removed the duplicated default pipelines from the service provider; the package config file is the single source of defaults.

## [1.0.0] - 2026-06-20

- Initial release.

[1.3.0]: https://github.com/TheCorpsDev/laravel-cqrs/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/TheCorpsDev/laravel-cqrs/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/TheCorpsDev/laravel-cqrs/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/TheCorpsDev/laravel-cqrs/releases/tag/v1.0.0
