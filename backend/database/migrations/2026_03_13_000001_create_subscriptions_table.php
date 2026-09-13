<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('lab_id_fk')->nullable();
            $table->foreign('lab_id_fk')->references('id')->on('users')->nullOnDelete();
            $table->string('plan_name');
            $table->string('status')->default('trial'); // active, expired, suspended, trial
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('max_users')->nullable();
            $table->integer('max_invoices_per_month')->nullable();
            $table->bigInteger('price')->default(0);
            $table->string('currency')->default('IQD');
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('lab_id_fk');
            $table->index('status');
            $table->index('end_date');
            $table->index(['lab_id_fk', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
