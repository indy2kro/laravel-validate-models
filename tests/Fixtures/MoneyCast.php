<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Tests\Fixtures;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<Money, Money>
 */
final class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): Money
    {
        return new Money(\is_int($value) ? $value : 0);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): int
    {
        return $value instanceof Money ? $value->cents : 0;
    }
}
