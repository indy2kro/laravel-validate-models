# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Laravel package (`indy2kro/laravel-validate-models`, PSR-4 namespace `Indy2kro\ValidateModels\` → `src/`) that validates Eloquent models against the database schema: columns, casts, fillables, relations and `@property` annotations. Requires PHP >= 8.3 and Laravel 12 or 13 (Laravel 11 and older are out: end-of-life, no `Schema::getColumns()` before 11, and every 11.x release has security advisories). Keep dependency constraints compatible with PHP 8.3 (`config.platform.php`).

## Commands

`composer quality` (alias `composer ci`) runs the gates CI runs (`.github/workflows/ci.yml`; CI also runs `composer validate --strict` and `composer audit`):

```
composer cs      # php-cs-fixer dry-run (.php-cs-fixer.php)
composer stan    # phpstan level max + larastan, 1G memory limit
composer rector  # rector dry-run (rector.php)
composer test    # phpunit (coverage: XDEBUG_MODE=coverage composer test:coverage, needs pcov/Xdebug)
```

Automatic fixes: `composer cs:fix`, `composer rector:fix`.

Single test: `vendor/bin/phpunit --filter testName tests/SomeTest.php`

## Architecture

- `ValidateModelsCommand` (`src/Console`) reads `Settings` (typed view of the config), locates models through the `ModelLocator` contract (`FilesystemModelLocator`) and runs each through `ModelValidator` (`ModelValidatorService`). A model that throws becomes an `internal` issue. Output formats (`text`, `json`, `github`) and baseline files live in the command.
- The service aggregates checks from `src/Services`: `ColumnCastChecker`, `RelationChecker`, `AnnotationChecker`. Each adds `ValidationIssue`s to an immutable `ValidationReport` (`src/Dto`).
- `src/Support`: `SchemaInspector` (one `Schema::getColumns()` call per table, on the model's own connection), `TypeMatcher` (cast token to database types; `canonicalCast()`, `isCustomCast()`), `DocblockPropertyParser`, `IgnoreList` (`field` or `Model::class::field` entries), `Settings`.
- `RelationChecker` only calls methods whose return type is a `Relation`, because calling arbitrary model methods has side effects (`relations.include_untyped` opts in).

## Conventions

- PSR-12, short array syntax, `declare(strict_types=1)`.
- No `composer.lock` is committed (library); CI resolves fresh dependencies, so reproduce CI with `composer update` and run the gates before pushing.
- Dependabot (composer and github-actions, weekly, grouped) keeps dependencies and pinned action SHAs current.

The improvement audit is available as the `project-improvement-audit` skill (`.claude/skills/`).
