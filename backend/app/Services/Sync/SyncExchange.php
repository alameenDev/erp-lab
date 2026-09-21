<?php

namespace App\Services\Sync;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SyncExchange
{
    public function __construct(private SyncLedger $ledger) {}

    public function run(int $peerId): array
    {
        abort_unless(config('lab_sync.enabled'), 503);
        // One bounded batch per invocation; scheduler can retry after disconnection.
        $lock = Cache::lock('lab-sync-exchange:'.$peerId, 1800);
        if (!$lock->get()) { throw new RuntimeException('exchange_busy'); }
        try {
            $peer = DB::table('lab_sync_peers')->where('enabled', true)->find($peerId);
            if (!$peer || !$peer->outbound_token) { throw new RuntimeException('peer_not_configured'); }
            $url = $this->origin($peer->remote_url);
            $client = Http::acceptJson()->asJson()->withToken(Crypt::decryptString($peer->outbound_token))
                ->connectTimeout(5)->timeout(15)->withOptions(['allow_redirects' => false, 'verify' => true]);
            $sent = 0;
            $pending = DB::table('lab_sync_events')->where('peer_id', $peerId)->where('outbound', true)
                ->whereNull('delivered_at')->orderBy('id')->limit(config('lab_sync.batch_size'))->get();
            foreach ($pending as $row) {
                $response = $client->post($url.'/api/lab-sync/v1/events', $this->ledger->decode($row));
                if (!$response->successful()) { throw new RuntimeException('remote_rejected'); }
                $receipt = $response->json();
                $this->ledger->acknowledge($peerId, $row->event_uuid, $receipt);
                $sent++;
            }
            $response = $client->get($url.'/api/lab-sync/v1/changes', ['after' => $peer->pull_cursor]);
            if (!$response->successful()) { throw new RuntimeException('remote_rejected'); }
            $batch = $response->json();
            if (!is_array($batch) || ($batch['protocol'] ?? null) !== 1 ||
                ($batch['mode'] ?? null) !== 'staging_only' || ($batch['clinical_applied'] ?? null) !== false ||
                !is_array($batch['events'] ?? null) || !array_is_list($batch['events']) ||
                count($batch['events']) > config('lab_sync.batch_size') || !is_int($batch['cursor'] ?? null)) {
                throw new RuntimeException('invalid_remote_batch');
            }
            $cursor = (int) $peer->pull_cursor;
            // Validate ordering before accepting any entry; never skip an unknown cursor.
            foreach ($batch['events'] as $entry) {
                if (!is_array($entry) || !is_int($entry['sequence'] ?? null) ||
                    $entry['sequence'] <= $cursor || !is_array($entry['event'] ?? null)) {
                    throw new RuntimeException('invalid_remote_batch');
                }
                $cursor = $entry['sequence'];
            }
            if ($cursor !== $batch['cursor']) { throw new RuntimeException('invalid_remote_cursor'); }
            foreach ($batch['events'] as $entry) {
                $receipt = $this->ledger->receive($peerId, $entry['event']);
                $ack = $client->post($url.'/api/lab-sync/v1/acknowledgements', $receipt);
                if (!$ack->successful() || $ack->json('acknowledged') !== $receipt['event_uuid']) {
                    throw new RuntimeException('acknowledgement_failed');
                }
            }
            // If the process dies before here, persisted inbox entries deduplicate the next pull.
            DB::table('lab_sync_peers')->where('id', $peerId)->update([
                'pull_cursor' => $cursor, 'last_contact_at' => now(), 'last_error' => null, 'updated_at' => now(),
            ]);
            return ['sent' => $sent, 'received' => count($batch['events']), 'clinical_applied' => false];
        } catch (\Throwable $e) {
            // Do not persist URLs, credentials, exception bodies or patient payloads in logs.
            DB::table('lab_sync_peers')->where('id', $peerId)->update(['last_error' => 'exchange_failed', 'updated_at' => now()]);
            throw new RuntimeException('Sync exchange failed; pending events and cursor are retained.');
        } finally {
            $lock->release();
        }
    }

    private function origin(?string $url): string
    {
        $parts = parse_url($url ?? '');
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) ||
            isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) ||
            !in_array($parts['path'] ?? '', ['', '/'], true)) {
            throw new RuntimeException('invalid_peer_origin');
        }
        return rtrim($url, '/');
    }
}
