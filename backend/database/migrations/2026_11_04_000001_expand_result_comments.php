<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_test_rels', function (Blueprint $table) {
            $table->longText('comment')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Never truncate a patient's existing comment during a rollback.
        if (DB::table('invoice_test_rels')->whereRaw('CHAR_LENGTH(comment) > 255')->exists()) {
            throw new RuntimeException('Cannot narrow result comments: comments longer than 255 characters exist.');
        }
        Schema::table('invoice_test_rels', function (Blueprint $table) {
            $table->string('comment')->nullable()->change();
        });
    }
};
