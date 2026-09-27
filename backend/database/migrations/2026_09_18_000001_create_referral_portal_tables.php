<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Branding a referral partner (small lab/doctor) uses on their own
        // printed results - independent of the main lab's own lab_settings,
        // since resolveLabOwnerId()-style tenant resolution elsewhere in
        // the app treats a referral account as a member of the main lab's
        // tenant (correct for billing/permissions), but for print branding
        // here we want THEIR identity, not the main lab's.
        Schema::create('referral_lab_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id_fk')->unique()->constrained('users')->onDelete('cascade');
            $table->string('display_name')->nullable();
            $table->string('logo')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            // Set when the referring lab has viewed a completed result in
            // their portal - drives the "new results ready" badge/count.
            $table->timestamp('referral_seen_at')->nullable()->after('from_lab_id_fk');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('referral_seen_at');
        });

        Schema::dropIfExists('referral_lab_profiles');
    }
};
