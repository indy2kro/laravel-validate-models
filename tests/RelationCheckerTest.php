<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Tests;

use Does\Not\Exist;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Indy2kro\ValidateModels\Services\RelationChecker;

class RelationCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('parents', fn ($t) => $t->increments('id'));
        Schema::create('children', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('parent_id')->nullable();
        });
    }

    public function test_reports_broken_relation(): void
    {
        // Keep a direct reference to the anonymous model instance
        $model = new class () extends Model {
            protected $table = 'children';

            // Force an exception on invocation: related class doesn't exist
            // @phpstan-ignore-next-line
            public function parent(): BelongsTo
            {
                // @phpstan-ignore-next-line
                return $this->belongsTo(Exist::class, 'parent_id');
            }
        };

        $this->assertInstanceOf(Model::class, $model);

        $checker = new RelationChecker();
        $report  = $checker->check($model);

        $this->assertNotEmpty($report->issues, 'Expected at least one relation issue.');
        $this->assertSame('relation', $report->issues[0]->kind);
        $this->assertStringContainsString('parent', $report->issues[0]->message);
    }
}
