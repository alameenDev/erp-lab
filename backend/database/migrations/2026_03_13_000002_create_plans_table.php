<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar');
            $table->bigInteger('monthly_price')->default(0);
            $table->bigInteger('yearly_price')->default(0);
            $table->string('currency', 10)->default('IQD');
            $table->integer('max_users')->nullable();
            $table->integer('max_invoices_per_month')->nullable();
            $table->jsonb('features')->nullable();
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_custom')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
