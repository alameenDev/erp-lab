<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_map_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->uuid('request_id');
            $table->char('payload_hash', 64);
            $table->string('name', 180);
            $table->string('address', 500);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 11, 7);
            $table->string('logo_path');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['created_by', 'request_id']);
            $table->index(['enabled', 'deleted_at', 'sort_order'], 'client_map_active_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_map_locations');
    }
};
