<?php

namespace App\Services;

use App\Models\{Invoice, User};
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/** Append-only evidence. Application APIs expose no update/delete operation. */
class InvoiceChangeArchive
{
    public function capture(Activity $activity, bool $notify = false): void
    {
        if ($activity->subject_type !== Invoice::class) return;
        $event = $activity->event ?: (['تعديل' => 'updated', 'حذف' => 'deleted'][$activity->log_name] ?? '');
        if (! in_array($event, ['updated', 'deleted'], true)) return;
        $props = AuditSnapshot::clean($activity->properties?->toArray() ?? []);
        if (($props['status'] ?? 'success') !== 'success') return;
        $before = $props['old_value'] ?? null;
        $after = $props['new_value'] ?? null;
        $oldInvoice = $before['invoice'] ?? $before;
        $newInvoice = $after['invoice'] ?? $after;
        $tenant = $activity->audit_tenant_id;
        if (! $tenant) {
            $account = $oldInvoice['lab_id_fk'] ?? $newInvoice['lab_id_fk'] ?? $activity->creator_id;
            $user = $account ? User::withTrashed()->find($account) : null;
            $tenant = $user ? app(AccountAccess::class)->tenantId($user) : null;
        }
        if (! $tenant || ! $activity->subject_id) return;
        $number = fn ($value) => is_numeric($value) ? (float) $value : null;
        $oldTotal = $number($oldInvoice['total'] ?? null);
        $newTotal = $event === 'deleted' ? 0 : $number($newInvoice['total'] ?? null);
        $paid = function ($snapshot) {
            if (! is_array($snapshot) || ! array_key_exists('payments', $snapshot)) return null;
            return collect($snapshot['payments'])->filter(fn ($p) => empty($p['deleted_at']))->sum('amount');
        };
        $oldPaid = $paid($before); $newPaid = $event === 'deleted' ? 0 : $paid($after);
        $changes = $props['changes'] ?? AuditSnapshot::changes($before, $after);
        $fields = collect($changes)->pluck('field');
        $financial = $fields->contains(fn ($field) => preg_match('/^(?:invoice\.)?(total|sub_total|paid|discount|discount_type_id_fk|loyalty_discount)$|^payments\.|^analyses\..*\.price$/', $field));
        $results = $fields->contains(fn ($field) => preg_match('/^analyses\.|^(?:invoice\.)?is_done$/', $field));
        $context = $props['invoice'] ?? [];
        DB::transaction(function () use ($activity, $tenant, $props, $before, $after, $context, $oldInvoice, $newInvoice, $event, $financial, $results, $oldTotal, $newTotal, $oldPaid, $newPaid, $changes, $notify) {
        $inserted = DB::table('invoice_change_archive')->insertOrIgnore([
            'activity_id' => $activity->id, 'tenant_id' => $tenant, 'invoice_id' => $activity->subject_id,
            'actor_id' => $activity->causer_type === User::class ? $activity->causer_id : null,
            'actor_name' => $props['actor_name'] ?? null, 'actor_role' => $props['actor_role'] ?? null,
            'patient_name' => $context['patient_name'] ?? $before['patient']['name'] ?? $after['patient']['name'] ?? null,
            'barcode' => $context['barcode'] ?? $oldInvoice['barcode'] ?? $newInvoice['barcode'] ?? null,
            'event' => $event, 'source' => $activity->audit_source ?? 'legacy',
            'financial_changed' => $financial || $event === 'deleted', 'results_changed' => $results,
            'before_total' => $oldTotal, 'after_total' => $newTotal,
            'total_delta' => $oldTotal !== null && $newTotal !== null ? $newTotal - $oldTotal : null,
            'paid_delta' => $oldPaid !== null && $newPaid !== null ? $newPaid - $oldPaid : null,
            'snapshot' => json_encode(['before' => $before, 'after' => $after, 'changes' => $changes], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'occurred_at' => $activity->created_at,
        ]);
        if ($inserted && $notify) app(OwnerNotifications::class)->invoiceChanged(DB::table('invoice_change_archive')->where('activity_id', $activity->id)->first());
        });
    }
}
