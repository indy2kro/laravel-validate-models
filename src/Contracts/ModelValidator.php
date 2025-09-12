<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Contracts;

use Illuminate\Database\Eloquent\Model;
use Indy2kro\ValidateModels\Dto\ValidationReport;

interface ModelValidator
{
    /** Validate a single model instance and return a report. */
    public function validate(Model $model): ValidationReport;
}
