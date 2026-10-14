<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class ExplodingModel extends Model
{
    protected $table = 'ok_table';

    public function __construct()
    {
        throw new RuntimeException('cannot construct');
    }
}
