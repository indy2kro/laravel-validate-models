<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Indy2kro\ValidateModels\Dto\ValidationIssue;
use Indy2kro\ValidateModels\Dto\ValidationReport;
use ReflectionClass;
use Throwable;

final class RelationChecker
{
    /**
     * @param array<int, string> $ignore
     */
    public function __construct(private readonly array $ignore = [])
    {
    }

    public function check(Model $model): ValidationReport
    {
        $report = new ValidationReport();
        $ref    = new ReflectionClass($model);
        foreach ($ref->getMethods() as $method) {
            if ($method->class !== $model::class || $method->getNumberOfParameters() !== 0) {
                continue;
            }
            if (\in_array($method->name, $this->ignore, true)) {
                continue;
            }

            try {
                $result = $method->invoke($model);
                if (!$result instanceof Relation) {
                    continue;
                }
                // success: we don’t add an issue
            } catch (Throwable $e) {
                $report = $report->with(new ValidationIssue(
                    $model::class,
                    'relation',
                    "Could not resolve relation '{$method->name}': " . $e->getMessage()
                ));
            }
        }

        return $report;
    }
}
