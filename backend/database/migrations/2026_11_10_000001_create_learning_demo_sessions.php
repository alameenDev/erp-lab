<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_demo_sessions', function (Blueprint $table) {
            $table->id(); $table->char('secret_hash', 64)->unique();
            $table->json('identities'); $table->json('examples');
            $table->timestamp('expires_at')->index(); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('learning_demo_sessions'); }
};
