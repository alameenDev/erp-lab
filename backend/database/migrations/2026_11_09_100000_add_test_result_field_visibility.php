<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->boolean('show_unit')->default(true);
            $table->boolean('show_reference_range')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('tests', fn (Blueprint $table) => $table->dropColumn(['show_unit', 'show_reference_range']));
    }
};
