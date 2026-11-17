<?php

namespace SabiraKhan\EloquentMcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use SabiraKhan\EloquentMcp\Support\ModelRegistry;

#[Description('List the Eloquent models this application exposes, with their tables, readable columns and relations. Call this first.')]
#[IsReadOnly]
#[IsIdempotent]
class ListModels extends Tool
{
    public function handle(Request $request, ModelRegistry $registry): Response|ResponseFactory
    {
        $models = array_map(fn ($model) => array_filter([
            'model' => $model->key,
            'description' => $model->description(),
            'table' => $model->table(),
            'columns' => $model->columns(),
            'relations' => $model->relationNames(),
        ], fn ($value) => $value !== null), array_values($registry->all()));

        if ($models === []) {
            return Response::error('No models are exposed. Add models to the eloquent-mcp.models config.');
        }

        return Response::structured(['models' => $models, 'max_rows' => $registry->maxRows()]);
    }
}
