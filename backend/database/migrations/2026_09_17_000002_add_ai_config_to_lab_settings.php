<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_settings', function (Blueprint $table) {
            // Encrypted at rest (LabSetting casts this as encrypted:array),
            // since it holds an API key. Holds:
            // { enabled, base_url, api_key, model, promo_code_note }
            $table->text('ai_config')->nullable()->after('printer_config');
        });
    }

    public function down(): void
    {
        Schema::table('lab_settings', function (Blueprint $table) {
            $table->dropColumn('ai_config');
        });
    }
};
