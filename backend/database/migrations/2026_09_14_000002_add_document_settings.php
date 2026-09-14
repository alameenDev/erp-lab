<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lab_settings', function (Blueprint $table) {
            $table->json('document_config')->nullable();
            $table->text('whatsapp_invoice_message')->nullable();
            $table->text('whatsapp_result_message')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('lab_settings', function (Blueprint $table) {
            $table->dropColumn(['document_config', 'whatsapp_invoice_message', 'whatsapp_result_message']);
        });
    }
};
