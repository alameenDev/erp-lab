<?php
$app = require __DIR__.'/bootstrap.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode(['installed' => \App\Models\User::where('role_id', 1)->exists(),
    'database' => \Illuminate\Support\Facades\DB::connection()->getDatabaseName()]);
