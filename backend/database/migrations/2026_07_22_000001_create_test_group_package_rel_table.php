<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Packages can now contain whole test groups (not just individual tests and
 * cultures). Mirrors the existing test_package_rel / culture_package_rel
 * pivots so Package::testGroups() behaves like its siblings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_group_package_rel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_group_id_fk')->references('id')->on('test_groups')->cascadeOnDelete();
            $table->foreignId('package_id_fk')->references('id')->on('packages')->cascadeOnDelete();
            $table->timestamps();

            $table->index('test_group_id_fk', 'tg_package_rel_test_group_id_fk_idx');
            $table->index('package_id_fk', 'tg_package_rel_package_id_fk_idx');
            $table->unique(['test_group_id_fk', 'package_id_fk'], 'tg_package_rel_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_group_package_rel');
    }
};
