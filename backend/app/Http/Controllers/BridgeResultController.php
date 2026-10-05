<?php

namespace App\Http\Controllers;

use App\Models\DeviceResult;
use App\Models\Invoice;
use App\Models\LabDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Durable inbox for the DxH bridge; CBC drafts never release or overwrite existing results. */
class BridgeResultController extends DeviceResultController
{
    public function check(Request $request)
    {
        $device = $this->authenticateDevice($request);
        if (! $device) {
            return response()->json(['message' => 'Invalid device token'], 401);
        }
        if (! Schema::hasColumns('device_results', ['delivery_id', 'delivery_hash', 'instrument_metadata'])) {
            return response()->json(['message' => 'Bridge database migration is required'], 503);
        }
        $device->update(['last_seen_at' => now(), 'status' => 'online']);
        $settings = $device->bridgeSettings();

        return response()->json([
            'protocol' => 'labbridge-v1', 'device_id' => $device->id,
            'device_name' => $device->name, 'lab_id' => $device->lab_id_fk, 'lab_name' => $device->lab?->name,
            'idempotency' => true, 'adapters' => [$settings['adapter']],
            'storage' => 'device_results', 'automatic_invoice_apply' => $settings['automatic_invoice_apply'],
            'cbc_interface_code' => $settings['cbc_interface_code'],
        ]);
    }

