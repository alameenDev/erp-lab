<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Change default from true to false
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('public_with_background')->default(false)->change();
        });

        // Update all existing invoices to false (pre-feature default)
        DB::table('invoices')->update(['public_with_background' => false]);
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('public_with_background')->default(true)->change();
        });
    }
};
