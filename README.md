## Introduction

Validate your Eloquent models against the database schema.
Checks columns, casts, fillable fields, relations and `@property` annotations — catch mismatches early in CI before they break production.

## ✨ Features

 * 🔍 Scans all models in app/Models, including sub-directories (configurable paths/namespaces)
 * ✅ Validates:
   * Table existence (on the model's own connection)
   * Column type vs. model cast (built-in casts, enums, custom casters and `Castable` classes)
   * Fillable fields vs. table columns
   * Relations resolve without error
   * `@property` annotations vs. column types, nullability and casts
 * ⚙️ Configurable via config/validate-models.php
 * 🤖 CI friendly: JSON and GitHub annotation output, baseline files, exit codes
 * 📦 Works with Laravel 12 and 13 / PHP 8.3+
 * 🧪 Fully tested with Orchestra Testbench
 * 🔧 Ships with CI tooling (PHP-CS-Fixer, PHPStan, Rector, PHPUnit)

Laravel 11 and older are not supported: Laravel 10 is end-of-life, `Schema::getColumns()` (used to read column types and nullability) needs Laravel 11+, and every Laravel 11 release is flagged by security advisories.

## 📦 Installation

```
composer require indy2kro/laravel-validate-models --dev
```

Publish the config (optional):

```
php artisan vendor:publish --tag=validate-models-config
```

## ⚙️ Configuration

`config/validate-models.php`:

```php
return [
    'models_paths' => [base_path('app/Models')],
    'models_namespaces' => ['App\\Models'],
    'connection' => null,

    // Models that must not be validated (fully-qualified class names)
    'exclude_models' => [],

    'checks' => [
        'columns'   => true,
        'casts'     => true,
        'fillable'  => true,
        'relations' => true,
        'annotations' => true,
    ],

    'relations' => [
        // Relation methods are found by their Relation return type. Set to true to also call methods without
        // a return type; this executes arbitrary zero-argument model methods, so only enable it if needed.
        'include_untyped' => false,
    ],

    'annotations' => [
        'columns_only' => true, // only check @property names that exist as columns
        'check_casts'  => true, // also compare annotation types vs model casts
        'check_nullability' => true, // enable/disable nullable enforcement
        'ignore'       => [     // names to ignore, or 'App\Models\User::name' for one model
        ],
        'aliases'      => [     // map short names used in @property tags to FQCNs
        ],
    ],

    // Field names to ignore, or 'App\Models\User::field' to ignore a field on a single model
    'ignore' => [
        'casts'     => [],
        'fillable'  => [],
        'columns'   => [],
        'relations' => [],
    ],

    'fail_on_warnings' => true,

    // Cast token => database column types it may be stored in. See config/validate-models.php for the full default.
    'type_map' => [
        '*' => [
            'integer'  => ['int', 'integer', 'bigint', 'mediumint', 'smallint', 'tinyint', 'int2', 'int4', 'int8'],
            // ...
        ],
    ],
];
```

The type map uses the type names reported by the schema builder, so it covers SQLite, MySQL/MariaDB (JSON is `longtext` on MariaDB) and PostgreSQL (`int4`, `bool`, `timestamptz`, …). Add a driver key (`'pgsql' => [...]`) to extend the map for one driver; it is merged with the `'*'` entries.

## 🚀 Usage

Run the validation:

```
php artisan validate:models
```

Options:

| Option | Description |
|---|---|
| `--path=*` | Override model paths (default: `app/Models`) |
| `--namespace=*` | Override model namespaces (default: `App\Models`) |
| `--connection=` | Use a specific DB connection as the default |
| `--no-columns` | Do not report missing tables |
| `--no-casts` | Skip cast checks |
| `--no-fillable` | Skip fillable checks |
| `--no-relations` | Skip relation checks |
| `--no-annotations` | Skip `@property` annotation checks |
| `--no-fail` | Always exit 0, even with warnings |
| `--format=` | `text` (default), `json` or `github` (workflow annotations) |
| `--baseline=` | JSON file of known issues to ignore |
| `--generate-baseline=` | Write the current issues to a file and exit 0 |

A model that cannot be instantiated or validated is reported as an `internal` issue; the run continues with the next model.

### Adopting it in an existing project

Record today's issues once, commit the file, and fail CI only on new ones:

```
php artisan validate:models --generate-baseline=validate-models-baseline.json
php artisan validate:models --baseline=validate-models-baseline.json --format=github
```

### GitHub Actions

```yaml
- name: Validate models
  run: php artisan validate:models --format=github
```

## 🧪 Testing locally

```
composer install
composer quality    # cs + stan + rector + test (what CI runs)
composer cs         # lint (php-cs-fixer dry-run)
composer cs:fix     # auto-fix style
composer stan       # static analysis (PHPStan level max)
composer rector     # preview Rector refactors
composer rector:fix # apply Rector refactors
composer test       # run PHPUnit
XDEBUG_MODE=coverage composer test:coverage   # needs pcov or Xdebug
```
