<?php
use Illuminate\Http\Request;
define('LARAVEL_START', microtime(true));
$backend = '/var/www/backend';
if (file_exists($maintenance = $backend.'/storage/framework/maintenance.php')) {
    require $maintenance;
}
require $backend.'/vendor/autoload.php';
$app = require $backend.'/bootstrap/app.php';
$app->usePublicPath('/var/www/html');
$app->handleRequest(Request::capture());
