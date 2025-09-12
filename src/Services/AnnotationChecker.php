<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Services;

use Carbon\Carbon;
use Doctrine\DBAL\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Indy2kro\ValidateModels\Dto\ValidationIssue;
use Indy2kro\ValidateModels\Dto\ValidationReport;
use Indy2kro\ValidateModels\Support\DocblockPropertyParser;
use Indy2kro\ValidateModels\Support\TypeMatcher;
use ReflectionClass;
use Throwable;

final class AnnotationChecker
{
    /**
     * @param array<string, string>       $ignore  property names to skip
     * @param array<string, class-string> $aliases DocBlock short type => FQCN
     */
    public function __construct(
        private readonly TypeMatcher $matcher,
        private readonly DocblockPropertyParser $parser = new DocblockPropertyParser(),
        private readonly bool $columnsOnly = true,
        private readonly bool $checkCasts = true,
        private readonly array $ignore = [],
        private readonly array $aliases = [],
        private readonly bool $checkNullability = true
    ) {
    }

    public function check(Model $model): ValidationReport
    {
        $report = new ValidationReport();

        $ref   = new ReflectionClass($model);
        $doc   = $ref->getDocComment() ?: null;
        $props = $this->parser->parse($doc);
        if (!$props) {
            return $report;
        }

        $table = $model->getTable();
        if (!Schema::hasTable($table)) {
            return $report;
        }

        $columns = Schema::getColumnListing($table);
        $casts   = $model->getCasts();

        foreach ($props as $name => $info) {
            if (\in_array($name, $this->ignore, true)) {
                continue;
            }
            if ($this->columnsOnly && !\in_array($name, $columns, true)) {
                continue;
            }

            // DB type
            try {
                $dbType = DB::getSchemaBuilder()->getColumnType($table, $name);
            } catch (Throwable) {
                $report = $report->with(new ValidationIssue(
                    $model::class,
                    'annotation',
                    \sprintf("@property \$%s type '%s' cannot be checked: unknown DB type.", $name, $info['raw'])
                ));
                continue;
            }

            // DB nullability
            if ($this->checkNullability) {
                $isNullable = $this->resolveColumnNullability($table, $name);
                if ($isNullable !== null) {
                    if ($info['nullable'] && $isNullable === false) {
                        $report = $report->with(new ValidationIssue(
                            $model::class,
                            'annotation',
                            \sprintf("@property \$%s allows null but column '%s.%s' is NOT NULL", $name, $table, $name)
                        ));
                    }
                    if (!$info['nullable'] && $isNullable === true) {
                        $report = $report->with(new ValidationIssue(
                            $model::class,
                            'annotation',
                            \sprintf("@property \$%s does not allow null but column '%s.%s' is NULLABLE", $name, $table, $name)
                        ));
                    }
                }
            }

            // DB type compatibility
            $dbOk = false;
            foreach ($info['types'] as $type) {
                $resolved = $this->resolveAlias($type);
                $mapped   = $this->mapDocTypeToCastToken($resolved);

                if ($mapped !== null) {
                    if ($this->matcher->isCompatible((string) $dbType, $mapped)) {
                        $dbOk = true;
                        break;
                    }
                } else {
                    $class = ltrim($resolved, '\\');
                    if (enum_exists($class) && $this->matcher->isCompatible((string) $dbType, $class)) {
                        $dbOk = true;
                        break;
                    }
                }
            }

            if (!$dbOk) {
                $report = $report->with(new ValidationIssue(
                    $model::class,
                    'annotation',
                    \sprintf("@property \$%s '%s' not compatible with DB type '%s'", $name, $info['raw'], (string) $dbType)
                ));
            }

            // Cast consistency
            if ($this->checkCasts && isset($casts[$name])) {
                $castToken = $casts[$name];
                $castOk    = false;

                foreach ($info['types'] as $type) {
                    $resolved = $this->resolveAlias($type);

                    if (\is_string($castToken)) {
                        $castClass = ltrim((string) $castToken, '\\');

                        // Enum class ↔ cast FQCN
                        if (enum_exists($castClass) && strcasecmp($castClass, ltrim($resolved, '\\')) === 0) {
                            $castOk = true;
                            break;
                        }

                        // Scalar/datetime-ish tokens
                        $docToken = $this->mapDocTypeToCastToken($resolved);
                        if ($docToken !== null && $this->castTokensSimilar($docToken, $castClass)) {
                            $castOk = true;
                            break;
                        }
                    }
                }

                if (!$castOk) {
                    $report = $report->with(new ValidationIssue(
                        $model::class,
                        'annotation',
                        \sprintf("@property \$%s '%s' seems inconsistent with cast '%s'", $name, $info['raw'], (string) $castToken)
                    ));
                }
            }
        }

        return $report;
    }

    /**
     * Resolve DocBlock type through configured aliases; returns a class-ish or scalar-ish token.
     */
    private function resolveAlias(string $type): string
    {
        $t = ltrim($type, '\\');
        if (isset($this->aliases[$t])) {
            return ltrim((string) $this->aliases[$t], '\\');
        }

        return $t;
    }

