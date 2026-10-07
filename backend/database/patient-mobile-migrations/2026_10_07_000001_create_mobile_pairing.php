<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Intentionally outside database/migrations: normal ERP deployment cannot run this.
return new class extends Migration {
    protected $connection = 'patient_mobile';

    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());
        $schema->create('mobile_pairing_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portal_id')->index();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('lab_id');
            $table->char('phone_digest', 64);
            $table->char('code_digest', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at');
        });
        $schema->create('mobile_patient_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portal_id')->index();
            $table->unsignedBigInteger('patient_id')->index();
            $table->unsignedBigInteger('lab_id');
            $table->char('phone_digest', 64);
            $table->char('token_digest', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->getConnection());
        $schema->dropIfExists('mobile_patient_sessions');
        $schema->dropIfExists('mobile_pairing_codes');
    }
};
