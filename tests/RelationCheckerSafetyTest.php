<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Tests;

use Does\Not\Exist;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Indy2kro\ValidateModels\Services\RelationChecker;
use Indy2kro\ValidateModels\Tests\Fixtures\Article;
use RuntimeException;

class RelationCheckerSafetyTest extends TestCase
{
    public function test_non_relation_methods_are_not_called(): void
    {
        $model = new class () extends Model {
            public int $calls = 0;

            public function archive(): void
            {
                $this->calls++;
            }

            public function explode(): never
            {
                throw new RuntimeException('should never run');
            }
        };

        $report = (new RelationChecker())->check($model);

        $this->assertSame(0, $model->calls);
        $this->assertTrue($report->isClean());
    }

    public function test_untyped_methods_are_only_called_when_opted_in(): void
    {
        $model = new class () extends Model {
            public int $calls = 0;

            /** @phpstan-ignore-next-line */
            public function archive()
            {
                $this->calls++;

                return null;
            }

            /** @phpstan-ignore-next-line */
            public function broken()
            {
                // @phpstan-ignore-next-line
                return $this->hasMany(Exist::class);
            }
        };

        $this->assertTrue((new RelationChecker())->check($model)->isClean());
        $this->assertSame(0, $model->calls);

        $report = (new RelationChecker([], true))->check($model);

        $this->assertSame(1, $model->calls);
        $this->assertCount(1, $report->issues);
        $this->assertStringContainsString("'broken'", $report->issues[0]->message);
    }

    public function test_typed_relation_that_throws_is_reported(): void
    {
        $model = new class () extends Model {
            /** @return BelongsTo<Model, Model> */
            public function owner(): BelongsTo
            {
                // @phpstan-ignore-next-line
                return $this->belongsTo(Exist::class);
            }

            /** @return HasMany<Article, $this> */
            public function items(): HasMany
            {
                return $this->hasMany(Article::class);
            }
        };

        $report = (new RelationChecker())->check($model);

        $this->assertCount(1, $report->issues);
        $this->assertSame('relation', $report->issues[0]->kind);
        $this->assertStringContainsString("'owner'", $report->issues[0]->message);
    }

    public function test_relation_can_be_ignored_for_one_model(): void
    {
        $model = new class () extends Model {
            /** @return BelongsTo<Model, Model> */
            public function owner(): BelongsTo
            {
                // @phpstan-ignore-next-line
                return $this->belongsTo(Exist::class);
            }
        };

        $this->assertTrue((new RelationChecker(['owner']))->check($model)->isClean());
        $this->assertTrue((new RelationChecker([$model::class . '::owner']))->check($model)->isClean());
        $this->assertFalse((new RelationChecker(['App\\Other::owner']))->check($model)->isClean());
    }
}
