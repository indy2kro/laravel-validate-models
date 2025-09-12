<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Services;

use Carbon\Carbon;
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
     * @param array<string, string>       $ignore  // property names to skip
     * @param array<string, class-string> $aliases // DocBlock short type => FQCN
     */
    public function __construct(
        private readonly TypeMatcher $matcher,
        private readonly DocblockPropertyParser $parser = new DocblockPropertyParser(),
        private readonly bool $columnsOnly = true,
        private readonly bool $checkCasts = true,
        private readonly array $ignore = [],
        private readonly array $aliases = []
    ) {
    }

    public function check(Model $model): ValidationReport
    {
        $report = new ValidationReport();

        $ref   = new ReflectionClass($model);
        $doc   = $ref->getDocComment() ?: null;
        $props = $this->parser->parse($doc);
        if (!$props) {
            return $report; // nothing to check
        }

        $table = $model->getTable();
        if (!Schema::hasTable($table)) {
            // table existence is handled elsewhere; skip here
            return $report;
        }

        $columns = Schema::getColumnListing($table);
        $casts   = $model->getCasts();

        foreach ($props as $name => $info) {
            if (\in_array($name, $this->ignore, true)) {
                continue;
            }
            if ($this->columnsOnly && !\in_array($name, $columns, true)) {
                // skip relation-like annotations etc.
                continue;
            }

            // 1) Check DB compatibility
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
                    // If it's an enum class, let the matcher validate against DB type
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

            // 2) Optional: compare annotation vs cast token
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
     * Map a DocBlock type to a "cast token" that TypeMatcher understands (or null for class/enum).
     * Returns null for classes/enums so the caller can handle them specifically.
     */
    private function mapDocTypeToCastToken(string $type): ?string
    {
        $t     = ltrim($type, '\\');
        $lower = strtolower($t);

        // scalars
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

        // datetime-ish
        if (\in_array($t, ['Carbon', Carbon::class, \Illuminate\Support\Carbon::class, 'DateTime', 'DateTimeInterface'], true)) {
            return 'datetime';
        }
        if ($lower === 'date') {
            return 'date';
        }

        // If it's an enum or any other known class, let caller decide
        if (enum_exists($t) || class_exists($t)) {
            return null;
        }

        // Unknown pseudo type: return normalized token
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
