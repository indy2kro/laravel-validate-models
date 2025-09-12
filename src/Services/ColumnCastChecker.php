<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Indy2kro\ValidateModels\Dto\ValidationIssue;
use Indy2kro\ValidateModels\Dto\ValidationReport;
use Indy2kro\ValidateModels\Support\TypeMatcher;
use Throwable;

final class ColumnCastChecker
{
    /**
     * @param array<string, mixed> $ignore
     * @param array<string, mixed> $checks
     */
    public function __construct(
        private readonly TypeMatcher $matcher,
        private readonly array $ignore = [
            'casts' => [], 'fillable' => [], 'columns' => [],
        ],
        private readonly array $checks = [
            'columns' => true, 'casts' => true, 'fillable' => true,
        ]
    ) {
    }

    public function check(Model $model): ValidationReport
    {
        $report = new ValidationReport();
        $table  = $model->getTable();

        if (!Schema::hasTable($table)) {
            return $report->with(new ValidationIssue($model::class, 'table', "Table '$table' does not exist."));
        }

        $columns  = Schema::getColumnListing($table);
        $casts    = $model->getCasts();
        $fillable = $model->getFillable();

        foreach ($columns as $column) {
            if (\in_array($column, $this->ignore['columns'], true)) {
                continue;
            }

            $type = 'unknown';
            try {
                $type = DB::getSchemaBuilder()->getColumnType($table, $column);
            } catch (Throwable $e) {
                $report = $report->with(new ValidationIssue(
                    $model::class,
                    'column',
                    "Could not get type for column '$column': " . $e->getMessage()
                ));
                continue;
            }

            if ($this->checks['casts']) {
                $cast = $casts[$column] ?? null;
                if ($cast && !\in_array($column, $this->ignore['casts'], true)) {
                    if (!$this->matcher->isCompatible($type, $cast)) {
                        $report = $report->with(new ValidationIssue(
                            $model::class,
                            'cast',
                            "Column '$column' mismatch. DB: $type, Cast: $cast"
                        ));
                    }
                }
            }
        }

        if ($this->checks['casts']) {
            foreach ($casts as $field => $cast) {
                if (\in_array($field, $this->ignore['casts'], true)) {
                    continue;
                }
                if (!\in_array($field, $columns, true)) {
                    $report = $report->with(new ValidationIssue(
                        $model::class,
                        'cast',
                        "Model cast '$field' not found in table."
                    ));
                }
            }
        }

        if ($this->checks['fillable']) {
            foreach ($fillable as $field) {
                if (\in_array($field, $this->ignore['fillable'], true)) {
                    continue;
                }
                if (!\in_array($field, $columns, true)) {
                    $report = $report->with(new ValidationIssue(
                        $model::class,
                        'fillable',
                        "Fillable '$field' not found in table."
                    ));
                }
            }
        }

        return $report;
    }
}
