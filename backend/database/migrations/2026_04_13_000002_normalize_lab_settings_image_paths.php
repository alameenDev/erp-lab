<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Strip any "http(s)://.../storage/" prefix from stored values,
        // leaving only the relative path (e.g. "logos/abc.png").
        DB::table('lab_settings')->orderBy('id')->chunkById(500, function ($settings) {
            foreach ($settings as $setting) {
                $changes = [];
                foreach (['logo', 'report_background'] as $column) {
                    if ($setting->$column !== null) {
                        $path = preg_replace('#^https?://[^/]+/storage/#', '', $setting->$column);
                        if ($path !== $setting->$column) {
                            $changes[$column] = $path;
                        }
                    }
                }
                if ($changes) {
                    DB::table('lab_settings')->where('id', $setting->id)->update($changes);
                }
            }
        });
    }

    public function down(): void
    {
        // No-op — we can't know the original host
    }
};
