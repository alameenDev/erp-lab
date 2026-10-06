<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('report_printed_at')->nullable();
            $table->timestamp('report_saved_at')->nullable();
            $table->timestamp('report_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['report_printed_at', 'report_saved_at', 'report_sent_at']);
        });
    }
};
