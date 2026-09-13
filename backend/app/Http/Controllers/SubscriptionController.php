<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    private function assertAdmin()
    {
        if (Auth::user()->role_id != 1) {
            abort(403, 'Unauthorized');
        }
    }

    public function plans()
    {
        $this->assertAdmin();

        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();

        return response()->json($plans);
    }

    public function index(Request $request)
    {
        $this->assertAdmin();

        $query = Subscription::with(['lab:id,name,email,phone_number', 'plan:id,name,name_ar']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->lab_id) {
            $query->where('lab_id_fk', $request->lab_id);
        }

        if ($request->expiring_soon) {
            $query->where('status', 'active')
                ->where('end_date', '<=', now()->addDays(7)->toDateString())
                ->where('end_date', '>=', now()->toDateString());
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereLike('plan_name', "%{$search}%")
                    ->orWhereHas('lab', function ($q2) use ($search) {
                        $q2->whereLike('name', "%{$search}%")
                            ->orWhereLike('email', "%{$search}%");
                    });
            });
        }

        $subscriptions = $query->orderByDesc('created_at')->paginate(25);

        return response()->json([
            'data' => $subscriptions->items(),
            'pagination' => [
                'current_page' => $subscriptions->currentPage(),
                'total' => $subscriptions->total(),
                'per_page' => $subscriptions->perPage(),
                'last_page' => $subscriptions->lastPage(),
            ],
        ]);
    }

    public function show($id)
    {
        $this->assertAdmin();

        $subscription = Subscription::with('lab:id,name,email,phone_number')->findOrFail($id);

        return response()->json($subscription);
    }

    public function store(Request $request)
    {
        $this->assertAdmin();

        $request->validate([
            'lab_id_fk' => 'required|exists:users,id',
            'plan_id_fk' => 'nullable|exists:plans,id',
            'plan_name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:active,expired,suspended,trial',
            'max_users' => 'nullable|integer|min:1',
            'max_invoices_per_month' => 'nullable|integer|min:1',
            'price' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|max:10',
            'notes' => 'nullable|string',
        ]);

        // Verify the user is a lab (role_id=2)
        $lab = User::findOrFail($request->lab_id_fk);
        if ($lab->role_id != 2) {
            return response()->json(['message' => 'المستخدم المحدد ليس مختبراً'], 422);
        }

        $subscription = Subscription::create([
            'lab_id_fk' => $request->lab_id_fk,
            'plan_id_fk' => ($request->plan_id_fk ?? null) ?: null,
            'plan_name' => $request->plan_name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => $request->status,
            'max_users' => $request->max_users,
            'max_invoices_per_month' => $request->max_invoices_per_month,
            'price' => $request->price ?? 0,
            'currency' => $request->currency ?? 'IQD',
            'notes' => $request->notes,
        ]);

        ActivityLogController::storeActivity('إنشاء اشتراك: '.$subscription->plan_name, $subscription);

        return response()->json($subscription->load('lab:id,name,email,phone_number'), 201);
    }

    public function update(Request $request)
    {
        $this->assertAdmin();

        $request->validate([
            'id' => 'required|exists:subscriptions,id',
            'lab_id_fk' => 'required|exists:users,id',
            'plan_id_fk' => 'nullable|exists:plans,id',
            'plan_name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:active,expired,suspended,trial',
            'max_users' => 'nullable|integer|min:1',
            'max_invoices_per_month' => 'nullable|integer|min:1',
            'price' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|max:10',
            'notes' => 'nullable|string',
        ]);

        $subscription = Subscription::findOrFail($request->id);
        $old = $subscription->replicate();

        $subscription->update([
            'lab_id_fk' => $request->lab_id_fk,
            'plan_id_fk' => ($request->plan_id_fk ?? null) ?: null,
            'plan_name' => $request->plan_name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => $request->status,
            'max_users' => $request->max_users,
            'max_invoices_per_month' => $request->max_invoices_per_month,
            'price' => $request->price ?? 0,
            'currency' => $request->currency ?? 'IQD',
            'notes' => $request->notes,
        ]);

        ActivityLogController::updateActivity('تعديل اشتراك: '.$subscription->plan_name, $old, $subscription);

        return response()->json($subscription->load('lab:id,name,email,phone_number'));
    }

    public function mySubscription()
    {
        $user = Auth::user();
        if ($user->role_id == 1) {
            return response()->json(['has_subscription' => true, 'subscription' => null]);
        }

        $labId = $user->role_id == 2 ? $user->id : $user->creator_id;
        $subscription = Subscription::where('lab_id_fk', $labId)
            ->with('plan:id,name,name_ar')
            ->orderByDesc('end_date')
            ->first();

        if (! $subscription) {
            return response()->json([
                'has_subscription' => false,
                'expired' => true,
                'message' => 'انتهت الفترة التجريبية. يرجى التواصل مع قسم المبيعات: 07838334835',
                'sales_phone' => '07838334835',
            ]);
        }

        $isActive = in_array($subscription->status, ['active', 'trial']) && $subscription->end_date >= now()->toDateString();
        $daysLeft = max(0, (int) now()->diffInDays($subscription->end_date, false));

        return response()->json([
            'has_subscription' => $isActive,
            'expired' => ! $isActive,
            'days_left' => $daysLeft,
            'subscription' => $subscription,
            'message' => ! $isActive ? 'انتهت الفترة التجريبية. يرجى التواصل مع قسم المبيعات: 07838334835' : null,
            'sales_phone' => '07838334835',
        ]);
    }

    public function destroy(Request $request)
    {
        $this->assertAdmin();

        $subscription = Subscription::findOrFail($request->id);
        ActivityLogController::deleteActivity('حذف اشتراك: '.$subscription->plan_name, $subscription);
        $subscription->delete();

        return response()->json(['message' => 'تم حذف الاشتراك بنجاح']);
    }
}
