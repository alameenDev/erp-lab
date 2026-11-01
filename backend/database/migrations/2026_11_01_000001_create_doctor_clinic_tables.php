<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('doctor_clinic_patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_user_id_fk')->constrained('users')->cascadeOnDelete();
            $table->string('name', 255);
            $table->unsignedSmallInteger('age_value');
            $table->string('age_unit', 10)->default('years');
            $table->string('phone', 24);
            $table->string('gender', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['doctor_user_id_fk', 'name']);
        });

        Schema::create('doctor_clinic_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_user_id_fk')->constrained('users')->cascadeOnDelete();
            $table->foreignId('clinic_patient_id_fk')->constrained('doctor_clinic_patients')->cascadeOnDelete();
            $table->date('visit_date');
            $table->time('appointment_time')->nullable();
            $table->unsignedInteger('queue_number');
            $table->string('status', 24)->default('waiting');
            $table->unsignedSmallInteger('age_at_visit');
            $table->string('age_unit_at_visit', 10)->default('years');
            $table->text('chief_complaint')->nullable();
            $table->json('symptoms')->nullable();
            $table->string('symptom_duration', 255)->nullable();
            $table->text('history_of_present_illness')->nullable();
            $table->text('review_of_systems')->nullable();
            $table->text('medical_history')->nullable();
            $table->text('surgical_history')->nullable();
            $table->text('family_history')->nullable();
            $table->text('allergies')->nullable();
            $table->text('current_medications')->nullable();
            $table->json('vitals')->nullable();
            $table->text('physical_examination')->nullable();
            $table->text('assessment')->nullable();
            $table->string('diagnosis_code', 64)->nullable();
            $table->text('plan')->nullable();
            $table->json('lab_requests')->nullable();
            $table->json('imaging_requests')->nullable();
            $table->json('prescriptions')->nullable();
            $table->text('patient_instructions')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['doctor_user_id_fk', 'visit_date', 'queue_number'], 'doctor_visit_day_queue_unique');
            $table->index(['doctor_user_id_fk', 'visit_date', 'status'], 'doctor_visit_day_status');
            $table->index(['clinic_patient_id_fk', 'visit_date']);
        });

        Schema::create('doctor_clinic_visit_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id_fk')->constrained('doctor_clinic_visits')->cascadeOnDelete();
            $table->foreignId('doctor_user_id_fk')->constrained('users')->cascadeOnDelete();
            $table->json('previous_values');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_clinic_visit_revisions');
        Schema::dropIfExists('doctor_clinic_visits');
        Schema::dropIfExists('doctor_clinic_patients');
    }
};
