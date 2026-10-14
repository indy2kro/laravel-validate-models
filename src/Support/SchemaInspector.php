<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Reads table metadata through the model's own connection, one query per table.
 */
final class SchemaInspector
{
    /** @var array<string, array<string, array{type: string, nullable: bool}>|null> */
    private array $cache = [];

    /**
     * Column name => lower-cased type name and nullability, or null when the table does not exist.
     *
     * @return array<string, array{type: string, nullable: bool}>|null
     */
    public function columns(Model $model): ?array
    {
        $connection = $model->getConnection();
        $table      = $model->getTable();
        $key        = $connection->getName() . '|' . $table;

        if (\array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $schema = $connection->getSchemaBuilder();
        if (!$schema->hasTable($table)) {
            return $this->cache[$key] = null;
        }

        $columns = [];
        foreach ($schema->getColumns($table) as $column) {
            $columns[(string) $column['name']] = [
                'type'     => strtolower((string) $column['type_name']),
                'nullable' => (bool) $column['nullable'],
            ];
        }

        return $this->cache[$key] = $columns;
    }

    public function flush(): void
    {
        $this->cache = [];
    }
}
