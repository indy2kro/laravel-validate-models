<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Tests;

use Carbon\Carbon;
use Indy2kro\ValidateModels\Support\DocblockPropertyParser;
use PHPUnit\Framework\TestCase;

class DocblockPropertyParserTest extends TestCase
{
    public function test_parses_simple_nullable_and_union_types(): void
    {
        $props = (new DocblockPropertyParser())->parse(<<<'DOC'
            /**
             * @property int $id
             * @property ?string $name
             * @property \Carbon\Carbon|null $created_at
             * @property-read int|string $mixed
             * @property-write bool $flag
             */
            DOC);

        $this->assertSame(['id', 'name', 'created_at', 'mixed', 'flag'], array_keys($props));
        $this->assertSame(['int'], $props['id']['types']);
        $this->assertFalse($props['id']['nullable']);
        $this->assertSame(['string'], $props['name']['types']);
        $this->assertTrue($props['name']['nullable']);
        $this->assertSame([Carbon::class], $props['created_at']['types']);
        $this->assertTrue($props['created_at']['nullable']);
        $this->assertSame(['int', 'string'], $props['mixed']['types']);
    }

    public function test_generics_with_spaces_and_inner_unions_stay_in_one_type(): void
    {
        $props = (new DocblockPropertyParser())->parse(<<<'DOC'
            /**
             * @property array<string, int> $counts
             * @property Collection<int, Post>|null $posts
             * @property array<int|string> $keys
             * @property int<0, max> $size
             */
            DOC);

        $this->assertSame(['array<string, int>'], $props['counts']['types']);
        $this->assertSame(['Collection<int, Post>'], $props['posts']['types']);
        $this->assertTrue($props['posts']['nullable']);
        $this->assertSame(['array<int|string>'], $props['keys']['types']);
        $this->assertSame(['int<0, max>'], $props['size']['types']);
    }

    public function test_ignores_malformed_and_unrelated_tags(): void
    {
        $props = (new DocblockPropertyParser())->parse(<<<'DOC'
            /**
             * @property $untyped
             * @method int count()
             * @var int $other
             * @property int
             */
            DOC);

        $this->assertSame([], $props);
        $this->assertSame([], (new DocblockPropertyParser())->parse(null));
        $this->assertSame([], (new DocblockPropertyParser())->parse(''));
    }
}
