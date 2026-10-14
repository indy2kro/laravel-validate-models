<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Indy2kro\ValidateModels\Contracts\ModelLocator;
use Indy2kro\ValidateModels\Contracts\ModelValidator;
use Indy2kro\ValidateModels\Dto\ValidationIssue;
use Indy2kro\ValidateModels\Services\AnnotationChecker;
use Indy2kro\ValidateModels\Services\ColumnCastChecker;
use Indy2kro\ValidateModels\Services\ModelValidatorService;
use Indy2kro\ValidateModels\Services\RelationChecker;
use Indy2kro\ValidateModels\Support\DocblockPropertyParser;
use Indy2kro\ValidateModels\Support\SchemaInspector;
use Indy2kro\ValidateModels\Support\Settings;
use Indy2kro\ValidateModels\Support\TypeMatcher;
use Throwable;

final class ValidateModelsCommand extends Command
{
    private const FORMATS = ['text', 'json', 'github'];

    protected $signature = 'validate:models
        {--path=* : Directory to scan for models (repeatable)}
        {--namespace=* : Namespace of the models in each path (repeatable)}
        {--connection= : Database connection to use as the default}
        {--no-columns : Do not report missing tables}
        {--no-casts : Skip cast checks}
        {--no-fillable : Skip fillable checks}
        {--no-relations : Skip relation checks}
        {--no-annotations : Skip @property annotation checks}
        {--no-fail : Exit with 0 even when issues are found}
        {--format=text : Output format: text, json or github}
        {--baseline= : JSON file of known issues to ignore}
        {--generate-baseline= : Write the current issues to this file and exit with 0}';

    protected $description = 'Validate Eloquent models against the database schema';

    public function handle(ModelLocator $locator): int
    {
        $settings = Settings::fromArray((array) config('validate-models', []));

        $format = $this->stringOption('format') ?? 'text';
        if (!\in_array($format, self::FORMATS, true)) {
            $this->error("Unknown format '{$format}'. Use one of: " . implode(', ', self::FORMATS) . '.');

            return self::INVALID;
        }

        $baselineFile = $this->stringOption('baseline');
        $baseline     = [];
        if ($baselineFile !== null) {
            $loaded = $this->readBaseline($baselineFile);
            if ($loaded === null) {
                return self::INVALID;
            }
            $baseline = array_flip($loaded);
        }

        $paths      = $this->listOption('path') ?: $settings->modelsPaths;
        $namespaces = $this->listOption('namespace') ?: $settings->modelsNamespaces;

        $connection = $this->stringOption('connection') ?? $settings->connection;
        if ($connection !== null) {
            DB::setDefaultConnection($connection);
        }

        $matcher   = new TypeMatcher($settings->typeMap, DB::connection()->getDriverName());
        $validator = $this->makeValidator($settings, $matcher);
        $exclude   = array_map(static fn (string $c): string => ltrim($c, '\\'), $settings->excludeModels);
        $verbose   = $format === 'text';

        /** @var list<ValidationIssue> $issues */
        $issues = [];
        foreach ($locator->locate($paths, $namespaces) as $class) {
            if (\in_array(ltrim($class, '\\'), $exclude, true)) {
                continue;
            }

            try {
                /** @var Model $model */
                $model = new $class();
                if ($verbose) {
                    $this->info("🔍 Validating model: {$class} (table: {$model->getTable()})");
                }
                $found = $validator->validate($model)->issues;
            } catch (Throwable $e) {
                $found = [new ValidationIssue($class, 'internal', $e->getMessage())];
            }

            foreach ($found as $issue) {
                if (isset($baseline[$this->fingerprint($issue)])) {
                    continue;
                }
                $issues[] = $issue;
                if ($verbose) {
                    $this->warn("  ⚠️ [{$issue->kind}] {$issue->message}");
                }
            }
        }

        $generate = $this->stringOption('generate-baseline');
        if ($generate !== null) {
            return $this->writeBaseline($generate, $issues);
        }

        $this->report($format, $issues);

        if ($issues !== []) {
            return !$this->option('no-fail') && $settings->failOnWarnings ? 1 : 0;
        }

        return 0;
    }

