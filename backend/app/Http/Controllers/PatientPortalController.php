<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PortalAccessToken;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PatientPortalController extends Controller
{
    public function __construct(private LoyaltyService $loyalty) {}

    /**
     * Staff-triggered: create (or reuse) a magic-link token for a patient
     * and return the portal URL, ready to be dropped into a WhatsApp
     * message. Also returns the patient's current loyalty snapshot so the
     * caller can fill {loyalty_points}/{loyalty_tier} in the message
     * template without a second request.
     */
    public function generateLink(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
        ]);

        $user = Auth::user();
        $patient = Patient::with('user')->find($validated['patient_id']);
        if (! $patient) {
            return response()->json(['message' => 'Patient not found'], 404);
        }

        $lab = $this->patientLab($patient);
        if (! $lab || ((int) $user->role_id !== 1 && app(\App\Services\InventoryService::class)->labId($user) !== (int) $lab->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $token = PortalAccessToken::where('patient_id_fk', $patient->id)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $token) {
            $token = PortalAccessToken::create([
                'patient_id_fk' => $patient->id,
                'token' => PortalAccessToken::generatePlainToken(),
                'expires_at' => now()->addDays(90),
            ]);
        }

        $summary = $this->loyalty->refreshSummary($patient, $lab);
        $appUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/');

        return response()->json([
            'url' => "{$appUrl}/portal/{$token->token}",
            'loyalty_points' => $summary['balance'],
            'loyalty_tier' => $summary['tier']['label_ar'] ?? $summary['tier']['key'],
        ]);
    }

    /**
     * Public: the patient's dashboard - card info, reports list, loyalty
     * summary. If the lab requires OTP and this token hasn't been
     * verified yet, only a minimal "requires_otp" payload is returned.
     */
    public function show(string $token)
    {
        $access = PortalAccessToken::where('token', $token)->first();
        if (! $access || $access->isExpired()) {
            return response()->json(['message' => 'الرابط غير صالح أو منتهي الصلاحية'], 404);
        }

        $patient = Patient::with('user', 'title')->find($access->patient_id_fk);
        if (! $patient) {
            return response()->json(['message' => 'الرابط غير صالح'], 404);
        }

        $lab = $this->patientLab($patient);
        $config = $lab ? $this->loyalty->config($lab) : LoyaltyService::defaultConfig();

        if (($config['require_otp'] ?? false) && ! $access->isOtpVerified()) {
            return response()->json([
                'requires_otp' => true,
                'patient_name' => $patient->user?->name,
            ]);
        }

        $access->last_accessed_at = now();
        $access->save();

        if ($lab) {
            $this->loyalty->awardWelcomeBonusIfNeeded($patient, $lab);
        }
        $summary = $lab ? $this->loyalty->refreshSummary($patient, $lab) : null;

        $invoices = Invoice::where('patient_id_fk', $patient->id)
            ->with(['invoiceTestRels.test', 'invoiceTestRels.culture', 'invoiceTestRels.package', 'invoiceTestRels.testGroup'])
            ->orderByDesc('created_at')
            ->get(['id', 'barcode', 'is_done', 'created_at', 'result_date', 'sub_total', 'total', 'paid', 'loyalty_discount', 'loyalty_points_spent']);

        return response()->json([
            'requires_otp' => false,
            'patient' => [
                'name' => $patient->user?->name,
                'code' => $patient->code,
            ],
            'reports' => $invoices->map(fn ($inv) => [
                'id' => $inv->id,
                'barcode' => $inv->barcode,
                'date' => $inv->created_at,
                'result_date' => $inv->result_date,
                'total' => (float) $inv->total,
                'paid' => (float) $inv->paid,
                'due' => max(0, (float) $inv->total - (float) $inv->paid),
                'loyalty_discount' => (float) $inv->loyalty_discount,
                'loyalty_points_spent' => (int) $inv->loyalty_points_spent,
                // Only workflow status is published here, never unapproved result values.
                'tests' => $inv->invoiceTestRels->map(fn ($rel) => [
                    'id' => $rel->id,
                    'name' => $rel->test?->report_name ?: ($rel->test?->name ?? $rel->culture?->name ?? $rel->package?->name ?? $rel->testGroup?->group_name ?? 'فحص'),
                    'kind' => $rel->package_id_fk ? 'باقة' : ($rel->test_group_id_fk ? 'مجموعة' : ($rel->culture_id_fk ? 'زرع' : 'تحليل')),
                    'status' => $rel->is_done ? 'ready' : 'pending',
                    'sample_received' => (bool) $rel->is_sample_received,
                    'price' => (float) $rel->price,
                ]),
                'status' => $inv->is_done ? 'ready' : 'pending',
                'view_url' => $inv->is_done ? rtrim(config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/')."/result/{$inv->id}" : null,
            ]),
            'loyalty' => $summary && ($config['enabled'] ?? true) ? [
                'balance' => $summary['balance'],
                'tier' => $summary['tier'],
                'next_tier' => $summary['next_tier'],
                'yearly_points' => $summary['yearly_points'],
                'redemption_catalog' => $config['redemption_catalog'],
            ] : null,
        ]);
    }

    public function requestOtp(string $token)
    {
        $access = PortalAccessToken::where('token', $token)->first();
        if (! $access || $access->isExpired()) {
            return response()->json(['message' => 'الرابط غير صالح أو منتهي الصلاحية'], 404);
        }

        $patient = Patient::with('user')->find($access->patient_id_fk);
        $phone = $patient?->user?->phone_number;
        if (! $phone) {
            return response()->json(['message' => 'لا يوجد رقم هاتف مسجل'], 422);
        }

        $code = (string) random_int(100000, 999999);
        $access->setOtp($code);

        $this->sendWhatsAppText($phone, "رمز التحقق الخاص بك: {$code}\nصالح لمدة 5 دقائق.");

        return response()->json(['message' => 'تم إرسال رمز التحقق']);
    }

    public function verifyOtp(string $token, Request $request)
    {
        $validated = $request->validate(['code' => 'required|string']);

        $access = PortalAccessToken::where('token', $token)->first();
        if (! $access || $access->isExpired()) {
            return response()->json(['message' => 'الرابط غير صالح أو منتهي الصلاحية'], 404);
        }

        if (! $access->checkOtp($validated['code'])) {
            return response()->json(['message' => 'رمز التحقق غير صحيح أو منتهي'], 422);
        }

        $access->verified_at = now();
        $access->save();

        return response()->json(['message' => 'تم التحقق بنجاح']);
    }

    public function redeem(string $token, Request $request)
    {
        $validated = $request->validate(['catalog_key' => 'required|string']);

        $access = PortalAccessToken::where('token', $token)->first();
        if (! $access || $access->isExpired()) {
            return response()->json(['message' => 'الرابط غير صالح أو منتهي الصلاحية'], 404);
        }

        $patient = Patient::find($access->patient_id_fk);
        $lab = $patient ? $this->patientLab($patient) : null;
        if (! $patient || ! $lab) {
            return response()->json(['message' => 'تعذر إتمام العملية'], 422);
        }

        if (($this->loyalty->config($lab)['require_otp'] ?? false) && ! $access->isOtpVerified()) {
            return response()->json(['message' => 'يجب التحقق أولاً'], 403);
        }

        try {
            $item = $this->loyalty->redeem($patient, $lab, $validated['catalog_key']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'تم الاستبدال بنجاح، يرجى إبراز هذه الرسالة عند زيارتك للمختبر',
            'item' => $item,
            'balance' => $this->loyalty->balance($patient),
        ]);
    }

    private function patientLab(Patient $patient): ?\App\Models\User
    {
        $creator = $patient->lab;
        return $creator ? \App\Models\User::find(app(\App\Services\InventoryService::class)->labId($creator)) : null;
    }

    /**
     * Sends the OTP text via the same WhatsApp Cloud API integration
     * already used for invoice/result messages, so it shares the same
     * configuration checks and phone normalization instead of duplicating
     * them here.
     */
    private function sendWhatsAppText(string $phone, string $message): void
    {
        $phone = preg_replace('/\s+/', '', $phone);
        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }
        if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        }
        if (str_starts_with($phone, '0')) {
            $phone = '964'.substr($phone, 1);
        }
        if (! str_starts_with($phone, '964')) {
            $phone = '964'.$phone;
        }

        try {
            WhatsAppController::sendWhatsAppMessage(
                Request::create('/api/whatsapp/message', 'POST', [
                    'phone' => $phone,
                    'message' => $message,
                ])
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
