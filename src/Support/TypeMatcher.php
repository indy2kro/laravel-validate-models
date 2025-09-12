<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Support;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes;
use ReflectionEnum;

class TypeMatcher
{
    /** The selected driver key, e.g. "mysql", "pgsql", or "*" */
    protected string $driver;

    /**
     * @param array<string, array<string, string[]>> $map
     */
    public function __construct(
        protected array $map,
        string $driver = '*'
    ) {
        // Keep only the *key*; fall back to '*'
        $this->driver = \array_key_exists($driver, $this->map) ? $driver : '*';
    }

    public function isCompatible(string $dbType, string $cast): bool
    {
        $dbType    = strtolower($dbType);
        $cast      = ltrim($cast, '\\');
        $castLower = strtolower($cast);

        // 1) PHP 8.1+ enums
        if (\function_exists('enum_exists') && enum_exists($cast)) {
            if (is_subclass_of($cast, BackedEnum::class)) {
                $ref     = new ReflectionEnum($cast);
                $backing = $ref->getBackingType()?->getName(); // 'int'|'string'

                return $backing === 'int'
                    ? \in_array($dbType, ['int','integer','bigint','tinyint','smallint'], true)
                    : ($backing === 'string'
                        ? \in_array($dbType, ['string','varchar','char','text','enum','set'], true)
                        : true);
            }

            // Unit enums: assume custom cast handles storage
            return true;
        }

        // 2) Custom caster classes: assume OK
        if (class_exists($cast) && (is_subclass_of($cast, CastsAttributes::class)
            || is_subclass_of($cast, CastsInboundAttributes::class))) {
            return true;
        }

        // 3) Shorthand ints
        if ($castLower === 'int' || $castLower === 'integer') {
            return \in_array($dbType, ['int','integer','bigint','smallint','tinyint'], true);
        }

        // 4) Driver-specific + fallback map
        $sets      = [];
        $driverMap = $this->map[$this->driver] ?? null;
        if (\is_array($driverMap)) {
            $sets[] = $driverMap;
        }
        $fallbackMap = $this->map['*'] ?? null;
        if (\is_array($fallbackMap) && $this->driver !== '*') {
            $sets[] = $fallbackMap;
        }

        foreach ($sets as $map) {
            foreach ($map as $castType => $dbTypes) {
                if (str_contains($castLower, (string) $castType) && \in_array($dbType, $dbTypes, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
