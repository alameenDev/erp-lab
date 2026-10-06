<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('portal_lab_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lab_id_fk')->unique();
            $table->boolean('enabled')->default(true);
            $table->boolean('results_enabled')->default(true);
            $table->boolean('announcements_enabled')->default(true);
            $table->string('result_title', 120);
            $table->string('result_body', 500);
            $table->timestamps();
        });
        Schema::create('portal_notification_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('lab_id_fk');
            $table->unsignedBigInteger('actor_id');
            $table->string('actor_name');
            $table->uuid('request_id');
            $table->char('payload_hash', 64);
            $table->string('kind', 20);
            $table->string('audience', 20);
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->string('title', 120);
            $table->string('body', 500);
            $table->string('status', 20)->default('queued');
            $table->unsignedBigInteger('last_patient_id')->default(0);
            $table->unsignedBigInteger('max_patient_id')->default(0);
            $table->unsignedInteger('audience_count')->default(0);
            $table->unsignedInteger('queued_patients')->default(0);
            $table->timestamps();
            $table->unique(['lab_id_fk', 'request_id'], 'portal_campaign_request');
            $table->index(['status', 'created_at']);
        });
        Schema::table('portal_notifications', function (Blueprint $table) {
            $table->uuid('campaign_id')->nullable()->index();
            $table->timestamp('expires_at')->nullable();
        });
        Schema::table('portal_push_deliveries', function (Blueprint $table) {
            $table->string('status_reason', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('portal_push_deliveries', fn (Blueprint $table) => $table->dropColumn('status_reason'));
        Schema::table('portal_notifications', function (Blueprint $table) {
            $table->dropIndex(['campaign_id']);
            $table->dropColumn(['campaign_id', 'expires_at']);
        });
        Schema::dropIfExists('portal_notification_campaigns');
        Schema::dropIfExists('portal_lab_notification_settings');
    }
};
