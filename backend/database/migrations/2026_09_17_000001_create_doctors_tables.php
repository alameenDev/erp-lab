<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id_fk')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('specialty')->nullable();
            $table->text('description')->nullable();
            $table->string('photo')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            // Freeform social/contact links, e.g. {"website":"...", "facebook":"...", "instagram":"..."}
            $table->json('links')->nullable();
            $table->boolean('bookable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['lab_id_fk', 'is_active']);
        });

        Schema::create('doctor_booking_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id_fk')->constrained('doctors')->onDelete('cascade');
            $table->foreignId('lab_id_fk')->constrained('users')->onDelete('cascade');
            $table->foreignId('patient_id_fk')->nullable()->constrained('patients')->onDelete('set null');
            // Snapshot contact details at request time, so the request still
            // makes sense even if the patient record later changes.
            $table->string('patient_name')->nullable();
            $table->string('phone')->nullable();
            $table->timestamp('preferred_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending'); // pending | confirmed | cancelled
            $table->timestamps();

            $table->index(['lab_id_fk', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_booking_requests');
        Schema::dropIfExists('doctors');
    }
};
