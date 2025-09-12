<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Tests\_Tmp;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int     $id
 * @property int     $year
 * @property int     $month
 * @property int     $ordinal
 * @property Carbon  $created_at
 * @property Carbon  $updated_at
 * @property string  $status
 * @property ?string $error_message
 */
class Batch extends Model
{
    protected $table = 'batches';

    protected function casts(): array
    {
        return [
            'year'       => 'integer',
            'month'      => 'integer',
            'ordinal'    => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            // 'status' intentionally lacks a cast for the test
        ];
    }
}
