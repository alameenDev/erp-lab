<?php
namespace App\Demo;
use Illuminate\Support\Facades\DB;
class DemoIsolation {
    public static function assertConfigured(): void {
        abort_unless(config('demo.enabled'), 404);
        $connection = config('database.default');
        $db = config("database.connections.$connection");
        // DB_URL could override these fields. Disallow it rather than guessing.
        abort_unless($connection === 'mysql' && empty($db['url'])
            && config('demo.database') && config('demo.live_database') && config('demo.live_username')
            && $db['database'] === config('demo.database')
            && $db['database'] !== config('demo.live_database')
            && $db['username'] !== config('demo.live_username'), 503, 'بيئة التدريب غير مهيأة بعد.');
        abort_unless(DB::connection()->getDatabaseName() === config('demo.database'), 503);
    }
}
