<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Print in black & white instead of the configured colours.
 *
 * Defaults to FALSE (colour) — unlike the show_* flags which default to true —
 * so existing labs keep printing exactly as they do today and the toggle is an
 * explicit opt-in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_settings', function (Blueprint $table) {
            $table->boolean('print_black_white')->default(false)->after('show_last_result');
        });
    }

    public function down(): void
    {
        Schema::table('lab_settings', function (Blueprint $table) {
            $table->dropColumn('print_black_white');
        });
    }
};
