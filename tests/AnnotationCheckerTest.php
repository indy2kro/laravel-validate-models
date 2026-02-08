<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Indy2kro\ValidateModels\Services\AnnotationChecker;
use Indy2kro\ValidateModels\Support\DocblockPropertyParser;
use Indy2kro\ValidateModels\Support\TypeMatcher;
use Indy2kro\ValidateModels\Tests\_Tmp\Batch;
use Indy2kro\ValidateModels\Tests\_Tmp\BatchBad;

class AnnotationCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('batches', function ($t) {
            $t->increments('id');
            $t->integer('year');
            $t->integer('month');
            $t->unsignedInteger('ordinal');
            $t->string('status')->nullable();
            $t->text('error_message')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->timestamp('updated_at')->nullable();
        });
    }

    public function test_docblock_properties_are_checked_against_db_and_casts(): void
    {
        $model = new Batch();

        $matcher = new TypeMatcher([
            '*' => [
                'integer'  => ['int','integer','bigint','smallint','tinyint'],
                'string'   => ['string','varchar','char','text','enum','set'],
                'datetime' => ['datetime','timestamp'],
                'text'     => ['text','string'],
            ],
        ], '*');

        $checker = new AnnotationChecker(
            $matcher,
            new DocblockPropertyParser(),
            columnsOnly: true,
            checkCasts: true,
            ignore: []
        );

        $report = $checker->check($model);
        $this->assertTrue($report->isClean(), 'Expected annotations to match DB/casts.');
    }

    public function test_mismatched_annotation_is_reported(): void
    {
        $model = new BatchBad();

        $matcher = new TypeMatcher([
            '*' => [
                'integer'  => ['int','integer','bigint','smallint','tinyint'],
                'string'   => ['string','varchar','char','text','enum','set'],
                'datetime' => ['datetime','timestamp'],
            ],
        ], '*');

        $checker = new AnnotationChecker($matcher, new DocblockPropertyParser(), true, true, []);
        $report  = $checker->check($model);

        $this->assertNotEmpty($report->issues);
        $this->assertSame('annotation', $report->issues[0]->kind);
    }
}
