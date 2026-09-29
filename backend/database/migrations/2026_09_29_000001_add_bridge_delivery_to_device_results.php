<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_results', function (Blueprint $table) {
            $table->string('delivery_id', 64)->nullable();
            $table->string('delivery_hash', 64)->nullable();
            $table->json('instrument_metadata')->nullable();
            $table->unique(['device_id_fk', 'delivery_id'], 'device_result_delivery_unique');
        });
    }

    public function down(): void
    {
        Schema::table('device_results', function (Blueprint $table) {
            $table->dropUnique('device_result_delivery_unique');
            $table->dropColumn(['delivery_id', 'delivery_hash', 'instrument_metadata']);
        });
    }
};
