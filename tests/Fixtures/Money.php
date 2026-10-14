<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Tests\Fixtures;

final class Money
{
    public function __construct(public readonly int $cents)
    {
    }
}
