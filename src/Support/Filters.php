<?php

namespace SabiraKhan\EloquentMcp\Support;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class Filters
{
    public const OPERATORS = ['=', '!=', '>', '>=', '<', '<=', 'like', 'in', 'not_in', 'is_null', 'not_null'];

    public static function apply(Builder $query, ExposedModel $model, array $filters): Builder
    {
        foreach ($filters as $filter) {
            $column = $filter['column'] ?? null;
            $operator = $filter['operator'] ?? '=';
            $value = $filter['value'] ?? null;
            $values = $filter['values'] ?? $value;

            if (! is_string($column) || ! $model->hasColumn($column)) {
                throw new InvalidArgumentException("Unknown column [{$column}] on {$model->key}.");
            }

            if (! in_array($operator, self::OPERATORS, true)) {
                throw new InvalidArgumentException("Unsupported operator [{$operator}].");
            }

            match ($operator) {
                'is_null' => $query->whereNull($column),
                'not_null' => $query->whereNotNull($column),
                'in' => $query->whereIn($column, self::list($values)),
                'not_in' => $query->whereNotIn($column, self::list($values)),
                default => $query->where($column, $operator === '!=' ? '<>' : $operator, self::scalar($value)),
            };
        }

        return $query;
    }

    private static function list(mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            throw new InvalidArgumentException('The in and not_in operators need a non-empty values array.');
        }

        return array_map(self::scalar(...), array_values($value));
    }

    private static function scalar(mixed $value): string|int|float|bool
    {
        if (! is_scalar($value)) {
            throw new InvalidArgumentException('Filter values must be strings, numbers or booleans.');
        }

        return $value;
    }
}
