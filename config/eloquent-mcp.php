<?php

return [

    'handle' => env('ELOQUENT_MCP_HANDLE', 'eloquent'),

    'enabled' => env('ELOQUENT_MCP_ENABLED', true),

    'connection' => env('ELOQUENT_MCP_CONNECTION'),

    'max_rows' => (int) env('ELOQUENT_MCP_MAX_ROWS', 50),

    'max_string_length' => (int) env('ELOQUENT_MCP_MAX_STRING_LENGTH', 1000),

    'models' => [],

];
