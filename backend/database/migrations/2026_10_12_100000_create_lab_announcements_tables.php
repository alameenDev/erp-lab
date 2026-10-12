<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users');
            $table->uuid('request_id');
            $table->char('payload_hash', 64);
            $table->string('title', 200);
            $table->text('body');
            $table->string('level', 20)->default('info');
            $table->string('audience', 20);
            $table->string('button_label', 80)->nullable();
            $table->text('button_url')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['sender_id', 'request_id'], 'lab_announcements_request_unique');
        });
        Schema::create('lab_announcement_targets', function (Blueprint $table) {
            $table->foreignId('announcement_id')->constrained('lab_announcements')->cascadeOnDelete();
            // Tenant IDs in this project are the lab owner's users.id, not labs.id.
            $table->foreignId('lab_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['announcement_id', 'lab_id']);
            $table->index(['lab_id', 'announcement_id']);
        });
        Schema::create('lab_announcement_reads', function (Blueprint $table) {
            $table->foreignId('announcement_id')->constrained('lab_announcements')->cascadeOnDelete();
            $table->foreignId('lab_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->primary(['announcement_id', 'lab_id', 'user_id'], 'lab_announcement_reads_pk');
            $table->index(['user_id', 'lab_id', 'announcement_id'], 'lab_announcement_reads_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_announcement_reads');
        Schema::dropIfExists('lab_announcement_targets');
        Schema::dropIfExists('lab_announcements');
    }
};
