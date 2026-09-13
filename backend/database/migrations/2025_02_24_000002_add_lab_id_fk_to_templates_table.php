<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('templates', 'lab_id_fk')) {
            Schema::table('templates', function (Blueprint $table) {
                $table->unsignedBigInteger('lab_id_fk')->nullable()->after('test_group_id_fk');
                $table->foreign('lab_id_fk')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('templates', 'lab_id_fk')) {
            Schema::table('templates', function (Blueprint $table) {
                $table->dropForeign(['lab_id_fk']);
                $table->dropColumn('lab_id_fk');
            });
        }
    }
};
