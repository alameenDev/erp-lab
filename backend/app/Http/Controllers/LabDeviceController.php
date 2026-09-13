<?php

namespace App\Http\Controllers;

use App\Models\LabDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class LabDeviceController extends Controller
{
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

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'device_type' => 'nullable|string|max:100|in:hematology,chemistry,immunoassay,urinalysis,coagulation,microbiology,other',
            'serial_number' => 'nullable|string|max:255',
            'connection_type' => 'nullable|string|in:serial,tcp',
            'connection_config' => 'nullable|array',
            'connection_config.com_port' => 'nullable|string|max:20',
            'connection_config.baud_rate' => 'nullable|integer|in:9600,19200,38400,57600,115200',
            'connection_config.ip' => 'nullable|ip',
            'connection_config.port' => 'nullable|integer|min:1|max:65535',
        ]);

        $apiToken = LabDevice::generateApiToken();

        // Resolve lab owner ID using tenant chain
        $labOwnerId = $user->role_id == 2 ? $user->id : ($user->creator_id ?? $user->id);
        if ($labOwnerId !== $user->id) {
            $creator = User::find($labOwnerId);
            if ($creator && $creator->role_id != 2 && $creator->creator_id) {
                $labOwnerId = $creator->creator_id;
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
            'connection_config' => $validated['connection_config'] ?? null,
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

        $validated = $request->validate([
            'id' => 'required|integer',
            'name' => 'required|string|max:255',
            'device_type' => 'nullable|string|max:100|in:hematology,chemistry,immunoassay,urinalysis,coagulation,microbiology,other',
            'serial_number' => 'nullable|string|max:255',
            'connection_type' => 'nullable|string|in:serial,tcp',
            'connection_config' => 'nullable|array',
            'connection_config.com_port' => 'nullable|string|max:20',
            'connection_config.baud_rate' => 'nullable|integer|in:9600,19200,38400,57600,115200',
            'connection_config.ip' => 'nullable|ip',
            'connection_config.port' => 'nullable|integer|min:1|max:65535',
        ]);

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

        $oldDevice = $device->toArray();

        $device->update([
            'name' => $validated['name'],
            'device_type' => $validated['device_type'] ?? $device->device_type,
            'serial_number' => $validated['serial_number'] ?? $device->serial_number,
            'connection_type' => $validated['connection_type'] ?? $device->connection_type,
            'connection_config' => $validated['connection_config'] ?? $device->connection_config,
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
