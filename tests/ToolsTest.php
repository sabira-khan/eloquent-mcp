<?php

namespace SabiraKhan\EloquentMcp\Tests;

use Laravel\Mcp\Facades\Mcp;
use SabiraKhan\EloquentMcp\EloquentServer;
use SabiraKhan\EloquentMcp\Tests\Fixtures\Customer;
use SabiraKhan\EloquentMcp\Tests\Fixtures\Secret;
use SabiraKhan\EloquentMcp\Tools\AggregateModel;
use SabiraKhan\EloquentMcp\Tools\DescribeModel;
use SabiraKhan\EloquentMcp\Tools\ListModels;
use SabiraKhan\EloquentMcp\Tools\QueryModel;

class ToolsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $amna = Customer::create(['name' => 'Amna', 'email' => 'amna@example.com', 'password' => 'hashed-1']);
        $omar = Customer::create(['name' => 'Omar', 'email' => 'omar@example.com', 'password' => 'hashed-2']);

        $amna->orders()->create(['status' => 'paid', 'total' => 120.5, 'card_token' => 'tok_1', 'internal_ref' => 'r1', 'meta' => ['channel' => 'web']]);
        $amna->orders()->create(['status' => 'paid', 'total' => 30, 'card_token' => 'tok_2']);
        $amna->orders()->create(['status' => 'refunded', 'total' => 15]);
        $omar->orders()->create(['status' => 'paid', 'total' => 200, 'notes' => str_repeat('x', 2000)]);
        $omar->orders()->create(['status' => 'pending', 'total' => 9.99]);
        $omar->orders()->create(['status' => 'paid', 'total' => 999])->delete();

        Secret::create(['customer_id' => $amna->id, 'value' => 'do not leak']);
    }

    public function test_the_server_is_registered_as_a_local_server(): void
    {
        $this->assertNotNull(Mcp::getLocalServer('eloquent'));
    }

    public function test_tools_are_read_only(): void
    {
        foreach ([ListModels::class, DescribeModel::class, QueryModel::class, AggregateModel::class] as $tool) {
            $this->assertTrue((new $tool)->toArray()['annotations']['readOnlyHint']);
        }
    }

    public function test_it_lists_exposed_models_without_hidden_or_unlisted_columns(): void
    {
        EloquentServer::tool(ListModels::class)
            ->assertOk()
            ->assertStructuredContentPath('models.0.model', 'Customer')
            ->assertStructuredContentPath('models.0.columns', ['id', 'name', 'email', 'created_at', 'updated_at'])
            ->assertStructuredContentPath('models.0.relations', ['orders', 'secrets'])
            ->assertStructuredContentPath('models.1.columns', ['id', 'customer_id', 'status', 'total', 'meta', 'notes', 'created_at'])
            ->assertDontSee(['password', 'card_token', 'internal_ref']);
    }

    public function test_it_describes_columns_and_relations(): void
    {
        EloquentServer::tool(DescribeModel::class, ['model' => 'Order'])
            ->assertOk()
            ->assertStructuredContentPath('primary_key', 'id')
            ->assertStructuredContentPath('columns.3', ['name' => 'total', 'type' => 'numeric', 'nullable' => false, 'cast' => 'decimal:2'])
            ->assertStructuredContentPath('relations', [['name' => 'customer', 'type' => 'BelongsTo', 'model' => 'Customer']]);
    }

    public function test_unknown_models_are_rejected(): void
    {
        EloquentServer::tool(QueryModel::class, ['model' => 'Secret'])
            ->assertHasErrors(['Unknown model. Available models: Customer, Order.']);
    }

    public function test_it_filters_sorts_and_limits_rows(): void
    {
        EloquentServer::tool(QueryModel::class, [
            'model' => 'Order',
            'columns' => ['id', 'total'],
            'where' => [['column' => 'status', 'operator' => '=', 'value' => 'paid']],
            'order_by' => 'total',
            'direction' => 'desc',
        ])
            ->assertOk()
            ->assertStructuredContentPath('count', 3)
            ->assertStructuredContentPath('has_more', false)
            ->assertStructuredContentPath('rows', [
                ['id' => 4, 'total' => '200.00'],
                ['id' => 1, 'total' => '120.50'],
                ['id' => 2, 'total' => '30.00'],
            ]);
    }

    public function test_the_row_limit_is_capped_by_config(): void
    {
        EloquentServer::tool(QueryModel::class, ['model' => 'Order', 'limit' => 500])
            ->assertStructuredContentPath('count', 3)
            ->assertStructuredContentPath('has_more', true);
    }

    public function test_soft_deleted_rows_stay_hidden(): void
    {
        EloquentServer::tool(QueryModel::class, [
            'model' => 'Order',
            'where' => [['column' => 'total', 'operator' => '>', 'value' => 500]],
        ])->assertStructuredContentPath('count', 0);
    }

    public function test_hidden_columns_cannot_be_selected_or_filtered(): void
    {
        EloquentServer::tool(QueryModel::class, ['model' => 'Order', 'columns' => ['id', 'card_token']])
            ->assertHasErrors(['Unknown column [card_token] on Order.']);

        EloquentServer::tool(QueryModel::class, [
            'model' => 'Order',
            'where' => [['column' => 'internal_ref', 'value' => 'r1']],
        ])->assertHasErrors(['Unknown column [internal_ref] on Order.']);

        EloquentServer::tool(QueryModel::class, ['model' => 'Customer'])
            ->assertOk()
            ->assertDontSee(['password', 'hashed-1']);
    }

    public function test_in_filters_and_long_strings_are_truncated(): void
    {
        $response = EloquentServer::tool(QueryModel::class, [
            'model' => 'Order',
            'columns' => ['id', 'notes'],
            'where' => [['column' => 'id', 'operator' => 'in', 'values' => [4]]],
        ])->assertOk();

        $response->assertStructuredContentPath('rows.0.notes', str_repeat('x', 1000).'...');
    }

    public function test_relations_use_the_related_model_rules(): void
    {
        EloquentServer::tool(QueryModel::class, [
            'model' => 'Order',
            'columns' => ['id'],
            'where' => [['column' => 'id', 'value' => 1]],
            'with' => ['customer'],
        ])
            ->assertOk()
            ->assertStructuredContentPath('rows.0.customer.name', 'Amna')
            ->assertDontSee('hashed-1');
    }

    public function test_relations_to_unexposed_models_are_rejected(): void
    {
        EloquentServer::tool(QueryModel::class, ['model' => 'Customer', 'with' => ['secrets']])
            ->assertHasErrors(['Unknown relation [secrets] on Customer.'])
            ->assertDontSee('do not leak');
    }

    public function test_it_aggregates_with_and_without_grouping(): void
    {
        EloquentServer::tool(AggregateModel::class, ['model' => 'Order', 'function' => 'sum', 'column' => 'total', 'where' => [['column' => 'status', 'value' => 'paid']]])
            ->assertOk()
            ->assertStructuredContentPath('value', 350.5);

        EloquentServer::tool(AggregateModel::class, ['model' => 'Order', 'group_by' => 'status'])
            ->assertOk()
            ->assertStructuredContentPath('groups', [
                ['group' => 'paid', 'value' => 3],
                ['group' => 'pending', 'value' => 1],
                ['group' => 'refunded', 'value' => 1],
            ]);
    }

    public function test_aggregates_reject_hidden_columns(): void
    {
        EloquentServer::tool(AggregateModel::class, ['model' => 'Order', 'function' => 'max', 'column' => 'card_token'])
            ->assertHasErrors(['Unknown column [card_token] on Order.']);
    }
}
