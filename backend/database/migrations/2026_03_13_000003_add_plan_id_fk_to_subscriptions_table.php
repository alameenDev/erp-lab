<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id_fk')->nullable()->after('lab_id_fk');
            $table->foreign('plan_id_fk')->references('id')->on('plans')->nullOnDelete();
            $table->index('plan_id_fk');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['plan_id_fk']);
            $table->dropColumn('plan_id_fk');
        });
    }
};
