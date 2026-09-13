<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_settings', function (Blueprint $table) {
            $table->jsonb('print_margins')->nullable()->after('tagline'); // {top, bottom, left, right}
            $table->boolean('show_categories')->default(true)->after('print_margins');
            $table->boolean('show_tests_on_barcode')->default(true)->after('show_categories');
            $table->boolean('show_test_names')->default(true)->after('show_tests_on_barcode');
            $table->string('report_background')->nullable()->after('show_test_names');
        });
    }

    public function down(): void
    {
        Schema::table('lab_settings', function (Blueprint $table) {
            $table->dropColumn(['print_margins', 'show_categories', 'show_tests_on_barcode', 'show_test_names', 'report_background']);
        });
    }
};
