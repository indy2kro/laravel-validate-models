<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Services;

use ArrayObject;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Indy2kro\ValidateModels\Dto\ValidationIssue;
use Indy2kro\ValidateModels\Dto\ValidationReport;
use Indy2kro\ValidateModels\Support\DocblockPropertyParser;
use Indy2kro\ValidateModels\Support\IgnoreList;
use Indy2kro\ValidateModels\Support\SchemaInspector;
use Indy2kro\ValidateModels\Support\TypeMatcher;
use ReflectionClass;
use stdClass;
use Throwable;

final class AnnotationChecker
{
    /** Token for doc types that cannot be checked against the database (mixed, object, unknown names). */
    private const ANY = '*';

    private const INTEGER_DOC_TYPES = ['int', 'integer', 'positive-int', 'negative-int', 'non-positive-int', 'non-negative-int', 'non-zero-int'];

    private const STRING_DOC_TYPES = [
        'string', 'non-empty-string', 'numeric-string', 'class-string', 'literal-string', 'lowercase-string',
        'uppercase-string', 'non-falsy-string', 'truthy-string', 'callable-string', 'trait-string', 'interface-string',
    ];

    private const ARRAY_DOC_TYPES = ['array', 'list', 'non-empty-array', 'non-empty-list', 'iterable'];

    private const DATE_CLASS_NAMES = ['carbon', 'carbonimmutable', 'carboninterface', 'datetime', 'datetimeimmutable', 'datetimeinterface'];

    private readonly SchemaInspector $schema;

    /**
     * @param list<string>          $ignore  property names to skip (or "Model\Class::property")
     * @param array<string, string> $aliases DocBlock short type => FQCN
     */
    public function __construct(
        private readonly TypeMatcher $matcher,
        private readonly DocblockPropertyParser $parser = new DocblockPropertyParser(),
        private readonly bool $columnsOnly = true,
        private readonly bool $checkCasts = true,
        private readonly array $ignore = [],
        private readonly array $aliases = [],
        private readonly bool $checkNullability = true,
        ?SchemaInspector $schema = null,
    ) {
        $this->schema = $schema ?? new SchemaInspector();
    }

    public function check(Model $model): ValidationReport
    {
        $report = new ValidationReport();
        $class  = $model::class;

        $doc   = (new ReflectionClass($model))->getDocComment();
        $props = $this->parser->parse($doc === false ? null : $doc);
        if ($props === []) {
            return $report;
        }

        try {
            $columns = $this->schema->columns($model);
        } catch (Throwable) {
            return $report; // ColumnCastChecker reports the failure
        }
        if ($columns === null) {
            return $report;
        }

        $table = $model->getTable();
        $casts = $model->getCasts();

        foreach ($props as $name => $info) {
            if (IgnoreList::has($this->ignore, $class, $name)) {
                continue;
            }
            if (!isset($columns[$name])) {
                if (!$this->columnsOnly) {
                    $report = $report->with(new ValidationIssue(
                        $class,
                        'annotation',
                        \sprintf("@property \$%s type '%s' cannot be checked: unknown DB type.", $name, $info['raw'])
                    ));
                }
                continue;
            }

            $dbType = $columns[$name]['type'];

            if ($this->checkNullability) {
                $isNullable  = $columns[$name]['nullable'];
                $docNullable = $info['nullable'] || $this->acceptsNull($info['types']);
                if ($docNullable && !$isNullable) {
                    $report = $report->with(new ValidationIssue(
                        $class,
                        'annotation',
                        \sprintf("@property \$%s allows null but column '%s.%s' is NOT NULL", $name, $table, $name)
                    ));
                }
                if (!$docNullable && $isNullable) {
                    $report = $report->with(new ValidationIssue(
                        $class,
                        'annotation',
                        \sprintf("@property \$%s does not allow null but column '%s.%s' is NULLABLE", $name, $table, $name)
                    ));
                }
            }

            $cast       = $casts[$name] ?? null;
            $customCast = $cast !== null && $this->matcher->isCustomCast($cast);

            // DB type compatibility. A custom cast defines its own value type, so there is nothing to compare.
            if (!$customCast && $info['types'] !== [] && !$this->matchesDbType($info['types'], $dbType)) {
                $report = $report->with(new ValidationIssue(
                    $class,
                    'annotation',
                    \sprintf("@property \$%s '%s' not compatible with DB type '%s'", $name, $info['raw'], $dbType)
                ));
            }

            // Cast consistency
            if ($this->checkCasts && $cast !== null && !$customCast && $info['types'] !== []
                                  && !$this->matchesCast($info['types'], $cast)) {
                $report = $report->with(new ValidationIssue(
                    $class,
                    'annotation',
                    \sprintf("@property \$%s '%s' seems inconsistent with cast '%s'", $name, $info['raw'], $cast)
                ));
            }
        }

        return $report;
    }

