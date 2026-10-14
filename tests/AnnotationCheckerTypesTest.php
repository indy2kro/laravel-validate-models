<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Indy2kro\ValidateModels\Services\AnnotationChecker;
use Indy2kro\ValidateModels\Support\Settings;
use Indy2kro\ValidateModels\Support\TypeMatcher;
use Indy2kro\ValidateModels\Tests\Fixtures\Article;

class AnnotationCheckerTypesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('articles', function (Blueprint $t): void {
            $t->increments('id');
            $t->string('title');
            $t->timestamp('published_at')->nullable();
            $t->text('meta')->nullable();
            $t->integer('price');
            $t->text('settings')->nullable();
            $t->string('status');
        });
    }

    public function test_immutable_dates_generics_mixed_and_custom_casts_are_accepted(): void
    {
        $report = $this->checker()->check(new Article());

        $this->assertSame([], array_map(static fn ($i): string => $i->message, $report->issues));
    }

    public function test_model_qualified_ignore_skips_a_property(): void
    {
        $checker = $this->checker(['Wrong\\Model::title']);
        $this->assertTrue($checker->check(new Article())->isClean());

        Schema::table('articles', function (Blueprint $t): void {
            $t->dropColumn('title');
            $t->integer('title')->default(0);
        });

        $broken  = $this->checker()->check(new Article());
        $ignored = $this->checker([Article::class . '::title'])->check(new Article());

        $this->assertFalse($broken->isClean());
        $this->assertTrue($ignored->isClean());
    }

    /**
     * @param list<string> $ignore
     */
    private function checker(array $ignore = []): AnnotationChecker
    {
        $config = require __DIR__ . '/../config/validate-models.php';
        $this->assertIsArray($config);

        return new AnnotationChecker(
            new TypeMatcher(Settings::fromArray($config)->typeMap, 'sqlite'),
            ignore: $ignore,
        );
    }
}
