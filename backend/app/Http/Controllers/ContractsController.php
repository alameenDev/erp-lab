<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ContractsController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('contracts view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $query = Contract::with(['lab']);

        // Admin (role_id = 1) can see all contracts
        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->whereIn('lab_id_fk', $users_ids);
        }

        $contracts = $query->orderBy('created_at', 'desc')->get();
        $contractsJson = $contracts->map(function ($contract) {
            return [
                'id' => $contract->id,
                'name' => $contract->name,
                'payment_percent' => $contract->payment_percent,
                'maximum_payment_per_invoice' => $contract->maximum_payment_per_invoice,
                'credit_limit' => $contract->credit_limit,
                'price_limit' => $contract->price_limit,
                'discount_percentage' => $contract->discount_percentage,
                'address' => $contract->address,
                'phone_number' => $contract->phone_number,
                'email' => $contract->email,
                'lab' => $contract->lab?->name,
            ];
        });

        return response()->json($contractsJson);
    }

    public function show(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('contracts view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $contract = Contract::with(['lab'])->find($request->id);
        if (! $contract) {
            return response()->json(['message' => 'Contract not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser->role_id != 1 && $contract->lab_id_fk != $authUser->id && $contract->lab_id_fk != $authUser->creator_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $contract = [
            'id' => $contract->id,
            'name' => $contract->name,
            'payment_percent' => $contract->payment_percent,
            'maximum_payment_per_invoice' => $contract->maximum_payment_per_invoice,
            'credit_limit' => $contract->credit_limit,
            'price_limit' => $contract->price_limit,
            'discount_percentage' => $contract->discount_percentage,
            'address' => $contract->address,
            'phone_number' => $contract->phone_number,
            'email' => $contract->email,
            'lab' => $contract->lab?->name,
        ];

        return response()->json($contract);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('contracts create')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validatedData = $request->validate([
            'name' => 'nullable|string',
            'payment_percent' => 'nullable|integer',
            'maximum_payment_per_invoice' => 'nullable|integer',
            'credit_limit' => 'nullable|integer',
            'price_limit' => 'nullable|integer',
            'discount_percentage' => 'nullable|integer',
            'address' => 'nullable|string',
            'phone_number' => 'nullable|string',
            'email' => 'nullable|email',
            'password' => 'nullable|string',
        ]);

        if (!empty($validatedData['password'])) {
            $validatedData['password'] = Hash::make($validatedData['password']);
        } else {
            unset($validatedData['password']);
        }

        $contract = Contract::create([
            'name' => $validatedData['name'],
            'lab_id_fk' => Auth::user()->id,
            'payment_percent' => $validatedData['payment_percent'],
            'maximum_payment_per_invoice' => $validatedData['maximum_payment_per_invoice'],
            'credit_limit' => $validatedData['credit_limit'],
            'price_limit' => $validatedData['price_limit'],
            'discount_percentage' => $validatedData['discount_percentage'],
            'address' => $validatedData['address'],
            'phone_number' => $validatedData['phone_number'],
            'email' => $validatedData['email'],
            'password' => $validatedData['password'],
        ]);

        return response()->json($contract, 201);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('contracts edit')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $contract = Contract::find($request->id);
        if (! $contract) {
            return response()->json(['message' => 'Contract not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser->role_id != 1 && $contract->lab_id_fk != $authUser->id && $contract->lab_id_fk != $authUser->creator_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validatedData = $request->validate([
            'name' => 'nullable|string',
            'payment_percent' => 'nullable|integer',
            'maximum_payment_per_invoice' => 'nullable|integer',
            'credit_limit' => 'nullable|integer',
            'price_limit' => 'nullable|integer',
            'discount_percentage' => 'nullable|integer',
            'address' => 'nullable|string',
            'phone_number' => 'nullable|string',
            'email' => 'nullable|email',
            'password' => 'nullable|string',
        ]);

        if (isset($validatedData['password'])) {
            $validatedData['password'] = Hash::make($validatedData['password']);
        } else {
            unset($validatedData['password']);
        }

        $updateData = [
            'name' => $validatedData['name'],
            'payment_percent' => $validatedData['payment_percent'],
            'maximum_payment_per_invoice' => $validatedData['maximum_payment_per_invoice'],
            'credit_limit' => $validatedData['credit_limit'],
            'price_limit' => $validatedData['price_limit'],
            'discount_percentage' => $validatedData['discount_percentage'],
            'address' => $validatedData['address'],
            'phone_number' => $validatedData['phone_number'],
            'email' => $validatedData['email'],
        ];

        if (isset($validatedData['password'])) {
            $updateData['password'] = $validatedData['password'];
        }

        $contract->update($updateData);

        return response()->json($contract);
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('contracts delete')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $contract = Contract::find($request->id);
        if (! $contract) {
            return response()->json(['message' => 'Contract not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser->role_id != 1 && $contract->lab_id_fk != $authUser->id && $contract->lab_id_fk != $authUser->creator_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $contract->delete();

        return response()->json(['message' => 'Contract deleted successfully']);
    }
}
