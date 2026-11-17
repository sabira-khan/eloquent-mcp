# Eloquent MCP

[![tests](https://github.com/sabira-khan/eloquent-mcp/actions/workflows/tests.yml/badge.svg)](https://github.com/sabira-khan/eloquent-mcp/actions/workflows/tests.yml)

Give AI agents read-only access to your Laravel data through the Eloquent models you choose.

Eloquent MCP turns selected models into a [Model Context Protocol](https://modelcontextprotocol.io) server built on the official [`laravel/mcp`](https://github.com/laravel/mcp) package. Agents can then answer questions like "how many orders were refunded this week?" or "show me this customer's last five orders" against your real database, without raw SQL and without seeing data you have not exposed.

## Why

Giving an agent database credentials means it can read every table and run any query. Eloquent MCP goes through your models instead:

- **Opt in per model.** Only models listed in the config are visible.
- **Hidden stays hidden.** `$hidden` attributes such as passwords and tokens are never returned, filtered on or aggregated, even if you list them.
- **Column allowlists.** Narrow each model to the columns you want to share.
- **Scopes still apply.** Queries start from `newQuery()`, so soft deletes and other global scopes such as tenancy keep working.
- **Read-only by construction.** Every query is built with the query builder. There is no raw SQL input and no write path, and all tools carry the MCP `readOnlyHint`.
- **Bounded output.** Rows are capped per request, long strings are truncated, and relations only resolve to other exposed models.

## Installation

```bash
composer require sabira-khan/eloquent-mcp
php artisan vendor:publish --tag=eloquent-mcp-config
```

Requires PHP 8.2+ and Laravel 12 or 13.

## Configuration

List the models to expose in `config/eloquent-mcp.php`:

```php
'models' => [
    App\Models\Customer::class => [
        'description' => 'People who have placed at least one order.',
        'relations' => ['orders'],
    ],
    App\Models\Order::class => [
        'description' => 'One row per checkout.',
        'columns' => ['id', 'customer_id', 'status', 'total', 'currency', 'created_at'],
        'relations' => ['customer', 'items'],
    ],
    App\Models\OrderItem::class,
],
```

| Option | Default | Purpose |
|---|---|---|
| `handle` | `eloquent` | Name used with `mcp:start` |
| `enabled` | `true` | Register the local server |
| `connection` | model default | Database connection to read from, e.g. a read replica |
| `max_rows` | `50` | Maximum rows or groups per request |
| `max_string_length` | `1000` | Longer strings are truncated |

Per model, `columns` limits readable columns, `relations` lists relations the agent may load, `description` tells the agent what the model means, and `as` renames the model.

For extra safety, point `connection` at a database user that only has `SELECT` rights.

## Connecting a client

The server runs over stdio through Artisan:

```bash
php artisan mcp:start eloquent
```

Claude Code:

```bash
claude mcp add eloquent -- php artisan mcp:start eloquent
```

Any MCP client that supports stdio servers (Cursor, VS Code, Windsurf) can use the same command. To try it in the browser:

```bash
php artisan mcp:inspector eloquent
```

## Tools

| Tool | What it does |
|---|---|
| `list-models` | Exposed models with their tables, readable columns and relations |
| `describe-model` | Column types, nullability, casts and relations of one model |
| `query-model` | Rows with `where` filters, sorting, a limit and optional relations |
| `aggregate-model` | `count`, `sum`, `avg`, `min` or `max`, optionally grouped by a column |

Filters use `=`, `!=`, `>`, `>=`, `<`, `<=`, `like`, `in`, `not_in`, `is_null` and `not_null`:

```json
{
  "model": "Order",
  "where": [
    { "column": "status", "operator": "in", "values": ["paid", "shipped"] },
    { "column": "created_at", "operator": ">=", "value": "2026-11-01" }
  ],
  "order_by": "total",
  "direction": "desc",
  "limit": 10,
  "with": ["customer"]
}
```

## Testing

```bash
composer install
composer test
composer lint
```

## License

MIT