    public function receive(Request $request)
    {
        $device = $this->authenticateDevice($request);
        if (! $device) {
            return response()->json(['message' => 'Invalid device token'], 401);
        }
        if (strlen($request->getContent()) > 1048576) {
            return response()->json(['message' => 'Payload too large'], 413);
        }
        $data = $request->validate([
            'delivery_id' => 'required|string|size:64|regex:/^[a-f0-9]+$/',
            'device_id' => 'required|integer|min:1',
            'specimen_barcode' => 'required|string|max:255',
            'raw_message' => 'required|string|max:65535',
            'parsed_results' => 'required|array|min:1|max:200',
            'parsed_results.*.test_code' => 'required|string|max:100',
            'parsed_results.*.test_name' => 'nullable|string|max:255',
            'parsed_results.*.value' => 'present|nullable|string|max:500',
            'parsed_results.*.unit' => 'nullable|string|max:50',
            'parsed_results.*.flags' => 'nullable|string|max:100',
            'parsed_results.*.reference_range' => 'nullable|string|max:255',
            'parsed_results.*.result_status' => 'nullable|string|max:50',
            'parsed_results.*.research_only' => 'required|boolean',
            'parsed_results.*.raw_value' => 'present|nullable|string|max:1000',
            'instrument_metadata' => 'required|array',
            'instrument_metadata.adapter' => 'required|in:dxh500,np21h,bm850',
            'instrument_metadata.comments' => 'present|array|max:200',
            'instrument_metadata.comments.*.text' => 'required|string|max:2000',
            'instrument_metadata.comments.*.code' => 'nullable|string|max:100',
            'instrument_metadata.comments.*.scope' => 'required|in:sample,result',
            'instrument_metadata.warnings' => 'present|array|max:200',
            'instrument_metadata.warnings.*' => 'string|max:2000',
            'instrument_metadata.orders' => 'present|array|max:200',
        ]);
        if ((int) $data['device_id'] !== $device->id) {
            return response()->json(['message' => 'Device identity does not match queued destination'], 409);
        }
        // The raw payload is the stable identity across lost HTTP acknowledgments.
        if (strlen($data['raw_message']) > 65535 ||
            ! hash_equals(hash('sha256', $data['raw_message']), $data['delivery_id'])) {
            return response()->json(['message' => 'Invalid raw message digest or size'], 422);
        }
        if ($data['instrument_metadata']['adapter'] === 'np21h') {
            $lines = explode("\r", $data['raw_message']);
            $header = explode('|', $lines[0]);
            if (count($header) < 12 || $header[0] !== 'MSH' || $header[2] !== 'NP-21H/NP-26H' ||
                $header[8] !== 'ORU^R01' || $header[11] !== '2.3.1') {
                return response()->json(['message' => 'Invalid NP-21H header'], 422);
            }
            $header[6] = ''; // Ignore only transport timestamp when recognizing a retransmission.
            $lines[0] = implode('|', $header);
            $data['instrument_metadata']['np21_identity'] = hash('sha256', implode("\r", $lines));
        }
        if ($data['instrument_metadata']['adapter'] === 'bm850') {
            $lines = explode("\r", $data['raw_message']);
            $header = explode('|', $lines[0]);
            $orders = array_values(array_filter(array_map(fn ($line) => explode('|', $line), $lines), fn ($row) => $row[0] === 'OBR'));
            if (count($header) < 12 || $header[0] !== 'MSH' || $header[1] !== '^~\\&' ||
                $header[2] !== 'BM850^HL7MW' || $header[8] !== 'ORU^R01' || $header[11] !== '2.7' || empty($header[9]) ||
                count($orders) !== 1 || trim($orders[0][4] ?? '') !== $data['specimen_barcode']) {
                return response()->json(['message' => 'Invalid BM850 header or OBR-4 barcode'], 422);
            }
            // Verify numeric observations against raw data, including their preliminary status.
            $numeric = [];
            foreach ($lines as $line) {
                $row = explode('|', $line);
                if ($row[0] !== 'OBX' || ($row[2] ?? '') !== 'NM') { continue; }
                $code = $row[3] ?? '';
                if (isset($numeric[$code]) || ! in_array($row[11] ?? '', ['P', 'F'], true)) {
                    return response()->json(['message' => 'Duplicate or unsupported BM850 observation'], 422);
                }
                $numeric[$code] = $row;
            }
            if (count($numeric) !== 22 || count($data['parsed_results']) !== 22) {
                return response()->json(['message' => 'Expected 22 BM850 results'], 422);
            }
            $seen = [];
            foreach ($data['parsed_results'] as $observation) {
                $code = $observation['test_code'];
                $row = $numeric[$code] ?? [];
                if (isset($seen[$code]) || ($row[5] ?? null) !== $observation['value'] ||
                    ($row[6] ?? null) !== ($observation['unit'] ?? '') ||
                    ($row[7] ?? null) !== ($observation['reference_range'] ?? '') ||
                    ($row[11] ?? null) !== ($observation['result_status'] ?? '') ||
                    (($row[8] ?? '') === '""' ? '' : ($row[8] ?? '')) !== ($observation['flags'] ?? '') ||
                    $observation['research_only']) {
                    return response()->json(['message' => 'BM850 parsed result differs from raw observation'], 422);
                }
                $seen[$code] = true;
            }
            if (collect($data['parsed_results'])->contains(fn ($o) => $o['result_status'] === 'P')) {
                $data['instrument_metadata']['warnings'][] = 'Instrument marked results P (preliminary); review before report approval.';
                $data['instrument_metadata']['warnings'] = array_values(array_unique($data['instrument_metadata']['warnings']));
            }
            foreach ([6, 7] as $index) {
                if (preg_match('/^\d{14}$/D', $header[$index])) { $header[$index] = ''; }
            }
            $lines[0] = implode('|', $header);
            $data['instrument_metadata']['bm850_identity'] = hash('sha256', implode("\r", $lines));
        }
        $data['instrument_metadata']['review_required'] = true;
        foreach ($data['parsed_results'] as &$item) {
            $item['research_only'] = $item['research_only'] || str_starts_with($item['test_code'], '@');
        }
        unset($item);
        $hash = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($device, $data, $hash) {
            // Serialize ingestion for this device; the unique index is the final duplicate guard.
            $locked = LabDevice::whereKey($device->id)->lockForUpdate()->first();
            if (! $locked || ! hash_equals($locked->api_token, $device->api_token)) {
                return response()->json(['message' => 'Device was removed or token changed'], 401);
            }
            $prior = DeviceResult::where('device_id_fk', $device->id)
                ->where('delivery_id', $data['delivery_id'])->first();
            if ($prior) {
                if (! hash_equals($prior->delivery_hash, $hash)) {
                    return response()->json(['message' => 'Delivery ID already stored with different content'], 409);
                }
                return $this->receipt($prior, true);
            }
            if ($data['instrument_metadata']['adapter'] === 'np21h') {
                $prior = DeviceResult::where('device_id_fk', $device->id)
                    ->where('instrument_metadata->np21_identity', $data['instrument_metadata']['np21_identity'])->first();
                if ($prior) {
                    if ($prior->specimen_barcode !== $data['specimen_barcode'] || $prior->parsed_results != $data['parsed_results']) {
                        return response()->json(['message' => 'Repeated NP-21H message has inconsistent parsed data'], 409);
                    }
                    return $this->receipt($prior, true, $data['delivery_id']);
                }
            }
            if ($data['instrument_metadata']['adapter'] === 'bm850') {
                $prior = DeviceResult::where('device_id_fk', $device->id)
                    ->where('instrument_metadata->bm850_identity', $data['instrument_metadata']['bm850_identity'])->first();
                if ($prior) {
                    if ($prior->specimen_barcode !== $data['specimen_barcode'] || $prior->parsed_results != $data['parsed_results']) {
                        return response()->json(['message' => 'Repeated BM850 message has inconsistent parsed data'], 409);
                    }
                    return $this->receipt($prior, true, $data['delivery_id']);
                }
            }
            $settings = $locked->bridgeSettings();
            if ($settings['adapter'] !== $data['instrument_metadata']['adapter']) {
                return response()->json(['message' => 'Analyzer model does not match this laboratory device. Check its settings and API token.'], 409);
            }
            $invoices = Invoice::where('barcode', $data['specimen_barcode'])
                ->whereIn('lab_id_fk', $this->getDeviceTenantIds($device))->limit(2)->get();
            $invoice = $invoices->count() === 1 &&
                (string) $invoices[0]->barcode === $data['specimen_barcode'] ? $invoices[0] : null;
            $result = DeviceResult::create([
                'device_id_fk' => $device->id, 'invoice_id_fk' => $invoice?->id,
                'specimen_barcode' => $data['specimen_barcode'], 'raw_message' => $data['raw_message'],
                'parsed_results' => $data['parsed_results'], 'instrument_metadata' => $data['instrument_metadata'],
                'delivery_id' => $data['delivery_id'], 'delivery_hash' => $hash,
                'status' => $invoice ? 'matched' : 'pending', 'matched_at' => $invoice ? now() : null,
            ]);
            if ($settings['automatic_invoice_apply']) {
                app(\App\Services\BridgeCbcService::class)->apply($result);
            }
            $result->refresh();
            $locked->update(['last_seen_at' => now(), 'status' => 'online']);

            return $this->receipt($result, false);
        }, 3);
    }

    private function receipt(DeviceResult $result, bool $duplicate, ?string $requestDeliveryId = null)
    {
        return response()->json([
            'protocol' => 'labbridge-v1', 'stored' => true, 'duplicate' => $duplicate,
            'delivery_id' => $requestDeliveryId ?? $result->delivery_id, 'stored_delivery_id' => $result->delivery_id, 'device_id' => $result->device_id_fk,
            'id' => $result->id, 'status' => $result->status,
            'invoice_matched' => (bool) $result->invoice_id_fk,
        ], $duplicate ? 200 : 201);
    }
}
