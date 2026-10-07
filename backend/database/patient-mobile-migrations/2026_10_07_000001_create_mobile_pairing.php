<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Intentionally outside database/migrations: normal ERP deployment cannot run this.
return new class extends Migration {
    protected $connection = 'patient_mobile';

    private function assertSeparateDatabase(): void
    {
        $name = (string) config('database.connections.patient_mobile.database');
        $erp = (string) config('database.connections.'.config('database.default').'.database');
        if ($name === '' || $name === $erp) {
            throw new \RuntimeException('Pairing requires a separately named database; refusing to modify ERP.');
        }
    }

    public function up(): void
    {
        $this->assertSeparateDatabase();
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
        $this->assertSeparateDatabase();
        $schema = Schema::connection($this->getConnection());
        $schema->dropIfExists('mobile_patient_sessions');
        $schema->dropIfExists('mobile_pairing_codes');
    }
};
