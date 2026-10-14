<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Tests\Fixtures;

enum StringStatus: string
{
    case Open   = 'open';
    case Closed = 'closed';
}
