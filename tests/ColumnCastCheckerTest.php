<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Indy2kro\ValidateModels\Services\ColumnCastChecker;
use Indy2kro\ValidateModels\Support\TypeMatcher;

class ColumnCastCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('widgets', function ($t) {
            $t->increments('id');
            $t->string('name');
            $t->integer('qty')->nullable();
            $t->timestamps();
        });
    }

    public function test_reports_cast_and_fillable_issues(): void
    {
        // Fake model
        $model = new class () extends Model {
            protected $table    = 'widgets';
            protected $fillable = ['name', 'missing_field']; // 'missing_field' doesn't exist
            protected $casts    = ['name' => 'integer'];      // wrong cast on purpose
        };

        $matcher = new TypeMatcher([
            '*' => [
                'integer' => ['int','integer','bigint','smallint','tinyint'],
                'string'  => ['string','varchar','char','text'],
            ],
        ], '*');

        $checker = new ColumnCastChecker($matcher);
        $report  = $checker->check($model);

        $kinds = array_map(fn ($i) => $i->kind, $report->issues);
        $this->assertContains('cast', $kinds);
        $this->assertContains('fillable', $kinds);
        $this->assertGreaterThanOrEqual(2, \count($report->issues));
    }
}
