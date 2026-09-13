<?php

namespace App\Http\Controllers;

use App\Models\DeviceResult;
use App\Models\Invoice;
use App\Models\InvoiceTestRel;
use App\Models\LabDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class DeviceResultController extends Controller
{
    // ========== DEVICE-FACING (X-Device-Token auth) ==========

    /**
     * Receive results from the Electron agent.
     * Auth via X-Device-Token header (not Sanctum).
     */
    public function receiveResults(Request $request)
    {
        $device = $this->authenticateDevice($request);
        if (! $device) {
            return response()->json(['message' => 'Invalid device token'], 401);
        }

        $validated = $request->validate([
            'specimen_barcode' => 'required|string|max:50|regex:/^[a-zA-Z0-9\-_]+$/',
            'raw_message' => 'required|string|max:65535',
            'parsed_results' => 'required|array|max:200',
            'parsed_results.*.test_code' => 'required|string|max:100',
            'parsed_results.*.value' => 'required|string|max:500',
            'parsed_results.*.unit' => 'nullable|string|max:50',
            'parsed_results.*.flags' => 'nullable|string|max:20',
            'parsed_results.*.reference_range' => 'nullable|string|max:255',
            'parsed_results.*.test_name' => 'nullable|string|max:255',
        ]);

        // Sanitize parsed results — strip any HTML/script tags
        $sanitizedResults = array_map(function ($result) {
            return array_map(function ($value) {
                return is_string($value) ? strip_tags($value) : $value;
            }, $result);
        }, $validated['parsed_results']);

        DB::beginTransaction();
        try {
            // Create the device result record
            $result = DeviceResult::create([
                'device_id_fk' => $device->id,
                'specimen_barcode' => $validated['specimen_barcode'],
                'raw_message' => $validated['raw_message'],
                'parsed_results' => $sanitizedResults,
                'status' => 'pending',
            ]);

            // Attempt auto-match by barcode within the device's lab scope
            $tenantIds = $this->getDeviceTenantIds($device);

            $invoice = Invoice::where('barcode', $validated['specimen_barcode'])
                ->whereIn('lab_id_fk', $tenantIds)
                ->whereNull('deleted_at')
                ->first();

            if ($invoice) {
                $result->update([
                    'invoice_id_fk' => $invoice->id,
                    'status' => 'matched',
                    'matched_at' => now(),
                ]);
            }

            // Update device last_seen
            $device->update(['last_seen_at' => now(), 'status' => 'online']);

            DB::commit();

            Log::info('Device result received', [
                'device_id' => $device->id,
                'barcode' => $validated['specimen_barcode'],
                'tests_count' => count($sanitizedResults),
                'matched' => (bool) $invoice,
            ]);

            return response()->json([
                'id' => $result->id,
                'status' => $result->status,
                'invoice_matched' => (bool) $invoice,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Device result failed', ['error' => $e->getMessage(), 'device_id' => $device->id]);

            return response()->json(['message' => 'Failed to process results'], 500);
        }
    }

    /**
     * Heartbeat from the Electron agent.
     */
    public function heartbeat(Request $request)
    {
        $device = $this->authenticateDevice($request);
        if (! $device) {
            return response()->json(['message' => 'Invalid device token'], 401);
        }

        $device->update([
            'last_seen_at' => now(),
            'status' => 'online',
        ]);

        return response()->json(['status' => 'ok', 'server_time' => now()->toISOString()]);
    }

    // ========== USER-FACING (Sanctum auth) ==========

    /**
     * List device results for the lab.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo('devices view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = DeviceResult::with(['device:id,name', 'invoice:id,barcode,patient_id_fk']);

        // Scope to tenant's devices
        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $deviceIds = LabDevice::whereIn('lab_id_fk', $users_ids)->pluck('id');
            $query->whereIn('device_id_fk', $deviceIds);
        }

        // Filter by status — validate allowed values
        if ($request->filled('status')) {
            $allowedStatuses = ['pending', 'matched', 'applied', 'failed'];
            $statuses = array_intersect(explode(',', $request->status), $allowedStatuses);
            if (! empty($statuses)) {
                $query->whereIn('status', $statuses);
            }
        }

        // Filter by device — validate it's an integer
        if ($request->filled('device_id')) {
            $query->where('device_id_fk', (int) $request->device_id);
        }

        $perPage = min((int) ($request->input('per_page', 25)), 100);
        $results = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $results->items(),
            'pagination' => [
                'total' => $results->total(),
                'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
            ],
        ]);
    }

    /**
     * Apply a matched device result to InvoiceTestRel records.
     */
    public function applyResult(Request $request, $id)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo('medical reports update')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $result = DeviceResult::with('device')->find((int) $id);
        if (! $result) {
            return response()->json(['message' => 'Result not found'], 404);
        }

        // Verify tenant ownership
        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (! $result->device || ! in_array($result->device->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        if ($result->status !== 'matched' || ! $result->invoice_id_fk) {
            return response()->json(['message' => 'Result must be matched to an invoice before applying'], 422);
        }

        // Prevent double-apply
        if ($result->status === 'applied') {
            return response()->json(['message' => 'Result already applied'], 422);
        }

        DB::beginTransaction();
        try {
            $parsedResults = is_string($result->parsed_results)
                ? json_decode($result->parsed_results, true)
                : $result->parsed_results;

            if (empty($parsedResults)) {
                return response()->json(['message' => 'No parsed results to apply'], 422);
            }

            $appliedCount = 0;

            foreach ($parsedResults as $deviceTest) {
                $testCode = trim($deviceTest['test_code'] ?? '');
                $testName = trim($deviceTest['test_name'] ?? '');

                if (empty($testCode) && empty($testName)) {
                    continue;
                }

                // Find matching InvoiceTestRel by test shortcut or name — properly scoped
                $invoiceTestRel = InvoiceTestRel::where('invoice_id_fk', $result->invoice_id_fk)
                    ->whereHas('test', function ($q) use ($testCode, $testName) {
                        $q->where(function ($inner) use ($testCode, $testName) {
                            if ($testCode) {
                                $inner->whereLike('shortcut', $testCode)
                                      ->orWhereLike('name', $testCode);
                            }
                            if ($testName && $testName !== $testCode) {
                                $inner->orWhereLike('shortcut', $testName)
                                      ->orWhereLike('name', $testName);
                            }
                        });
                    })
                    ->first();

                if ($invoiceTestRel) {
                    $invoiceTestRel->update([
                        'result' => strip_tags($deviceTest['value'] ?? ''),
                        'is_done' => true,
                    ]);
                    $appliedCount++;
                }
            }

            if ($appliedCount === 0) {
                DB::rollBack();

                return response()->json([
                    'message' => 'No matching tests found in the invoice. Check test codes/shortcuts.',
                    'applied_count' => 0,
                    'total_results' => count($parsedResults),
                ], 422);
            }

            $result->update([
                'status' => 'applied',
                'applied_at' => now(),
            ]);

            DB::commit();

            ActivityLogController::storeActivity(
                'تطبيق نتائج جهاز: '.$result->device->name.' على فاتورة: '.$result->specimen_barcode,
                $result
            );

            return response()->json([
                'message' => "Applied {$appliedCount} results successfully",
                'applied_count' => $appliedCount,
                'total_results' => count($parsedResults),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Apply device result failed', [
                'result_id' => $result->id,
                'error' => $e->getMessage(),
            ]);

            $result->update([
                'status' => 'failed',
                'error_message' => 'Internal error during result application',
            ]);

            return response()->json(['message' => 'Failed to apply results. Please try again.'], 500);
        }
    }

    /**
     * Manually match a pending result to an invoice.
     */
    public function manualMatch(Request $request, $id)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo('devices edit')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'invoice_id' => 'required|integer|exists:invoices,id',
        ]);

        $result = DeviceResult::with('device')->find((int) $id);
        if (! $result) {
            return response()->json(['message' => 'Result not found'], 404);
        }

        // Only allow matching for pending or failed results
        if (! in_array($result->status, ['pending', 'failed'])) {
            return response()->json(['message' => 'Only pending or failed results can be matched'], 422);
        }

        // Verify tenant ownership of the result
        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (! $result->device || ! in_array($result->device->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        // Verify invoice belongs to the same tenant
        $invoice = Invoice::find($request->invoice_id);
        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found'], 404);
        }

        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (! in_array($invoice->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Invoice does not belong to your lab'], 403);
            }
        }

        $result->update([
            'invoice_id_fk' => $invoice->id,
            'status' => 'matched',
            'matched_at' => now(),
            'error_message' => null,
        ]);

        return response()->json([
            'message' => 'Result matched to invoice successfully',
        ]);
    }

    // ========== PRIVATE ==========

    /**
     * Authenticate device via X-Device-Token header.
     * Uses hash_equals for timing-safe comparison.
     */
    private function authenticateDevice(Request $request): ?LabDevice
    {
        $token = $request->header('X-Device-Token');
        if (! $token || strlen($token) !== 64) {
            return null;
        }

        // Only allow alphanumeric tokens (hex from bin2hex)
        if (! ctype_xdigit($token)) {
            return null;
        }

        $device = LabDevice::where('api_token', $token)->whereNull('deleted_at')->first();

        // Timing-safe comparison
        if ($device && hash_equals($device->api_token, $token)) {
            return $device;
        }

        return null;
    }

    /**
     * Get tenant user IDs for a device's lab.
     */
    private function getDeviceTenantIds(LabDevice $device): array
    {
        $labId = $device->lab_id_fk;
        $tenantIds = User::where('creator_id', $labId)->pluck('id')->toArray();
        $tenantIds[] = $labId;

        return $tenantIds;
    }
}
