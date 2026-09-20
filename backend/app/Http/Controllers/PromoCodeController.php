<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PromoCode;
use App\Services\PromoCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PromoCodeController extends Controller
{
    public function __construct(private PromoCodeService $service) {}

    public function index(Request $request)
    {
        $labId = $this->service->resolveLabId(Auth::user());

        $query = PromoCode::where('lab_id_fk', $labId)->orderByDesc('created_at');
        if ($request->filled('batch_label')) {
            $query->where('batch_label', $request->batch_label);
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->boolean('is_active'));
        }

        return response()->json($query->paginate($request->integer('per_page', 25)));
    }

    public function store(Request $request)
    {
        $labId = $this->service->resolveLabId(Auth::user());

        $validated = $request->validate([
            'code' => 'nullable|string|max:50',
            'label' => 'nullable|string|max:255',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_patient' => 'nullable|integer|min:1',
            'min_invoice_amount' => 'nullable|integer|min:0',
            'expires_at' => 'nullable|date',
        ]);

        if ($validated['discount_type'] === 'percentage' && $validated['discount_value'] > 100) {
            return response()->json(['message' => 'نسبة الخصم لا يمكن أن تتجاوز 100%'], 422);
        }

        $code = $validated['code'] ? strtoupper(trim($validated['code'])) : $this->service->generateUniqueCode($labId);

        if (PromoCode::where('lab_id_fk', $labId)->where('code', $code)->exists()) {
            return response()->json(['message' => 'هذا الكود مستخدم مسبقاً'], 422);
        }

        $promo = PromoCode::create([
            ...$validated,
            'code' => $code,
            'lab_id_fk' => $labId,
            'created_by' => Auth::id(),
        ]);

        return response()->json($promo, 201);
    }

    /**
     * Bulk-generate a batch of unique codes sharing the same rules
     * (e.g. for a printed campaign / flyer).
     */
    public function generateBatch(Request $request)
    {
        $labId = $this->service->resolveLabId(Auth::user());

        $validated = $request->validate([
            'count' => 'required|integer|min:1|max:500',
            'prefix' => 'nullable|string|max:20',
            'label' => 'nullable|string|max:255',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'max_uses_per_code' => 'nullable|integer|min:1',
            'max_uses_per_patient' => 'nullable|integer|min:1',
            'min_invoice_amount' => 'nullable|integer|min:0',
            'expires_at' => 'nullable|date',
        ]);

        if ($validated['discount_type'] === 'percentage' && $validated['discount_value'] > 100) {
            return response()->json(['message' => 'نسبة الخصم لا يمكن أن تتجاوز 100%'], 422);
        }

        $batchLabel = ($validated['label'] ?? 'batch').'-'.now()->format('YmdHis');
        $codes = [];

        for ($i = 0; $i < $validated['count']; $i++) {
            $codes[] = PromoCode::create([
                'lab_id_fk' => $labId,
                'code' => $this->service->generateUniqueCode($labId, $validated['prefix'] ?? null),
                'label' => $validated['label'] ?? null,
                'discount_type' => $validated['discount_type'],
                'discount_value' => $validated['discount_value'],
                'max_uses' => $validated['max_uses_per_code'] ?? 1,
                'max_uses_per_patient' => $validated['max_uses_per_patient'] ?? 1,
                'min_invoice_amount' => $validated['min_invoice_amount'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
                'batch_label' => $batchLabel,
                'created_by' => Auth::id(),
            ]);
        }

        return response()->json(['batch_label' => $batchLabel, 'codes' => $codes], 201);
    }

    public function update(Request $request, $id)
    {
        $labId = $this->service->resolveLabId(Auth::user());
        $promo = PromoCode::where('lab_id_fk', $labId)->findOrFail($id);

        $validated = $request->validate([
            'label' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_patient' => 'nullable|integer|min:1',
            'min_invoice_amount' => 'nullable|integer|min:0',
            'expires_at' => 'nullable|date',
        ]);

        $promo->update($validated);

        return response()->json($promo);
    }

    public function destroy($id)
    {
        $labId = $this->service->resolveLabId(Auth::user());
        $promo = PromoCode::where('lab_id_fk', $labId)->findOrFail($id);
        $promo->delete();

        return response()->json(['message' => 'تم الحذف']);
    }

    /**
     * Lightweight check used by the invoice form to preview the discount
     * live, without consuming a use.
     */
    public function preview(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'invoice_amount' => 'required|integer|min:0',
            'patient_id_fk' => 'nullable|integer',
        ]);

        $labId = $this->service->resolveLabId(Auth::user());

        try {
            $promo = $this->service->validate($labId, $validated['code'], $validated['invoice_amount'], $validated['patient_id_fk'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $discount = $this->service->computeDiscount($promo, $validated['invoice_amount']);

        return response()->json([
            'discount_type' => $promo->discount_type,
            'discount_value' => $promo->discount_value,
            'discount_amount' => $discount,
        ]);
    }

    /**
     * Apply a promo code to an already-saved invoice: recomputes and
     * persists discount/total, and records the redemption.
     */
    public function applyToInvoice(Request $request, $invoiceId)
    {
        $validated = $request->validate(['code' => 'required|string|max:50']);

        $invoice = Invoice::findOrFail($invoiceId);
        if ($invoice->loyalty_discount > 0) return response()->json(['message'=>'لا يمكن جمع البروموكود مع استبدال النقاط في هذه الفاتورة.'],422);
        $labId = $this->service->resolveLabId(Auth::user());
        if ($invoice->lab_id_fk != $labId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $baseAmount = (int) ($invoice->sub_total ?? $invoice->total ?? 0);

        try {
            $promo = $this->service->validate($labId, $validated['code'], $baseAmount, $invoice->patient_id_fk);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Release any promo already applied to this invoice first, so
        // switching codes (or re-applying the same one) never double-counts.
        $this->service->release($invoice);

        $discountAmount = $this->service->computeDiscount($promo, $baseAmount);

        $invoice->discount_type_id_fk = $promo->discount_type === 'percentage' ? 2 : 3;
        $invoice->discount = $promo->discount_type === 'percentage' ? $promo->discount_value : $discountAmount;
        $invoice->promo_code_id_fk = $promo->id;
        $invoice->total = max(0, $baseAmount - $discountAmount);
        $invoice->save();

        $this->service->redeem($promo, $invoice, $invoice->patient_id_fk, $discountAmount);

        return response()->json([
            'message' => 'تم تطبيق البروموكود بنجاح',
            'discount' => $invoice->discount,
            'discount_type_id_fk' => $invoice->discount_type_id_fk,
            'discount_amount' => $discountAmount,
            'total' => $invoice->total,
        ]);
    }

    public function removeFromInvoice($invoiceId)
    {
        $invoice = Invoice::findOrFail($invoiceId);
        if ($invoice->loyalty_discount > 0) return response()->json(['message'=>'لا يمكن جمع البروموكود مع استبدال النقاط في هذه الفاتورة.'],422);
        $labId = $this->service->resolveLabId(Auth::user());
        if ($invoice->lab_id_fk != $labId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->service->release($invoice);

        $baseAmount = (int) ($invoice->sub_total ?? 0);
        $invoice->discount = null;
        $invoice->discount_type_id_fk = null;
        $invoice->promo_code_id_fk = null;
        $invoice->total = $baseAmount;
        $invoice->save();

        return response()->json([
            'message' => 'تمت إزالة البروموكود',
            'total' => $invoice->total,
        ]);
    }
}
