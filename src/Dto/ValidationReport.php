<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Dto;

final class ValidationReport
{
    /** @param list<ValidationIssue> $issues */
    public function __construct(
        public readonly array $issues = []
    ) {
    }

    public function isClean(): bool
    {
        return \count($this->issues) === 0;
    }

    public function with(ValidationIssue $issue): self
    {
        $copy   = $this->issues;
        $copy[] = $issue;

        return new self($copy);
    }
}
