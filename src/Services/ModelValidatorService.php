<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Services;

use Illuminate\Database\Eloquent\Model;
use Indy2kro\ValidateModels\Contracts\ModelValidator;
use Indy2kro\ValidateModels\Dto\ValidationReport;

final class ModelValidatorService implements ModelValidator
{
    /**
     * @param array<string, bool> $checks
     */
    public function __construct(
        private readonly ColumnCastChecker $columns,
        private readonly RelationChecker   $relations,
        private readonly ?AnnotationChecker $annotations = null,
        private readonly array $checks = ['columns' => true, 'casts' => true, 'fillable' => true, 'relations' => true, 'annotations' => true],
    ) {
    }

    public function validate(Model $model): ValidationReport
    {
        $report = new ValidationReport();

        if ($this->checks['columns'] || $this->checks['casts'] || $this->checks['fillable']) {
            $report = $this->merge($report, $this->columns->check($model));
        }
        if ($this->checks['relations']) {
            $report = $this->merge($report, $this->relations->check($model));
        }
        if (($this->checks['annotations'] ?? false) && $this->annotations) {
            $report = $this->merge($report, $this->annotations->check($model));
        }

        return $report;
    }

    private function merge(ValidationReport $a, ValidationReport $b): ValidationReport
    {
        $acc = $a->issues;
        array_push($acc, ...$b->issues);

        return new ValidationReport($acc);
    }
}
