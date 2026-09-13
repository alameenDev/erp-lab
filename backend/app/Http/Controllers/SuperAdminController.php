<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class SuperAdminController extends Controller
{
    private function assertAdmin()
    {
        if (Auth::user()->role_id != 1) {
            abort(403, 'Unauthorized');
        }
    }

    public function systemKpis()
    {
        $this->assertAdmin();

        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        $totalLabs = User::where('role_id', 2)->count();
        $totalUsers = User::count();
        $totalInvoices = Invoice::count();
        $totalPatients = Patient::count();
        $activeSubscriptions = Subscription::active()->count();

        $revenueStats = Invoice::selectRaw('COALESCE(SUM(total), 0) as total_revenue, COALESCE(SUM(paid), 0) as total_paid')->first();

        // Month-over-month changes
        $newLabsThisMonth = User::where('role_id', 2)->where('created_at', '>=', $startOfMonth)->count();
        $newLabsLastMonth = User::where('role_id', 2)->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();

        $invoicesThisMonth = Invoice::where('created_at', '>=', $startOfMonth)->count();
        $invoicesLastMonth = Invoice::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();

        $revenueThisMonth = Invoice::where('created_at', '>=', $startOfMonth)->sum('total');
        $revenueLastMonth = Invoice::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->sum('total');

        return response()->json([
            'total_labs' => $totalLabs,
            'total_users' => $totalUsers,
            'total_invoices' => $totalInvoices,
            'total_revenue' => $revenueStats->total_revenue,
            'total_paid' => $revenueStats->total_paid,
            'total_patients' => $totalPatients,
            'active_subscriptions' => $activeSubscriptions,
            'changes' => [
                'new_labs' => $newLabsThisMonth,
                'new_labs_last_month' => $newLabsLastMonth,
                'invoices_this_month' => $invoicesThisMonth,
                'invoices_last_month' => $invoicesLastMonth,
                'revenue_this_month' => $revenueThisMonth,
                'revenue_last_month' => $revenueLastMonth,
            ],
        ]);
    }

    public function labComparison(Request $request)
    {
        $this->assertAdmin();

        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->toDateString();

        $labs = DB::table('invoices')
            ->join('users', 'invoices.lab_id_fk', '=', 'users.id')
            ->whereBetween('invoices.created_at', [$startDate, $endDate.' 23:59:59'])
            ->whereNull('invoices.deleted_at')
            ->groupBy('invoices.lab_id_fk', 'users.name', 'users.email')
            ->select(
                'invoices.lab_id_fk',
                'users.name as lab_name',
                'users.email as lab_email',
                DB::raw('COUNT(invoices.id) as invoice_count'),
                DB::raw('COALESCE(SUM(invoices.total), 0) as revenue'),
                DB::raw('COALESCE(SUM(invoices.paid), 0) as paid'),
                DB::raw('COUNT(DISTINCT invoices.patient_id_fk) as patient_count')
            )
            ->orderByDesc('revenue')
            ->get();

        return response()->json($labs);
    }

    public function revenueTrend(Request $request)
    {
        $this->assertAdmin();

        $months = $request->months ?? 6;

        $trend = DB::table('invoices')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', now()->subMonths($months)->startOfMonth())
            ->groupBy('month')
            ->select(
                DB::raw(in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)
                    ? "DATE_FORMAT(created_at, '%Y-%m-01') as month"
                    : "DATE_TRUNC('month', created_at) as month"),
                DB::raw('COALESCE(SUM(total), 0) as revenue'),
                DB::raw('COALESCE(SUM(paid), 0) as paid'),
                DB::raw('COUNT(*) as invoice_count')
            )
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                $item->month = date('Y-m', strtotime($item->month));

                return $item;
            });

        return response()->json($trend);
    }

    public function labDetail($labId)
    {
        $this->assertAdmin();

        $lab = User::with(['lab', 'subscription'])->findOrFail($labId);

        $invoiceStats = Invoice::where('lab_id_fk', $labId)
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(total), 0) as revenue, COALESCE(SUM(paid), 0) as paid')
            ->selectRaw('SUM(CASE WHEN is_done = true THEN 1 ELSE 0 END) as done_count')
            ->first();

        $patientCount = Patient::where('creator_id', $labId)->count();

        $branchLabs = User::where('creator_id', $labId)
            ->where('role_id', 4)
            ->select('id', 'name', 'email', 'phone_number', 'created_at')
            ->get();

        $recentInvoices = Invoice::where('lab_id_fk', $labId)
            ->with('patient:id,first_name,middle_name,last_name')
            ->select('id', 'patient_id_fk', 'total', 'paid', 'is_done', 'created_at', 'barcode')
            ->latest()
            ->limit(10)
            ->get();

        $monthlyRevenue = DB::table('invoices')
            ->where('lab_id_fk', $labId)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('month')
            ->select(
                DB::raw(in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)
                    ? "DATE_FORMAT(created_at, '%Y-%m-01') as month"
                    : "DATE_TRUNC('month', created_at) as month"),
                DB::raw('COALESCE(SUM(total), 0) as revenue'),
                DB::raw('COALESCE(SUM(paid), 0) as paid')
            )
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                $item->month = date('Y-m', strtotime($item->month));

                return $item;
            });

        return response()->json([
            'lab' => $lab,
            'invoice_stats' => $invoiceStats,
            'patient_count' => $patientCount,
            'branch_labs' => $branchLabs,
            'recent_invoices' => $recentInvoices,
            'monthly_revenue' => $monthlyRevenue,
        ]);
    }

    public function labsList(Request $request)
    {
        $this->assertAdmin();

        $query = User::where('role_id', 2)
            ->with(['lab', 'subscription']);

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereLike('name', "%{$search}%")
                    ->orWhereLike('email', "%{$search}%");
            });
        }

        if ($request->subscription_status) {
            $status = $request->subscription_status;
            $query->whereHas('subscription', function ($q) use ($status) {
                $q->where('status', $status);
            });
        }

        $labs = $query->withCount([
            'subscription as has_subscription' => function ($q) {
                $q->select(DB::raw('COUNT(*)'));
            },
        ])
            ->orderByDesc('created_at')
            ->paginate(25);

        // Add computed stats via subquery
        $labIds = $labs->pluck('id')->toArray();
        $invoiceStats = DB::table('invoices')
            ->whereIn('lab_id_fk', $labIds)
            ->whereNull('deleted_at')
            ->groupBy('lab_id_fk')
            ->select(
                'lab_id_fk',
                DB::raw('COUNT(*) as invoice_count'),
                DB::raw('COALESCE(SUM(total), 0) as total_revenue'),
                DB::raw('COUNT(DISTINCT patient_id_fk) as patient_count')
            )
            ->get()
            ->keyBy('lab_id_fk');

        $labsData = $labs->getCollection()->map(function ($lab) use ($invoiceStats) {
            $stats = $invoiceStats[$lab->id] ?? null;
            $lab->invoice_count = $stats?->invoice_count ?? 0;
            $lab->total_revenue = $stats?->total_revenue ?? 0;
            $lab->patient_count = $stats?->patient_count ?? 0;

            return $lab;
        });

        return response()->json([
            'data' => $labsData,
            'pagination' => [
                'current_page' => $labs->currentPage(),
                'total' => $labs->total(),
                'per_page' => $labs->perPage(),
                'last_page' => $labs->lastPage(),
            ],
        ]);
    }

    public function activityLog(Request $request)
    {
        $this->assertAdmin();

        $query = Activity::with('causer.role');

        if ($request->lab_id) {
            $query->where('creator_id', $request->lab_id);
        }

        if ($request->event) {
            $query->where('log_name', $request->event);
        }

        if ($request->date_from) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->where('created_at', '<=', $request->date_to.' 23:59:59');
        }

        if ($request->search) {
            $query->whereLike('description', "%{$request->search}%");
        }

        $activities = $query->orderByDesc('created_at')->paginate(50);

        // Batch-load missing causers to avoid N+1 on old records without causer_type
        $collection = $activities->getCollection();
        $missingIds = $collection->filter(fn ($a) => ! $a->causer && $a->causer_id)->pluck('causer_id')->unique();
        $missingCausers = $missingIds->isNotEmpty() ? User::with('role')->whereIn('id', $missingIds)->get()->keyBy('id') : collect();

        $activitiesData = $collection->map(function ($activity) use ($missingCausers) {
            $causer = $activity->causer ?: $missingCausers->get($activity->causer_id);

            return [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'description' => $activity->description,
                'subject_id' => $activity->subject_id,
                'subject_type' => last(explode('\\', $activity->subject_type ?? '')),
                'causer_id' => $activity->causer_id,
                'causer_name' => $causer?->name,
                'causer_role' => $causer?->role?->name,
                'creator_id' => $activity->creator_id,
                'properties' => $activity->properties,
                'created_at' => $activity->created_at,
            ];
        });

        return response()->json([
            'data' => $activitiesData,
            'pagination' => [
                'current_page' => $activities->currentPage(),
                'total' => $activities->total(),
                'per_page' => $activities->perPage(),
                'last_page' => $activities->lastPage(),
            ],
        ]);
    }

    public function patientsList(Request $request)
    {
        $this->assertAdmin();

        $query = Patient::with([
            'user:id,name,email,phone_number',
            'lab:id,name',
            'gender:id,gender_type',
            'nationality:id,country_name',
        ]);

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereLike('code', "%{$search}%")
                    ->orWhereLike('barcode', "%{$search}%")
                    ->orWhereHas('user', function ($q2) use ($search) {
                        $q2->whereLike('name', "%{$search}%")
                            ->orWhereLike('phone_number', "%{$search}%");
                    });
            });
        }

        if ($request->lab_id) {
            $query->where('creator_id', $request->lab_id);
        }

        if ($request->gender_id) {
            $query->where('gender_id_fk', $request->gender_id);
        }

        $patients = $query->orderByDesc('created_at')->paginate(25);

        // Get invoice counts for these patients
        $patientIds = $patients->pluck('id')->toArray();
        $invoiceCounts = DB::table('invoices')
            ->whereIn('patient_id_fk', $patientIds)
            ->whereNull('deleted_at')
            ->groupBy('patient_id_fk')
            ->select(
                'patient_id_fk',
                DB::raw('COUNT(*) as invoice_count'),
                DB::raw('COALESCE(SUM(total), 0) as total_amount'),
                DB::raw('COALESCE(SUM(paid), 0) as total_paid')
            )
            ->get()
            ->keyBy('patient_id_fk');

        $patientsData = $patients->getCollection()->map(function ($patient) use ($invoiceCounts) {
            $stats = $invoiceCounts[$patient->id] ?? null;

            return [
                'id' => $patient->id,
                'code' => $patient->code,
                'barcode' => $patient->barcode,
                'name' => $patient->user?->name,
                'phone' => $patient->user?->phone_number,
                'email' => $patient->user?->email,
                'lab_name' => $patient->lab?->name,
                'creator_id' => $patient->creator_id,
                'gender' => $patient->gender?->gender_type,
                'nationality' => $patient->nationality?->country_name,
                'age' => $patient->age,
                'dob' => $patient->dob?->format('Y-m-d'),
                'invoice_count' => $stats?->invoice_count ?? 0,
                'total_amount' => $stats?->total_amount ?? 0,
                'total_paid' => $stats?->total_paid ?? 0,
                'created_at' => $patient->created_at,
            ];
        });

        return response()->json([
            'data' => $patientsData,
            'pagination' => [
                'current_page' => $patients->currentPage(),
                'total' => $patients->total(),
                'per_page' => $patients->perPage(),
                'last_page' => $patients->lastPage(),
            ],
        ]);
    }

    public function patientDetail($patientId)
    {
        $this->assertAdmin();

        $patient = Patient::with([
            'user:id,name,email,phone_number,address',
            'lab:id,name',
            'gender:id,gender_type',
            'nationality:id,country_name',
            'title:id,title',
            'ageUnit:id,unit_name',
            'contract:id,name',
        ])->findOrFail($patientId);

        $invoices = Invoice::where('patient_id_fk', $patientId)
            ->select('id', 'lab_id_fk', 'total', 'paid', 'discount', 'is_done', 'created_at')
            ->with('lab:id,name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($inv) {
                return [
                    'id' => $inv->id,
                    'lab_name' => $inv->lab?->name,
                    'total' => $inv->total,
                    'paid' => $inv->paid,
                    'discount' => $inv->discount,
                    'remaining' => ($inv->total ?? 0) - ($inv->paid ?? 0) - ($inv->discount ?? 0),
                    'is_done' => $inv->is_done,
                    'created_at' => $inv->created_at,
                ];
            });

        $invoiceStats = DB::table('invoices')
            ->where('patient_id_fk', $patientId)
            ->whereNull('deleted_at')
            ->select(
                DB::raw('COUNT(*) as total_invoices'),
                DB::raw('COALESCE(SUM(total), 0) as total_amount'),
                DB::raw('COALESCE(SUM(paid), 0) as total_paid'),
                DB::raw('COALESCE(SUM(discount), 0) as total_discount'),
                DB::raw('SUM(CASE WHEN is_done = true THEN 1 ELSE 0 END) as done_count')
            )
            ->first();

        return response()->json([
            'patient' => [
                'id' => $patient->id,
                'code' => $patient->code,
                'barcode' => $patient->barcode,
                'name' => $patient->user?->name,
                'phone' => $patient->user?->phone_number,
                'email' => $patient->user?->email,
                'address' => $patient->user?->address,
                'lab_name' => $patient->lab?->name,
                'creator_id' => $patient->creator_id,
                'title' => $patient->title?->title,
                'gender' => $patient->gender?->gender_type,
                'nationality' => $patient->nationality?->country_name,
                'age' => $patient->age,
                'age_unit' => $patient->ageUnit?->unit_name,
                'dob' => $patient->dob?->format('Y-m-d'),
                'contract' => $patient->contract?->name,
                'image' => $patient->image,
                'created_at' => $patient->created_at,
            ],
            'stats' => $invoiceStats,
            'invoices' => $invoices,
        ]);
    }
}
