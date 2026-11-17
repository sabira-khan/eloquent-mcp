<?php

namespace SabiraKhan\EloquentMcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use SabiraKhan\EloquentMcp\Support\Filters;
use SabiraKhan\EloquentMcp\Support\ModelRegistry;

#[Description('Count, sum, average, min or max a column of an exposed model, optionally grouped by another column. Use this instead of reading many rows.')]
#[IsReadOnly]
#[IsIdempotent]
class AggregateModel extends Tool
{
    public const FUNCTIONS = ['count', 'sum', 'avg', 'min', 'max'];

    public function handle(Request $request, ModelRegistry $registry): Response|ResponseFactory
    {
        $model = $registry->find((string) $request->get('model'));

        if ($model === null) {
            return Response::error('Unknown model. Available models: '.implode(', ', $registry->keys()).'.');
        }

        $function = $request->get('function', 'count');
        $column = $request->get('column');
        $groupBy = $request->get('group_by');

        if (! in_array($function, self::FUNCTIONS, true)) {
            return Response::error('Function must be one of: '.implode(', ', self::FUNCTIONS).'.');
        }

        if ($function !== 'count' && $column === null) {
            return Response::error("The {$function} function needs a column.");
        }

        foreach (array_filter([$column, $groupBy]) as $name) {
            if (! is_string($name) || ! $model->hasColumn($name)) {
                return Response::error("Unknown column [{$name}] on {$model->key}.");
            }
        }

        try {
            $query = Filters::apply($model->query(), $model, $request->get('where') ?: []);
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        if ($groupBy === null) {
            $value = $function === 'count' ? $query->count($column ?? '*') : $query->{$function}($column);

            return Response::structured(['model' => $model->key, 'function' => $function, 'column' => $column, 'value' => $this->number($value)]);
        }

        $grammar = $query->getQuery()->getGrammar();
        $target = $column === null ? '*' : $grammar->wrap($column);

        $groups = $query->toBase()
            ->select($groupBy)
            ->selectRaw("{$function}({$target}) as aggregate")
            ->groupBy($groupBy)
            ->orderByDesc('aggregate')
            ->orderBy($groupBy)
            ->limit($registry->maxRows() + 1)
            ->get();

        return Response::structured([
            'model' => $model->key,
            'function' => $function,
            'column' => $column,
            'group_by' => $groupBy,
            'has_more' => $groups->count() > $registry->maxRows(),
            'groups' => $groups->take($registry->maxRows())
                ->map(fn ($row) => ['group' => $row->{$groupBy}, 'value' => $this->number($row->aggregate)])
                ->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'model' => $schema->string()->description('Model name from list-models.')->required(),
            'function' => $schema->string()->enum(self::FUNCTIONS)->default('count'),
            'column' => $schema->string()->description('Column to aggregate. Optional for count.'),
            'group_by' => $schema->string()->description('Column to group results by.'),
            'where' => $schema->array()->items($schema->object([
                'column' => $schema->string()->required(),
                'operator' => $schema->string()->enum(Filters::OPERATORS)->default('='),
                'value' => $schema->union(['string', 'number', 'boolean'])->description('Value for comparison operators.'),
                'values' => $schema->array()->description('Values for the in and not_in operators.'),
            ]))->description('Filters joined with AND, same format as query-model.'),
        ];
    }

    private function number(mixed $value): int|float|null
    {
        if ($value === null) {
            return null;
        }

        return is_numeric($value) && (string) (int) $value === (string) $value ? (int) $value : (float) $value;
    }
}
