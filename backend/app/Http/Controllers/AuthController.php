<?php

namespace App\Http\Controllers;

use App\Models\Lab;
use App\Models\Referal;
use App\Models\User;
use App\Models\VerifyCode;
use App\Traits\SecureFileUpload;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    use SecureFileUpload;
    /**
     * Register a new user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        // Validate incoming request
        // SECURITY: Removed SVG from allowed types (XSS risk), use secure validation
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'role_id' => 'required|integer|in:2',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'signiture' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'sample_collector_id' => 'nullable|integer',
            'discount_percentage' => 'nullable|integer',
            'price_list_id' => 'nullable|integer',
        ], [
            'password.min' => 'Password must be at least 8 characters.',
        ]);

        // SECURITY: Use secure file upload with random filename and validation
        $image = '';
        $signiture = '';
        if ($request->hasFile('image')) {
            $result = $this->secureUploadImage($request->file('image'), 'users');
            if (!$result['success']) {
                return response()->json(['message' => $result['error']], 422);
            }
            $image = $result['url'];
        }

        if ($request->hasFile('signiture')) {
            $result = $this->secureUploadImage($request->file('signiture'), 'users');
            if (!$result['success']) {
                return response()->json(['message' => $result['error']], 422);
            }
            $signiture = $result['url'];
        }

        // Create a new user instance
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
            'creator_id' => Auth::check() ? Auth::user()->id : null,
            'phone_number' => $request->phone ?? null,
            'address' => $request->address ?? null,
            'image' => $image,
            'signiture' => $signiture,
        ]);
        if ($request->role_id == 4) {
            Lab::create([
                'user_id_fk' => $user->id,
                'parent_lab_id_fk' => Auth::check() ? Auth::user()->id : null,
                'sample_collector_id_fk' => $request->sample_collector_id ?? null,
                'discount_percentage' => $request->discount_percentage ?? null,
                'price_list_id_fk' => $request->price_list_id ?? null,
            ]);
        }
        if ($user) {
            $role = Role::findOrFail($request->role_id);

            // Remove existing roles first
            $user->roles()->detach();

            // Assign new role using Spatie's method
            $user->assignRole($role);

            // Update role_id if you need to maintain the legacy system
            $user->role_id = $role->id;

            // Auto-verify email (skip OTP verification)
            $user->email_verified_at = new DateTime;
            $user->save();

            // Load user relationships
            $user->load(['roles.permissions', 'permissions']);

            // log user register activity
            ActivityLogController::registerActivity('إنشاء حساب جديد: ' . $user->name);

            // Send welcome email
            try {
                Mail::to($user->email)->send(new \App\Mail\WelcomeMail($user));
            } catch (\Exception $e) {
                \Log::warning('Welcome email failed for ' . $user->email . ': ' . $e->getMessage());
            }

            // Auto-login: Generate an access token for the user
            $token = $user->createToken('authToken')->plainTextToken;

            // Return user and token (same as login)
            return response()->json([
                'message' => 'Registration successful',
                'user' => $user,
                'token' => $token
            ], 200);

        }

        // Return an error response
        return response()->json(['message' => 'Unable to register user'], 500);

    }

    /**
     * create a new user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate incoming request
        // SECURITY: Removed SVG from allowed types (XSS risk), use secure validation
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'role_id' => ['required', 'integer', 'exists:roles,id', function ($attribute, $value, $fail) {
                if ($value == 1 && Auth::user()?->role_id != 1) {
                    $fail('Cannot assign admin role.');
                }
            }],
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'signiture' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'sample_collector_id' => 'nullable|integer',
            'discount_percentage' => 'nullable|integer',
            'price_list_id' => 'nullable|integer',
            'commission' => 'nullable|integer',
        ], [
            'password.min' => 'Password must be at least 8 characters.',
        ]);

        // SECURITY: Use secure file upload with random filename and validation
        $image = '';
        $signiture = '';
        if ($request->hasFile('image')) {
            $result = $this->secureUploadImage($request->file('image'), 'users');
            if (!$result['success']) {
                return response()->json(['message' => $result['error']], 422);
            }
            $image = $result['url'];
        }

        if ($request->hasFile('signiture')) {
            $result = $this->secureUploadImage($request->file('signiture'), 'users');
            if (!$result['success']) {
                return response()->json(['message' => $result['error']], 422);
            }
            $signiture = $result['url'];
        }

        // Create a new user instance
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
            'email_verified_at' => new DateTime,
            'creator_id' => Auth::check() ? Auth::user()->id : null,
            'phone_number' => $request->phone ?? null,
            'address' => $request->address ?? null,
            'image' => $image,
            'signiture' => $signiture,
        ]);
        if ($user == null) {
            return response()->json(['message' => 'Unable to register user'], 500);
        }
        if ($request->role_id == 4) {
            Lab::create([
                'user_id_fk' => $user->id,
                'parent_lab_id_fk' => Auth::check() ? Auth::user()->id : null,
                'sample_collector_id_fk' => $request->sample_collector_id ?? null,
                'discount_percentage' => $request->discount_percentage ?? null,
                'price_list_id_fk' => $request->price_list_id ?? null,
            ]);
        } elseif ($request->role_id == 6) {
            Referal::create([
                'referral_id_fk' => $user->id,
                'lab_id_fk' => Auth::user()->id,
                'commission' => $request->discount_percentage ?? null,
            ]);
        }
        if ($user) {
            // $code = random_int(100000, 999999);
            // // save verification code
            // $codeModel = VerifyCode::where('email', $request->email)->first();
            // if ($codeModel) {
            //     $codeModel->email = $request->email;
            //     $codeModel->code = $code;
            //     $codeModel->save();
            // } else {
            //     VerifyCode::create([
            //         'email' => $request->email,
            //         'code' => $code
            //     ]);
            // }
            // log user register activity
            $role = Role::findOrFail($request->role_id);

            // Remove existing roles first
            $user->roles()->detach();

            // Assign new role using Spatie's method
            $user->assignRole($role);

            // Update role_id if you need to maintain the legacy system
            $user->role_id = $role->id;
            $user->save();
            $user->load(['roles.permissions', 'permissions']);

            ActivityLogController::registerActivity('إنشاء حساب جديد: ' . $user->name);

            // Send email verification code
            // EmailController::sendEmail($request->email, $code);
            return response()->json(['message' => 'Verification code has been sent to ' . $request->email . ' successfully, please verify your email'], 200);

        }

        // Return an error response
        return response()->json(['message' => 'Unable to register user'], 500);

    }

    /**
     * Log in an existing user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {

        // Validate incoming request
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        // Find the user by email
        $user = User::where('email', $request->email)->first();
        if ($user === null) {
            // Use generic message to prevent user enumeration
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Check if email is verified (security requirement)
        if ($user->email_verified_at === null) {
            return response()->json(['message' => 'Please verify your email before logging in.'], 401);
        }

        // Check if user account is active
        if ($user->status !== 1) {
            return response()->json(['message' => 'Your account has been deactivated.'], 401);
        }

        // Attempt to log the user in
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {

            // Check subscription for non-admin users
            if ($user->role_id != 1) {
                $labId = $user->role_id == 2 ? $user->id : $user->creator_id;
                if ($labId) {
                    $subscription = \App\Models\Subscription::where('lab_id_fk', $labId)
                        ->whereIn('status', ['active', 'trial'])
                        ->where('end_date', '>=', now()->toDateString())
                        ->first();
                    if (! $subscription) {
                        Auth::logout();

                        return response()->json([
                            'message' => 'انتهت الفترة التجريبية. يرجى التواصل مع قسم المبيعات: 07838334835',
                            'subscription_expired' => true,
                            'sales_phone' => '07838334835',
                        ], 403);
                    }
                }
            }

            // Generate an access token for the user
            $token = $request->user()->createToken('authToken')->plainTextToken;

            // Load user relationships including permissions
            $user->load(['roles.permissions', 'permissions']);

            // log user login activity
            ActivityLogController::loginActivity('تسجيل الدخول');

            // Return a successful response
            return response()->json(['message' => 'User logged in successfully', 'user' => $user, 'token' => $token], 200);
        }

        // Return an error response
        return response()->json(['message' => 'Invalid email or password'], 401);
    }

    public function forgetPassword(Request $request)
    {

        $user = User::where('email', $request->email)->first();

        if ($user) {
            $code = random_int(100000, 999999);
            // save verification code
            $codeModel = VerifyCode::where('email', $request->email)->first();
            if ($codeModel) {
                $codeModel->email = $request->email;
                $codeModel->code = $code;
                $codeModel->save();
            } else {
                VerifyCode::create([
                    'email' => $request->email,
                    'code' => $code,
                ]);
            }
            // log user register activity
            ActivityLogController::registerActivity('إرسال رمز إعادة تعيين كلمة المرور: ' . $user->name);

            // Send email verification code
            EmailController::sendEmail($request->email, $code);

            return response()->json(['message' => 'a password reset code has been sent to ' . $request->email . ' successfully, please check your email'], 200);

        } else {
            return response()->json(['error' => 'User does not exist'], 400);
        }
    }

    // reset password
    public function resetPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:8',
            'code' => 'required|string',
            'email' => 'required|email',
        ], [
            'password.min' => 'Password must be at least 8 characters.',
        ]);

        $user = User::where('email', $request->email)->first();
        if ($user === null) {
            return response()->json(['Result' => 'User not found.'], 404);
        }

        $code = VerifyCode::where('email', $user->email)->first();
        if ($code === null) {
            return response()->json(['Result' => 'No password reset code found.'], 404);
        }

        // Check code expiration (30 minutes)
        if ($code->created_at->addMinutes(30)->isPast()) {
            $code->delete();
            return response()->json(['Result' => 'Code has expired. Please request a new one.'], 400);
        }

        // Use constant-time comparison to prevent timing attacks
        if (hash_equals((string) $code->code, (string) $request->code)) {
            $user->password = Hash::make($request->password);
            $user->save();
            $code->delete();

            return response()->json(['Result' => 'Successfully changed password.'], 200);
        } else {
            return response()->json(['Result' => 'Invalid code.'], 400);
        }

    }

    // resend verification code
    public function resendCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users',
        ]);
        $email = $request->email;
        $verifyCode = VerifyCode::where('email', $email)->first();
        if ($verifyCode == null) {
            return response()->json(['Result' => 'Verification code does not exist for ' . $email . '.'], 400);
        }
        EmailController::sendEmail($email, $verifyCode->code);

        return response()->json(['Result' => 'Verification code has been re-sent to ' . $email . ' successfully.'], 200);
    }

    // verify code
    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'code' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();
        if ($user === null) {
            return response()->json(['Result' => 'User not found.'], 404);
        }

        $verifyCode = VerifyCode::where('email', $request->email)->first();
        if ($verifyCode === null) {
            return response()->json(['Result' => 'No verification code found.'], 404);
        }

        // Check code expiration (30 minutes)
        if ($verifyCode->created_at->addMinutes(30)->isPast()) {
            $verifyCode->delete();
            return response()->json(['Result' => 'Code has expired. Please request a new one.'], 400);
        }

        // Use constant-time comparison to prevent timing attacks
        if (hash_equals((string) $verifyCode->code, (string) $request->code)) {
            $user->email_verified_at = new DateTime;
            $user->save();
            $verifyCode->delete();

            return response()->json(['Result' => 'Email verified successfully.'], 200);
        } else {
            return response()->json(['Result' => 'Verification code is incorrect.'], 400);
        }
    }

    public function logoutUser(Request $request)
    {
        // Revoke access token
        // => Set oauth_access_tokens.revoked to TRUE (t)
        $request->user()->currentAccessToken()->delete();

        return response()->json(['Result' => 'Successfully logged out'], 200);
    }
}
