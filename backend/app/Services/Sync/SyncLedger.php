<?php

namespace App\Services\Sync;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Durable, versioned transport staging. Does NOT write patient, invoice, result,
 * stock or loyalty tables. Domain adapters and baseline pairing must precede rollout.
 */
class SyncLedger
{
    public const KINDS = ['patient', 'invoice', 'result', 'inventory_movement', 'loyalty_movement', 'lab_settings'];

    public function stage(int $peerId, array $event): array
    {
        return $this->record($peerId, $event, true);
    }

    public function receive(int $peerId, array $event): array
    {
        return $this->record($peerId, $event, false);
    }

    private function record(int $peerId, array $event, bool $outbound): array
    {
        abort_unless(config('lab_sync.enabled'), 503, 'Sync transport is disabled.');
        $event = $this->validateEnvelope($event);
        return DB::transaction(function () use ($peerId, $event, $outbound) {
            // Serialize commit order per lab, including first creation and duplicate retries.
            $peer = DB::table('lab_sync_peers')->lockForUpdate()->find($peerId);
            abort_unless($peer && $peer->enabled, 403);
            abort_unless($peer->local_node_uuid !== $peer->remote_node_uuid, 409);
            abort_unless($event['lab_uuid'] === $peer->lab_uuid, 403);
            $expectedNode = $outbound ? $peer->local_node_uuid : $peer->remote_node_uuid;
            abort_unless($event['origin_node_uuid'] === $expectedNode, 403);
            $encoded = json_encode($this->canonical($event), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $hash = hash('sha256', $encoded);
            $existing = DB::table('lab_sync_events')->where('peer_id', $peerId)
                ->where('event_uuid', $event['event_uuid'])->first();
            if ($existing) {
                // A reused ID with different content is not a successful retry.
                abort_unless(hash_equals($existing->envelope_hash, $hash), 409, 'Event identifier reused.');
                return $this->receipt($existing, true);
            }
            $query = DB::table('lab_sync_heads')->where('peer_id', $peerId)
                ->where('kind', $event['kind'])->where('entity_uuid', $event['entity_uuid']);
            $head = $query->first();
            $headUuid = $head?->event_uuid;
            $blocked = DB::table('lab_sync_events')->where('peer_id', $peerId)
                ->where('kind', $event['kind'])->where('entity_uuid', $event['entity_uuid'])
                ->where(function ($q) { $q->where('state', 'conflict')->orWhere('remote_state', 'conflict'); })->exists();
            $state = !$blocked && $headUuid === $event['base_event_uuid'] ? 'staged' : 'conflict';
            // Local producers must use the head they read, never silently overwrite it.
            abort_if($outbound && $state === 'conflict', 409, 'Entity has changed or requires review.');
            $id = DB::table('lab_sync_events')->insertGetId([
                'peer_id' => $peerId, 'event_uuid' => $event['event_uuid'],
                'origin_node_uuid' => $event['origin_node_uuid'], 'kind' => $event['kind'],
                'entity_uuid' => $event['entity_uuid'], 'base_event_uuid' => $event['base_event_uuid'],
                'observed_head_uuid' => $headUuid, 'envelope_hash' => $hash,
                'envelope' => Crypt::encryptString($encoded), 'state' => $state,
                'outbound' => $outbound, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($state === 'staged') {
                if ($head) {
                    $query->update(['event_uuid' => $event['event_uuid']]);
                } else {
                    DB::table('lab_sync_heads')->insert(['peer_id' => $peerId, 'kind' => $event['kind'],
                        'entity_uuid' => $event['entity_uuid'], 'event_uuid' => $event['event_uuid']]);
                }
            }
            return $this->receipt(DB::table('lab_sync_events')->find($id), false);
        }, 3);
    }

    public function validateEnvelope(array $event): array
    {
        $keys = ['protocol', 'event_uuid', 'lab_uuid', 'origin_node_uuid', 'kind', 'entity_uuid',
            'base_event_uuid', 'author_uuid', 'authored_at', 'operation', 'payload'];
        abort_if(array_diff(array_keys($event), $keys), 422, 'Unknown envelope fields.');
        abort_if(strlen(json_encode($event, JSON_THROW_ON_ERROR)) > config('lab_sync.max_event_bytes'), 413);
        Validator::make($event, [
            'protocol' => ['required', 'integer', Rule::in([1])],
            'event_uuid' => ['required', 'uuid'], 'lab_uuid' => ['required', 'uuid'],
            'origin_node_uuid' => ['required', 'uuid'], 'entity_uuid' => ['required', 'uuid'],
            'base_event_uuid' => ['present', 'nullable', 'uuid', 'different:event_uuid'],
            'author_uuid' => ['required', 'uuid'],
            'authored_at' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'kind' => ['required', Rule::in(self::KINDS)],
            'operation' => ['required', Rule::in(['upsert', 'tombstone'])],
            'payload' => ['present', 'array'],
        ])->validate();
        // Normalize UUID case; timestamps remain audit metadata, never ordering authority.
        foreach (['event_uuid','lab_uuid','origin_node_uuid','entity_uuid','base_event_uuid','author_uuid'] as $key) {
            if ($event[$key] !== null) { $event[$key] = strtolower($event[$key]); }
        }
        $event['protocol'] = 1;
        return $event;
    }

    private function canonical(mixed $value): mixed
    {
        if (!is_array($value)) { return $value; }
        if (!array_is_list($value)) { ksort($value, SORT_STRING); }
        return array_map(fn ($v) => $this->canonical($v), $value);
    }

    public function receipt(object $row, bool $duplicate): array
    {
        return ['event_uuid' => $row->event_uuid, 'state' => $row->state,
            'duplicate' => $duplicate, 'clinical_applied' => false];
    }

    public function decode(object $row): array
    {
        return json_decode(Crypt::decryptString($row->envelope), true, 32, JSON_THROW_ON_ERROR);
    }

    public function acknowledge(int $peerId, string $eventUuid, mixed $receipt): void
    {
        abort_unless(config('lab_sync.enabled'), 503);
        abort_unless(is_array($receipt) && ($receipt['event_uuid'] ?? null) === $eventUuid &&
            in_array($receipt['state'] ?? null, ['staged', 'conflict'], true) &&
            ($receipt['clinical_applied'] ?? null) === false, 422, 'Invalid receipt.');
        DB::transaction(function () use ($peerId, $eventUuid, $receipt) {
            $peer = DB::table('lab_sync_peers')->lockForUpdate()->find($peerId);
            abort_unless($peer && $peer->enabled, 403);
            $query = DB::table('lab_sync_events')->where('peer_id', $peerId)
                ->where('event_uuid', $eventUuid)->where('outbound', true);
            $row = $query->first();
            abort_unless($row, 404);
            abort_if($row->remote_state !== null && $row->remote_state !== $receipt['state'], 409);
            $query->update(['delivered_at' => $row->delivered_at ?? now(),
                'remote_state' => $receipt['state'], 'updated_at' => now()]);
        }, 3);
    }

    public function changes(int $peerId, int $after): array
    {
        $rows = DB::table('lab_sync_events')->where('peer_id', $peerId)->where('outbound', true)
            ->where('id', '>', $after)->orderBy('id')->limit(config('lab_sync.batch_size'))->get();
        return ['protocol' => 1, 'mode' => 'staging_only', 'clinical_applied' => false,
            'cursor' => $rows->last()?->id ?? $after,
            'events' => $rows->map(fn ($row) => ['sequence' => $row->id, 'event' => $this->decode($row)])->all()];
    }
}