    private function makeValidator(Settings $settings, TypeMatcher $matcher): ModelValidator
    {
        $schema = new SchemaInspector();

        $checks = [
            'columns'     => $settings->checks['columns']     && !$this->option('no-columns'),
            'casts'       => $settings->checks['casts']       && !$this->option('no-casts'),
            'fillable'    => $settings->checks['fillable']    && !$this->option('no-fillable'),
            'relations'   => $settings->checks['relations']   && !$this->option('no-relations'),
            'annotations' => $settings->checks['annotations'] && !$this->option('no-annotations'),
        ];

        $columns = new ColumnCastChecker($matcher, [
            'casts'    => $settings->ignore['casts'],
            'fillable' => $settings->ignore['fillable'],
            'columns'  => $settings->ignore['columns'],
        ], [
            'columns'  => $checks['columns'],
            'casts'    => $checks['casts'],
            'fillable' => $checks['fillable'],
        ], $schema);

        $relations = new RelationChecker($settings->ignore['relations'], $settings->relationsIncludeUntyped);

        $annotations = new AnnotationChecker(
            $matcher,
            new DocblockPropertyParser(),
            $settings->annotationsColumnsOnly,
            $settings->annotationsCheckCasts,
            $settings->annotationsIgnore,
            $settings->annotationsAliases,
            $settings->annotationsCheckNullability,
            $schema,
        );

        return new ModelValidatorService($columns, $relations, $annotations, $checks);
    }

    /**
     * @param list<ValidationIssue> $issues
     */
    private function report(string $format, array $issues): void
    {
        if ($format === 'json') {
            $this->line((string) json_encode([
                'count'  => \count($issues),
                'issues' => array_map(static fn (ValidationIssue $i): array => [
                    'model'   => $i->modelClass,
                    'kind'    => $i->kind,
                    'message' => $i->message,
                ], $issues),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return;
        }

        if ($format === 'github') {
            foreach ($issues as $issue) {
                $title   = $this->escapeAnnotation("{$issue->modelClass} [{$issue->kind}]", true);
                $message = $this->escapeAnnotation($issue->message, false);
                $this->line("::warning title={$title}::{$message}");
            }

            return;
        }

        if ($issues !== []) {
            $this->error('Validation completed with ' . \count($issues) . ' issue(s).');

            return;
        }

        $this->info('✅ All models validated successfully.');
    }

    private function escapeAnnotation(string $value, bool $property): string
    {
        $value = str_replace(['%', "\r", "\n"], ['%25', '%0D', '%0A'], $value);

        return $property ? str_replace([':', ','], ['%3A', '%2C'], $value) : $value;
    }

    private function fingerprint(ValidationIssue $issue): string
    {
        return $issue->modelClass . '|' . $issue->kind . '|' . $issue->message;
    }

    /**
     * @return list<string>|null null when the file cannot be read
     */
    private function readBaseline(string $file): ?array
    {
        $json = is_file($file) ? file_get_contents($file) : false;
        $data = $json === false ? null : json_decode($json, true);

        if (!\is_array($data)) {
            $this->error("Cannot read baseline file '{$file}'.");

            return null;
        }

        return array_values(array_filter($data, is_string(...)));
    }

    /**
     * @param list<ValidationIssue> $issues
     */
    private function writeBaseline(string $file, array $issues): int
    {
        $fingerprints = array_values(array_unique(array_map($this->fingerprint(...), $issues)));
        sort($fingerprints);

        $json = json_encode($fingerprints, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($file, $json . "\n") === false) {
            $this->error("Cannot write baseline file '{$file}'.");

            return self::FAILURE;
        }

        $this->info('Wrote ' . \count($fingerprints) . " known issue(s) to {$file}.");

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return \is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function listOption(string $name): array
    {
        $value = $this->option($name);

        return \is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }
}
