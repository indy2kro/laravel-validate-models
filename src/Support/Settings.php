<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Support;

/**
 * Typed view of the `validate-models` configuration array.
 */
final class Settings
{
    /**
     * @param list<string>                                                                                       $modelsPaths
     * @param list<string>                                                                                       $modelsNamespaces
     * @param array<string, array<string, list<string>>>                                                         $typeMap
     * @param array{columns: bool, casts: bool, fillable: bool, relations: bool, annotations: bool}              $checks
     * @param array{casts: list<string>, fillable: list<string>, columns: list<string>, relations: list<string>} $ignore
     * @param list<string>                                                                                       $excludeModels
     * @param list<string>                                                                                       $annotationsIgnore
     * @param array<string, string>                                                                              $annotationsAliases
     */
    public function __construct(
        public readonly array $modelsPaths,
        public readonly array $modelsNamespaces,
        public readonly ?string $connection,
        public readonly array $typeMap,
        public readonly array $checks,
        public readonly array $ignore,
        public readonly array $excludeModels,
        public readonly bool $relationsIncludeUntyped,
        public readonly bool $annotationsColumnsOnly,
        public readonly bool $annotationsCheckCasts,
        public readonly bool $annotationsCheckNullability,
        public readonly array $annotationsIgnore,
        public readonly array $annotationsAliases,
        public readonly bool $failOnWarnings,
    ) {
    }

    /**
     * @param array<mixed> $cfg
     */
    public static function fromArray(array $cfg): self
    {
        $checks      = self::map($cfg['checks'] ?? null);
        $ignore      = self::map($cfg['ignore'] ?? null);
        $annotations = self::map($cfg['annotations'] ?? null);
        $relations   = self::map($cfg['relations'] ?? null);

        $defaultPath = \function_exists('base_path') ? base_path('app/Models') : 'app/Models';

        $connection = $cfg['connection'] ?? null;

        return new self(
            self::strings($cfg['models_paths'] ?? null, [$defaultPath]),
            self::strings($cfg['models_namespaces'] ?? null, ['App\\Models']),
            \is_string($connection) && $connection !== '' ? $connection : null,
            self::typeMap($cfg['type_map'] ?? null),
            [
                'columns'     => self::bool($checks['columns'] ?? null, true),
                'casts'       => self::bool($checks['casts'] ?? null, true),
                'fillable'    => self::bool($checks['fillable'] ?? null, true),
                'relations'   => self::bool($checks['relations'] ?? null, true),
                'annotations' => self::bool($checks['annotations'] ?? null, true),
            ],
            [
                'casts'     => self::strings($ignore['casts'] ?? null),
                'fillable'  => self::strings($ignore['fillable'] ?? null),
                'columns'   => self::strings($ignore['columns'] ?? null),
                'relations' => self::strings($ignore['relations'] ?? null),
            ],
            self::strings($cfg['exclude_models'] ?? null),
            self::bool($relations['include_untyped'] ?? null, false),
            self::bool($annotations['columns_only'] ?? null, true),
            self::bool($annotations['check_casts'] ?? null, true),
            self::bool($annotations['check_nullability'] ?? null, true),
            self::strings($annotations['ignore'] ?? null),
            self::stringMap($annotations['aliases'] ?? null),
            self::bool($cfg['fail_on_warnings'] ?? null, true),
        );
    }

    /**
     * @return array<mixed>
     */
    private static function map(mixed $value): array
    {
        return \is_array($value) ? $value : [];
    }

    private static function bool(mixed $value, bool $default): bool
    {
        return $value === null ? $default : (bool) $value;
    }

    /**
     * @param  list<string> $default
     * @return list<string>
     */
    private static function strings(mixed $value, array $default = []): array
    {
        if (!\is_array($value)) {
            return $default;
        }

        $out = [];
        foreach ($value as $item) {
            if (\is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private static function stringMap(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $key => $item) {
            if (\is_string($key) && \is_string($item)) {
                $out[$key] = $item;
            }
        }

        return $out;
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    private static function typeMap(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $driver => $casts) {
            if (!\is_array($casts)) {
                continue;
            }
            foreach ($casts as $cast => $types) {
                $out[(string) $driver][(string) $cast] = self::strings($types);
            }
        }

        return $out;
    }
}
