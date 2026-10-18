<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('work_register_entries', function (Blueprint $table) {
            // Do not inherit legacy price/paid: those values came from invoices.
            // Keep the old columns intact, but only explicit manual amounts are used.
            $table->unsignedBigInteger('work_register_amount')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('work_register_entries', fn (Blueprint $table) => $table->dropColumn('work_register_amount'));
    }
};
