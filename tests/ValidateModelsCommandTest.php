<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\PendingCommand;
use Indy2kro\ValidateModels\Contracts\ModelLocator;

class ValidateModelsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('ok_table', fn ($t) => $t->increments('id'));
    }

    public function test_command_exits_zero_on_clean_run(): void
    {
        // Keep a direct reference to the anonymous model instance/class
        $anonModel = new class () extends Model {
            protected $table = 'ok_table';
        };
        $modelClass = $anonModel::class;

        // Bind a fake locator that returns exactly one *valid* model class
        App::bind(ModelLocator::class, fn () => new class ($modelClass) implements ModelLocator {
            /** @param class-string $c */
            public function __construct(private readonly string $c)
            {
            }

            public function locate(array $p, array $n): iterable
            {
                yield $this->c;
            }
        });

        $command = $this->artisan('validate:models --no-fail');
        $this->assertInstanceOf(PendingCommand::class, $command);

        $command->assertExitCode(0)->run();
    }
}
