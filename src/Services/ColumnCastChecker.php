<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Services;

use Illuminate\Database\Eloquent\Model;
use Indy2kro\ValidateModels\Dto\ValidationIssue;
use Indy2kro\ValidateModels\Dto\ValidationReport;
use Indy2kro\ValidateModels\Support\IgnoreList;
use Indy2kro\ValidateModels\Support\SchemaInspector;
use Indy2kro\ValidateModels\Support\TypeMatcher;
use Throwable;

final class ColumnCastChecker
{
    private readonly SchemaInspector $schema;

    /**
     * @param array{casts?: list<string>, fillable?: list<string>, columns?: list<string>} $ignore
     * @param array{columns?: bool, casts?: bool, fillable?: bool}                         $checks
     */
    public function __construct(
        private readonly TypeMatcher $matcher,
        private readonly array $ignore = [],
        private readonly array $checks = ['columns' => true, 'casts' => true, 'fillable' => true],
        ?SchemaInspector $schema = null,
    ) {
        $this->schema = $schema ?? new SchemaInspector();
    }

    public function check(Model $model): ValidationReport
    {
        $report = new ValidationReport();
        $class  = $model::class;
        $table  = $model->getTable();

        try {
            $columns = $this->schema->columns($model);
        } catch (Throwable $e) {
            return $report->with(new ValidationIssue(
                $class,
                'column',
                "Could not read the columns of table '$table': " . $e->getMessage()
            ));
        }

        if ($columns === null) {
            if ($this->checks['columns'] ?? true) {
                $report = $report->with(new ValidationIssue($class, 'table', "Table '$table' does not exist."));
            }

            return $report;
        }

        if ($this->checks['casts'] ?? true) {
            $casts = $model->getCasts();

            foreach ($columns as $column => $info) {
                $column = (string) $column;
                $cast   = $casts[$column] ?? null;

                if ($cast === null || IgnoreList::has($this->ignore['casts'] ?? [], $class, $column)) {
                    continue;
                }
                if (IgnoreList::has($this->ignore['columns'] ?? [], $class, $column)) {
                    continue;
                }

                if (!$this->matcher->isCompatible($info['type'], $cast)) {
                    $report = $report->with(new ValidationIssue(
                        $class,
                        'cast',
                        "Column '$column' mismatch. DB: {$info['type']}, Cast: $cast"
                    ));
                }
            }

            foreach ($casts as $field => $cast) {
                $field = (string) $field;
                if (IgnoreList::has($this->ignore['casts'] ?? [], $class, $field)) {
                    continue;
                }
                if (!isset($columns[$field])) {
                    $report = $report->with(new ValidationIssue($class, 'cast', "Model cast '$field' not found in table."));
                }
            }
        }

        if ($this->checks['fillable'] ?? true) {
            foreach ($model->getFillable() as $field) {
                if (IgnoreList::has($this->ignore['fillable'] ?? [], $class, $field)) {
                    continue;
                }
                if (!isset($columns[$field])) {
                    $report = $report->with(new ValidationIssue($class, 'fillable', "Fillable '$field' not found in table."));
                }
            }
        }

        return $report;
    }
}
