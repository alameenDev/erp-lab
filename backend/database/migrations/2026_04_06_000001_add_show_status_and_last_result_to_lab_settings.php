<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_settings', function (Blueprint $table) {
            $table->boolean('show_status')->default(true)->after('show_test_names');
            $table->boolean('show_last_result')->default(false)->after('show_status');
        });
    }

    public function down(): void
    {
        Schema::table('lab_settings', function (Blueprint $table) {
            $table->dropColumn(['show_status', 'show_last_result']);
        });
    }
};
