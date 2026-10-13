<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['lab_settings', 'referral_print_settings'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedTinyInteger('previous_results_count')->default(1);
            });
        }
    }

    public function down(): void
    {
        foreach (['lab_settings', 'referral_print_settings'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('previous_results_count'));
        }
    }
};
