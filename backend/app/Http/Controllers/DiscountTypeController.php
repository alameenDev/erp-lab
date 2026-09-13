<?php

namespace App\Http\Controllers;

use App\Models\DiscountType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DiscountTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = DiscountType::query();

        // Admin (role_id = 1) can see all discount types
        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->whereIn('lab_id_fk', $users_ids);
        }

        $discountTypes = $query->latest()->get();

        return response()->json($discountTypes);
    }

    public function show(Request $request)
    {
        $discountType = DiscountType::find($request->id);
        if (!$discountType) {
            return response()->json(['message' => 'Discount type not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser->role_id != 1 && $discountType->lab_id_fk != $authUser->id && $discountType->lab_id_fk != $authUser->creator_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($discountType);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // validation
        $request->validate([
            'type' => 'required|string',
        ]);

        // store
        $discountType = new DiscountType;
        $discountType->type = $request->type;
        $discountType->lab_id_fk = Auth::user()->id;
        $discountType->save();

        return response()->json($discountType);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        // validation
        $request->validate([
            'type' => 'required|string',
        ]);
        $discountType = DiscountType::find($request->id);
        if (!$discountType) {
            return response()->json(['message' => 'Discount type not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser->role_id != 1 && $discountType->lab_id_fk != $authUser->id && $discountType->lab_id_fk != $authUser->creator_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $discountType->type = $request->type;
        $discountType->save();

        return response()->json($discountType);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        $discountType = DiscountType::find($request->id);
        if (!$discountType) {
            return response()->json(['message' => 'Discount type not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser->role_id != 1 && $discountType->lab_id_fk != $authUser->id && $discountType->lab_id_fk != $authUser->creator_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $discountType->delete();

        return response()->json('Discount type deleted successfully');
    }
}
