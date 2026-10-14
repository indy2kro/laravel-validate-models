<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Support;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes;
use ReflectionEnum;
use ReflectionNamedType;

class TypeMatcher
{
    /** Database types an int-backed enum or an "int" cast may be stored in. */
    public const INTEGER_TYPES = ['int', 'integer', 'bigint', 'mediumint', 'smallint', 'tinyint', 'int2', 'int4', 'int8'];

    /** Database types a string-backed enum may be stored in. */
    public const STRING_TYPES = ['string', 'varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext', 'enum', 'set', 'bpchar'];

    /** The selected driver key, e.g. "mysql", "pgsql", or "*" */
    protected string $driver;

    /**
     * @param array<string, array<string, list<string>>> $map driver => canonical cast token => database types
     */
    public function __construct(
        protected array $map,
        string $driver = '*'
    ) {
        $this->driver = \array_key_exists($driver, $this->map) ? $driver : '*';
    }

    /**
     * Reduce a cast string (e.g. "decimal:2", "immutable_datetime", "encrypted:array") to a canonical token,
     * or null when it is not a built-in Eloquent cast.
     */
    public static function canonicalCast(string $cast): ?string
    {
        $base = explode(':', strtolower(ltrim($cast, '\\')), 2)[0];

        return match ($base) {
            'int', 'integer'                                                 => 'integer',
            'real', 'float', 'double'                                        => 'float',
            'decimal'                                                        => 'decimal',
            'string', 'hashed', 'encrypted'                                  => 'string',
            'bool', 'boolean'                                                => 'boolean',
            'date', 'immutable_date'                                         => 'date',
            'datetime', 'immutable_datetime', 'custom_datetime', 'timestamp' => 'datetime',
            'json', 'array', 'object', 'collection'                          => 'json',
            default                                                          => null,
        };
    }

    /**
     * A user-defined cast: CastsAttributes / CastsInboundAttributes / Castable class, whose value type is arbitrary.
     */
    public function isCustomCast(string $cast): bool
    {
        $class = explode(':', ltrim($cast, '\\'), 2)[0];

        return class_exists($class) && (
            is_subclass_of($class, CastsAttributes::class)
            || is_subclass_of($class, CastsInboundAttributes::class)
            || is_subclass_of($class, Castable::class)
        );
    }

    public function isCompatible(string $dbType, string $cast): bool
    {
        $dbType = strtolower(trim($dbType));
        $cast   = ltrim($cast, '\\');

        // 1) Enums
        if (enum_exists($cast)) {
            if (is_subclass_of($cast, BackedEnum::class)) {
                $backingType = (new ReflectionEnum($cast))->getBackingType();
                $backing     = $backingType instanceof ReflectionNamedType ? $backingType->getName() : null;

                if ($backing === 'int') {
                    return \in_array($dbType, self::INTEGER_TYPES, true);
                }
                if ($backing === 'string') {
                    return \in_array($dbType, self::STRING_TYPES, true);
                }
            }

            // Unit enums and unexpected backing types: be permissive to avoid false positives
            return true;
        }

        // 2) Custom casters and Castable classes: the stored type is theirs to define
        if ($this->isCustomCast($cast)) {
            return true;
        }

        // 3) Built-in casts
        $token = self::canonicalCast($cast);
        if ($token !== null) {
            $types = $this->typesFor($token);
            if ($types !== null) {
                return \in_array($dbType, $types, true);
            }
            if ($token === 'integer') {
                return \in_array($dbType, self::INTEGER_TYPES, true);
            }
        }

        // 4) Custom map keys: a configured key that appears in the cast name
        $castLower = strtolower($cast);
        foreach ($this->sets() as $set) {
            foreach ($set as $castType => $dbTypes) {
                if (str_contains($castLower, (string) $castType) && \in_array($dbType, $dbTypes, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<array<string, list<string>>>
     */
    private function sets(): array
    {
        $sets = [];
        if (isset($this->map[$this->driver])) {
            $sets[] = $this->map[$this->driver];
        }
        if ($this->driver !== '*' && isset($this->map['*'])) {
            $sets[] = $this->map['*'];
        }

        return $sets;
    }

    /**
     * Database types configured for a canonical token (union of driver and fallback maps), or null when none.
     *
     * @return list<string>|null
     */
    private function typesFor(string $token): ?array
    {
        $keys = match ($token) {
            'json'  => ['json', 'array'],
            'date'  => ['date', 'datetime'],
            default => [$token],
        };
        $types = null;

        foreach ($this->sets() as $set) {
            foreach ($keys as $key) {
                if (isset($set[$key])) {
                    $types = array_merge($types ?? [], $set[$key]);
                }
            }
        }

        return $types;
    }
}