    /**
     * Determine nullability for a DB column.
     * Returns: true (=NULL allowed), false (=NOT NULL), null (=unknown).
     */
    private function resolveColumnNullability(string $table, string $column): ?bool
    {
        // 1) Prefer Doctrine DBAL if the connection exposes it (and DBAL is installed)
        $conn   = DB::connection();
        $method = 'getDoctrineConnection';
        if (class_exists(Connection::class) && method_exists($conn, $method)) {
            try {
                /** @var object $doctrine */
                $doctrine = $conn->$method(); // dynamic call avoids static analysis error
                $sm       = null;
                if (method_exists($doctrine, 'createSchemaManager')) {
                    $sm = $doctrine->createSchemaManager(); // DBAL 3
                } elseif (method_exists($doctrine, 'getSchemaManager')) {
                    $sm = $doctrine->getSchemaManager();   // DBAL 2
                }
                if ($sm && method_exists($sm, 'introspectTable')) {
                    // @phpstan-ignore-next-line
                    $tbl = $sm->introspectTable($table);
                    if ($tbl->hasColumn($column)) {
                        $col = $tbl->getColumn($column);

                        return !$col->getNotnull();
                    }
                } elseif ($sm && method_exists($sm, 'listTableDetails')) {
                    // @phpstan-ignore-next-line
                    $tbl = $sm->listTableDetails($table);
                    if ($tbl->hasColumn($column)) {
                        $col = $tbl->getColumn($column);

                        return !$col->getNotnull();
                    }
                }
            } catch (Throwable) {
                // fall through to driver-specific heuristics
            }
        }

        // 2) Driver-specific fallbacks
        $driver = (string) DB::connection()->getDriverName();

        try {
            if ($driver === 'sqlite') {
                $rows = DB::select('PRAGMA table_info(' . $this->quoteSqliteIdent($table) . ')');
                foreach ($rows as $r) {
                    if (isset($r->name, $r->notnull) && (string) $r->name === $column) {
                        return ((int) $r->notnull) === 0; // 0 => nullable, 1 => not null
                    }
                }

                return null;
            }

            if ($driver === 'mysql' || $driver === 'mariadb') {
                $schema = DB::getDatabaseName();
                $row    = DB::selectOne(
                    'SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
                    [$schema, $table, $column]
                );
                if ($row && isset($row->IS_NULLABLE)) {
                    return strtoupper((string) $row->IS_NULLABLE) === 'YES';
                }

                return null;
            }

            if ($driver === 'pgsql') {
                $row = DB::selectOne(
                    'SELECT is_nullable FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ? LIMIT 1',
                    [$table, $column]
                );
                if ($row && isset($row->is_nullable)) {
                    return strtoupper((string) $row->is_nullable) === 'YES';
                }

                return null;
            }
        } catch (Throwable) {
            // ignore and report unknown
        }

        return null;
    }

    private function quoteSqliteIdent(string $ident): string
    {
        return '"' . str_replace('"', '""', $ident) . '"';
    }

    /**
     * Map a DocBlock type to a "cast token" that TypeMatcher understands (or null for class/enum).
     */
    private function mapDocTypeToCastToken(string $type): ?string
    {
        $t     = ltrim($type, '\\');
        $lower = strtolower($t);

        if ($lower === 'int' || $lower === 'integer') {
            return 'integer';
        }
        if ($lower === 'string') {
            return 'string';
        }
        if ($lower === 'bool' || $lower === 'boolean') {
            return 'boolean';
        }
        if ($lower === 'float' || $lower === 'double') {
            return 'float';
        }
        if ($lower === 'decimal') {
            return 'decimal';
        }
        if ($lower === 'array') {
            return 'array';
        }
        if ($lower === 'json') {
            return 'json';
        }

        if (\in_array($t, ['Carbon', Carbon::class, \Illuminate\Support\Carbon::class, 'DateTime', 'DateTimeInterface'], true)) {
            return 'datetime';
        }
        if ($lower === 'date') {
            return 'date';
        }

        if (enum_exists($t) || class_exists($t)) {
            return null;
        }

        return $lower;
    }

    /** Compare doc token vs cast token loosely (normalize keywords). */
    private function castTokensSimilar(string $docToken, string $castToken): bool
    {
        $a = strtolower($docToken);
        $b = strtolower(ltrim($castToken, '\\'));

        $normalize = static function (string $x): string {
            if (str_contains($x, 'int')) {
                return 'integer';
            }
            if (str_contains($x, 'bool')) {
                return 'boolean';
            }
            if (str_contains($x, 'string')) {
                return 'string';
            }
            if (str_contains($x, 'float') || str_contains($x, 'double')) {
                return 'float';
            }
            if (str_contains($x, 'decimal')) {
                return 'decimal';
            }
            if (str_contains($x, 'datetime')) {
                return 'datetime';
            }
            if (str_contains($x, 'date')) {
                return 'date';
            }
            if (str_contains($x, 'json')) {
                return 'json';
            }
            if (str_contains($x, 'array')) {
                return 'array';
            }

            return $x;
        };

        return $normalize($a) === $normalize($b);
    }
}
