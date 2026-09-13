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
        DB::statement(<<<'SQL'
            INSERT INTO test_test_group_rel (test_id_fk, test_group_id_fk, "order", created_at, updated_at)
            SELECT id, test_group_id_fk, COALESCE("order", 0), NOW(), NOW()
            FROM tests
            WHERE test_group_id_fk IS NOT NULL
            ON CONFLICT DO NOTHING
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('test_test_group_rel');
    }
};
