<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Dto;

final class ValidationIssue
{
    public function __construct(
        public readonly string $modelClass,
        public readonly string $kind,     // 'table' | 'column' | 'cast' | 'fillable' | 'relation' | 'internal'
        public readonly string $message
    ) {
    }
}
