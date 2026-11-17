<?php

namespace SabiraKhan\EloquentMcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use SabiraKhan\EloquentMcp\Support\ExposedModel;
use SabiraKhan\EloquentMcp\Support\Filters;
use SabiraKhan\EloquentMcp\Support\ModelRegistry;

#[Description('Read rows of an exposed model with filters, sorting, a row limit and optional relations. Model scopes such as soft deletes still apply, and hidden columns are never returned.')]
#[IsReadOnly]
#[IsIdempotent]
class QueryModel extends Tool
{
    public function handle(Request $request, ModelRegistry $registry): Response|ResponseFactory
    {
        $model = $registry->find((string) $request->get('model'));

        if ($model === null) {
            return Response::error('Unknown model. Available models: '.implode(', ', $registry->keys()).'.');
        }

        $limit = min(max(1, (int) $request->get('limit', 20)), $registry->maxRows());
        $columns = $request->get('columns') ?: $model->columns();
        $relations = $request->get('with') ?: [];
        $relatedModels = [];

        try {
            foreach ($columns as $column) {
                $this->ensureColumn($model, $column);
            }

            $query = Filters::apply($model->query(), $model, $request->get('where') ?: []);

            if ($orderBy = $request->get('order_by')) {
                $this->ensureColumn($model, $orderBy);
                $query->orderBy($orderBy, $request->get('direction') === 'desc' ? 'desc' : 'asc');
            }

            $eager = [];

            foreach ($relations as $name) {
                $related = $this->relatedModel($model, $registry, $name);
                $eager[$name] = fn ($relation) => $relation->limit($registry->maxRows());
                $relatedModels[$name] = $related;
            }

            $rows = $query->with($eager)->limit($limit + 1)->get();
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        $more = $rows->count() > $limit;

        $data = $rows->take($limit)->map(function (Model $row) use ($model, $columns, $relatedModels) {
            $values = array_intersect_key($model->serialize($row), array_flip($columns));

            foreach ($relatedModels as $name => $related) {
                $loaded = $row->getRelation($name);
                $values[$name] = $loaded instanceof Collection
                    ? $loaded->map(fn (Model $item) => $related->serialize($item))->all()
                    : ($loaded ? $related->serialize($loaded) : null);
            }

            return $values;
        })->values()->all();

        return Response::structured([
            'model' => $model->key,
            'count' => count($data),
            'has_more' => $more,
            'rows' => $data,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'model' => $schema->string()->description('Model name from list-models.')->required(),
            'columns' => $schema->array()->items($schema->string())->description('Columns to return. Defaults to every readable column.'),
            'where' => $schema->array()->items($schema->object([
                'column' => $schema->string()->required(),
                'operator' => $schema->string()->enum(Filters::OPERATORS)->default('='),
                'value' => $schema->union(['string', 'number', 'boolean'])->description('Value for comparison operators.'),
                'values' => $schema->array()->description('Values for the in and not_in operators.'),
            ]))->description('Filters joined with AND. Use "in" and "not_in" with values, "like" with % wildcards.'),
            'order_by' => $schema->string()->description('Column to sort by.'),
            'direction' => $schema->string()->enum(['asc', 'desc'])->default('asc'),
            'limit' => $schema->integer()->min(1)->description('Maximum rows to return. Capped by the server.')->default(20),
            'with' => $schema->array()->items($schema->string())->description('Relations to include, from describe-model.'),
        ];
    }

    private function ensureColumn(ExposedModel $model, mixed $column): void
    {
        if (! is_string($column) || ! $model->hasColumn($column)) {
            throw new InvalidArgumentException("Unknown column [{$column}] on {$model->key}.");
        }
    }

    private function relatedModel(ExposedModel $model, ModelRegistry $registry, mixed $name): ExposedModel
    {
        $relation = is_string($name) ? $model->relation($name) : null;
        $related = $relation ? $registry->forClass($relation->getRelated()::class) : null;

        if ($related === null) {
            throw new InvalidArgumentException("Unknown relation [{$name}] on {$model->key}.");
        }

        return $related;
    }
}
