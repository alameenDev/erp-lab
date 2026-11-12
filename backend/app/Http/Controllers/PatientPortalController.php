<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PortalAccessToken;
use App\Services\AiAssistantService;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PatientPortalController extends Controller
{
    public function __construct(private LoyaltyService $loyalty, private AiAssistantService $ai) {}

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
            ->get();
        $renderer = app(InvoiceController::class);
        $showInvoiceAmounts = $lab?->labSetting?->show_patient_portal_invoice_amounts !== false;

        return response()->json([
            'requires_otp' => false,
            'patient' => [
                'name' => $patient->user?->name,
                'code' => $patient->code,
            ],
            'show_invoice_amounts' => $showInvoiceAmounts,
            'reports' => $invoices->map(function ($inv) use ($renderer, $showInvoiceAmounts) {
                $publication = $renderer->patientResultData($inv, false)['portal_report'];
                return [
                'id' => $inv->id,
                'barcode' => $inv->barcode,
                'date' => $inv->created_at,
                'result_date' => $inv->result_date,
                ...($showInvoiceAmounts ? [
                    'total' => (float) $inv->total,
                    'paid' => (float) $inv->paid,
                    'due' => max(0, (float) $inv->total - (float) $inv->paid),
                    'loyalty_discount' => (float) $inv->loyalty_discount,
                    'loyalty_points_spent' => (int) $inv->loyalty_points_spent,
                ] : []),
                'tests' => $publication['items'],
                'ready_count' => $publication['ready'],
                'pending_count' => $publication['pending'],
                'status' => !$publication['pending'] && $publication['ready'] ? 'ready' : ($publication['ready'] ? 'partial' : 'pending'),
                'view_url' => rtrim(config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/')."/result/{$inv->id}",
            ]; }),
            'loyalty' => $summary && ($config['enabled'] ?? true) ? [
                'balance' => $summary['balance'],
                'tier' => $summary['tier'],
                'next_tier' => $summary['next_tier'],
                'yearly_points' => $summary['yearly_points'],
                'redemption_catalog' => $config['redemption_catalog'],
            ] : null,
            'doctors' => $lab ? \App\Models\Doctor::where('lab_id_fk', $lab->id)
                ->where('is_active', true)
                ->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'specialty', 'description', 'photo', 'phone', 'whatsapp', 'links', 'bookable'])
                : [],
            'ai_enabled' => $lab ? (bool) ($lab->labSetting?->ai_config['enabled'] ?? false) : false,
        ])->header('Cache-Control', 'private, no-store');
    }

    /** Approved result history for this patient. */
    public function resultTrends(string $token)
    {
        $access = PortalAccessToken::where('token', $token)->first();
        abort_if(! $access || $access->isExpired(), 404, 'الرابط غير صالح أو منتهي الصلاحية');
        $patient = Patient::findOrFail($access->patient_id_fk);
        $lab = $this->patientLab($patient);
        abort_unless($lab, 404);
        $config = $this->loyalty->config($lab);
        abort_if(($config['require_otp'] ?? false) && ! $access->isOtpVerified(), 403, 'يرجى التحقق من رمز الدخول أولاً');
        $ids = \App\Models\User::where('creator_id', $lab->id)->pluck('id')->push($lab->id);
        $query = Invoice::where('patient_id_fk', $patient->id)->whereIn('lab_id_fk', $ids)->where('is_done', true);
        return response()->json(['series' => app(\App\Services\ResultTrendService::class)->series($query)]);
    }

    public function catalog(string $token)
    {
        [, , $lab] = app(\App\Services\PatientPortalAccess::class)->resolve($token);

        return response()->json(['packages' => $this->portalPackages($lab)]);
    }

    /** Public package names and prices only; never flatten tests or groups. */
    private function portalPackages(\App\Models\User $lab): \Illuminate\Support\Collection
    {
        return \App\Models\Package::where('lab_id_fk', $lab->id)
            ->where('show_in_patient_portal', true)
            ->orderBy('name')->orderBy('id')
            ->get(['id', 'name', 'price'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => $p->price === null ? null : (float) $p->price,
            ]);
    }

    private function aiReportContext(Patient $patient, \App\Models\User $lab, int $invoiceId): array
    {
        $tenantIds = \App\Models\User::where('creator_id', $lab->id)->pluck('id')->push($lab->id);
        $invoice = Invoice::where('patient_id_fk', $patient->id)->whereIn('lab_id_fk', $tenantIds)->findOrFail($invoiceId);
        return app(\App\Services\PatientAiContext::class)->fromReport(app(InvoiceController::class)->patientResultData($invoice, false));
    }

    /** Local preparation only: never contacts the AI provider. */
    public function aiReportOptions(string $token, Request $request)
    {
        [, $patient, $lab] = app(\App\Services\PatientPortalAccess::class)->resolve($token);
        abort_unless($lab->labSetting?->ai_config['enabled'] ?? false, 422, 'محادثة ابن سينا غير مفعّلة حالياً.');
        $input = $request->validate(['invoice_id' => 'required|integer|min:1']);
        $context = $this->aiReportContext($patient, $lab, (int) $input['invoice_id']);
        return response()->json([
            'analyses' => app(\App\Services\PatientAiContext::class)->choices($context),
            'revision' => hash('sha256', json_encode($context)),
            'pending' => $context['report']['pending'],
        ])->header('Cache-Control', 'private, no-store');
    }

    /** Explain published results and journal consented messages; never change medical results. */
    public function aiChat(string $token, Request $request)
    {
        [, $patient, $lab] = app(\App\Services\PatientPortalAccess::class)->resolve($token);
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'consent' => 'required|accepted',
            'consent_version' => 'required|in:'.\App\Services\PatientAiJournal::CONSENT_VERSION,
            'conversation_id' => 'required|uuid',
            'request_id' => 'required|uuid',
            'invoice_id' => 'nullable|integer|min:1',
            'report_revision' => 'nullable|string|size:64',
            'selection_revision' => 'required_with:analysis_keys|nullable|string|size:64',
            'analysis_keys' => 'sometimes|required|array|min:1|max:200',
            'analysis_keys.*' => 'required|string|regex:/^[a-f0-9]{64}$/|distinct',
            'guided' => 'sometimes|boolean',
            'concern' => 'nullable|string|max:2000',
            'history' => 'nullable|array|max:8',
            'history.*' => 'required|array:role,content',
            'history.*.role' => 'required|in:user,assistant',
            'history.*.content' => 'required|string|max:6000',
        ], ['consent_version.required' => 'حدّث الصفحة ووافق على حفظ المحادثة وإتاحتها للمختبر قبل الإرسال.',
            'consent_version.in' => 'حدّث الصفحة للاطلاع على شروط حفظ المحادثة الجديدة.']);
        $settings = $lab->labSetting;
        if (!$settings || !($settings->ai_config['enabled'] ?? false)) return response()->json(['message' => 'محادثة ابن سينا غير متاحة حالياً'], 422);
        abort_if(isset($validated['analysis_keys']) && empty($validated['invoice_id']), 422, 'اختر التقرير أولاً.');
        $doctors = \App\Models\Doctor::where('lab_id_fk', $lab->id)->where('is_active', true)->where('bookable', true)
            ->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'specialty', 'phone', 'whatsapp', 'bookable']);
        $context = [
            'lab_name' => $settings->lab_display_name ?: $lab->name,
            'doctors' => $doctors->map(fn ($doctor) => $doctor->only(['id', 'name', 'specialty']))->all(),
            'tests' => [], 'packages' => $this->portalPackages($lab)->all(), 'report' => null,
        ];
        $source = null;
        $history = $validated['history'] ?? [];
        if (!empty($validated['invoice_id'])) {
            $report = $this->aiReportContext($patient, $lab, (int) $validated['invoice_id']);
            $selectionRevision = hash('sha256', json_encode($report));
            if (!empty($validated['selection_revision']) && !hash_equals($selectionRevision, $validated['selection_revision'])) {
                return response()->json(['message' => 'تغيّرت نتائج التقرير؛ حدّثنا القائمة. اختر التحاليل مرة أخرى.', 'context_reset' => true], 409)
                    ->header('Cache-Control', 'private, no-store');
            }
            $available = count($report['report']['analyses']);
            if (isset($validated['analysis_keys'])) $report = app(\App\Services\PatientAiContext::class)->select($report, $validated['analysis_keys']);
            $report['report']['available_count'] = $available;
            $report['report']['selected_count'] = count($report['report']['analyses']);
            $context = array_merge($context, $report);
            $revision = hash('sha256', json_encode($context));
            $contextReset = count($history) > 0 && !hash_equals($revision, $validated['report_revision'] ?? '');
            if ($contextReset) $history = [];
            $source = [
                'revision' => $revision, 'context_reset' => $contextReset,
                'invoice_id' => (int) $validated['invoice_id'],
                'ready' => $context['report']['ready'], 'pending' => $context['report']['pending'],
                'selected_count' => $context['report']['selected_count'], 'available_count' => $available,
            ];
        }
        if (!empty($validated['concern'])) $context['patient_reported_concern'] = $validated['concern'];
        $journal = app(\App\Services\PatientAiJournal::class);
        [$turn, $send] = $journal->begin($lab, $patient, $validated, $context);
        if ($send) {
            try {
                $answer = ($validated['guided'] ?? false)
                    ? $this->ai->guidedChat($settings, $validated['message'], $context, $history)
                    : ['reply' => $this->ai->chat($settings, $validated['message'], $context, $history), 'doctor_ids' => [], 'urgent' => false];
            } catch (\RuntimeException $e) {
                $journal->finish($turn, null);
                return response()->json(['message' => $e->getMessage()], 422)->header('Cache-Control', 'private, no-store');
            }
            abort_unless($journal->finish($turn, $answer), 409, 'أُعيدت محاولة هذه الرسالة؛ حدّث المحادثة.');
        } else {
            $answer = $turn->answer;
        }
        // Re-check directory visibility even for an idempotent retry of a completed turn.
        $recommended = $answer['urgent'] ? [] : \App\Models\Doctor::where('lab_id_fk', $lab->id)
            ->where('is_active', true)->where('bookable', true)
            ->whereIn('id', array_intersect($doctors->pluck('id')->all(), $answer['doctor_ids']))
            ->orderBy('sort_order')->get(['id', 'name', 'specialty', 'phone', 'whatsapp', 'bookable'])->all();
        return response()->json(['reply' => $answer['reply'], 'report' => $source, 'doctors' => $recommended,
            'urgent' => $answer['urgent'], 'conversation_id' => $turn->conversation_id])
            ->header('Cache-Control', 'private, no-store');
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

    public function bookDoctor(string $token, Request $request)
    {
        [, $patient, $lab] = app(\App\Services\PatientPortalAccess::class)->resolve($token);
        $validated = $request->validate([
            'doctor_id' => 'required|integer|exists:doctors,id',
            'phone' => 'nullable|string|max:50',
            'preferred_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $doctor = \App\Models\Doctor::find($validated['doctor_id']);
        if (! $doctor || ! $lab || $doctor->lab_id_fk != $lab->id || ! $doctor->is_active || ! $doctor->bookable) {
            return response()->json(['message' => 'هذا الطبيب غير متاح للحجز حالياً'], 422);
        }

        $booking = \App\Models\DoctorBookingRequest::create([
            'doctor_id_fk' => $doctor->id,
            'lab_id_fk' => $lab->id,
            'patient_id_fk' => $patient->id,
            'patient_name' => $patient->user?->name,
            'phone' => $validated['phone'] ?? $patient->user?->phone_number,
            'preferred_date' => $validated['preferred_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'تم إرسال طلب الحجز، سيتواصل معك المختبر لتأكيد الموعد',
            'booking' => $booking,
        ], 201);
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
