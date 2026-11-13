# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and the project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
- PHPUnit test suite, PHPStan at level max and a GitHub Actions workflow covering PHP 8.2–8.5 and Laravel 11–13.

### Changed

- The closure chain of each pipeline is built once and reused for every dispatch.
- Removed the duplicated default pipelines from the service provider; the package config file is the single source of defaults.

## [1.0.0] - 2026-06-20

- Initial release.

[Unreleased]: https://github.com/TheCorpsDev/laravel-cqrs/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/TheCorpsDev/laravel-cqrs/releases/tag/v1.0.0
