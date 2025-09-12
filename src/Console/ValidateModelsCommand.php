<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Indy2kro\ValidateModels\Contracts\ModelLocator;
use Indy2kro\ValidateModels\Contracts\ModelValidator;
use Indy2kro\ValidateModels\Services\AnnotationChecker;
use Indy2kro\ValidateModels\Services\ColumnCastChecker;
use Indy2kro\ValidateModels\Services\ModelValidatorService;
use Indy2kro\ValidateModels\Services\RelationChecker;
use Indy2kro\ValidateModels\Support\DocblockPropertyParser;
use Indy2kro\ValidateModels\Support\TypeMatcher;

final class ValidateModelsCommand extends Command
{
    protected $signature = 'validate:models
        {--path=*}
        {--namespace=*}
        {--connection=}
        {--no-columns}
        {--no-casts}
        {--no-fillable}
        {--no-relations}
        {--no-fail}';

    protected $description = 'Validate Eloquent models against the database schema';

    public function handle(ModelLocator $locator): int
    {
        $cfg        = Config::get('validate-models', []);
        $paths      = (array) ($this->option('path') ?: ($cfg['models_paths'] ?? [base_path('app/Models')]));
        $namespaces = (array) ($this->option('namespace') ?: ($cfg['models_namespaces'] ?? ['App\\Models']));

        $connection = (string) ($this->option('connection') ?: ($cfg['connection'] ?? ''));
        if ($connection) {
            DB::setDefaultConnection($connection);
        }

        $driver  = config('database.connections.' . config('database.default') . '.driver', '*');
        $matcher = new TypeMatcher($cfg['type_map'] ?? [], $driver);

        $checks = [
            'columns'   => !$this->option('no-columns')   && ($cfg['checks']['columns'] ?? true),
            'casts'     => !$this->option('no-casts')     && ($cfg['checks']['casts'] ?? true),
            'fillable'  => !$this->option('no-fillable')  && ($cfg['checks']['fillable'] ?? true),
            'relations' => !$this->option('no-relations') && ($cfg['checks']['relations'] ?? true),
        ];
        $ignore = $cfg['ignore'] ?? ['casts' => [], 'fillable' => [], 'columns' => [], 'relations' => []];

        // Compose the validator with concrete checkers (DI-friendly)
        $validator = $this->makeValidator($matcher, $checks, $ignore);

        $issues = 0;
        foreach ($locator->locate($paths, $namespaces) as $class) {
            /** @var Model $model */
            $model = new $class();
            $this->info("🔍 Validating model: {$class} (table: {$model->getTable()})");
            $report = $validator->validate($model);

            foreach ($report->issues as $issue) {
                $issues++;
                $this->warn("  ⚠️ [{$issue->kind}] {$issue->message}");
            }
        }

        $failOnWarnings = !$this->option('no-fail') && (bool)($cfg['fail_on_warnings'] ?? true);
        if ($issues > 0) {
            $this->error("Validation completed with {$issues} issue(s).");

            return $failOnWarnings ? 1 : 0;
        }

        $this->info('✅ All models validated successfully.');

        return 0;
    }

    /**
     * @param array<string, mixed> $checks
     * @param array<string, mixed> $ignore
     */
    private function makeValidator(TypeMatcher $matcher, array $checks, array $ignore): ModelValidator
    {
        $columns = new ColumnCastChecker($matcher, [
            'casts'    => $ignore['casts']    ?? [],
            'fillable' => $ignore['fillable'] ?? [],
            'columns'  => $ignore['columns']  ?? [],
        ], [
            'columns'  => $checks['columns'],
            'casts'    => $checks['casts'],
            'fillable' => $checks['fillable'],
        ]);

        $relations = new RelationChecker($ignore['relations'] ?? []);

        $annCfg            = config('validate-models.annotations', []);
        $annotationChecker = new AnnotationChecker(
            $matcher,
            new DocblockPropertyParser(),
            (bool)($annCfg['columns_only'] ?? true),
            (bool)($annCfg['check_casts'] ?? true),
            (array)($annCfg['ignore'] ?? []),
            (array)($annCfg['aliases'] ?? [])
        );

        return new ModelValidatorService($columns, $relations, $annotationChecker, [
            'columns'     => $checks['columns'],
            'casts'       => $checks['casts'],
            'fillable'    => $checks['fillable'],
            'relations'   => $checks['relations'],
            'annotations' => (bool)config('validate-models.checks.annotations', true),
        ]);
    }
}
