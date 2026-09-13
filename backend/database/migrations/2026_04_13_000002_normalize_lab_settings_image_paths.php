<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Strip any "http(s)://.../storage/" prefix from stored values,
        // leaving only the relative path (e.g. "logos/abc.png").
        DB::statement(<<<'SQL'
            UPDATE lab_settings
            SET logo = regexp_replace(logo, '^https?://[^/]+/storage/', '')
            WHERE logo IS NOT NULL AND logo ~ '^https?://'
        SQL);

        DB::statement(<<<'SQL'
            UPDATE lab_settings
            SET report_background = regexp_replace(report_background, '^https?://[^/]+/storage/', '')
            WHERE report_background IS NOT NULL AND report_background ~ '^https?://'
        SQL);
    }

    public function down(): void
    {
        // No-op — we can't know the original host
    }
};
