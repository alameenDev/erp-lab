<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('work_register_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id_fk')->constrained('users')->cascadeOnDelete();
            $table->date('work_date');
            $table->unsignedBigInteger('version')->default(0);
            $table->unique(['lab_id_fk', 'work_date']);
        });
        Schema::create('work_register_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('day_id')->constrained('work_register_days')->cascadeOnDelete();
            $table->foreignId('lab_id_fk')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_invoice_id')->nullable()->unique()->constrained('invoices')->nullOnDelete();
            $table->unsignedInteger('position');
            $table->string('patient_name');
            $table->unsignedInteger('age')->nullable();
            $table->string('age_unit', 30)->default('Years');
            $table->string('referral_doctor')->nullable();
            $table->string('referral_lab')->nullable();
            $table->json('tests');
            $table->json('groups');
            $table->json('packages');
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedBigInteger('paid')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['day_id', 'deleted_at', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_register_entries');
        Schema::dropIfExists('work_register_days');
    }
};
