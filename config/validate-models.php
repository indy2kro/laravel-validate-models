<?php

return [
    'models_paths' => [base_path('app/Models')],
    'models_namespaces' => ['App\\Models'],
    'connection' => null,

    'checks' => [
        'columns'   => true,
        'casts'     => true,
        'fillable'  => true,
        'relations' => true,
        'annotations'=> true,
    ],

    'annotations' => [
        'columns_only' => true, // only check @property names that exist as columns
        'check_casts'  => true, // also compare annotation types vs model casts
        'ignore'       => [     // names to ignore
        ],
        'aliases'      => [     // map short names used in @property tags to FQCNs 
        ],
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

