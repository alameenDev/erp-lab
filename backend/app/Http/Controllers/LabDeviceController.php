<?php

namespace App\Http\Controllers;

use App\Models\LabDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class LabDeviceController extends Controller
{
    public function options(Request $request)
    {
        $user = $request->user();
        if (! $user->hasAnyPermission(['devices view', 'devices create', 'devices edit'])) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $labs = User::where('role_id', 2);
        if ($user->role_id != 1) {
            $labs->whereIn('id', $this->getTenantUserIds());
        }
        return response()->json([
            'profiles' => LabDevice::bridgeProfiles(),
            'labs' => $labs->orderBy('name')->get(['id', 'name']),
            'can_select_lab' => $user->role_id == 1,
        ]);
    }

    private function deviceRules(): array
    {
        return [
            'lab_id_fk' => 'sometimes|required|integer|min:1',
            'name' => 'required|string|max:255',
            'device_type' => 'nullable|string|in:hematology,chemistry,immunoassay,urinalysis,coagulation,microbiology,other',
            'serial_number' => 'nullable|string|max:255',
            'connection_type' => 'nullable|string|in:serial,tcp',
            'connection_config' => 'nullable|array',
            'connection_config.com_port' => 'nullable|string|max:20',
            'connection_config.baud_rate' => 'nullable|integer|in:9600,19200,38400,57600,115200',
            'connection_config.ip' => 'nullable|ip',
            'connection_config.port' => 'nullable|integer|min:1|max:65535',
            'connection_config.bridge_adapter' => 'sometimes|required|in:dxh500,np21h,bm850',
            'connection_config.cbc_interface_code' => 'sometimes|required|string|max:100|regex:/^[A-Za-z0-9_.-]+$/',
            'connection_config.automatic_invoice_apply' => 'sometimes|required|boolean',
        ];
    }

    private function mergeConfig(array $existing, array $incoming): array
    {
        return array_replace($existing, Arr::only($incoming, [
            'com_port', 'baud_rate', 'ip', 'port', 'bridge_adapter',
            'cbc_interface_code', 'automatic_invoice_apply',
        ]));
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo('devices view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = LabDevice::withCount(['deviceResults as pending_results_count' => function ($q) {
            $q->whereIn('status', ['pending', 'matched']);
        }]);

        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->whereIn('lab_id_fk', $users_ids);
        }

        $devices = $query->with('lab:id,name')->orderBy('created_at', 'desc')->get();

        $data = $devices->map(function ($device) {
            return [
                'id' => $device->id,
                'name' => $device->name,
                'device_type' => $device->device_type,
                'serial_number' => $device->serial_number,
                'connection_type' => $device->connection_type,
                'connection_config' => $device->connection_config,
                'bridge_settings' => $device->bridgeSettings(),
                'status' => $device->status,
                'last_seen_at' => $device->last_seen_at,
                'pending_results_count' => $device->pending_results_count,
                'lab' => $device->lab?->name,
                'lab_id_fk' => $device->lab_id_fk,
                'created_at' => $device->created_at,
            ];
        });

        return response()->json($data);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo('devices create')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate($this->deviceRules());

        $apiToken = LabDevice::generateApiToken();

        if ($user->role_id == 1) {
            $request->validate(['lab_id_fk' => ['required', 'integer', Rule::exists('users', 'id')->where('role_id', 2)->whereNull('deleted_at')]]);
            $labOwnerId = (int) $validated['lab_id_fk'];
        } else {
            $labOwnerId = User::whereIn('id', $this->getTenantUserIds())->where('role_id', 2)->value('id');
            if (! $labOwnerId) {
                return response()->json(['message' => 'A laboratory owner is required'], 422);
            }
            if (isset($validated['lab_id_fk']) && (int) $validated['lab_id_fk'] !== (int) $labOwnerId) {
                return response()->json(['message' => 'Cannot create a device for another laboratory'], 403);
            }
        }

        // Limit devices per lab (max 50)
        $existingCount = LabDevice::where('lab_id_fk', $labOwnerId)->count();
        if ($existingCount >= 50) {
            return response()->json(['message' => 'Maximum device limit (50) reached for this lab'], 422);
        }

        $device = LabDevice::create([
            'lab_id_fk' => $labOwnerId,
            'name' => $validated['name'],
            'device_type' => $validated['device_type'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
            'connection_type' => $validated['connection_type'] ?? 'serial',
            'connection_config' => $this->mergeConfig([], $validated['connection_config'] ?? []),
            'api_token' => $apiToken,
            'status' => 'offline',
        ]);

        ActivityLogController::storeActivity('إضافة جهاز: '.$device->name, $device);

        Log::info('Device created', ['device_id' => $device->id, 'lab_id' => $labOwnerId]);

        return response()->json([
            'device' => $device,
            'api_token' => $apiToken,
            'message' => 'Device created successfully. Save the API token — it will not be shown again.',
        ], 201);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo('devices edit')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate(['id' => 'required|integer'] + $this->deviceRules());

        $device = LabDevice::find($validated['id']);
        if (! $device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (! in_array($device->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        if (isset($validated['lab_id_fk']) && (int) $validated['lab_id_fk'] !== (int) $device->lab_id_fk) {
            return response()->json(['message' => 'Device ownership cannot be transferred. Create a separate device for the other laboratory.'], 422);
        }
        $oldDevice = $device->toArray();

        $device->update([
            'name' => $validated['name'],
            'device_type' => $validated['device_type'] ?? $device->device_type,
            'serial_number' => $validated['serial_number'] ?? $device->serial_number,
            'connection_type' => $validated['connection_type'] ?? $device->connection_type,
            'connection_config' => $this->mergeConfig($device->connection_config ?? [], $validated['connection_config'] ?? []),
        ]);

        ActivityLogController::updateActivity('تعديل جهاز: '.$device->name, $oldDevice, $device);

        return response()->json($device);
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo('devices delete')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $device = LabDevice::find((int) $request->id);
        if (! $device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (! in_array($device->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        ActivityLogController::deleteActivity('حذف جهاز: '.$device->name, $device);
        $device->delete();

        return response()->json(['message' => 'Device deleted']);
    }

    public function regenerateToken(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo('devices edit')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $device = LabDevice::find((int) $request->id);
        if (! $device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (! in_array($device->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $newToken = LabDevice::generateApiToken();
        $device->update(['api_token' => $newToken]);

        Log::info('Device token regenerated', ['device_id' => $device->id, 'user_id' => $user->id]);

        return response()->json([
            'api_token' => $newToken,
            'message' => 'Token regenerated. Save the new token — it will not be shown again.',
        ]);
    }
}
