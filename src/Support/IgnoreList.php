<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Support;

/**
 * Ignore lists accept a plain field name (applies to every model) or "Fully\Qualified\Model::field".
 */
final class IgnoreList
{
    /**
     * @param list<string> $list
     */
    public static function has(array $list, string $modelClass, string $name): bool
    {
        return \in_array($name, $list, true)
            || \in_array(ltrim($modelClass, '\\') . '::' . $name, $list, true);
    }
}