    /**
     * `mixed` includes null, so it cannot contradict the column's nullability.
     *
     * @param list<string> $types
     */
    private function acceptsNull(array $types): bool
    {
        foreach ($types as $type) {
            if (strtolower(ltrim($type, '\\')) === 'mixed') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $types
     */
    private function matchesDbType(array $types, string $dbType): bool
    {
        foreach ($types as $type) {
            $resolved = $this->resolveAlias($type);
            $mapped   = $this->mapDocTypeToCastToken($resolved);

            if ($mapped === self::ANY) {
                return true;
            }
            if ($mapped !== null) {
                if ($this->matcher->isCompatible($dbType, $mapped)) {
                    return true;
                }
                continue;
            }

            $class = ltrim($resolved, '\\');
            if (enum_exists($class) && $this->matcher->isCompatible($dbType, $class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $types
     */
    private function matchesCast(array $types, string $cast): bool
    {
        $castClass = ltrim($cast, '\\');

        foreach ($types as $type) {
            $resolved = $this->resolveAlias($type);
            $mapped   = $this->mapDocTypeToCastToken($resolved);

            if ($mapped === self::ANY) {
                return true;
            }
            // Enum class <-> cast FQCN
            if (enum_exists($castClass) && strcasecmp($castClass, ltrim($resolved, '\\')) === 0) {
                return true;
            }
            if ($mapped !== null && $this->castTokensSimilar($mapped, $castClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Strip generics and array suffixes, then resolve DocBlock short names through the configured aliases.
     */
    private function resolveAlias(string $type): string
    {
        $t = ltrim($type, '\\');

        if (str_ends_with($t, '[]')) {
            return 'array';
        }
        $generic = strpos($t, '<');
        if ($generic !== false) {
            $t = substr($t, 0, $generic);
        }

        return isset($this->aliases[$t]) ? ltrim($this->aliases[$t], '\\') : $t;
    }

    /**
     * Map a DocBlock type to a canonical token TypeMatcher understands, ANY when it cannot be checked,
     * or null for a class/enum that has to be compared by name.
     */
    private function mapDocTypeToCastToken(string $type): ?string
    {
        $t     = ltrim($type, '\\');
        $lower = strtolower($t);

        if (\in_array($lower, self::INTEGER_DOC_TYPES, true)) {
            return 'integer';
        }
        if (\in_array($lower, self::STRING_DOC_TYPES, true)) {
            return 'string';
        }
        if (\in_array($lower, ['bool', 'boolean', 'true', 'false'], true)) {
            return 'boolean';
        }
        if ($lower === 'float' || $lower === 'double') {
            return 'float';
        }
        if (\in_array($lower, self::ARRAY_DOC_TYPES, true)) {
            return 'array';
        }

        switch ($lower) {
            case 'decimal':
            case 'json':
            case 'date':
                return $lower;
            case 'mixed':
            case 'object':
            case 'resource':
                return self::ANY;
        }

        $separator = strrpos($t, '\\');
        $short     = strtolower($separator === false ? $t : substr($t, $separator + 1));
        if (\in_array($short, self::DATE_CLASS_NAMES, true) || (class_exists($t) && is_a($t, DateTimeInterface::class, true))) {
            return 'datetime';
        }
        if (class_exists($t) && (is_a($t, Collection::class, true) || is_a($t, ArrayObject::class, true) || $t === stdClass::class)) {
            return 'json';
        }
        if (enum_exists($t) || class_exists($t)) {
            return null;
        }

        // Unresolvable name (a DocBlock carries no import information): do not guess.
        return self::ANY;
    }

    /** Compare doc token vs cast token by family (date/datetime and json/array are interchangeable). */
    private function castTokensSimilar(string $docToken, string $castToken): bool
    {
        $family = static fn (string $x): string => match ($x) {
            'date', 'datetime' => 'datetime',
            'json', 'array'    => 'json',
            default            => $x,
        };

        $cast = TypeMatcher::canonicalCast($castToken) ?? strtolower(ltrim($castToken, '\\'));

        return $family(strtolower($docToken)) === $family($cast);
    }
}
