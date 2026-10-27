<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('owner_notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->unsignedBigInteger('owner_id'); $t->unsignedBigInteger('lab_id')->nullable();
            $t->string('event_key', 64)->unique(); $t->string('kind', 30);
            $t->string('title', 180); $t->text('body'); $t->json('data');
            $t->timestamp('read_at')->nullable(); $t->timestamp('expires_at'); $t->timestamps();
            $t->index(['owner_id', 'created_at']); $t->index(['lab_id', 'kind']);
        });
        Schema::create('owner_push_subscriptions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('owner_id')->index(); $t->unsignedBigInteger('auth_token_id')->nullable()->index();
            $t->string('endpoint_hash', 64)->unique(); $t->text('subscription');
            $t->boolean('invoice_enabled')->default(true); $t->boolean('daily_enabled')->default(true); $t->timestamps();
        });
        Schema::create('owner_push_deliveries', function (Blueprint $t) {
            $t->id(); $t->uuid('notification_id'); $t->unsignedBigInteger('subscription_id');
            $t->string('status', 20)->default('pending'); $t->unsignedTinyInteger('attempts')->default(0);
            $t->uuid('claim')->nullable(); $t->timestamp('next_attempt_at')->nullable(); $t->timestamps();
            $t->unique(['notification_id', 'subscription_id'], 'owner_delivery_unique');
            $t->index(['status', 'next_attempt_at']);
            $t->foreign('notification_id')->references('id')->on('owner_notifications')->cascadeOnDelete();
            $t->foreign('subscription_id')->references('id')->on('owner_push_subscriptions')->cascadeOnDelete();
        });
        Schema::create('owner_digest_settings', function (Blueprint $t) {
            $t->unsignedBigInteger('owner_id')->primary(); $t->date('start_date');
        });
        DB::table('users')->where('owner_portal_only', true)->orderBy('id')->chunkById(200, function ($owners) {
            foreach ($owners as $owner) DB::table('owner_digest_settings')->insert(['owner_id' => $owner->id, 'start_date' => now('Asia/Baghdad')->toDateString()]);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('owner_push_deliveries'); Schema::dropIfExists('owner_push_subscriptions');
        Schema::dropIfExists('owner_notifications'); Schema::dropIfExists('owner_digest_settings');
    }
};
