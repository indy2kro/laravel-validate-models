<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Indy2kro\ValidateModels\Services\ColumnCastChecker;
use Indy2kro\ValidateModels\Services\ModelValidatorService;
use Indy2kro\ValidateModels\Services\RelationChecker;
use Indy2kro\ValidateModels\Support\TypeMatcher;

class ModelValidatorServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('things', function ($t) {
            $t->increments('id');
            $t->string('name');
        });
    }

    public function test_returns_clean_report_when_everything_matches(): void
    {
        $model = new class () extends Model {
            protected $table    = 'things';
            protected $fillable = ['name'];

            protected function casts(): array
            {
                return ['name' => 'string'];
            }
        };

        $this->assertInstanceOf(Model::class, $model);

        $matcher   = new TypeMatcher(['*' => ['string' => ['string','varchar','char','text']]], '*');
        $columns   = new ColumnCastChecker($matcher);
        $relations = new RelationChecker();

        $service = new ModelValidatorService($columns, $relations);
        $report  = $service->validate($model);

        $this->assertTrue($report->isClean());
    }
}
