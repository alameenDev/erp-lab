<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_test_rels', function (Blueprint $table) {
            $table->string('result_status_text', 255)->nullable()->after('result_status_id_fk');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_test_rels', function (Blueprint $table) {
            $table->dropColumn('result_status_text');
        });
    }
};
