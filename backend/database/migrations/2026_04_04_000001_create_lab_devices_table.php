<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lab_id_fk');
            $table->string('name');
            $table->string('device_type')->nullable(); // hematology, chemistry, immunoassay, urinalysis, coagulation
            $table->string('serial_number')->nullable();
            $table->string('connection_type')->default('serial'); // serial, tcp
            $table->jsonb('connection_config')->nullable(); // {com_port, baud_rate, ip, port, data_bits, stop_bits, parity}
            $table->string('api_token', 64)->unique();
            $table->string('status')->default('offline'); // online, offline, error
            $table->timestamp('last_seen_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('lab_id_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->index('lab_id_fk');
            $table->index('status');
            $table->index(['lab_id_fk', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_devices');
    }
};
