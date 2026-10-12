<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lab_banners', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->uuid('request_id');
            $table->char('payload_hash', 64);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('image_path');
            $table->string('mobile_image_path')->nullable();
            $table->string('button_label', 80)->nullable();
            $table->text('button_url')->nullable();
            $table->string('audience', 16)->default('all');
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['created_by', 'request_id']);
            $table->index(['enabled', 'deleted_at', 'sort_order'], 'lab_banners_active_order');
        });
        Schema::create('lab_banner_targets', function (Blueprint $table) {
            $table->foreignId('banner_id')->constrained('lab_banners')->cascadeOnDelete();
            $table->unsignedBigInteger('lab_id');
            $table->primary(['banner_id', 'lab_id']);
            $table->index(['lab_id', 'banner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_banner_targets');
        Schema::dropIfExists('lab_banners');
    }
};
