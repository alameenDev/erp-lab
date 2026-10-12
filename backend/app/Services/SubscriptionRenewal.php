<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubscriptionRenewal
{
    public const MONTHS = [1, 3, 6, 12];

    public function preview(Subscription $subscription): array
    {
        $this->assertRenewable($subscription);
        $today = today();
        // The expiry day is included, matching the existing login check.
        $continues = in_array($subscription->status, ['active', 'trial'], true)
            && $subscription->end_date->gte($today);
        $start = $continues ? $subscription->end_date->copy()->addDay() : $today;

        return [
            'subscription' => $subscription->load('lab:id,name,email'),
            // Includes all stored fields and today's date: a retry, concurrent edit,
            // or confirmation after midnight must fetch a fresh preview.
            'version' => hash('sha256', json_encode([$subscription->getRawOriginal(), $today->toDateString()])),
            'continues_current_term' => $continues,
            'renewal_start_date' => $start->toDateString(),
            'options' => array_map(fn ($months) => [
                'months' => $months,
                'end_date' => $start->copy()->addMonthsNoOverflow($months)->subDay()->toDateString(),
            ], self::MONTHS),
        ];
    }

    private function assertRenewable(Subscription $subscription): void
    {
        abort_unless(User::whereKey($subscription->lab_id_fk)->where('role_id', 2)->exists(), 422,
            'لا يمكن تجديد اشتراك مختبر محذوف أو غير صالح.');
        $latestId = Subscription::where('lab_id_fk', $subscription->lab_id_fk)->max('id');
        abort_unless((int) $latestId === (int) $subscription->id, 409,
            'يوجد اشتراك أحدث لهذا المختبر. افتح اشتراكه الحالي لتجديده.');
        abort_if($subscription->status === 'suspended', 422,
            'الاشتراك موقوف. أزل الإيقاف من تعديل الاشتراك قبل التجديد.');
        abort_if($subscription->start_date->gt(today()), 422,
            'لم تبدأ مدة هذا الاشتراك بعد. يمكن تغيير تواريخه من تعديل الاشتراك.');
    }

    public function renew(int $id, int $months, string $version): Subscription
    {
        return DB::transaction(function () use ($id, $months, $version) {
            $subscription = Subscription::lockForUpdate()->findOrFail($id);
            $preview = $this->preview($subscription);
            abort_unless(hash_equals($preview['version'], $version), 409,
                'تغيّر الاشتراك أو تم تجديده بالفعل. حدّث التفاصيل قبل تأكيد تجديد آخر.');
            $option = collect($preview['options'])->firstWhere('months', $months);
            abort_unless($option, 422, 'مدة التجديد غير صالحة.');
            $old = clone $subscription;
            $subscription->update([
                'start_date' => $preview['continues_current_term']
                    ? $subscription->start_date->toDateString() : $preview['renewal_start_date'],
                'end_date' => $option['end_date'],
                'status' => 'active',
            ]);
            \App\Http\Controllers\ActivityLogController::updateActivity('تجديد اشتراك المختبر', $old, $subscription);

            return $subscription;
        });
    }
}
