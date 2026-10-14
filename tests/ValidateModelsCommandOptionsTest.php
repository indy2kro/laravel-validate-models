<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\PendingCommand;
use Indy2kro\ValidateModels\Contracts\ModelLocator;
use Indy2kro\ValidateModels\Tests\Fixtures\ExplodingModel;

class ValidateModelsCommandOptionsTest extends TestCase
{
    private string $baseline;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('ok_table', fn (Blueprint $t) => $t->increments('id'));
        $this->baseline = sys_get_temp_dir() . '/validate-models-baseline-' . bin2hex(random_bytes(4)) . '.json';
    }

    protected function tearDown(): void
    {
        File::delete($this->baseline);

        parent::tearDown();
    }

    public function test_json_format_lists_issues_and_fails(): void
    {
        $this->locate([$this->badModel()]);

        $exit    = Artisan::call('validate:models', ['--format' => 'json']);
        $payload = json_decode(trim(Artisan::output()), true);

        $this->assertSame(1, $exit);
        $this->assertIsArray($payload);
        $this->assertSame(1, $payload['count']);
        $this->assertSame('fillable', data_get($payload, 'issues.0.kind'));
        $this->assertSame("Fillable 'missing' not found in table.", data_get($payload, 'issues.0.message'));
    }

    public function test_github_format_emits_workflow_annotations(): void
    {
        $this->locate([$this->badModel()]);

        $this->command('validate:models --format=github --no-fail')
            ->expectsOutputToContain('::warning title=')
            ->assertExitCode(0)
            ->run();
    }

    public function test_unknown_format_is_rejected(): void
    {
        $this->locate([]);

        $this->command('validate:models --format=xml')->assertExitCode(2)->run();
    }

    public function test_baseline_round_trip_hides_known_issues(): void
    {
        $this->locate([$this->badModel()]);

        $this->command('validate:models', ['--generate-baseline' => $this->baseline])->assertExitCode(0)->run();
        $this->assertFileExists($this->baseline);

        $this->command('validate:models', ['--baseline' => $this->baseline])
            ->expectsOutputToContain('All models validated successfully.')
            ->assertExitCode(0)
            ->run();
    }

    public function test_unreadable_baseline_is_an_error(): void
    {
        $this->locate([]);

        $this->command('validate:models', ['--baseline' => $this->baseline])->assertExitCode(2)->run();
    }

    public function test_one_broken_model_does_not_abort_the_run(): void
    {
        $this->locate([ExplodingModel::class, $this->badModel()]);

        $exit    = Artisan::call('validate:models', ['--format' => 'json']);
        $payload = json_decode(trim(Artisan::output()), true);

        $this->assertSame(1, $exit);
        $this->assertIsArray($payload);
        $this->assertSame(2, $payload['count']);
        $this->assertSame('internal', data_get($payload, 'issues.0.kind'));
        $this->assertSame('cannot construct', data_get($payload, 'issues.0.message'));
        $this->assertSame('fillable', data_get($payload, 'issues.1.kind'));
    }

    public function test_excluded_models_are_skipped(): void
    {
        $model = $this->badModel();
        config(['validate-models.exclude_models' => [$model]]);
        $this->locate([$model]);

        $this->command('validate:models')->assertExitCode(0)->run();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function command(string $command, array $options = []): PendingCommand
    {
        $pending = $this->artisan($command, $options);
        $this->assertInstanceOf(PendingCommand::class, $pending);

        return $pending;
    }

    /**
     * @return class-string<Model>
     */
    private function badModel(): string
    {
        return (new class () extends Model {
            protected $table = 'ok_table';

            protected $fillable = ['missing'];
        })::class;
    }

    /**
     * @param list<class-string> $classes
     */
    private function locate(array $classes): void
    {
        App::bind(ModelLocator::class, fn () => new class ($classes) implements ModelLocator {
            /** @param list<class-string> $classes */
            public function __construct(private readonly array $classes)
            {
            }

            public function locate(array $paths, array $namespaces): iterable
            {
                yield from $this->classes;
            }
        });
    }
}
