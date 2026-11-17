<?php

namespace SabiraKhan\EloquentMcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use SabiraKhan\EloquentMcp\Support\ModelRegistry;

#[Description('Describe one exposed model: column types, nullability, casts and relations with the models they point to.')]
#[IsReadOnly]
#[IsIdempotent]
class DescribeModel extends Tool
{
    public function handle(Request $request, ModelRegistry $registry): Response|ResponseFactory
    {
        $model = $registry->find((string) $request->get('model'));

        if ($model === null) {
            return Response::error('Unknown model. Available models: '.implode(', ', $registry->keys()).'.');
        }

        $schema = $model->schema();
        $casts = $model->model()->getCasts();

        $columns = array_map(fn (string $column) => array_filter([
            'name' => $column,
            'type' => $schema[$column]['type_name'] ?? null,
            'nullable' => $schema[$column]['nullable'] ?? null,
            'cast' => is_string($casts[$column] ?? null) ? $casts[$column] : null,
        ], fn ($value) => $value !== null), $model->columns());

        $relations = array_map(function (string $name) use ($model, $registry) {
            $relation = $model->relation($name);
            $related = $registry->forClass($relation->getRelated()::class);

            return array_filter([
                'name' => $name,
                'type' => class_basename($relation),
                'model' => $related?->key,
            ], fn ($value) => $value !== null);
        }, $model->relationNames());

        return Response::structured(array_filter([
            'model' => $model->key,
            'description' => $model->description(),
            'table' => $model->table(),
            'primary_key' => $model->model()->getKeyName(),
            'columns' => $columns,
            'relations' => $relations,
        ], fn ($value) => $value !== null));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'model' => $schema->string()->description('Model name from list-models, e.g. "Order".')->required(),
        ];
    }
}
