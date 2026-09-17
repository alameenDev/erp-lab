<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============================================
        // Loyalty summary fields cached on the patient
        // (real ledger lives in loyalty_transactions; these
        // columns are a fast-read cache kept in sync by
        // LoyaltyService so lists don't need to sum the ledger).
        // ============================================
        Schema::table('patients', function (Blueprint $table) {
            $table->integer('loyalty_points')->default(0)->after('barcode');
            $table->string('loyalty_tier', 20)->default('silver')->after('loyalty_points');
            $table->integer('loyalty_year_points')->default(0)->after('loyalty_tier');
            $table->timestamp('loyalty_joined_at')->nullable()->after('loyalty_year_points');
            $table->foreignId('referred_by_patient_id')->nullable()->after('loyalty_joined_at')
                ->constrained('patients')->onDelete('set null');
        });

        // ============================================
        // Loyalty transactions ledger (source of truth)
        // ============================================
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id_fk')->constrained('patients')->onDelete('cascade');
            $table->foreignId('lab_id_fk')->constrained('users')->onDelete('cascade');
            $table->string('type', 30); // welcome, purchase, referral, review, checkup, redemption, manual, expiry
            $table->integer('points'); // positive = earn, negative = redeem/expire
            $table->string('description')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('expires_at')->nullable(); // only set on positive (earn) entries
            $table->timestamps();

            $table->index(['patient_id_fk', 'created_at']);
            $table->index(['patient_id_fk', 'expires_at']);
        });

        // ============================================
        // Magic-link portal access tokens
        // ============================================
        Schema::create('portal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id_fk')->constrained('patients')->onDelete('cascade');
            $table->string('token', 64)->unique();
            $table->string('otp_code')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();
        });

        // ============================================
        // Per-lab loyalty program configuration
        // (rates, tiers, redemption catalog - editable from Lab Settings)
        // ============================================
        Schema::table('lab_settings', function (Blueprint $table) {
            $table->json('loyalty_config')->nullable()->after('document_config');
        });
    }

    public function down(): void
    {
        Schema::table('lab_settings', function (Blueprint $table) {
            $table->dropColumn('loyalty_config');
        });

        Schema::dropIfExists('portal_access_tokens');
        Schema::dropIfExists('loyalty_transactions');

        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_patient_id');
            $table->dropColumn(['loyalty_points', 'loyalty_tier', 'loyalty_year_points', 'loyalty_joined_at']);
        });
    }
};
