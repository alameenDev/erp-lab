<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id_fk');
            $table->unsignedBigInteger('invoice_id_fk')->nullable();
            $table->string('specimen_barcode');
            $table->text('raw_message');
            $table->jsonb('parsed_results')->nullable(); // [{test_code, test_name, value, unit, flags, reference_range}]
            $table->string('status')->default('pending'); // pending, matched, applied, failed
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('device_id_fk')->references('id')->on('lab_devices')->cascadeOnDelete();
            $table->foreign('invoice_id_fk')->references('id')->on('invoices')->nullOnDelete();
            $table->index('device_id_fk');
            $table->index('invoice_id_fk');
            $table->index('specimen_barcode');
            $table->index('status');
            $table->index(['device_id_fk', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_results');
    }
};
