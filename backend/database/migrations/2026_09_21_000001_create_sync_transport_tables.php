<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lab_sync_peers', function (Blueprint $t) {
            $t->id();
            // Exactly one counterpart per lab in the initial two-node protocol.
            $t->foreignId('lab_id')->unique()->constrained('users')->restrictOnDelete();
            $t->uuid('lab_uuid')->unique();
            $t->uuid('local_node_uuid');
            $t->uuid('remote_node_uuid');
            $t->string('token_hash', 64)->unique();
            $t->text('outbound_token')->nullable(); // encrypted with APP_KEY
            $t->string('remote_url')->nullable();
            $t->boolean('enabled')->default(false);
            $t->unsignedBigInteger('pull_cursor')->default(0);
            $t->timestamp('last_contact_at')->nullable();
            $t->string('last_error', 80)->nullable(); // category only, never exception body
            $t->timestamps();
        });
        Schema::create('lab_sync_heads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('peer_id')->constrained('lab_sync_peers')->restrictOnDelete();
            $t->string('kind', 32);
            $t->uuid('entity_uuid');
            $t->uuid('event_uuid')->nullable();
            $t->unique(['peer_id', 'kind', 'entity_uuid'], 'sync_head_entity');
        });
        Schema::create('lab_sync_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('peer_id')->constrained('lab_sync_peers')->restrictOnDelete();
            $t->uuid('event_uuid');
            $t->uuid('origin_node_uuid');
            $t->string('kind', 32);
            $t->uuid('entity_uuid');
            $t->uuid('base_event_uuid')->nullable();
            $t->uuid('observed_head_uuid')->nullable();
            $t->string('envelope_hash', 64);
            $t->longText('envelope'); // encrypted, includes original author and authored time
            $t->string('state', 24); // staged or conflict; NEVER means applied clinically
            $t->boolean('outbound');
            $t->timestamp('delivered_at')->nullable();
            $t->string('remote_state', 24)->nullable();
            $t->timestamps();
            $t->unique(['peer_id', 'event_uuid']);
            $t->index(['peer_id', 'outbound', 'delivered_at'], 'sync_pending');
            $t->index(['peer_id', 'kind', 'entity_uuid'], 'sync_history');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_sync_events');
        Schema::dropIfExists('lab_sync_heads');
        Schema::dropIfExists('lab_sync_peers');
    }
};
