<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_settings', function (Blueprint $table) {
            // Existing labs retain their current patient-portal visibility.
            $table->boolean('show_patient_portal_invoice_amounts')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('lab_settings', function (Blueprint $table) {
            $table->dropColumn('show_patient_portal_invoice_amounts');
        });
    }
};
