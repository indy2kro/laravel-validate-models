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
     * @param list<string> $ignore
     */
    public function __construct(
        private readonly TypeMatcher $matcher,
        private readonly DocblockPropertyParser $parser = new DocblockPropertyParser(),
        private readonly bool $columnsOnly = true,
        private readonly bool $checkCasts = true,
        private readonly array $ignore = [],
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
            // handled by other checkers; skip here
            return $report;
        }

        $columns = Schema::getColumnListing($table);
        $casts   = $model->getCasts();

        foreach ($props as $name => $info) {
            if (\in_array($name, $this->ignore, true)) {
                continue;
            }
            if ($this->columnsOnly && !\in_array($name, $columns, true)) {
                continue; // ignore relation-like annotations, etc.
            }

            // 1) DB compatibility: any of the annotated types must be compatible with the column DB type
            $dbType = null;
            try {
                $dbType = DB::getSchemaBuilder()->getColumnType($table, $name);
            } catch (Throwable) {
                // Unknown column type – report and continue
                $report = $report->with(new ValidationIssue(
                    $model::class,
                    'annotation',
                    \sprintf("@property \$%s type '%s' cannot be checked: unknown DB type.", $name, $info['raw'])
                ));
                continue;
            }

            $dbOk = false;
            foreach ($info['types'] as $type) {
                $mapped = $this->mapDocTypeToCastToken($type);
                if ($mapped !== null && $this->matcher->isCompatible((string) $dbType, $mapped)) {
                    $dbOk = true;
                    break;
                }
                // If it's an enum class, we can pass it directly to the matcher
                if ($mapped === null && enum_exists($type)) {
                    if ($this->matcher->isCompatible((string) $dbType, $type)) {
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

            // 2) Optional: compare annotation vs cast token (so docs match your code intent)
            if ($this->checkCasts && isset($casts[$name])) {
                $castToken = $casts[$name]; // can be 'int', 'datetime', class-string, etc.
                $castOk    = false;

                foreach ($info['types'] as $type) {
                    // If doc is an enum class AND cast is that enum class => fine
                    if (\is_string($castToken) && enum_exists(ltrim($castToken, '\\')) && strcasecmp(ltrim($castToken, '\\'), ltrim($type, '\\')) === 0) {
                        $castOk = true;
                        break;
                    }

                    $docToken = $this->mapDocTypeToCastToken($type);
                    if ($docToken !== null && $this->castTokensSimilar($docToken, (string) $castToken)) {
                        $castOk = true;
                        break;
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
     * Map a DocBlock type to a "cast token" compatible with TypeMatcher or simple comparison.
     * Returns null when we should keep the raw class name (e.g., enum/custom caster).
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
        if (\in_array($t, ['Carbon',Carbon::class,\Illuminate\Support\Carbon::class,'DateTime','DateTimeInterface'], true)) {
            return 'datetime';
        }
        if ($lower === 'date') {
            return 'date';
        }

        // If it's an enum (will be handled by matcher) or another class, return null -> use original class
        if (enum_exists($t) || class_exists($t)) {
            return null; // let caller handle as class/enum
        }

        // Unknown pseudo type – return as-is to attempt a generic match key (rare)
        return $lower;
    }

    /**
     * Compare a doc token vs cast token loosely (e.g., 'datetime' ~ 'datetime', Carbon ~ datetime, etc.)
     */
    private function castTokensSimilar(string $docToken, string $castToken): bool
    {
        $a = strtolower($docToken);
        $b = strtolower(ltrim($castToken, '\\'));

        // If cast is a known scalar keyword, normalize
        $normalize = static fn (string $x): string => match (true) {
            str_contains($x, 'int')    => 'integer',
            str_contains($x, 'bool')   => 'boolean',
            str_contains($x, 'string') => 'string',
            str_contains($x, 'float'),
            str_contains($x, 'double')   => 'float',
            str_contains($x, 'decimal')  => 'decimal',
            str_contains($x, 'datetime') => 'datetime',
            str_contains($x, 'date')     => 'date',
            str_contains($x, 'json')     => 'json',
            str_contains($x, 'array')    => 'array',
            default                      => $x,
        };

        return $normalize($a) === $normalize($b);
    }
}
