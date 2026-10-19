<?php

namespace App\Http\Controllers;

use App\Models\Referal;
use App\Models\User;
use App\Models\VerifyCode;
use App\Traits\SecureFileUpload;
use DateTime;
use App\Services\AccountAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    use SecureFileUpload;
    /**
     * Get all users.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $users = [];

        $user = Auth::user();
        if (!$user->hasPermissionTo(permission: 'users view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $tenantIds = $this->getTenantUserIds();
        $users = User::query()->when((int) $user->role_id !== 1, fn ($q) => $q->whereIn('id', $tenantIds))->with(['role', 'referals', 'creator', 'lab', 'permissions'])
            ->where('id', '!=', $user->id)
            ->where('role_id', '!=', 3)
            ->orderBy('created_at', 'desc')
            ->get();


        if ($users == null || empty($users)) {
            return response()->json([], 200);
        } else {

            $users_data = $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone_number,
                    'image' => $user->image,
                    'belong_to' => $user->creator?->name,
                    'address' => $user->address,
                    'role' => $user->role?->name,
                    'role_id' => $user->role_id,
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    'discount_percentage' => $user->role_id == 6 ? $user->referals?->commission : $user->lab?->discount_percentage,
                    'signiture' => $user->signiture,
                    'commission' => $user->role_id == 6 ? $user->referals?->commission : $user->lab?->discount_percentage,
                ];
            });

            return response()->json($users_data, 200);

        }

    }
    /**
     * Register a new user.
     *
     * @return \Illuminate\Http\JsonResponse
     */

    /**
     * Get a user by ID.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserById(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:users,id',
        ]);

        $authUser = Auth::user();

        // Find the user by ID
        $user = User::where('id', $request->id)->with(['role', 'referals', 'creator', 'lab', 'permissions'])->first();

        // Check if user exists
        if ($user == null) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if (! $authUser->is($user)) {
            abort_unless($authUser->can('users view'), 403);
            app(AccountAccess::class)->manageUser($authUser, $user);
        }

        $user_data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'image' => $user->image,
            'address' => $user->address,
            'role' => $user->role?->name,
            'role_id' => $user->role_id,
            'signiture' => $user->signiture,
            'commission' => $user->role_id == 6 ? $user->referals?->commission : $user->lab?->discount_percentage,
            'discount_percentage' => $user->role_id == 6 ? $user->referals?->commission : $user->lab?->discount_percentage,
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ];

        return response()->json($user_data, 200);
    }

    /**
     * update an existing user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $authUser = Auth::user();
        if (!$authUser->hasPermissionTo(permission: 'users edit')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        // Validate incoming request
        $request->validate([
            'id' => 'required|integer',
            'name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpg,jpeg,png',
            'signiture' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'password' => 'sometimes|nullable|string|min:8|max:128',
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($request->integer('id'))],
            'role_id' => 'sometimes|nullable|integer|exists:roles,id',
        ]);

        // Find the user by ID
        $user = User::find($request->id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $access = app(AccountAccess::class);
        $changingRole = $request->filled('role_id') && (int) $request->role_id !== (int) $user->role_id;
        $access->manageUser($authUser, $user, $changingRole);
        // Older updates changed role_id without synchronizing model_has_roles.
        $needsRoleSync = $request->filled('role_id') && ($changingRole || $user->roles->pluck('id')->all() !== [$request->integer('role_id')]);
        $role = $needsRoleSync ? $access->assignableRole($authUser, $request->integer('role_id')) : null;
        $oldName = $user->name;
        $olduser = clone $user;

        // SECURITY: Use secure file upload
        if ($request->hasFile('image')) {
            // Delete old image
            $this->safeDeleteFile($user->image);

            // Upload new image securely
            $result = $this->secureUploadImage($request->file('image'), 'users');
            if (!$result['success']) {
                return response()->json(['message' => $result['error']], 422);
            }
            $user->image = $result['url'];
        }

        if ($request->hasFile('signiture')) {
            // Delete old signature
            $this->safeDeleteFile($user->signiture);

            // Upload new signature securely
            $result = $this->secureUploadImage($request->file('signiture'), 'users');
            if (!$result['success']) {
                return response()->json(['message' => $result['error']], 422);
            }
            $user->signiture = $result['url'];
        }

        $user->name = $request->name ?? $user->name;
        $user->address = $request->address ?? $user->address;
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        $user->phone_number = $request->phone ?? $user->phone_number;

        if ($request->filled('email')) $user->email = $request->email;

        if (isset($request->discount_percentage)) {

            $referral = Referal::where('referral_id_fk', $user->id)->first();
            if ($referral) {
                $referral->commission = $request->discount_percentage ?? $referral->commission;
                $referral->save();
            }
        }
        DB::transaction(function () use ($user, $role) {
            if ($role) {
                \Spatie\Permission\Models\Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
                $user->syncRoles([$role]);
                $user->role_id = $role->id;
            }
            $user->save();
        });
        $user->load(['roles.permissions', 'permissions']);
        ActivityLogController::updateActivity('تعديل بيانات المستخدم', $olduser, $user);

        // Return a successful response
        return response()->json(['message' => 'User updated successfully', 'user' => $user], 200);
    }

    /**
     * Remove a user by ID.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteUser(Request $request)
    {
        $authUser = Auth::user();
        if (!$authUser->hasPermissionTo(permission: 'users delete')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        // Find the user by ID
        $user = User::find($request->id);

        // Check if user exists
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        app(AccountAccess::class)->manageUser($authUser, $user);

        // Prevent self-deletion
        if ($user->id === $authUser->id) {
            return response()->json(['message' => 'Cannot delete your own account'], 400);
        }

        // Delete the verify code
        $verify = VerifyCode::where('email', $user->email)->first();
        if ($verify) {
            $verify->delete();
        }
        // Soft delete the user
        $user->tokens()->delete();
        $user->delete();
        ActivityLogController::deleteActivity('حذف مستخدم', $user);

        // Return a successful response
        return response()->json(['message' => 'User deleted successfully'], 200);
    }

    /**
     * Upload report background image for the authenticated lab.
     */
    public function uploadBackground(Request $request)
    {
        $user = Auth::user();
        $request->validate(['background' => 'required|image|max:5120']);

        // Delete old background if exists
        $oldPath = 'public/backgrounds/' . $user->id;
        $files = Storage::files($oldPath);
        foreach ($files as $file) {
            Storage::delete($file);
        }

        $file = $request->file('background');
        $ext = $file->getClientOriginalExtension();
        $filename = $user->id . '.' . $ext;
        $path = $file->storeAs('backgrounds', $filename, 'public');

        return response()->json([
            'url' => config('app.url') . Storage::url($path),
        ]);
    }

    /**
     * Get report background image by lab user ID (public).
     */
    public function getBackground($labId)
    {
        $files = Storage::disk('public')->files('backgrounds');
        foreach ($files as $file) {
            $basename = pathinfo($file, PATHINFO_FILENAME);
            if ($basename == $labId) {
                return response()->json([
                    'url' => Storage::url($file),
                ]);
            }
        }

        return response()->json(['url' => null]);
    }

    /**
     * Save print margins for the authenticated lab user.
     */
    public function saveMargins(Request $request)
    {
        $request->validate([
            'top' => 'required|numeric|min:0|max:100',
            'bottom' => 'required|numeric|min:0|max:100',
            'left' => 'required|numeric|min:0|max:100',
            'right' => 'required|numeric|min:0|max:100',
        ]);

        $user = Auth::user();
        $user->print_margins = $request->only(['top', 'bottom', 'left', 'right']);
        $user->save();

        return response()->json(['message' => 'Margins saved', 'margins' => $user->print_margins]);
    }

    /**
     * Get print margins for a lab user by ID (public).
     */
    public function getMargins($labId)
    {
        $user = User::find($labId);
        if (!$user || !$user->print_margins) {
            return response()->json(['margins' => ['top' => 20, 'bottom' => 20, 'left' => 15, 'right' => 15]]);
        }

        return response()->json(['margins' => $user->print_margins]);
    }
}
