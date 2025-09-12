## Introduction

Validate your Eloquent models against the database schema.
Checks columns, casts, fillable fields, and relations — catch mismatches early in CI before they break production.

## ✨ Features

 * 🔍 Scans all models in app/Models (configurable paths/namespaces)
 * ✅ Validates:
   * Table existence
   * Column type vs. model cast (including enums + custom casters)
   * Fillable fields vs. table columns
   * Relations resolve without error
 * ⚙️ Configurable via config/validate-models.php
 * 📦 Works with Laravel 10+ / PHP 8.1+
 * 🧪 Fully tested with Orchestra Testbench
 * 🔧 Ships with CI tooling (PHP-CS-Fixer, PHPStan, Rector, PHPUnit)

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

```
return [
    'models_paths' => [base_path('app/Models')],
    'models_namespaces' => ['App\\Models'],
    'connection' => null,

    'checks' => [
        'columns'   => true,
        'casts'     => true,
        'fillable'  => true,
        'relations' => true,
    ],

    'ignore' => [
        'casts'     => [],
        'fillable'  => [],
        'columns'   => [],
        'relations' => [],
    ],

    'fail_on_warnings' => true,

    'type_map' => [
        '*' => [
            'integer'  => ['int','integer','bigint','smallint','tinyint'],
            'string'   => ['string','varchar','char','text','enum','set'],
            'boolean'  => ['bool','boolean','tinyint'],
            'float'    => ['float','double','decimal'],
            'decimal'  => ['decimal','numeric'],
            'datetime' => ['datetime','timestamp'],
            'json'     => ['json','jsonb','array'],
            'array'    => ['json','jsonb','array'],
        ],
    ],
];
```

## 🚀 Usage

Run the validation:

```
php artisan validate:models
```

Options:

```
Option	Description
--path	Override model paths (default: app/Models)
--namespace	Override model namespaces (default: App\\Models)
--connection	Use a specific DB connection
--no-columns	Skip column checks
--no-casts	Skip cast checks
--no-fillable	Skip fillable checks
--no-relations	Skip relation checks
--no-fail	Always exit 0, even with warnings
```

## 🧪 Testing locally

```
composer install
composer cs         # lint (php-cs-fixer dry-run)
composer cs:fix     # auto-fix style
composer stan       # static analysis (PHPStan)
composer rector     # preview Rector refactors
composer rector:fix # apply Rector refactors
composer test       # run PHPUnit
composer test:coverage
```