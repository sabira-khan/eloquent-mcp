<?php

namespace SabiraKhan\EloquentMcp\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use SabiraKhan\EloquentMcp\EloquentMcpServiceProvider;
use SabiraKhan\EloquentMcp\Tests\Fixtures\Customer;
use SabiraKhan\EloquentMcp\Tests\Fixtures\Order;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [McpServiceProvider::class, EloquentMcpServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('eloquent-mcp.max_rows', 3);
        $app['config']->set('eloquent-mcp.models', [
            Customer::class => [
                'description' => 'People who place orders.',
                'relations' => ['orders', 'secrets'],
            ],
            Order::class => [
                'columns' => ['id', 'customer_id', 'status', 'total', 'meta', 'notes', 'card_token', 'created_at'],
                'relations' => ['customer'],
            ],
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id');
            $table->string('status');
            $table->decimal('total', 10, 2);
            $table->json('meta')->nullable();
            $table->text('notes')->nullable();
            $table->string('card_token')->nullable();
            $table->string('internal_ref')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('secrets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id');
            $table->string('value');
            $table->timestamps();
        });
    }
}
