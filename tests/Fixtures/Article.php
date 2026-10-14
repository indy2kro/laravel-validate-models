<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Tests\Fixtures;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int                     $id
 * @property string                  $title
 * @property ?CarbonImmutable        $published_at
 * @property array<string, int>|null $meta
 * @property Money                   $price
 * @property mixed                   $settings
 * @property StringStatus            $status
 */
class Article extends Model
{
    protected $table = 'articles';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'meta'         => 'array',
            'price'        => MoneyCast::class,
            'status'       => StringStatus::class,
        ];
    }
}
