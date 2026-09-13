<?php

namespace App\Http\Controllers;

use App\Http\Resources\SampleCollectorResource;
use App\Models\Lab;
use App\Models\User;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class LabController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $query = Lab::with([
            'priceList' => function ($query): void {
                $query->select('id');
            },
            'user',
            'sampleCollector',
        ]);

        // Admin (role_id = 1) can see all labs
        if ($user->role_id != 1) {
            $query->where('parent_lab_id_fk', $user->id);
        }

        $labs = $query->latest()->get();
        if ($labs == null || empty($labs)) {
            return response()->json(['message' => 'No labs found'], 404);
        }
        $labs_data = $labs->map(function ($lab) {
            return [
                'id' => $lab->id,
                'name' => $lab->user?->name,
                'image' => $lab->user?->image,
                'phone_number' => $lab->user?->phone_number,
                'email' => $lab->user?->email,
                'address' => $lab->user?->address,
                'sample_collector' => $lab->sampleCollector,
                'parent_lab_id' => $lab->parent_lab_id_fk,
                'price_list_id' => $lab->priceList?->id,
                'discount_percentage' => $lab->discount_percentage,
            ];
        });

        return response()->json($labs_data);

    }

    public function collector()
    {
        $user = Auth::user();

        // Properly group OR conditions and filter by role_id in the database query
        $sample_collectors = User::with(['role', 'referals', 'creator', 'lab'])
            ->where('role_id', 6)
            ->where(function ($query) use ($user): void {
                $query->where('creator_id', $user->id)
                    ->orWhere('creator_id', $user->creator_id);
            })
            ->latest()
            ->get();

        return response()->json(SampleCollectorResource::collection($sample_collectors));
    }

    public function show(Request $request)
    {
        $labs = Lab::with([
            'priceList' => function ($query): void {
                $query->select('id');
            },
            'user.referals',
            'sampleCollector',
        ])->find($request->id);

        if ($labs == null || empty($labs)) {
            return response()->json(['message' => 'No labs found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (!in_array($labs->parent_lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $labs_data = [
            'id' => $labs->id,
            'name' => $labs->user?->name,
            'address' => $labs->user?->address,
            'image' => $labs->user?->image,
            'phone_number' => $labs->user?->phone_number,
            'email' => $labs->user?->email,
            'sample_collector' => $labs->sampleCollector,
            'parent_lab_id' => $labs->parent_lab_id,
            'price_list_id' => $labs->priceList?->id,
            'discount_percentage' => $labs->discount_percentage,
            'signiture' => $labs->user?->signiture,
            'commission' => $labs->user?->referals?->commission,
        ];

        return response()->json($labs_data);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'role_id' => 'required|integer',
            'price_list_id' => 'nullable|integer',
            'discount_percentage' => 'nullable|integer',
            'sample_collector_id' => 'nullable|integer',

        ]);

        if (Auth::user()->role_id == 1 && $validatedData['role_id'] == 4) {
            return response()->json(['message' => 'only labs can create branch labs'], 403);
        }

        $image = '';
        if ($request->hasFile('image')) {

            $filename = \Illuminate\Support\Str::uuid().'_'.time().'.'.$request->image->getClientOriginalExtension();
            $path = $request->image->storeAs('labs', $filename, 'public');
            $image = config('app.url').Storage::url($path);

        }

        $signiture = '';
        if ($request->hasFile('signiture')) {
            $filename = \Illuminate\Support\Str::uuid().'_'.time().'.'.$request->signiture->getClientOriginalExtension();
            $signiture_path = $request->signiture->storeAs('labs', $filename, 'public');
            $signiture = config('app.url').Storage::url($signiture_path);
        }

        // Create a new user
        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
            'phone_number' => $validatedData['phone'] ?? null,
            'signiture' => $signiture ?? null,
            'image' => $image ?? null,
            'email_verified_at' => new DateTime,
            'creator_id' => Auth::user()->id,
            'address' => $validatedData['address'] ?? null,
            'role_id' => $validatedData['role_id'],
        ]);

        if (! $user) {
            return response()->json(['message' => 'Failed to create user'], 500);
        }
        ActivityLogController::storeActivity('إنشاء حساب جديد: '.$user->name, $user);
        // Create a new lab
        $lab = Lab::create([
            'user_id_fk' => $user->id,
            'parent_lab_id_fk' => Auth::user()->id,
            'price_list_id_fk' => $validatedData['price_list_id'] ?? null,
            'sample_collector_id_fk' => $validatedData['sample_collector_id'] ?? null,
            'discount_percentage' => $validatedData['discount_percentage'] ?? null,
        ]);
        if (! $lab) {
            return response()->json(['message' => 'Failed to create lab'], 500);
        }
        $lab->load('user');
        ActivityLogController::storeActivity('إنشاء مختبر جديد: '.$lab->user->name, $lab);

        return response()->json($lab, 201);
    }

    public function update(Request $request)
    {
        $lab = Lab::find($request->id);
        if (! $lab) {
            return response()->json(['message' => 'Lab not found', 'id' => $request->id], 404);
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (!in_array($lab->parent_lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $user = User::find($lab->user_id_fk);

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $validatedData = $request->validate([
            'email' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:255',
            'sample_collector_id_fk' => 'nullable|integer',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'discount_percentage' => 'nullable|integer',
            'price_list_id' => 'nullable|integer',
        ]);
        // add image
        if ($request->hasFile('image')) {
            $last = explode('storage/', $user->image);
            if (count($last) > 1) {
                $url = 'public/'.$last[1]; //  Storage::url($last[1]);
                if ($user->image && Storage::exists($url)) {
                    Storage::delete($url);
                }
            }
            $filename = \Illuminate\Support\Str::uuid().'_'.time().'.'.$request->image->getClientOriginalExtension();
            $path = $request->image->storeAs('users', $filename, 'public');
            $user->image = config('app.url').Storage::url($path);
        }

        if ($request->hasFile('signiture')) {
            $last = explode('storage/', $user->signiture);
            if (count($last) > 1) {
                $url = 'public/'.$last[1];
                if ($user->signiture && Storage::exists($url)) {
                    Storage::delete($url);
                }
            }
            $filename = \Illuminate\Support\Str::uuid().'_'.time().'.'.$request->signiture->getClientOriginalExtension();
            $signiture = $request->signiture->storeAs('users', $filename, 'public');
            $user->signiture = config('app.url').Storage::url($signiture);
        }

        // update user data
        $user->name = $validatedData['name'] ?? $user->name;
        $user->phone_number = $validatedData['phone_number'] ?? $user->phone_number;
        $user->address = $validatedData['address'] ?? $user->address;
        $user->save();

        // update lab data
        $lab->discount_percentage = $validatedData['discount_percentage'] ?? $lab->discount_percentage;
        $lab->price_list_id_fk = $validatedData['price_list_id'] ?? $lab->price_list_id;
        $lab->sample_collector_id_fk = $validatedData['sample_collector_id_fk'] ?? $lab->sample_collector_id_fk;
        $lab->save();

        return response()->json($lab);
    }

    public function destroy(Request $request)
    {
        $lab = Lab::with('user')->find($request->id);
        if (! $lab) {
            return response()->json(['message' => 'Lab not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (!in_array($lab->parent_lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $lab->delete();
        ActivityLogController::deleteActivity('حذف مختبر: '.$lab->user->name, $lab);

        return response()->json(['message' => 'Lab deleted successfully']);
    }
}
