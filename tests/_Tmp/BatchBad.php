<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Tests\_Tmp;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int            $id
 * @property int            $year
 * @property int            $month
 * @property int            $ordinal
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property int            $status     // <-- wrong on purpose; DB column is string
 */
class BatchBad extends Model
{
    protected $table = 'batches';
}
