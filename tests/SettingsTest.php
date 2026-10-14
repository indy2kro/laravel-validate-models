<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Tests;

use Indy2kro\ValidateModels\Support\Settings;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    public function test_defaults_apply_to_an_empty_config(): void
    {
        $settings = Settings::fromArray([]);

        $this->assertSame(['App\\Models'], $settings->modelsNamespaces);
        $this->assertNull($settings->connection);
        $this->assertTrue($settings->checks['casts']);
        $this->assertTrue($settings->failOnWarnings);
        $this->assertFalse($settings->relationsIncludeUntyped);
        $this->assertSame([], $settings->excludeModels);
    }

    public function test_values_are_filtered_to_their_expected_types(): void
    {
        $settings = Settings::fromArray([
            'models_paths'     => ['a', 1, '', 'b'],
            'connection'       => '',
            'checks'           => ['relations' => false],
            'ignore'           => ['casts' => 'nope', 'fillable' => ['x']],
            'relations'        => ['include_untyped' => true],
            'annotations'      => ['aliases' => ['Money' => 'App\\Money', 3 => 'x', 'Bad' => 5]],
            'type_map'         => ['*' => ['integer' => ['int', 7]], 'bad' => 'x'],
            'fail_on_warnings' => 0,
        ]);

        $this->assertSame(['a', 'b'], $settings->modelsPaths);
        $this->assertNull($settings->connection);
        $this->assertFalse($settings->checks['relations']);
        $this->assertSame([], $settings->ignore['casts']);
        $this->assertSame(['x'], $settings->ignore['fillable']);
        $this->assertTrue($settings->relationsIncludeUntyped);
        $this->assertSame(['Money' => 'App\\Money'], $settings->annotationsAliases);
        $this->assertSame(['*' => ['integer' => ['int']]], $settings->typeMap);
        $this->assertFalse($settings->failOnWarnings);
    }
}
