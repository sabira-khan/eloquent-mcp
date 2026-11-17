<?php

namespace SabiraKhan\EloquentMcp;

use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use SabiraKhan\EloquentMcp\Support\ModelRegistry;

class EloquentMcpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/eloquent-mcp.php', 'eloquent-mcp');

        $this->app->singleton(ModelRegistry::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/eloquent-mcp.php' => config_path('eloquent-mcp.php'),
        ], 'eloquent-mcp-config');

        if (config('eloquent-mcp.enabled')) {
            Mcp::local(config('eloquent-mcp.handle'), EloquentServer::class);
        }
    }
}
