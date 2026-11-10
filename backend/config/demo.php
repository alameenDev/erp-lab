<?php
return [
    'enabled' => (bool) env('DEMO_MODE', false),
    // Required independent database/account. Never infer either from production.
    'database' => env('DEMO_DATABASE'),
    'live_database' => env('DEMO_LIVE_DATABASE'),
    'live_username' => env('DEMO_LIVE_USERNAME'),
    'max_sessions' => (int) env('DEMO_MAX_SESSIONS', 500),
];
