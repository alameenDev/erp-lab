<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('patient_ai_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('lab_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('consent_version', 60);
            $table->timestamp('consented_at');
            $table->char('context_hash', 64);
            $table->longText('scope'); // encrypted analysis names and patient-provided concern
            $table->timestamps();
            $table->index(['lab_id', 'updated_at'], 'patient_ai_lab_date');
            $table->index(['lab_id', 'patient_id'], 'patient_ai_lab_patient');
        });
        Schema::create('patient_ai_turns', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('conversation_id')->constrained('patient_ai_conversations')->cascadeOnDelete();
            $table->uuid('request_id');
            $table->longText('message'); // encrypted, never copied from client-supplied history
            $table->longText('answer')->nullable(); // encrypted response envelope
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempt')->default(1);
            $table->timestamps();
            $table->unique(['conversation_id', 'request_id'], 'patient_ai_request_once');
        });
        DB::table('permissions')->insertOrIgnore(['name' => 'patient ai conversations view', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_ai_turns');
        Schema::dropIfExists('patient_ai_conversations');
        // Retain permission definitions and deliberate grants on rollback.
    }
};
