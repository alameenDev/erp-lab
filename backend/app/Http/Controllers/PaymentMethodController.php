<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentMethodController extends Controller
{
    // index api
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('payments view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $query = PaymentMethod::with('lab:id,name');

        // Admin (role_id = 1) can see all payment methods
        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->whereIn('lab_id_fk', $users_ids);
        }

        $paymentMethods = $query->orderBy('created_at', 'desc')->get();
        $paymentMethods = $paymentMethods->map(function ($paymentMethod) {
            return [
                'id' => $paymentMethod->id,
                'name' => $paymentMethod->name,
                'lab' => $paymentMethod->lab?->name,
                'amount' => $paymentMethod->amount,
                // 'invoice' => $paymentMethod->invoice?->invoice_no,
                // 'contract' => $paymentMethod->contract?->contract_no,
                // 'created_at' => $paymentMethod->created_at,
                // 'updated_at' => $paymentMethod->updated_at,
                // 'deleted_at' => $paymentMethod->deleted_at,

            ];
        });

        return response()->json($paymentMethods);
    }

    // store api
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('payments create')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // validation
        $request->validate([
            'name' => 'required|string',
        ]);
        $paymentMethod = new PaymentMethod;
        $paymentMethod->name = $request->name;
        $paymentMethod->lab_id_fk = Auth::user()->id;
        $paymentMethod->save();

        return response()->json($paymentMethod);
    }

    // show api
    public function show(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('payments view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $paymentMethod = PaymentMethod::with('lab:id,name')->find($request->id);
        if (!$paymentMethod) {
            return response()->json(['message' => 'Payment method not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (!in_array($paymentMethod->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        return response()->json($paymentMethod);
    }

    // update api
    public function update(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('payments edit')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $paymentMethod = PaymentMethod::find($request->id);
        if (!$paymentMethod) {
            return response()->json(['message' => 'Payment method not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (!in_array($paymentMethod->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $paymentMethod->name = $request->name;
        $paymentMethod->save();

        return response()->json($paymentMethod);
    }

    // destroy api
    public function destroy(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('payments delete')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $paymentMethod = PaymentMethod::find($request->id);
        if (!$paymentMethod) {
            return response()->json(['message' => 'Payment method not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (!in_array($paymentMethod->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $paymentMethod->delete();

        return response()->json('Payment method deleted successfully');
    }
}
