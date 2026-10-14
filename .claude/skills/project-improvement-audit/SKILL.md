---
name: project-improvement-audit
description: Use when you want a project-wide audit of the laravel-validate-models package (src/, tests, CI workflows, dependencies) to find improvement opportunities — correctness bugs (false positives and negatives in model validation), Laravel/PHP version compatibility, validation coverage gaps, docs drift, test/CI gaps — compiled into a prioritized checkbox backlog in docs/improvement-audit/. Discovery only; no source changes.
---

# Project Improvement Audit — laravel-validate-models

## Overview

The package is small (about 15 classes under `src/`), so the audit is one pass: harvest the
mechanical gates, read the code once against the lenses below, then write a dated backlog.
**No code changes.** The deliverable is the report.

The package reflects on arbitrary application models and reads the database schema; treat
model class names, table names and docblocks as untrusted input when they reach file or
class loading.

## Token budget

- **No subagents.** Work in the main conversation.
- Up to **20 deduplicated items** (honor an explicit override; never pad). One-sentence rationale each.
- Skip pure style findings (php-cs-fixer, PHPStan and Rector already guard them).
- Never pipe a gate through `tail` and then chain on it: it hides the exit code.
  Capture output to a file and check `$?` separately.
- `rg -g` can be rewritten by the rtk hook and fail. Use the Grep tool or `rtk proxy rg ...`.

## Security stop rule

If a finding is a **security vulnerability**, do not exploit it and do not build a proof
of concept. Record it, stop the audit, tell the user and ask them to inform information
security. Continue only when the user says to.

## Lenses

| Lens | Looks for |
|---|---|
| `bug` | Wrong results: false positives or negatives in column, cast, fillable, relation and `@property` checks; casts defined via `casts()` method versus `$casts` property; enum and custom cast classes; nullable and default handling; drivers other than SQLite (MySQL, PostgreSQL) |
| `compat` | Laravel 12/13 and PHP 8.3–8.5 differences, `Schema::getColumns()` type names per driver, constraints in `composer.json` that do not match what CI tests |
| `sec` | Model discovery loading arbitrary files or classes (`FilesystemModelLocator`), path handling, anything that executes or `include`s user-controlled paths |
| `feature` | Checks worth adding: unique/index awareness, morph relations, soft deletes, `$hidden`/`$appends`, JSON output formats for CI, exit codes and baseline files |
| `ux` | Console command options and output clarity, error messages, config publishing, service provider auto-discovery |
| `docs` | README and CLAUDE.md drift from the code, missing usage examples and CHANGELOG |
| `test` / `ci` | Checkers without tests, missing DB-driver or Laravel-version matrix, coverage reporting, gates missing from `.github/workflows/`, dependabot coverage |

Tie-break: `sec` > `bug` > `compat` > `feature` > `ux`.

## Pipeline

1. **Mechanical harvest** (repo root). CI resolves fresh dependencies (no lock file is committed), so reproduce that:

   ```sh
   composer update --no-progress
   composer validate --strict
   composer audit --abandoned=report ; composer outdated --direct
   composer quality          # php-cs-fixer, phpstan (level max), rector --dry-run, phpunit
   gitleaks detect --no-banner -s .
   actionlint
   ```

2. **Read** every file under `src/`, `tests/`, plus `README.md`, `composer.json`, `.github/`.
   Check every claim against the code (and with a small test when cheap) before recording it.

3. **Dedupe and prioritize.** High impact (wrong validation result, crash) and low effort first.
   Compare with earlier backlogs (`git log -- docs/improvement-audit/`) so fixed items are not re-reported.

4. **Write** `docs/improvement-audit/YYYY-MM-DD-improvement-backlog.md`. Stable IDs
   (`BUG-NN`, `COMPAT-NN`, `SEC-NN`, `FEAT-NN`, `UX-NN`, `DOC-NN`, `TEST-NN`, `CI-NN`), every item a `- [ ]`
   with `file:line`, effort (S/M/L), impact (low/med/high) and one-sentence rationale.
   Commit the report with no source changes.

## Executing a backlog (later rounds)

- One branch and one PR per theme. Write the failing test first (Orchestra Testbench, in-memory SQLite).
- Run `composer quality` against fresh dependencies before every push and check each exit code.
- Keep dependencies compatible with PHP 8.3 (`config.platform.php`).
- Remove `docs/improvement-audit/` once everything in it is done or explicitly declined.

## Done criteria

- [ ] Items have an ID, checkbox, `file:line`, effort, impact and rationale
- [ ] Mechanical gate failures aggregated, not listed per line
- [ ] No duplicates, none already fixed
- [ ] Report under `docs/improvement-audit/`; no source changes
