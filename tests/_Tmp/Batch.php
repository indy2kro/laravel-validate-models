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
 * @property ?Carbon $created_at    // nullable in schema
 * @property ?Carbon $updated_at    // nullable in schema
 * @property ?string $status        // nullable in schema
 * @property ?string $error_message // nullable in schema
 */
class Batch extends Model
{
    protected $table = 'batches';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year'       => 'integer',
            'month'      => 'integer',
            'ordinal'    => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            // no cast for status on purpose
        ];
    }
}
