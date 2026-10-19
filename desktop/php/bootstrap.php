<?php
// The desktop launcher supplies these paths; never load a hosted .env.
define('LARAVEL_START', microtime(true));
$backend = getenv('DL_BACKEND');
$data = getenv('DL_DATA');
if (!$backend || !$data || getenv('DESKTOP_MODE') !== 'true') {
    http_response_code(503);
    exit('Desktop runtime is not initialized.');
}
require $backend.'/vendor/autoload.php';
$app = require $backend.'/bootstrap/app.php';
$app->useEnvironmentPath($data.'/environment');
$app->useStoragePath($data.'/storage');
$app->booted(function () {
    \Illuminate\Support\Facades\URL::forceScheme('http');
});
return $app;
