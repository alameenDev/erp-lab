<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->unsignedBigInteger('audit_tenant_id')->nullable()->index();
            $table->unsignedBigInteger('audit_invoice_id')->nullable()->index();
            $table->uuid('audit_request_id')->nullable()->index();
            $table->string('audit_source', 20)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropColumn(['audit_tenant_id', 'audit_invoice_id', 'audit_request_id', 'audit_source']);
        });
    }
};
