<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_test_group_rel', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('test_id_fk');
            $table->unsignedBigInteger('test_group_id_fk');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->foreign('test_id_fk')->references('id')->on('tests')->cascadeOnDelete();
            $table->foreign('test_group_id_fk')->references('id')->on('test_groups')->cascadeOnDelete();
            $table->unique(['test_id_fk', 'test_group_id_fk'], 'test_test_group_unique');
            $table->index('test_group_id_fk');
            $table->index('test_id_fk');
        });

        // Backfill from legacy tests.test_group_id_fk
        DB::table('tests')->whereNotNull('test_group_id_fk')->orderBy('id')
            ->chunkById(500, function ($tests) {
                $rows = $tests->map(fn ($test) => [
                    'test_id_fk' => $test->id,
                    'test_group_id_fk' => $test->test_group_id_fk,
                    'order' => $test->order ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all();
                DB::table('test_test_group_rel')->upsert(
                    $rows, ['test_id_fk', 'test_group_id_fk'], ['order', 'updated_at']
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_test_group_rel');
    }
};
