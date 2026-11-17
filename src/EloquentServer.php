<?php

namespace SabiraKhan\EloquentMcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use SabiraKhan\EloquentMcp\Tools\AggregateModel;
use SabiraKhan\EloquentMcp\Tools\DescribeModel;
use SabiraKhan\EloquentMcp\Tools\ListModels;
use SabiraKhan\EloquentMcp\Tools\QueryModel;

#[Name('Eloquent')]
#[Version('1.0.0')]
#[Instructions('Read-only access to this Laravel application\'s data through its Eloquent models. Start with list-models, use describe-model to learn columns and relations, prefer aggregate-model for totals and counts, and use query-model to read rows.')]
class EloquentServer extends Server
{
    protected array $tools = [
        ListModels::class,
        DescribeModel::class,
        QueryModel::class,
        AggregateModel::class,
    ];
}
