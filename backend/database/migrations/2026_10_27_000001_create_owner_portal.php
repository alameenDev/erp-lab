<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('owner_portal_only')->default(false)->index());
        Schema::create('owner_labs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('lab_id')->constrained('users')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['owner_id', 'lab_id']);
        });
        Schema::create('invoice_change_archive', function (Blueprint $t) {
            $t->id();
            // Deliberately no FK to invoices/users/activity_log: deletion must not erase evidence.
            $t->unsignedBigInteger('activity_id')->unique();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->unsignedBigInteger('invoice_id')->index();
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('actor_name')->nullable();
            $t->string('actor_role')->nullable();
            $t->string('patient_name')->nullable();
            $t->string('barcode')->nullable();
            $t->string('event', 20);
            $t->string('source', 30);
            $t->boolean('financial_changed')->default(false);
            $t->boolean('results_changed')->default(false);
            $t->decimal('before_total', 18, 2)->nullable();
            $t->decimal('after_total', 18, 2)->nullable();
            $t->decimal('total_delta', 18, 2)->nullable();
            $t->decimal('paid_delta', 18, 2)->nullable();
            $t->json('snapshot');
            $t->dateTime('occurred_at');
            $t->index(['tenant_id', 'occurred_at']);
        });
        // Retain existing recorded edits/deletions as well as future operations.
        \Spatie\Activitylog\Models\Activity::where('subject_type', \App\Models\Invoice::class)
            ->where(fn ($q) => $q->whereIn('event', ['updated', 'deleted'])->orWhereIn('log_name', ['تعديل', 'حذف']))
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) app(\App\Services\InvoiceChangeArchive::class)->capture($row);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_change_archive');
        Schema::dropIfExists('owner_labs');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('owner_portal_only'));
    }
};
