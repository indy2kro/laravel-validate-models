<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Tests;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Indy2kro\ValidateModels\Support\Settings;
use Indy2kro\ValidateModels\Support\TypeMatcher;
use Indy2kro\ValidateModels\Tests\Fixtures\IntLevel;
use Indy2kro\ValidateModels\Tests\Fixtures\MoneyCast;
use Indy2kro\ValidateModels\Tests\Fixtures\StringStatus;
use PHPUnit\Framework\Attributes\DataProvider;

class TypeMatcherTest extends TestCase
{
    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function casts(): array
    {
        return [
            'int on sqlite integer'         => ['integer', 'int', true],
            'int on pgsql int4'             => ['int4', 'int', true],
            'integer on pgsql int8'         => ['int8', 'integer', true],
            'integer on mysql mediumint'    => ['mediumint', 'integer', true],
            'integer on bigint'             => ['bigint', 'integer', true],
            'integer on varchar'            => ['varchar', 'integer', false],
            'date on date'                  => ['date', 'date', true],
            'date on datetime'              => ['datetime', 'date', true],
            'immutable_date on date'        => ['date', 'immutable_date', true],
            'datetime with format'          => ['datetime', 'datetime:Y-m-d', true],
            'immutable_datetime'            => ['timestamp', 'immutable_datetime', true],
            'datetime on pgsql timestamptz' => ['timestamptz', 'datetime', true],
            'timestamp cast'                => ['datetime', 'timestamp', true],
            'datetime on integer'           => ['integer', 'datetime', false],
            'bool on tinyint'               => ['tinyint', 'bool', true],
            'boolean on pgsql bool'         => ['bool', 'boolean', true],
            'boolean on text'               => ['text', 'boolean', false],
            'double'                        => ['double', 'double', true],
            'real on pgsql float4'          => ['float4', 'real', true],
            'float on varchar'              => ['varchar', 'float', false],
            'decimal with scale'            => ['decimal', 'decimal:2', true],
            'string'                        => ['varchar', 'string', true],
            'hashed'                        => ['varchar', 'hashed', true],
            'encrypted'                     => ['text', 'encrypted', true],
            'encrypted array'               => ['text', 'encrypted:array', true],
            'array on sqlite text'          => ['text', 'array', true],
            'array on jsonb'                => ['jsonb', 'array', true],
            'json on mariadb longtext'      => ['longtext', 'json', true],
            'object on text'                => ['text', 'object', true],
            'collection on json'            => ['json', 'collection', true],
            'json on integer'               => ['integer', 'json', false],
            'AsArrayObject castable'        => ['text', AsArrayObject::class, true],
            'custom caster'                 => ['integer', MoneyCast::class, true],
            'string enum on varchar'        => ['varchar', StringStatus::class, true],
            'string enum on integer'        => ['integer', StringStatus::class, false],
            'int enum on int4'              => ['int4', IntLevel::class, true],
            'int enum on varchar'           => ['varchar', IntLevel::class, false],
        ];
    }

    #[DataProvider('casts')]
    public function test_cast_compatibility(string $dbType, string $cast, bool $expected): void
    {
        $matcher = new TypeMatcher($this->defaultMap(), 'sqlite');

        $this->assertSame($expected, $matcher->isCompatible($dbType, $cast));
    }

    public function test_driver_map_is_merged_with_fallback(): void
    {
        $matcher = new TypeMatcher([
            '*'     => ['string' => ['varchar']],
            'mysql' => ['string' => ['tinytext']],
        ], 'mysql');

        $this->assertTrue($matcher->isCompatible('varchar', 'string'));
        $this->assertTrue($matcher->isCompatible('tinytext', 'string'));
    }

    public function test_configured_custom_keys_still_match_by_substring(): void
    {
        $matcher = new TypeMatcher(['*' => ['uuid' => ['char']]], '*');

        $this->assertTrue($matcher->isCompatible('char', 'App\\Casts\\AsUuid'));
        $this->assertFalse($matcher->isCompatible('integer', 'App\\Casts\\AsUuid'));
    }

    public function test_canonical_cast_tokens(): void
    {
        $this->assertSame('datetime', TypeMatcher::canonicalCast('immutable_datetime'));
        $this->assertSame('decimal', TypeMatcher::canonicalCast('decimal:2'));
        $this->assertSame('json', TypeMatcher::canonicalCast('collection'));
        $this->assertNull(TypeMatcher::canonicalCast(MoneyCast::class));
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    private function defaultMap(): array
    {
        $config = require __DIR__ . '/../config/validate-models.php';
        $this->assertIsArray($config);

        return Settings::fromArray($config)->typeMap;
    }
}
