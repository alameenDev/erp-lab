<?php
$app = require __DIR__.'/bootstrap.php';
$app->handleRequest(\Illuminate\Http\Request::capture());
