<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tests', fn (Blueprint $table) => $table->string('default_result')->nullable());
    }

    public function down(): void
    {
        Schema::table('tests', fn (Blueprint $table) => $table->dropColumn('default_result'));
    }
};
