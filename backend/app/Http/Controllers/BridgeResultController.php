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

        return response()->json([
            'protocol' => 'labbridge-v1', 'device_id' => $device->id,
            'device_name' => $device->name, 'idempotency' => true,
            'storage' => 'device_results', 'automatic_invoice_apply' => true, 'cbc_interface_code' => '12345678',
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
            'instrument_metadata.adapter' => 'required|in:dxh500',
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
            app(\App\Services\BridgeCbcService::class)->apply($result);
            $result->refresh();
            $locked->update(['last_seen_at' => now(), 'status' => 'online']);

            return $this->receipt($result, false);
        }, 3);
    }

    private function receipt(DeviceResult $result, bool $duplicate)
    {
        return response()->json([
            'protocol' => 'labbridge-v1', 'stored' => true, 'duplicate' => $duplicate,
            'delivery_id' => $result->delivery_id, 'device_id' => $result->device_id_fk,
            'id' => $result->id, 'status' => $result->status,
            'invoice_matched' => (bool) $result->invoice_id_fk,
        ], $duplicate ? 200 : 201);
    }
}
