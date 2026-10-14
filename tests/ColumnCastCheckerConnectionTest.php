<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Indy2kro\ValidateModels\Services\ColumnCastChecker;
use Indy2kro\ValidateModels\Support\TypeMatcher;

class ColumnCastCheckerConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.other' => [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]]);

        DB::connection('other')->getSchemaBuilder()->create('audit_log', function (Blueprint $t): void {
            $t->increments('id');
            $t->boolean('seen')->default(false);
            $t->date('day');
            $t->text('payload')->nullable();
        });
    }

    public function test_model_is_checked_against_its_own_connection(): void
    {
        $model = new class () extends Model {
            protected $connection = 'other';

            protected $table = 'audit_log';

            protected $fillable = ['seen', 'nope'];

            protected function casts(): array
            {
                return ['seen' => 'bool', 'day' => 'date', 'payload' => 'array'];
            }
        };

        $report = (new ColumnCastChecker($this->matcher()))->check($model);

        $messages = array_map(static fn ($i): string => $i->message, $report->issues);
        $this->assertSame(["Fillable 'nope' not found in table."], $messages);
    }

    public function test_missing_table_is_reported_only_when_the_columns_check_is_on(): void
    {
        $model = new class () extends Model {
            protected $table = 'nowhere';
        };

        $on  = (new ColumnCastChecker($this->matcher()))->check($model);
        $off = (new ColumnCastChecker($this->matcher(), [], ['columns' => false, 'casts' => true, 'fillable' => true]))->check($model);

        $this->assertSame('table', $on->issues[0]->kind);
        $this->assertTrue($off->isClean());
    }

    public function test_ignore_list_accepts_model_qualified_names(): void
    {
        $model = new class () extends Model {
            protected $connection = 'other';

            protected $table = 'audit_log';

            protected $fillable = ['nope'];
        };

        $global    = (new ColumnCastChecker($this->matcher(), ['fillable' => ['nope']]))->check($model);
        $qualified = (new ColumnCastChecker($this->matcher(), ['fillable' => [$model::class . '::nope']]))->check($model);
        $other     = (new ColumnCastChecker($this->matcher(), ['fillable' => ['Other\\Model::nope']]))->check($model);

        $this->assertTrue($global->isClean());
        $this->assertTrue($qualified->isClean());
        $this->assertFalse($other->isClean());
    }

    private function matcher(): TypeMatcher
    {
        return new TypeMatcher(['*' => [
            'boolean' => ['bool', 'boolean', 'tinyint', 'integer'],
            'date'    => ['date'],
            'json'    => ['json', 'text'],
            'array'   => ['json', 'text'],
        ]], 'sqlite');
    }
}
