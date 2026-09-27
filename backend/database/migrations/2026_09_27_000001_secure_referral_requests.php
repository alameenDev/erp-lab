<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) { $t->boolean('referral_portal_only')->default(false); });
        Schema::table('invoices', function (Blueprint $t) {
            $t->uuid('referral_request_uuid')->nullable();
            $t->string('referral_request_hash', 64)->nullable();
            $t->unique(['from_lab_id_fk', 'referral_request_uuid'], 'referral_request_unique');
        });
    }
    public function down(): void {
        Schema::table('invoices', function (Blueprint $t) {
            $t->dropUnique('referral_request_unique');
            $t->dropColumn(['referral_request_uuid', 'referral_request_hash']);
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('referral_portal_only'));
    }
};
