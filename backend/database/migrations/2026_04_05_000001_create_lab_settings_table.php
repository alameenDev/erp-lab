<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lab_id_fk')->unique();
            $table->string('logo')->nullable();
            $table->string('primary_color', 20)->default('#0d9488');
            $table->string('secondary_color', 20)->default('#14b8a6');
            $table->string('font_family', 50)->default('Tajawal');
            $table->string('lab_display_name')->nullable();
            $table->string('tagline')->nullable();
            $table->timestamps();

            $table->foreign('lab_id_fk')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_settings');
    }
};
