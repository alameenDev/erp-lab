<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id_fk')->constrained('users')->onDelete('cascade');
            $table->string('code', 50);
            $table->string('label')->nullable();
            $table->string('discount_type', 20); // percentage | fixed
            $table->decimal('discount_value', 10, 2);
            $table->unsignedInteger('max_uses')->nullable(); // null = unlimited total uses
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('max_uses_per_patient')->nullable()->default(1); // null = unlimited per patient
            $table->bigInteger('min_invoice_amount')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('batch_label')->nullable(); // groups codes created together via bulk-generate
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['lab_id_fk', 'code']);
            $table->index(['lab_id_fk', 'batch_label']);
        });

        Schema::create('promo_code_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_code_id_fk')->constrained('promo_codes')->onDelete('cascade');
            $table->foreignId('invoice_id_fk')->constrained('invoices')->onDelete('cascade');
            $table->foreignId('patient_id_fk')->nullable()->constrained('patients')->onDelete('set null');
            $table->bigInteger('discount_amount');
            $table->timestamps();

            $table->unique(['promo_code_id_fk', 'invoice_id_fk']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('promo_code_id_fk')->nullable()->after('discount_type_id_fk')
                ->constrained('promo_codes')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_code_id_fk');
        });

        Schema::dropIfExists('promo_code_redemptions');
        Schema::dropIfExists('promo_codes');
    }
};
