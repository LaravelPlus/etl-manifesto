<?php

namespace Laravelplus\EtlManifesto\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QueryBuilder
{
    /**
     * Build a query based on manifest specifications
     */
    public function build(array $source): Builder
    {
        $query = DB::table($source['entities'][0]);

        $this->applyJoins($query, $source);
        $this->applyConditions($query, $source);
        $this->applySelects($query, $source);
        $this->applyGroupBy($query, $source);

        return $query;
    }

    /**
     * Apply joins based on relationships
     */
    protected function applyJoins(Builder $query, array $source): void
    {
        if (! isset($source['relationships'])) {
            return;
        }

        foreach ($source['relationships'] as $relationship) {
            $parts = explode(' ', $relationship);
            if (count($parts) !== 3) {
                throw new InvalidArgumentException("Invalid relationship format: {$relationship}");
            }

            [$leftTable, $type, $rightTable] = $parts;
            $this->applyJoin($query, $leftTable, $type, $rightTable);
        }
    }

    /**
     * Apply a single join
     */
    protected function applyJoin(Builder $query, string $leftTable, string $type, string $rightTable): void
    {
        $method = match ($type) {
            'hasMany' => 'leftJoin',
            'hasOne' => 'leftJoin',
            default => throw new InvalidArgumentException("Unsupported join type: {$type}")
        };

        // Convert table name to singular for foreign key
        $foreignKey = rtrim($leftTable, 's').'_id';

        $query->$method(
            $rightTable,
            "{$leftTable}.id",
            '=',
            "{$rightTable}.{$foreignKey}"
        );
    }

    /**
     * Apply conditions to the query
     */
    protected function applyConditions(Builder $query, array $source): void
    {
        if (! isset($source['conditions'])) {
            return;
        }

        foreach ($source['conditions'] as $condition) {
            foreach ($condition as $column => $value) {
                if ($value === 'last_month') {
                    $query->where($column, '>=', now()->subMonth()->startOfMonth());
                    $query->where($column, '<=', now()->subMonth()->endOfMonth());
                } else {
                    $query->where($column, $value);
                }
            }
        }
    }

    /**
     * Apply select statements
     */
    protected function applySelects(Builder $query, array $source): void
    {
        $selects = [];
        foreach ($source['mapping'] as $field) {
            if (is_string($field)) {
                // Handle simple field mapping like "id: users.id"
                $parts = explode(':', $field, 2);
                if (count($parts) === 2) {
                    $alias = trim($parts[0]);
                    $column = trim($parts[1]);
                    $selects[] = "{$column} as {$alias}";
                } else {
                    $selects[] = $field;
                }
            } else {
                // Handle complex field definitions
                foreach ($field as $alias => $definition) {
                    if (is_string($definition)) {
                        $selects[] = "{$definition} as {$alias}";
                    } else {
                        $selects[] = $this->buildAggregateSelect($definition, $alias);
                    }
                }
            }
        }

        $query->select($selects);
    }

    /**
     * Build an aggregate select statement
     */
    protected function buildAggregateSelect(array $field, string $alias): Expression
    {
        // Handle aggregate functions
        if (isset($field['function'])) {
            $function = $field['function'];

            // Handle count function
            if ($function === 'count') {
                return DB::raw("COUNT({$field['column']}) as {$alias}");
            }

            // Handle sum function
            if ($function === 'sum') {
                return DB::raw("SUM({$field['column']}) as {$alias}");
            }

            // Handle concat function
            if ($function === 'concat') {
                $columns = $field['columns'] ?? [];
                if (empty($columns)) {
                    throw new InvalidArgumentException('Missing columns for concat function');
                }

                // Process each column for concatenation
                $concatParts = array_map(function ($col) {
                    if (is_string($col) && strpos($col, '.') === false) {
                        // This is a string literal, wrap in quotes
                        return "'{$col}'";
                    }

                    // This is a column reference, use as is
                    return $col;
                }, $columns);

                // Use SQLite's string concatenation operator (||)
                $concat = implode(' || ', $concatParts);

                return DB::raw("({$concat}) as {$alias}");
            }
        }

        throw new InvalidArgumentException('Invalid field definition in mapping');
    }

    /**
     * Apply group by clauses
     */
    protected function applyGroupBy(Builder $query, array $source): void
    {
        if (! isset($source['group_by'])) {
            return;
        }

        $query->groupBy($source['group_by']);
    }
}
