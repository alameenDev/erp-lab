<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['test_package_rel', 'culture_package_rel', 'test_group_package_rel'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                // Legacy rows retain their insertion order via the pivot ID tie-breaker.
                $table->unsignedInteger('position')->default(0);
            });
        }

        Schema::table('cultures', function (Blueprint $table) {
            $table->unsignedInteger('test_group_order')->default(0);
        });
    }

    public function down(): void
    {
        foreach (['test_package_rel', 'culture_package_rel', 'test_group_package_rel'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('position'));
        }
        Schema::table('cultures', fn (Blueprint $table) => $table->dropColumn('test_group_order'));
    }
};
