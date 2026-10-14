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
                $table->boolean('report_form_default')->default(true);
            });
        }
    }

    public function down(): void
    {
        foreach (['lab_settings', 'referral_print_settings'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('report_form_default');
            });
        }
    }
};
