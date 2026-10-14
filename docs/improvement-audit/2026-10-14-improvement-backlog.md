# Improvement backlog — laravel-validate-models (2026-10-14)

Discovery only; no source changes. Items marked **(verified)** were reproduced with a throwaway test.

## Gate summary

| Gate | Result |
|---|---|
| `composer validate --strict` | pass |
| `composer audit --abandoned=report` | pass (1 abandoned transitive dev package: `symplify/rule-doc-generator-contracts`) |
| php-cs-fixer, PHPStan level 8, Rector, PHPUnit (6 tests) | pass on fresh deps |
| PHPStan `--level=max` | 82 errors: `mixed` config reads (`ValidateModelsCommand`, `AnnotationChecker` DBAL path) and untyped Schema closures in tests (see SWEEP-01) |
| `composer outdated --direct` | `orchestra/testbench` 11.3.0 available (Laravel 13), see COMPAT-01 |
| gitleaks | no leaks |
| actionlint | `ci.yml:39` SC2086 (CI-02) |

Effort: S = one file, M = one class plus tests, L = cross-cutting. Impact: high = wrong validation result or crash.

## Summary

| Lens | Items |
|---|---|
| bug | BUG-01 … BUG-09 |
| compat | COMPAT-01 … COMPAT-03 |
| feature / ux | FEAT-01, UX-01, UX-02 |
| docs | DOC-01 |
| test / ci | TEST-01, CI-01 … CI-03, SWEEP-01 |

## Bugs

- [ ] **BUG-01** Check models against their own connection. `ColumnCastChecker`/`AnnotationChecker` use the default `Schema`/`DB`, so a model with `$connection = 'other'` reports "Table does not exist" **(verified)**. Use `$model->getConnection()->getSchemaBuilder()` and pass the connection to the nullability lookup. `src/Services/ColumnCastChecker.php:37-52`, `src/Services/AnnotationChecker.php:48-65,181`. E: M, I: high.
- [ ] **BUG-02** `RelationChecker` invokes every zero-argument method declared on the model, so side-effecting helpers (`archive()`, `recalculate()`) run during validation **(verified)** and any throwing method becomes a bogus "Could not resolve relation" issue **(verified)**. Only call methods whose declared return type is a `Relation` subclass (opt-in config for untyped ones). `src/Services/RelationChecker.php:27-47`. E: M, I: high.
- [ ] **BUG-03** `FilesystemModelLocator` derives the class from the file name only: nested directories (`Models/Sub/Nested.php`) and non-PHP files (`readme.txt`) cause an uncaught `ReflectionException` that aborts the whole run **(verified)**. Filter to `*.php`, build the namespace from the relative path, and skip files that do not define the expected class. `src/Services/FilesystemModelLocator.php:28-40`. E: M, I: high.
- [ ] **BUG-04** `TypeMatcher` matches `str_contains($cast, $mapKey)`, which only works when the cast name contains the long map key, so common casts are flagged as mismatches **(verified)**: `date`, `bool`, `double`, `real`, `timestamp`, `hashed`, `encrypted`, `object`, `collection`, `AsArrayObject`. Add the missing aliases (or match exact cast tokens) and test each Laravel cast type. `src/Support/TypeMatcher.php:80-86`, `config/validate-models.php:35-46`. E: M, I: high.
- [ ] **BUG-05** The default `type_map` only knows SQLite/MySQL type names; PostgreSQL (`int2/int4/int8`, `bool`, `float4/float8`, `timestamptz`), MySQL `mediumint`/`year`, and MariaDB `longtext` for JSON columns are flagged as mismatches (`int4` vs an `int` cast **verified**). Add per-driver maps and a test per driver. `config/validate-models.php:35-46`, `src/Support/TypeMatcher.php:64-78`. E: M, I: high.
- [ ] **BUG-06** Annotation checks produce false positives for common docblock types: `CarbonImmutable`, `CarbonInterface`, `DateTimeImmutable`, `mixed`, `object`, `numeric-string`, `positive-int`, generics. Extend `mapDocTypeToCastToken` and skip unknown PHPStan pseudo-types instead of failing. `src/Services/AnnotationChecker.php:98-122,269-308`. E: M, I: med.
- [ ] **BUG-07** The annotation-vs-cast consistency check flags every custom cast class (`CastsAttributes`/`Castable`), because the annotated type is the value object, not the cast name. Skip the check when the cast is a custom caster, mirroring `TypeMatcher::isCompatible`. `src/Services/AnnotationChecker.php:124-155`. E: S, I: med.
- [ ] **BUG-08** `DocblockPropertyParser` reads the type as `[^\s]+` and splits on `|`, breaking `array<string, int>`, `Collection<int, Post>` and `array<int|string>`. Parse balanced brackets before splitting unions. `src/Support/DocblockPropertyParser.php:35-48`. E: M, I: med.
- [ ] **BUG-09** `ColumnCastChecker` ignores `checks.columns` entirely and calls `getColumnType()` for every column even when casts are off or the column has no cast, so large tables cost one metadata query per column per model. Fetch types once (`Schema::getColumns()`) and only when a cast is present. `src/Services/ColumnCastChecker.php:45-73`. E: M, I: med.

