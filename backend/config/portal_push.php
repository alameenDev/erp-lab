<?php

return [
    'enabled' => env('PORTAL_PUSH_ENABLED', true),
    'subject' => env('PORTAL_PUSH_SUBJECT', env('FRONTEND_URL', env('APP_URL'))),
    'key_file' => storage_path('app/private/portal-push-vapid.json'),
];
