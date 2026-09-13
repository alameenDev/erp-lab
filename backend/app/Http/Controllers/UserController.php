<?php

namespace App\Http\Controllers;

use App\Models\Referal;
use App\Models\User;
use App\Models\VerifyCode;
use App\Traits\SecureFileUpload;
use DateTime;
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
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $tenantIds = $this->getTenantUserIds();
        $users = User::whereIn('id', $tenantIds)->with(['role', 'referals', 'creator', 'lab', 'permissions'])
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
                    'permissions' => $user->permissions->pluck('name'),
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

        // SECURITY: Authorization check - verify access to this user
        // Users can view their own profile, or users they created (if admin/lab owner)
        if ($authUser->role_id !== 1) { // Not admin
            if ($user->id !== $authUser->id && $user->creator_id !== $authUser->id) {
                return response()->json(['message' => 'Unauthorized access to this user'], 403);
            }
        }

        $user_data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'image' => $user->image,
            'address' => $user->address,
            'role' => $user->role->name,
            'signiture' => $user->signiture,
            'commission' => $user->role_id == 6 ? $user->referals?->commission : $user->lab?->discount_percentage,
            'discount_percentage' => $user->role_id == 6 ? $user->referals?->commission : $user->lab?->discount_percentage,
            'permissions' => $user->permissions->pluck('name'),
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
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        // Validate incoming request
        $request->validate([
            'id' => 'required|integer',
            'name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpg,jpeg,png',
        ]);

        // Find the user by ID
        $user = User::find($request->id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // SECURITY: Authorization check - verify ownership
        // Admin can edit anyone, others can only edit users they created or themselves
        if ($authUser->role_id !== 1) { // Not admin
            if ($user->id !== $authUser->id && $user->creator_id !== $authUser->id) {
                return response()->json(['message' => 'Unauthorized to update this user'], 403);
            }
        }
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

        // Prevent non-admin users from escalating role to admin
        if ($request->filled('role_id')) {
            if ($authUser->role_id !== 1 && (int) $request->role_id === 1) {
                return response()->json(['message' => 'Cannot assign admin role'], 403);
            }
            $user->role_id = $request->role_id;
        }

        if (isset($request->discount_percentage)) {

            $referral = Referal::where('referral_id_fk', $user->id)->first();
            if ($referral) {
                $referral->commission = $request->discount_percentage ?? $referral->commission;
                $referral->save();
            }
        }
        // $user->email_verified_at = new DateTime();
        $user->save();
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
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        // Find the user by ID
        $user = User::find($request->id);

        // Check if user exists
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // SECURITY: Authorization check - verify ownership
        // Admin can delete anyone, others can only delete users they created
        // Users cannot delete themselves
        if ($authUser->role_id !== 1) { // Not admin
            if ($user->creator_id !== $authUser->id) {
                return response()->json(['message' => 'Unauthorized to delete this user'], 403);
            }
        }

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
