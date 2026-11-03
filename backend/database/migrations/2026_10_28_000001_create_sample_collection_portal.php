<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('collector_portal_only')->default(false)->index());
        Schema::create('collector_accounts', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id')->primary(); $t->unsignedBigInteger('lab_id')->index(); $t->timestamps();
        });
        Schema::create('collector_lab_assignments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('lab_id'); $t->unsignedBigInteger('source_lab_id'); $t->unsignedBigInteger('collector_id')->index();
            $t->timestamps(); $t->unique(['lab_id', 'source_lab_id']);
        });
        Schema::create('collection_submissions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('lab_id'); $t->unsignedBigInteger('collector_id'); $t->unsignedBigInteger('source_lab_id')->nullable();
            $t->uuid('request_uuid'); $t->string('request_hash', 64); $t->string('status', 20)->default('pending');
            $t->json('payload'); $t->string('image_path')->nullable(); $t->unsignedBigInteger('invoice_id')->nullable()->unique();
            $t->unsignedBigInteger('reviewer_id')->nullable(); $t->timestamp('reviewed_at')->nullable(); $t->text('review_note')->nullable();
            $t->timestamps(); $t->unique(['collector_id', 'request_uuid']); $t->index(['lab_id', 'status', 'created_at']);
        });
        Schema::create('collection_tasks', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('lab_id'); $t->unsignedBigInteger('source_lab_id')->nullable();
            $t->unsignedBigInteger('invoice_id')->unique(); $t->unsignedBigInteger('collector_id')->nullable()->index();
            $t->string('status', 20)->default('requested'); $t->unsignedInteger('version')->default(1);
            $t->unsignedInteger('sample_count')->nullable(); $t->json('snapshot')->nullable();
            $t->unsignedBigInteger('collected_by')->nullable()->index(); $t->unsignedBigInteger('delivered_by')->nullable()->index();
            $t->timestamp('collected_at')->nullable(); $t->timestamp('delivered_at')->nullable(); $t->timestamp('cancelled_at')->nullable();
            $t->text('note')->nullable(); $t->timestamps(); $t->index(['lab_id', 'status', 'created_at']);
        });
        Schema::create('collection_events', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('lab_id')->index(); $t->unsignedBigInteger('task_id')->nullable()->index();
            $t->unsignedBigInteger('submission_id')->nullable()->index(); $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('actor_name')->nullable(); $t->string('action', 40); $t->json('data'); $t->timestamp('created_at');
        });
        Schema::create('collector_notifications', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->unsignedBigInteger('collector_id'); $t->unsignedBigInteger('lab_id');
            $t->unsignedBigInteger('task_id')->nullable(); $t->unsignedBigInteger('submission_id')->nullable();
            $t->string('event_key', 64)->unique(); $t->string('title', 180); $t->text('body');
            $t->timestamp('read_at')->nullable(); $t->timestamp('expires_at'); $t->timestamps(); $t->index(['collector_id','created_at']);
        });
        Schema::create('collector_push_subscriptions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('collector_id')->index(); $t->unsignedBigInteger('auth_token_id')->nullable();
            $t->string('endpoint_hash',64)->unique(); $t->text('subscription'); $t->timestamps();
        });
        Schema::create('collector_push_deliveries', function (Blueprint $t) {
            $t->id(); $t->uuid('notification_id'); $t->unsignedBigInteger('subscription_id');
            $t->string('status',20)->default('pending'); $t->unsignedTinyInteger('attempts')->default(0); $t->uuid('claim')->nullable();
            $t->timestamp('next_attempt_at')->nullable(); $t->timestamps(); $t->unique(['notification_id','subscription_id'],'collector_delivery_unique');
            $t->index(['status','next_attempt_at']);
            $t->foreign('notification_id')->references('id')->on('collector_notifications')->cascadeOnDelete();
            $t->foreign('subscription_id')->references('id')->on('collector_push_subscriptions')->cascadeOnDelete();
        });
        foreach (['sample logistics view','sample logistics manage','sample logistics review'] as $name) {
            $permission = \Spatie\Permission\Models\Permission::findOrCreate($name, 'api');
            foreach (\Spatie\Permission\Models\Role::whereIn('id', [1,2])->where('guard_name','api')->get() as $role) $role->givePermissionTo($permission);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
    public function down(): void
    {
        foreach (['collector_push_deliveries','collector_push_subscriptions','collector_notifications','collection_events','collection_tasks','collection_submissions','collector_lab_assignments','collector_accounts'] as $table) Schema::dropIfExists($table);
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('collector_portal_only'));
    }
};
