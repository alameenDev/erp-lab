<?php

namespace App\Http\Controllers;

use App\Models\Referal;
use App\Models\User;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ReferalController extends Controller
{
    public function createPortalAccount(Request $request)
    {
        abort_unless(Auth::user()->hasPermissionTo('referrals create'), 403);
        $labId = app(\App\Services\InventoryService::class)->labId(Auth::user());
        $v = $request->validate([
            'name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:12|max:128|confirmed',
            'phone_number' => 'nullable|string|max:50', 'address' => 'nullable|string|max:255',
            'role_id' => 'required|in:2,5',
            'commission' => 'nullable|integer|min:0',
            'price_list_id_fk' => ['required_if:role_id,2', 'nullable', \Illuminate\Validation\Rule::exists('price_lists', 'id')->where('lab_id_fk', $labId)->whereNull('deleted_at')],
        ]);
        return \Illuminate\Support\Facades\DB::transaction(function () use ($v, $labId) {
            $user = User::create(['name' => $v['name'], 'email' => $v['email'], 'password' => Hash::make($v['password']),
                'phone_number' => $v['phone_number'] ?? null, 'address' => $v['address'] ?? null,
                'creator_id' => $labId, 'role_id' => (int) $v['role_id'], 'status' => 1, 'email_verified_at' => now()]);
            $user->referral_portal_only = true;
            $user->save();
            $referral = Referal::create(['referral_id_fk' => $user->id, 'lab_id_fk' => $labId,
                'commission' => $v['commission'] ?? 0, 'price_list_id_fk' => $v['price_list_id_fk'] ?? null]);
            if ((int) $v['role_id'] === 2) \App\Models\ReferralLabProfile::create(['user_id_fk' => $user->id, 'display_name' => $v['name']]);
            ActivityLogController::registerActivity('إنشاء حساب بوابة إحالة رقم '.$referral->id);
            return response()->json(['id' => $referral->id, 'email' => $user->email], 201);
        });
    }

    public function enableDoctorPortal(Request $request, Referal $referral)
    {
        abort_unless(Auth::user()->hasPermissionTo('referrals edit'), 403);
        $labId = app(\App\Services\InventoryService::class)->labId(Auth::user());
        abort_unless((int) $referral->lab_id_fk === $labId && (int) $referral->user?->role_id === 5, 404);
        $v = $request->validate([
            'email' => ['required', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($referral->referral_id_fk)],
            'password' => 'required|string|min:12|max:128|confirmed',
        ]);
        $doctor = $referral->user;
        $doctor->email = $v['email'];
        $doctor->password = Hash::make($v['password']);
        $doctor->referral_portal_only = true;
        $doctor->email_verified_at = now();
        $doctor->status = 1;
        $doctor->save();
        ActivityLogController::registerActivity('تفعيل بوابة الطبيب للإحالة رقم '.$referral->id);
        return response()->json(['message' => 'تم تفعيل بوابة الطبيب', 'email' => $doctor->email]);
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo('referrals view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $query = Referal::with(['user.role', 'user.lab.priceList', 'lab', 'priceList']);

        // Admin (role_id = 1) can see all referrals
        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->whereIn('lab_id_fk', $users_ids);
        }

        $referals = $query->orderBy('created_at', 'desc')->get();
        if ($referals == null || empty($referals)) {
            return response()->json(['message' => 'No referals found'], 404);
        }
        $referals = $referals->map(function ($referral) {
            // For Lab-role referrals, surface the partner-lab's own discount
            // and price-list (from the labs table) so /invoices/create can apply
            // them automatically when the user picks this referral as from_lab.
            $partnerLab = $referral->user?->lab;

            return [
                'id' => $referral->id,
                'user_id' => $referral->user->id,
                'name' => $referral->user->name,
                'email' => $referral->user->email,
                'phone_number' => $referral->user->phone_number,
                'address' => $referral->user->address,
                'commission' => $referral->commission,
                'role' => $referral->user->role?->name,
                'lab' => $referral->lab?->name,
                'role_id' => $referral->user->role_id,
                'portal_enabled' => (bool) $referral->user->referral_portal_only,
                'price_list_id_fk' => $referral->price_list_id_fk,
                // Partner-lab fields (only populated for Lab-role referrals)
                'lab_discount_percentage' => $partnerLab?->discount_percentage,
                'lab_price_list_id_fk' => $partnerLab?->price_list_id_fk,
                'lab_price_list_discount' => $partnerLab?->priceList?->discount,
                // The flat discount % defined on the price-list itself (e.g. "10% off all items").
                // Surfaced so /invoices/create can apply it when there's no per-item override row.
                'price_list_discount' => $referral->priceList?->discount,
                'created_at' => $referral->created_at,
            ];
        });

        return response()->json($referals);
    }

    public function searchReferals(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo('referrals view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = User::with(['role', 'referals'])->whereLike('name', '%'.$request->name.'%')->whereIn('role_id', [2, 4, 5]);
        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->where(function ($q) use ($users_ids) {
                $q->whereIn('id', $users_ids)->orWhereIn('creator_id', $users_ids);
            });
        }
        $users = $query->get();
        if ($users == null || empty($users)) {
            return response()->json(['message' => 'No users found'], 404);
        }
        $users = $users->map(function ($user) {
            return [
                'id' => $user->referals?->id,
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'address' => $user->address,
                'role' => $user->role?->name,
                'commission' => $user->referals?->commission,
                'price_list_id_fk' => $user->referals?->price_list_id_fk,
                'created_at' => $user->referals?->created_at,
            ];

        });

        return response()->json($users);
    }

    public function show(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo('referrals view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $referrals = Referal::where('id', $request->id)->with(['user.role', 'lab'])->get();
        if (! $referrals || empty($referrals)) {
            return response()->json(['message' => 'Referal not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (! in_array($referrals->first()->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $referals = $referrals->map(function ($referral) {
            return [
                'id' => $referral->user->id,
                'user_id' => $referral->user->id,
                'name' => $referral->user->name,
                'email' => $referral->user->email,
                'phone_number' => $referral->user->phone_number,
                'address' => $referral->user->address,
                'commission' => $referral->commission,
                'role' => $referral->user->role?->name,
                'lab' => $referral->lab?->name,
                'price_list_id_fk' => $referral->price_list_id_fk,
                'created_at' => $referral->created_at,
            ];
        });

        return response()->json($referals->first());
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo('referrals create')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users',
            'phone_number' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'referral_id_fk' => 'nullable|exists:users,id',
            'commission' => 'nullable|integer',
            'role_id' => 'required|exists:roles,id',
        ]);

        if ($request->referal_id_fk != null) {
            $referral = Referal::create([
                'referral_id_fk' => $request->referal_id_fk,
                'lab_id_fk' => Auth::user()->id,
                'commission' => $request->commission ?? 0,
                'price_list_id_fk' => $request->price_list_id_fk ?? null,
            ]);

            return response()->json($referral, 201);
        } else {
            $email = $validatedData['email'] ?: 'ref_'.time().'_'.random_int(1000, 9999).'@placeholder.local';

            $user = User::create([
                'name' => $validatedData['name'],
                'email' => $email,
                'password' => Hash::make(Str::random(16)),
                'phone_number' => $validatedData['phone_number'] ?? '',
                'image' => '',
                'email_verified_at' => new DateTime,
                'creator_id' => Auth::user()->id,
                'address' => $validatedData['address'] ?? '',
                'role_id' => $validatedData['role_id'],
            ]);

            $referral = Referal::create([
                'referral_id_fk' => $user->id,
                'lab_id_fk' => Auth::user()->id,
                'commission' => $request->commission,
                'price_list_id_fk' => $request->price_list_id_fk ?? null,
            ]);

            return response()->json($referral, 201);
        }

    }

    public function update(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo('referrals edit')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'role_id' => 'required|exists:roles,id',
            'commission' => 'nullable|integer',
            'price_list_id_fk' => 'nullable|exists:price_lists,id',
        ]);
        $referral = Referal::find($request->id);
        if (! $referral) {
            return response()->json(['message' => 'Referal not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (! in_array($referral->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $user = User::find($referral->referral_id_fk);

        $user->name = $request->name ?? $user->name;
        $user->phone_number = $request->phone_number ?? $user->phone_number;
        $user->address = $request->address ?? $user->address;
        $user->role_id = $request->role_id ?? $user->role_id;
        $user->save();

        $referral->commission = $validatedData['commission'];

        $referral->price_list_id_fk = $request->price_list_id_fk ?? null;
        $referral->save();
        $referral = $referral->load(['user.role', 'lab']);
        $referral = [
            'id' => $referral->id,
            'user_id' => $referral->user->id,
            'name' => $referral->user->name,
            'email' => $referral->user->email,
            'phone_number' => $referral->user->phone_number,
            'address' => $referral->user->address,
            'commission' => $referral->commission,
            'role' => $referral->user->role->name,
            'lab' => $referral->lab->name,
            'price_list_id_fk' => $referral->price_list_id_fk,
        ];

        return response()->json($referral);
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo('referrals delete')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $referral = Referal::find($request->id);
        if (! $referral) {
            return response()->json(['message' => 'Referal not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (! in_array($referral->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $referral->delete();

        return response()->json(['message' => 'Referal deleted successfully']);
    }
}
