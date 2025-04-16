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
        // Handle both old and new format
        $mainEntity = is_array($source['entities']) && !isset($source['entities'][0]) 
            ? array_key_first($source['entities']) 
            : $source['entities'][0];

        $tableName = is_array($source['entities']) && !isset($source['entities'][0])
            ? $source['entities'][$mainEntity]['table']
            : $mainEntity;

        $query = DB::table($tableName);

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
            if (is_string($relationship)) {
                // Old format: "users hasMany orders"
                $parts = explode(' ', $relationship);
                if (count($parts) !== 3) {
                    throw new InvalidArgumentException("Invalid relationship format: {$relationship}");
                }

                [$leftTable, $type, $rightTable] = $parts;
                $this->applyJoinOldFormat($query, $leftTable, $type, $rightTable);
            } else {
                // New format: {type: one_to_many, from: users, to: orders, on: {...}}
                if (!isset($relationship['type'], $relationship['from'], $relationship['to'], $relationship['on'])) {
                    throw new InvalidArgumentException("Invalid relationship format");
                }

                $this->applyJoinNewFormat(
                    $query,
                    $relationship['from'],
                    $relationship['type'],
                    $relationship['to'],
                    $relationship['on']
                );
            }
        }
    }

    /**
     * Apply a join using the old format
     */
    protected function applyJoinOldFormat(Builder $query, string $leftTable, string $type, string $rightTable): void
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
     * Apply a join using the new format
     */
    protected function applyJoinNewFormat(Builder $query, string $fromTable, string $type, string $toTable, array $on): void
    {
        $method = match ($type) {
            'one_to_many' => 'leftJoin',
            'one_to_one' => 'leftJoin',
            default => throw new InvalidArgumentException("Unsupported join type: {$type}")
        };

        $query->$method($toTable, function ($join) use ($on) {
            foreach ($on as $leftColumn => $rightColumn) {
                $join->on($leftColumn, '=', $rightColumn);
            }
        });
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

        // Handle both old and new format
        foreach ($source['mapping'] as $field) {
            if (is_array($field) && isset($field['source'], $field['target'])) {
                // New format: {source: users.id, target: user_id}
                if (isset($field['aggregate'])) {
                    $selects[] = $this->buildAggregateSelect($field['source'], $field['target'], $field['aggregate']);
                } else {
                    $selects[] = "{$field['source']} as {$field['target']}";
                }
            } elseif (is_string($field)) {
                // Old format: "id: users.id"
                $parts = explode(':', $field, 2);
                if (count($parts) === 2) {
                    $alias = trim($parts[0]);
                    $column = trim($parts[1]);
                    $selects[] = "{$column} as {$alias}";
                } else {
                    $selects[] = $field;
                }
            } else {
                // Old format: complex field definitions
                foreach ($field as $alias => $definition) {
                    if (is_string($definition)) {
                        $selects[] = "{$definition} as {$alias}";
                    } else {
                        $selects[] = $this->buildAggregateSelect($definition['column'], $alias, $definition['function']);
                    }
                }
            }
        }

        $query->select($selects);
    }

    /**
     * Build an aggregate select statement
     */
    protected function buildAggregateSelect(string $column, string $alias, string $function): Expression
    {
        return match($function) {
            'sum' => DB::raw("SUM({$column}) as {$alias}"),
            'count' => DB::raw("COUNT({$column}) as {$alias}"),
            'avg' => DB::raw("AVG({$column}) as {$alias}"),
            'min' => DB::raw("MIN({$column}) as {$alias}"),
            'max' => DB::raw("MAX({$column}) as {$alias}"),
            'concat' => $this->buildConcatExpression($column, $alias),
            default => throw new InvalidArgumentException("Unsupported aggregate function: {$function}")
        };
    }

    /**
     * Build a concatenation expression
     */
    protected function buildConcatExpression(string $column, string $alias): Expression
    {
        $parts = explode(',', $column);
        $concatParts = array_map(function ($part) {
            $part = trim($part);
            return strpos($part, '.') === false ? "'{$part}'" : $part;
        }, $parts);

        $concat = implode(' || ', $concatParts);
        return DB::raw("({$concat}) as {$alias}");
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
