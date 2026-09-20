<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\User;
use Illuminate\Support\Str;

class PromoCodeService
{
    /**
     * Resolve the tenant/lab id the same way the rest of the app does
     * (role_id 2 = lab owner; everyone else under that lab is a staff
     * account whose creator_id points at the lab).
     */
    public function resolveLabId(User $user): int
    {
        return $user->role_id == 2 ? $user->id : ($user->creator_id ?? $user->id);
    }

    public function generateUniqueCode(int $labId, ?string $prefix = null): string
    {
        do {
            $suffix = strtoupper(Str::random(6));
            $code = ($prefix ? strtoupper($prefix).'-' : '').$suffix;
        } while (PromoCode::where('lab_id_fk', $labId)->where('code', $code)->exists());

        return $code;
    }

    /**
     * Validate a code for a given invoice amount/patient WITHOUT consuming
     * a use. Throws \RuntimeException with an Arabic message on failure.
     */
    public function validate(int $labId, string $code, int $invoiceAmount, ?int $patientId = null): PromoCode
    {
        $promo = PromoCode::where('lab_id_fk', $labId)
            ->whereRaw('LOWER(code) = ?', [mb_strtolower(trim($code))])
            ->first();

        if (! $promo) {
            throw new \RuntimeException('البروموكود غير موجود');
        }
        if (! $promo->is_active) {
            throw new \RuntimeException('البروموكود غير مفعّل');
        }
        if ($promo->isExpired()) {
            throw new \RuntimeException('انتهت صلاحية البروموكود');
        }
        if (! $promo->hasRemainingUses()) {
            throw new \RuntimeException('تم استنفاد عدد مرات استخدام هذا البروموكود');
        }
        if ($promo->min_invoice_amount && $invoiceAmount < $promo->min_invoice_amount) {
            throw new \RuntimeException('قيمة الفاتورة أقل من الحد الأدنى المطلوب لهذا البروموكود');
        }
        if ($patientId && $promo->max_uses_per_patient !== null) {
            $patientUses = PromoCodeRedemption::where('promo_code_id_fk', $promo->id)
                ->where('patient_id_fk', $patientId)
                ->count();
            if ($patientUses >= $promo->max_uses_per_patient) {
                throw new \RuntimeException('تم استخدام هذا البروموكود مسبقاً من قبل هذا المريض');
            }
        }

        return $promo;
    }

    public function computeDiscount(PromoCode $promo, int $invoiceAmount): int
    {
        if ($promo->discount_type === 'percentage') {
            return (int) round($invoiceAmount * ($promo->discount_value / 100));
        }

        return (int) min($invoiceAmount, round($promo->discount_value));
    }

    /**
     * Record that an invoice used this promo code (idempotent per invoice:
     * re-applying the same code to the same invoice does not double-count).
     */
    public function redeem(PromoCode $promo, Invoice $invoice, ?int $patientId, int $discountAmount): void
    {
        $existing = PromoCodeRedemption::where('promo_code_id_fk', $promo->id)
            ->where('invoice_id_fk', $invoice->id)
            ->first();

        if ($existing) {
            $existing->discount_amount = $discountAmount;
            $existing->patient_id_fk = $patientId;
            $existing->save();

            return;
        }

        PromoCodeRedemption::create([
            'promo_code_id_fk' => $promo->id,
            'invoice_id_fk' => $invoice->id,
            'patient_id_fk' => $patientId,
            'discount_amount' => $discountAmount,
        ]);

        $promo->increment('used_count');
    }

    /**
     * Release whatever promo redemption is attached to this invoice (used
     * when clearing a promo code, or before applying a different one).
     */
    public function release(Invoice $invoice): void
    {
        $redemptions = PromoCodeRedemption::where('invoice_id_fk', $invoice->id)->get();
        foreach ($redemptions as $redemption) {
            $redemption->promoCode()->decrement('used_count');
            $redemption->delete();
        }
    }
}
