<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceTestRel;
use App\Models\Patient;
use App\Models\PriceListRel;
use App\Models\Referal;
use App\Models\ReferralLabProfile;
use App\Models\Test;
use App\Models\User;
use App\Traits\SecureFileUpload;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ReferralPortalController extends Controller
{
    use SecureFileUpload;

    /**
     * Every action here is scoped to the CURRENT user acting as a referral
     * partner (their own rel_labs_referals rows as referral_id_fk) -
     * independent of role_id, since a referral can be a lab or a doctor
     * account created however the main lab's staff set it up.
     */
    private function assertReferralPartner(): int
    {
        $userId = Auth::id();
        if (! Referal::where('referral_id_fk', $userId)->exists()) {
            abort(403, 'هذا الحساب غير مسجل كجهة إحالة');
        }

        return $userId;
    }

    /**
     * The main lab(s) this account sends samples to, each with its own
     * lab-to-lab price list (a referral can be connected to more than one
     * main lab).
     */
    public function options() {
        $this->assertReferralPartner();
        return response()->json(['genders' => \App\Models\Gender::whereIn('gender_type', ['Male', 'Female'])->get(['id','gender_type'])]);
    }

    public function connections()
    {
        $userId = $this->assertReferralPartner();

        $rows = Referal::with('lab:id,name')
            ->where('referral_id_fk', $userId)
            ->get(['id', 'lab_id_fk', 'price_list_id_fk', 'commission']);

        return response()->json($rows->map(fn ($r) => [
            'lab_id' => $r->lab_id_fk,
            'lab_name' => $r->lab?->name,
            'price_list_id' => $r->price_list_id_fk,
        ]));
    }

    public function profile()
    {
        $userId = $this->assertReferralPartner();
        $profile = ReferralLabProfile::firstOrCreate(
            ['user_id_fk' => $userId],
            ['display_name' => Auth::user()->name]
        );

        return response()->json($profile);
    }

    public function updateProfile(Request $request)
    {
        $userId = $this->assertReferralPartner();
        $validated = $request->validate([
            'display_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        $profile = ReferralLabProfile::firstOrCreate(['user_id_fk' => $userId]);

        if ($request->hasFile('logo')) {
            $oldLogo = $profile->getRawOriginal('logo');
            $result = $this->secureUploadImage($request->file('logo'), 'referral-logos');
            if (! $result['success']) {
                return response()->json(['message' => $result['error']], 422);
            }
            $validated['logo'] = $result['path'];
        }

        try { $profile->update($validated); } catch (\Throwable $e) {
            if (isset($result['path'])) $this->safeDeleteFile($result['path']);
            throw $e;
        }
        if (!empty($oldLogo)) $this->safeDeleteFile($oldLogo);

        return response()->json($profile);
    }

    /**
     * The lab-to-lab price list for a specific connected main lab.
     */
    public function priceList($labId)
    {
        $userId = $this->assertReferralPartner();
        $connection = Referal::where('referral_id_fk', $userId)->where('lab_id_fk', $labId)->first();
        if (! $connection) {
            return response()->json(['message' => 'غير مرتبط بهذا المختبر'], 403);
        }

        if (! $connection->price_list_id_fk) {
            return response()->json(['tests' => []]);
        }

        $tests = $this->allowedPrices($connection)->map(fn ($r) => [
            'test_id' => $r->test_id_fk, 'name' => $r->test->name,
            'price' => $this->price($r, $connection),
        ])->values();

        return response()->json(['tests' => $tests]);
    }

    /**
     * List this referral's own samples/invoices, most recent first.
     */
    public function index(Request $request)
    {
        $userId = $this->assertReferralPartner();

        $query = $this->ownInvoices($userId)->with('patient.user')->orderByDesc('created_at');

        $invoices = $query->paginate(max(1, min(100, $request->integer('per_page', 25))));
        $invoices->getCollection()->transform(fn ($inv) => [
            'id' => $inv->id,
            'barcode' => $inv->barcode,
            'patient_name' => $inv->patient?->user?->name,
            'status' => $inv->is_done ? 'ready' : 'pending',
            'is_new' => $inv->is_done && ! $inv->referral_seen_at,
            'created_at' => $inv->created_at,
            'updated_at' => $inv->updated_at,
        ]);

        $unseenReadyCount = $this->ownInvoices($userId)
            ->where('is_done', true)->whereNull('referral_seen_at')->count();

        return response()->json(['invoices' => $invoices, 'unseen_ready_count' => $unseenReadyCount]);
    }

    /**
     * Financial position of this referral partner with each connected lab.
     * The lab invoice and its recorded payment rows are the source of truth
     * for amounts owed to the performing lab. Customer collections in
     * referral_invoice_details are shown separately, never counted as lab
     * settlements.
     */
    public function financialReport(Request $request)
    {
        $userId = $this->assertReferralPartner();
        $filters = $request->validate([
            'lab_id' => 'nullable|integer',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'status' => 'nullable|in:unpaid,partial,paid',
            'page' => 'nullable|integer|min:1',
        ]);
        $connections = Referal::with('lab:id,name')->where('referral_id_fk', $userId)->get()->unique('lab_id_fk');
        $labIds = $connections->pluck('lab_id_fk');
        if (isset($filters['lab_id'])) abort_unless($labIds->contains((int) $filters['lab_id']), 403);

        $paymentTotals = DB::table('invoice_paid_details')
            ->select('invoice_id_fk')
            ->selectRaw('COALESCE(SUM(amount), 0) AS paid_sum')
            ->groupBy('invoice_id_fk');
        $paidSql = 'COALESCE(payment_totals.paid_sum, 0)';
        $balanceSql = "CASE WHEN invoices.total > {$paidSql} THEN invoices.total - {$paidSql} ELSE 0 END";
        $query = $this->ownInvoices($userId)
            ->leftJoinSub($paymentTotals, 'payment_totals', 'payment_totals.invoice_id_fk', '=', 'invoices.id');
        if (isset($filters['lab_id'])) $query->where('invoices.lab_id_fk', $filters['lab_id']);
        if (isset($filters['from'])) $query->where('invoices.created_at', '>=', $filters['from'].' 00:00:00');
        if (isset($filters['to'])) $query->where('invoices.created_at', '<=', $filters['to'].' 23:59:59');
        if (($filters['status'] ?? null) === 'unpaid') $query->whereRaw("{$paidSql} = 0 AND invoices.total > 0");
        if (($filters['status'] ?? null) === 'partial') $query->whereRaw("{$paidSql} > 0 AND {$paidSql} < invoices.total");
        if (($filters['status'] ?? null) === 'paid') $query->whereRaw("{$paidSql} >= invoices.total");

        $totals = (clone $query)->select('invoices.lab_id_fk')
            ->selectRaw("COUNT(*) AS invoice_count, COALESCE(SUM(invoices.total), 0) AS total_due, COALESCE(SUM({$paidSql}), 0) AS total_paid, COALESCE(SUM({$balanceSql}), 0) AS total_balance")
            ->groupBy('invoices.lab_id_fk')->get()->keyBy('lab_id_fk');
        $labs = $connections->filter(fn ($connection) => ! isset($filters['lab_id']) || (int) $connection->lab_id_fk === (int) $filters['lab_id'])
            ->map(function ($connection) use ($totals) {
                $row = $totals->get($connection->lab_id_fk);
                return [
                    'lab_id' => $connection->lab_id_fk,
                    'lab_name' => $connection->lab?->name ?? 'مختبر',
                    'invoice_count' => (int) ($row?->invoice_count ?? 0),
                    'total_due' => (int) ($row?->total_due ?? 0),
                    'total_paid' => (int) ($row?->total_paid ?? 0),
                    'total_balance' => (int) ($row?->total_balance ?? 0),
                ];
            })->values();

        $invoices = (clone $query)->select('invoices.*')->selectRaw("{$paidSql} AS recorded_paid")
            ->with(['lab:id,name', 'patient.user:id,name', 'paidDetails.paymentMethod:id,name',
                'invoiceTestRels.test:id,name', 'invoiceTestRels.culture:id,name',
                'invoiceTestRels.package:id,name', 'invoiceTestRels.testGroup:id,group_name'])
            ->orderByDesc('invoices.created_at')->orderByDesc('invoices.id')->paginate(25);
        $ownFinancials = DB::table('referral_invoice_details')->where('referral_id', $userId)
            ->whereIn('invoice_id', $invoices->getCollection()->pluck('id'))->get()->keyBy('invoice_id');
        $invoices->getCollection()->transform(function ($invoice) use ($ownFinancials) {
            $due = (int) $invoice->total;
            $paid = (int) $invoice->recorded_paid;
            $own = $ownFinancials->get($invoice->id);
            return [
                'id' => $invoice->id, 'barcode' => $invoice->barcode,
                'created_at' => $invoice->created_at,
                'lab_id' => $invoice->lab_id_fk, 'lab_name' => $invoice->lab?->name,
                'patient_name' => $invoice->patient?->user?->name,
                'total_due' => $due, 'total_paid' => $paid,
                'balance' => max(0, $due - $paid),
                'payment_status' => $paid >= $due ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
                'payments' => $invoice->paidDetails->map(fn ($payment) => [
                    'amount' => (int) $payment->amount,
                    'method' => $payment->paymentMethod?->name,
                    'date' => $payment->created_at,
                ])->values(),
                'items' => $invoice->invoiceTestRels->map(fn ($item) => [
                    'name' => $item->test?->name ?? $item->culture?->name ?? $item->package?->name ?? $item->testGroup?->group_name ?? 'فحص',
                    'price' => (int) $item->price,
                ])->values(),
                'customer_invoice' => $own ? ['total' => (int) $own->total, 'collected' => (int) $own->paid] : null,
            ];
        });
        return response()->json(['labs' => $labs, 'invoices' => $invoices]);
    }

    public function show($id)
    {
        $userId = $this->assertReferralPartner();
        $invoice = $this->ownInvoices($userId)->with([
            'patient.user', 'patient.gender', 'invoiceTestRels.test.testReferenceRanges.gender',
            'invoiceTestRels.test.testReferenceRanges.ageUnit', 'invoiceTestRels.testGroup', 'lab',
        ])->findOrFail($id);

        if ($invoice->is_done && ! $invoice->referral_seen_at) {
            $invoice->referral_seen_at = now();
            $invoice->save();
        }

        return response()->json([
            'id' => $invoice->id,
            'barcode' => $invoice->barcode,
            'status' => $invoice->is_done ? 'ready' : 'pending',
            'created_at' => $invoice->created_at,
            'result_date' => $invoice->result_date,
            'performing_lab' => $invoice->lab?->name,
            'patient' => [
                'name' => $invoice->patient?->user?->name,
                'phone' => $invoice->patient?->user?->phone_number,
                'code' => $invoice->patient?->code,
                'dob' => $invoice->patient?->dob?->format('Y-m-d'),
                'gender' => $invoice->patient?->gender?->gender_type,
            ],
            'tests' => $invoice->invoiceTestRels->map(fn ($t) => [
                'name' => $t->test?->report_name ?: ($t->test?->name ?? $t->testGroup?->group_name),
                'price' => $t->price,
                'result' => $invoice->is_done && $t->is_done ? $t->result : null,
                'unit' => $t->test?->unit,
                'sub_tests' => $invoice->is_done && $t->is_done ? collect($t->sub_tests ?? [])->map(fn ($s) => \Illuminate\Support\Arr::only($s, ['name', 'value', 'unit', 'comment']))->values() : [],
                'comment' => $invoice->is_done && $t->is_done ? $t->comment : null,
                'reference_ranges' => $t->test?->testReferenceRanges->whereNull('deleted_at')->where('lab_id_fk', $invoice->lab_id_fk)->map(fn ($r) => [
                    'from' => $r->from, 'to' => $r->to, 'notes' => $r->notes,
                    'gender' => $r->gender?->gender_type, 'age_from' => $r->age_from, 'age_to' => $r->age_to,
                    'age_unit' => $r->ageUnit?->unit_name,
                ])->values() ?? [],
                'result_status_text' => $invoice->is_done && $t->is_done ? $t->result_status_text : null,
                'is_done' => $t->is_done,
            ]),
        ]);
    }

    /** Only invoices belonging to this partner and a currently connected lab. */
    private function ownInvoices(int $userId) {
        return Invoice::where('from_lab_id_fk', $userId)->whereIn('lab_id_fk',
            Referal::where('referral_id_fk', $userId)->select('lab_id_fk'));
    }

    public function patients(Request $request) {
        $userId = $this->assertReferralPartner();
        $v = $request->validate(['lab_id' => 'required|integer', 'name' => 'required|string|min:3|max:255']);
        abort_unless(Referal::where('referral_id_fk', $userId)->where('lab_id_fk', $v['lab_id'])->exists(), 403);
        return Patient::with('user')->where('creator_id', $v['lab_id'])
            ->whereIn('id', $this->ownInvoices($userId)->where('lab_id_fk', $v['lab_id'])->select('patient_id_fk'))
            ->whereHas('user', fn ($q) => $q->whereNull('deleted_at')->where('name', 'like', '%'.$v['name'].'%'))
            ->limit(20)->get()->map(fn ($p) => ['id' => $p->id, 'name' => $p->user->name,
                'code' => $p->code, 'dob' => $p->dob?->format('Y-m-d')]);
    }

    private function allowedPrices(Referal $connection) {
        $connection->loadMissing('priceList');
        if (! $connection->priceList || (int) $connection->priceList->lab_id_fk !== (int) $connection->lab_id_fk) return collect();
        return PriceListRel::where('price_list_id_fk', $connection->price_list_id_fk)
            ->where('lab_id_fk', $connection->lab_id_fk)
            ->whereHas('test', fn ($q) => $q->whereNull('deleted_at')->where('lab_id_fk', $connection->lab_id_fk))
            ->with('test')->get()->filter(fn ($r) => is_numeric($r->price_for_customer ?? $r->original_price)
                && ($r->price_for_customer ?? $r->original_price) >= 0)->unique('test_id_fk');
    }

    private function price($row, Referal $connection): int {
        $discount = max(0, min(100, (float) ($connection->priceList->discount ?? 0)));
        return (int) round(($row->price_for_customer ?? $row->original_price) * (1 - $discount / 100));
    }

    public function store(Request $request) {
        $userId = $this->assertReferralPartner();
        $v = $request->validate([
            'request_id' => 'required|uuid', 'lab_id' => 'required|integer',
            'patient_id' => 'nullable|integer', 'patient_name' => 'required|string|max:255',
            'patient_phone' => 'nullable|string|max:50', 'patient_dob' => 'nullable|date|before_or_equal:today',
            'gender_id_fk' => 'nullable|integer|exists:genders,id',
            'test_ids' => 'required|array|min:1|max:100', 'test_ids.*' => 'required|integer|distinct',
            'notes' => 'nullable|string|max:255',
        ]);
        $v['test_ids'] = array_map('intval', $v['test_ids']); sort($v['test_ids']);
        $hash = hash('sha256', json_encode($v));
        return DB::transaction(function () use ($v, $userId, $hash) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            // Serializes retries for this partner and lab; no partial patient/invoice can survive a failure.
            $connection = Referal::where('referral_id_fk', $userId)->where('lab_id_fk', $v['lab_id'])->lockForUpdate()->first();
            abort_unless($connection, 403, 'غير مرتبط بهذا المختبر');
            $existing = Invoice::withTrashed()->where('from_lab_id_fk', $userId)->where('referral_request_uuid', $v['request_id'])->first();
            if ($existing) {
                abort_unless(! $existing->trashed() && hash_equals($existing->referral_request_hash, $hash), 409, 'تغير محتوى الطلب؛ ابدأ عينة جديدة');
                return response()->json($this->receipt($existing));
            }
            $prices = $this->allowedPrices($connection)->keyBy('test_id_fk');
            foreach ($v['test_ids'] as $id) {
                if (! $prices->has($id)) throw ValidationException::withMessages(['test_ids' => 'أحد الفحوصات غير متاح ضمن قائمة أسعار هذه الجهة.']);
            }
            $patient = null;
            if (! empty($v['patient_id'])) {
                $patient = Patient::with('user')->where('creator_id', $v['lab_id'])
                    ->whereIn('id', $this->ownInvoices($userId)->where('lab_id_fk', $v['lab_id'])->select('patient_id_fk'))
                    ->whereHas('user', fn ($q) => $q->whereNull('deleted_at'))->lockForUpdate()->find($v['patient_id']);
                abort_unless($patient, 422, 'اختر مريضاً من عيناتك السابقة في هذا المختبر');
                abort_unless($patient->user->name === $v['patient_name'] && ($patient->dob?->format('Y-m-d')) === ($v['patient_dob'] ?? null), 409, 'بيانات المريض تغيرت؛ أعد اختياره');
            }
            if (! $patient) {
                $user = User::create(['name' => $v['patient_name'], 'email' => 'referral-'.Str::uuid().'@example.invalid',
                    'password' => Hash::make(Str::random(40)), 'phone_number' => $v['patient_phone'] ?? null,
                    'creator_id' => $v['lab_id'], 'role_id' => 3, 'email_verified_at' => now()]);
                $patient = Patient::create(['user_id' => $user->id, 'creator_id' => $v['lab_id'],
                    'dob' => $v['patient_dob'] ?? null, 'gender_id_fk' => $v['gender_id_fk'] ?? null,
                    'code' => 'R'.$user->id, 'barcode' => 'P'.$user->id]);
            }
            $total = collect($v['test_ids'])->sum(fn ($id) => $this->price($prices[$id], $connection));
            $invoice = Invoice::create(['patient_id_fk' => $patient->id, 'lab_id_fk' => $v['lab_id'],
                'from_lab_id_fk' => $userId, 'notes' => $v['notes'] ?? null, 'is_done' => false,
                'registration_date' => now(), 'sub_total' => $total, 'total' => $total, 'paid' => 0, 'discount' => 0,
                'referral_request_uuid' => $v['request_id'], 'referral_request_hash' => $hash]);
            // ID-based barcode cannot collide with another referral invoice.
            $invoice->update(['barcode' => 'R'.$invoice->id]);
            foreach ($v['test_ids'] as $id) {
                InvoiceTestRel::create(['invoice_id_fk' => $invoice->id, 'test_id_fk' => $id,
                    'price' => $this->price($prices[$id], $connection), 'is_sample_received' => false, 'is_done' => false]);
            }
            ActivityLogController::registerActivity('إنشاء عينة إحالة رقم '.$invoice->id);
            return response()->json($this->receipt($invoice), 201);
        });
    }

    private function receipt(Invoice $invoice): array {
        $invoice->loadMissing('patient.user');
        return ['id' => $invoice->id, 'barcode' => $invoice->barcode,
            'patient_name' => $invoice->patient->user->name, 'total' => $invoice->total];
    }
}
