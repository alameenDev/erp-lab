<?php
$app = require __DIR__.'/bootstrap.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $payload = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
    $result = (new \App\Services\DesktopInstallation)->install($payload);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (\Illuminate\Validation\ValidationException $e) {
    fwrite(STDERR, json_encode(['errors' => $e->errors()], JSON_UNESCAPED_UNICODE));
    exit(2);
} catch (\Throwable $e) {
    // No credentials or SQL values in setup diagnostics.
    fwrite(STDERR, get_class($e).' at '.basename($e->getFile()).':'.$e->getLine());
    exit(1);
}
