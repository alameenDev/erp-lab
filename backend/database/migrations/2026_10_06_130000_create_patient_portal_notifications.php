<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('portal_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('lab_id');
            $table->unsignedBigInteger('portal_access_token_id');
            $table->char('endpoint_hash', 64);
            $table->text('subscription'); // encrypted endpoint and browser keys
            $table->boolean('results_enabled')->default(true);
            $table->boolean('offers_enabled')->default(false);
            $table->timestamps();
            $table->unique(['patient_id', 'endpoint_hash'], 'portal_push_patient_endpoint');
            $table->index(['lab_id', 'patient_id']);
        });
        Schema::create('portal_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('lab_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->char('event_key', 64)->unique();
            $table->string('kind', 20);
            $table->string('title', 120);
            $table->string('body', 500);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['patient_id', 'lab_id', 'created_at'], 'portal_notice_patient_date');
        });
        Schema::create('portal_push_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('notification_id');
            $table->unsignedBigInteger('subscription_id');
            $table->string('status', 20)->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamps();
            $table->unique(['notification_id', 'subscription_id'], 'portal_delivery_once');
            $table->index(['status', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_push_deliveries');
        Schema::dropIfExists('portal_notifications');
        Schema::dropIfExists('portal_push_subscriptions');
    }
};
