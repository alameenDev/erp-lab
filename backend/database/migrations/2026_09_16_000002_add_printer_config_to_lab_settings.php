<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_settings', function (Blueprint $table) {
            // { invoice: "printer name", thermal: "...", barcode: "...", result: "..." }
            // Consumed client-side via QZ Tray (qz.io) to send each document
            // type straight to its own printer, bypassing the browser's
            // print dialog and its "last used printer" mix-up between types.
            $table->json('printer_config')->nullable()->after('loyalty_config');
        });
    }

    public function down(): void
    {
        Schema::table('lab_settings', function (Blueprint $table) {
            $table->dropColumn('printer_config');
        });
    }
};
