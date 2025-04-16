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
        $mainEntity = is_array($source['entities']) && ! isset($source['entities'][0])
            ? array_key_first($source['entities'])
            : $source['entities'][0];

        $tableName = is_array($source['entities']) && ! isset($source['entities'][0])
            ? $source['entities'][$mainEntity]['table']
            : $mainEntity;

        $query = DB::table($tableName);

        $this->applyJoins($query, $source);
        $this->applyConditions($query, $source);
        $this->applySelects($query, $source['mapping']);
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
                if (! isset($relationship['type'], $relationship['from'], $relationship['to'], $relationship['on'])) {
                    throw new InvalidArgumentException('Invalid relationship format');
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
    protected function applySelects(Builder $query, array $mapping): void
    {
        foreach ($mapping as $item) {
            if (is_string($item)) {
                // Handle simple string format: "id: users.id"
                $parts = explode(':', $item, 2);
                if (count($parts) === 2) {
                    $alias = trim($parts[0]);
                    $column = trim($parts[1]);
                    $query->selectRaw("{$column} as {$alias}");
                } else {
                    $query->selectRaw($item);
                }
            } elseif (is_array($item)) {
                // Handle array format with alias and config
                foreach ($item as $alias => $config) {
                    if (is_string($config)) {
                        // Simple column mapping: "id: users.id"
                        $query->selectRaw("{$config} as {$alias}");
                    } elseif (is_array($config)) {
                        if (isset($config['function'])) {
                            // Function-based mapping
                            $function = $config['function'];
                            if (isset($config['columns'])) {
                                // Handle functions that take multiple columns (like concat)
                                $columns = $config['columns'];
                                $concatenated = implode(' || ', array_map(function($col) {
                                    return is_string($col) ? "'{$col}'" : $col;
                                }, $columns));
                                $query->selectRaw("({$concatenated}) as {$alias}");
                            } else {
                                // Handle functions that take a single column
                                $column = $config['column'] ?? null;
                                if ($column === null) {
                                    throw new InvalidArgumentException("Column must be specified for function {$function}");
                                }
                                $this->buildAggregateSelect($query, $column, $alias, $function);
                            }
                        } else {
                            throw new InvalidArgumentException("Invalid mapping configuration");
                        }
                    }
                }
            }
        }
    }

    /**
     * Build an aggregate select statement
     */
    protected function buildAggregateSelect(Builder $query, string $column, string $alias, string $function): void
    {
        $query->selectRaw(match ($function) {
            'sum' => "SUM({$column}) as {$alias}",
            'count' => "COUNT({$column}) as {$alias}",
            'avg' => "AVG({$column}) as {$alias}",
            'min' => "MIN({$column}) as {$alias}",
            'max' => "MAX({$column}) as {$alias}",
            'concat' => $this->buildConcatExpression($column, $alias),
            default => throw new InvalidArgumentException("Unsupported aggregate function: {$function}")
        });
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
