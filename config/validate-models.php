<?php

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

    // Cast token => database column types it may be stored in. Type names are the ones the schema builder reports.
    'type_map' => [
        '*' => [
            'integer'  => ['int', 'integer', 'bigint', 'mediumint', 'smallint', 'tinyint', 'int2', 'int4', 'int8'],
            'string'   => ['string', 'varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext', 'enum', 'set', 'bpchar', 'uuid'],
            'boolean'  => ['bool', 'boolean', 'tinyint', 'bit'],
            'float'    => ['float', 'double', 'real', 'float4', 'float8', 'decimal', 'numeric'],
            'decimal'  => ['decimal', 'numeric', 'float', 'double', 'real', 'float4', 'float8'],
            'date'     => ['date'],
            'datetime' => ['datetime', 'timestamp', 'timestamptz', 'datetimetz', 'datetime2'],
            // JSON is stored as text on SQLite and MariaDB (longtext)
            'json'     => ['json', 'jsonb', 'array', 'text', 'mediumtext', 'longtext'],
            'array'    => ['json', 'jsonb', 'array', 'text', 'mediumtext', 'longtext'],
        ],
    ],
];