## Compatibility

- [ ] **COMPAT-01** Laravel 13 is not allowed (`illuminate/*` ^10|^11|^12, testbench ^8|^9|^10) while `composer outdated` offers testbench 11. Widen the constraints and the CI matrix. `composer.json:29-40`. E: S, I: med.
- [ ] **COMPAT-02** `getColumnType()` needs `doctrine/dbal` on Laravel 10, but DBAL is only a `suggest`; without it every column reports "Could not get type". Use `Schema::getColumns()` where available, or `require` DBAL for L10. `src/Services/ColumnCastChecker.php:52`. E: M, I: high.
- [ ] **COMPAT-03** Nullability lookup is mostly dead code on Laravel 11+ (`getDoctrineConnection` was removed) and falls back to raw `PRAGMA`/`information_schema` SQL that ignores table prefixes and pgsql search paths. Replace it with `Schema::getColumns()['nullable']` and delete the DBAL branch. `src/Services/AnnotationChecker.php:178-259`. E: M, I: med.

## Features and UX

- [ ] **FEAT-01** Add `--format=json` (and a GitHub annotations format) plus a baseline file so large codebases can adopt the command in CI. `src/Console/ValidateModelsCommand.php:66-69`. E: L, I: med.
- [ ] **UX-01** One failing model aborts the run (BUG-03 is one example). Wrap each model in try/catch and report an `internal` issue, as `ValidationIssue` already documents. `src/Console/ValidateModelsCommand.php:60-70`. E: S, I: med.
- [ ] **UX-02** The command has no `--no-annotations` option although `checks.annotations` exists, and the `ignore` config is global per check type instead of per model. `src/Console/ValidateModelsCommand.php:22-30,48-54`. E: S, I: low.

## Docs

- [ ] **DOC-01** README says "Laravel 10+ / PHP 8.1+" but `composer.json` requires PHP ^8.3; it also omits the command options, the annotation checks and a CI example. `README.md:12`. E: S, I: med.

## Tests and CI

- [ ] **TEST-01** Only 6 tests: `TypeMatcher`, `DocblockPropertyParser`, `FilesystemModelLocator` and the command options are untested, which is how BUG-03/04/08 went unnoticed. Add unit tests (data providers for casts and types) and move the `tests/_Tmp` fixtures to a clearly named `tests/Fixtures`. `tests/`. E: M, I: high.
- [ ] **CI-01** CI tests only the latest resolvable Laravel on PHP 8.3–8.5 (`deps: ['stable']`), so Laravel 10/11 and `--prefer-lowest` are never exercised. Add a Laravel-version matrix (`orchestra/testbench` pin) and a lowest-deps job. `.github/workflows/ci.yml:16-23`. E: M, I: high.
- [ ] **CI-02** actionlint SC2086: quote `"$GITHUB_OUTPUT"`; also the cache key hashes `**/composer.lock`, which is not committed, so the key never changes. `.github/workflows/ci.yml:39,46`. E: S, I: low.
- [ ] **CI-03** `composer test` fails with "No tests executed!" on machines without Xdebug/pcov because `phpunit.xml` always configures a coverage report, which also breaks `composer quality`; `XDEBUG_MODE=coverage` in `test:coverage` is not portable to Windows shells; the config uses a deprecated schema (`--migrate-configuration`). Move the clover report to the `test:coverage` flags and migrate the XML. `phpunit.xml:1-14`, `composer.json:49-51`. E: S, I: med.

## Sweeps

- [ ] **SWEEP-01** PHPStan level max (82 errors): type the config array shape once (a small config DTO or array-shape at the command boundary) instead of reading `mixed` at about 30 call sites, and type the Schema closures in tests; then raise `phpstan.neon` to `level: max`. `src/Console/ValidateModelsCommand.php`, `tests/*Test.php`. E: M, I: low.

## Executing

One branch and one PR per theme (suggested order: BUG-03 + BUG-02 + UX-01, BUG-04/05 + TEST-01, BUG-01 + COMPAT-02/03, CI-01 + COMPAT-01, the rest). Failing test first; run `composer quality` on fresh dependencies; keep dependencies compatible with PHP 8.3. Delete this directory when everything is done or declined.
