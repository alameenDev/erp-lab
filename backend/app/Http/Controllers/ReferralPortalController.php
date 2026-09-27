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
            if ($profile->getRawOriginal('logo')) {
                $this->safeDeleteFile($profile->getRawOriginal('logo'));
            }
            $result = $this->secureUploadImage($request->file('logo'), 'referral-logos');
            if (! $result['success']) {
                return response()->json(['message' => $result['error']], 422);
            }
            $validated['logo'] = $result['path'];
        }

        $profile->update($validated);

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

        $tests = PriceListRel::where('price_list_id_fk', $connection->price_list_id_fk)
            ->whereNotNull('test_id_fk')
            ->with('test:id,name')
            ->get()
            ->filter(fn ($r) => $r->test)
            ->map(fn ($r) => [
                'test_id' => $r->test_id_fk,
                'name' => $r->test->name,
                'price' => $r->price_for_customer ?? $r->original_price,
            ])
            ->values();

        return response()->json(['tests' => $tests]);
    }

    /**
     * List this referral's own samples/invoices, most recent first.
     */
    public function index(Request $request)
    {
        $userId = $this->assertReferralPartner();

        $query = Invoice::where('from_lab_id_fk', $userId)->orderByDesc('created_at');

        $invoices = $query->paginate($request->integer('per_page', 25));
        $invoices->getCollection()->transform(fn ($inv) => [
            'id' => $inv->id,
            'barcode' => $inv->barcode,
            'patient_name' => $inv->patient?->user?->name,
            'status' => $inv->is_done ? 'ready' : 'pending',
            'is_new' => $inv->is_done && ! $inv->referral_seen_at,
            'created_at' => $inv->created_at,
        ]);

        $unseenReadyCount = Invoice::where('from_lab_id_fk', $userId)
            ->where('is_done', true)->whereNull('referral_seen_at')->count();

        return response()->json(['invoices' => $invoices, 'unseen_ready_count' => $unseenReadyCount]);
    }

    public function show($id)
    {
        $userId = $this->assertReferralPartner();
        $invoice = Invoice::where('from_lab_id_fk', $userId)->with([
            'patient.user', 'invoiceTestRels.test', 'invoiceTestRels.testGroup',
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
            'patient' => [
                'name' => $invoice->patient?->user?->name,
                'phone' => $invoice->patient?->user?->phone_number,
            ],
            'tests' => $invoice->invoiceTestRels->map(fn ($t) => [
                'name' => $t->test?->name ?? $t->testGroup?->group_name,
                'price' => $t->price,
                'result' => $t->result,
                'result_status_text' => $t->result_status_text,
                'is_done' => $t->is_done,
            ]),
        ]);
    }

    /**
     * Creates the patient (a fresh minimal record, or reuses one already
     * on file for this same connected main lab, matched by phone) and the
     * invoice itself with lab-to-lab pricing, ready to run once the
     * physical tube reaches the main lab. Deliberately does not touch
     * InvoiceController::store() at all - this is a small, self-contained
     * path scoped to referral portal accounts only.
     */
    public function store(Request $request)
    {
        $userId = $this->assertReferralPartner();

        $validated = $request->validate([
            'lab_id' => 'required|integer',
            'patient_name' => 'required|string|max:255',
            'patient_phone' => 'nullable|string|max:50',
            'patient_dob' => 'nullable|date',
            'gender_id_fk' => 'nullable|integer',
            'test_ids' => 'required|array|min:1',
            'test_ids.*' => 'integer',
            'notes' => 'nullable|string|max:1000',
        ]);

        $connection = Referal::where('referral_id_fk', $userId)->where('lab_id_fk', $validated['lab_id'])->first();
        if (! $connection) {
            return response()->json(['message' => 'غير مرتبط بهذا المختبر'], 403);
        }
        $labId = $connection->lab_id_fk;

        // Reuse an existing patient of this main lab by phone if one matches;
        // otherwise create a minimal new one. Patients belong to the MAIN
        // lab (creator_id) so its own staff see them exactly like any other
        // patient once the sample physically arrives.
        $patient = null;
        if (! empty($validated['patient_phone'])) {
            $patient = Patient::whereHas('user', fn ($q) => $q->where('phone_number', $validated['patient_phone']))
                ->where('creator_id', $labId)
                ->first();
        }

        if (! $patient) {
            $email = 'referral-'.Str::random(10).'@example.com';
            $user = User::create([
                'name' => $validated['patient_name'],
                'email' => $email,
                'password' => Hash::make(Str::random(16)),
                'phone_number' => $validated['patient_phone'] ?? null,
                'email_verified_at' => new DateTime(),
                'creator_id' => $labId,
                'role_id' => 3,
            ]);

            $patient = Patient::create([
                'user_id' => $user->id,
                'creator_id' => $labId,
                'dob' => $validated['patient_dob'] ?? null,
                'gender_id_fk' => $validated['gender_id_fk'] ?? null,
                'code' => random_int(100000, 999999),
                'barcode' => Str::upper(Str::random(10)),
            ]);
        }

        $invoice = Invoice::create([
            'patient_id_fk' => $patient->id,
            'lab_id_fk' => $labId,
            'from_lab_id_fk' => $userId,
            'barcode' => Invoice::generateUniqueBarcode(),
            'notes' => $validated['notes'] ?? null,
            'is_done' => false,
        ]);

        $priceMap = PriceListRel::where('price_list_id_fk', $connection->price_list_id_fk)
            ->whereIn('test_id_fk', $validated['test_ids'])
            ->pluck('price_for_customer', 'test_id_fk');

        foreach ($validated['test_ids'] as $testId) {
            $test = Test::find($testId);
            if (! $test) {
                continue;
            }
            InvoiceTestRel::create([
                'invoice_id_fk' => $invoice->id,
                'test_id_fk' => $testId,
                'price' => $priceMap[$testId] ?? $test->price,
                'is_sample_received' => false,
                'is_done' => false,
            ]);
        }

        return response()->json([
            'id' => $invoice->id,
            'barcode' => $invoice->barcode,
            'patient_name' => $validated['patient_name'],
        ], 201);
    }
}
