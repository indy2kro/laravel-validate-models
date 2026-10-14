<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Indy2kro\ValidateModels\Dto\ValidationIssue;
use Indy2kro\ValidateModels\Dto\ValidationReport;
use Indy2kro\ValidateModels\Support\IgnoreList;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Builds each relation method of a model to make sure it resolves.
 *
 * Only methods declared with a Relation return type are called. Methods without a return type are called only when
 * $includeUntyped is set, because calling an arbitrary method can have side effects.
 */
final class RelationChecker
{
    /**
     * @param list<string> $ignore
     */
    public function __construct(
        private readonly array $ignore = [],
        private readonly bool $includeUntyped = false,
    ) {
    }

    public function check(Model $model): ValidationReport
    {
        $report = new ValidationReport();
        $class  = $model::class;

        foreach ((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->isAbstract() || $method->getNumberOfParameters() !== 0) {
                continue;
            }
            $declaring = $method->getDeclaringClass();
            if (!$declaring->isAnonymous() && str_starts_with($declaring->getName(), 'Illuminate\\')) {
                continue;
            }
            if (IgnoreList::has($this->ignore, $class, $method->name) || !$this->isRelationCandidate($method)) {
                continue;
            }

            try {
                $method->invoke($model);
            } catch (Throwable $e) {
                $report = $report->with(new ValidationIssue(
                    $class,
                    'relation',
                    "Could not resolve relation '{$method->name}': " . $e->getMessage()
                ));
            }
        }

        return $report;
    }

    private function isRelationCandidate(ReflectionMethod $method): bool
    {
        $type = $method->getReturnType();

        if ($type === null) {
            return $this->includeUntyped;
        }

        return $type instanceof ReflectionNamedType
            && !$type->isBuiltin()
            && is_a($type->getName(), Relation::class, true);
    }
}
