<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_groups', function (Blueprint $table) {
            $table->text('formula')->nullable()->after('precautions');
        });
        Schema::table('packages', function (Blueprint $table) {
            $table->text('formula')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('test_groups', function (Blueprint $table) {
            $table->dropColumn('formula');
        });
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('formula');
        });
    }
};
