<?php

namespace App\Services;

use App\Models\{Invoice, User, WorkRegisterEntry};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Independent visit snapshots. Never writes to patients, invoices or payments. */
class DailyWorkRegister
{
    public const TIMEZONE = 'Asia/Baghdad';
    private const RELATIONS = ['patient.user', 'patient.ageUnit', 'referral', 'fromLab',
        'invoiceTestRels.test', 'invoiceTestRels.culture', 'invoiceTestRels.testGroup', 'invoiceTestRels.package'];

    public function today(): string { return now(self::TIMEZONE)->toDateString(); }

    public function day(int $lab, string $date): object
    {
        // The day row serializes additions, reordering and deletion, including
        // two receptionists opening a previously unused day at the same time.
        DB::table('work_register_days')->insertOrIgnore(['lab_id_fk' => $lab, 'work_date' => $date, 'version' => 0]);
        return DB::table('work_register_days')->where(['lab_id_fk' => $lab, 'work_date' => $date])->lockForUpdate()->first();
    }

    public function capture(int $invoiceId): void
    {
        $invoice = Invoice::with(self::RELATIONS)->find($invoiceId);
        if (! $invoice || WorkRegisterEntry::withTrashed()->where('source_invoice_id', $invoiceId)->exists()) return;
        $owner = User::withTrashed()->find($invoice->lab_id_fk);
        if (! $owner) return;
        $lab = app(InventoryService::class)->labId($owner);
        $date = $invoice->created_at->copy()->setTimezone(self::TIMEZONE)->toDateString();
        DB::transaction(function () use ($invoice, $lab, $date) {
            $day = $this->day($lab, $date);
            if ($this->append($day, $invoice)) $this->bump($day);
        }, 3);
    }

    public function synchronize(int $lab, string $date): void
    {
        // Also initializes pre-feature history and repairs a failed after-commit
        // capture. Tombstones prevent a user-deleted row from reappearing.
        $accounts = [$lab]; $frontier = [$lab];
        while ($frontier) {
            $frontier = User::withTrashed()->whereIn('creator_id', $frontier)
                ->whereNotIn('role_id', [1, 2, 3, 5])->where('referral_portal_only', false)
                ->whereNotIn('id', $accounts)->pluck('id')->all();
            $accounts = array_merge($accounts, $frontier);
        }
        $start = CarbonImmutable::parse($date, self::TIMEZONE)->startOfDay();
        $end = $start->addDay()->setTimezone(config('app.timezone'));
        $start = $start->setTimezone(config('app.timezone'));
        DB::transaction(function () use ($lab, $date, $accounts, $start, $end) {
            $day = $this->day($lab, $date);
            $changed = false;
            Invoice::with(self::RELATIONS)->whereIn('lab_id_fk', $accounts)
                ->where('created_at', '>=', $start)->where('created_at', '<', $end)
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('work_register_entries')->whereColumn('source_invoice_id', 'invoices.id'))
                ->orderBy('id')->chunkById(100, function ($invoices) use ($day, &$changed) {
                    foreach ($invoices as $invoice) $changed = $this->append($day, $invoice) || $changed;
                });
            if ($changed) $this->bump($day);
        }, 3);
    }

    public function amountForInvoice(int $invoiceId): ?int
    {
        return WorkRegisterEntry::withTrashed()->where('source_invoice_id', $invoiceId)->value('work_register_amount');
    }

    public function barcodeReference(Invoice $invoice): array
    {
        $owner = User::withTrashed()->findOrFail($invoice->lab_id_fk);
        $lab = app(InventoryService::class)->labId($owner);
        $date = $invoice->created_at->copy()->setTimezone(self::TIMEZONE)->toDateString();
        return DB::transaction(function () use ($lab, $date, $invoice) {
            $day = $this->day($lab, $date);
            // Initialize the complete historical day, rather than assigning a
            // position based on which old invoice happens to be printed first.
            $this->synchronize($lab, $date);
            $entry = WorkRegisterEntry::where('day_id', $day->id)->where('source_invoice_id', $invoice->id)->first();
            return ['work_register_number' => $entry?->position, 'work_register_date' => $entry ? $date : null];
        }, 3);
    }

    /** Called inside the invoice transaction, after its items and payments are saved. */
    public function setManualAmount(int $invoiceId, ?int $amount, ?array $expected = null): void
    {
        $this->capture($invoiceId);
        $entry = WorkRegisterEntry::withTrashed()->where('source_invoice_id', $invoiceId)->firstOrFail();
        // Use the same day lock as register edits; do not restore deleted entries.
        $day = DB::table('work_register_days')->where('id', $entry->day_id)->lockForUpdate()->first();
        $entry->refresh();
        if ($expected !== null && $entry->work_register_amount !== $expected['amount']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'work_register_amount' => 'تغيّر مبلغ السجل من مستخدم آخر. أعد فتح الفاتورة قبل تعديل مبلغ السجل.',
            ]);
        }
        if ($entry->work_register_amount === $amount) return;
        $entry->work_register_amount = $amount;
        $entry->save();
        $this->bump($day);
    }

    private function append(object $day, Invoice $invoice): bool
    {
        if (WorkRegisterEntry::withTrashed()->where('source_invoice_id', $invoice->id)->exists()) return false;
        $patient = $invoice->patient;
        $referral = $invoice->referral;
        $lab = $invoice->fromLab ?? (in_array((int) $referral?->role_id, [2, 4], true) ? $referral : null);
        $items = ['tests' => [], 'groups' => [], 'packages' => []];
        $rank = [];
        foreach ($invoice->selection_order ?? [] as $i => $selection) $rank[$selection['field'].':'.$selection['id']] = $i;
        $rows = $invoice->invoiceTestRels->sortBy(function ($row) use ($rank) {
            foreach (['tests' => 'test_id_fk', 'cultures' => 'culture_id_fk', 'test_groups' => 'test_group_id_fk', 'packages' => 'package_id_fk'] as $field => $key) {
                if ($row->$key) return $rank[$field.':'.$row->$key] ?? (count($rank) + $row->id);
            }
            return PHP_INT_MAX;
        });
        foreach ($rows as $row) {
            if ($row->test_id_fk) $items['tests'][] = $row->test?->name ?? 'تحليل #'.$row->test_id_fk;
            elseif ($row->culture_id_fk) $items['tests'][] = $row->culture?->name ?? 'زرع #'.$row->culture_id_fk;
            elseif ($row->test_group_id_fk) $items['groups'][] = $row->testGroup?->group_name ?? 'كروب #'.$row->test_group_id_fk;
            elseif ($row->package_id_fk) $items['packages'][] = $row->package?->name ?? 'باقة #'.$row->package_id_fk;
        }
        $age = $patient?->age;
        $unit = $patient?->ageUnit?->unit_name ?? 'Years';
        if ($age === null && $patient?->dob) {
            $age = max(0, (int) $patient->dob->diffInYears($invoice->created_at));
            $unit = 'Years';
        }
        // Quiet capture avoids presenting an automatic snapshot as another
        // invoice/financial edit in the audit log. User edits are audited normally.
        WorkRegisterEntry::withoutEvents(fn () => WorkRegisterEntry::create(array_merge($items, [
            'day_id' => $day->id, 'lab_id_fk' => $day->lab_id_fk, 'source_invoice_id' => $invoice->id,
            'position' => WorkRegisterEntry::where('day_id', $day->id)->count() + 1,
            'patient_name' => $patient?->user?->name ?? 'مريض غير مسمى', 'age' => $age, 'age_unit' => $unit,
            'referral_doctor' => (int) $referral?->role_id === 5 ? $referral->name : null,
            'referral_lab' => $lab?->name,
            'work_register_amount' => null,
        ])));
        return true;
    }

    public function bump(object $day): void
    {
        DB::table('work_register_days')->where('id', $day->id)->increment('version');
    }

    public function move(WorkRegisterEntry $entry, int $position): void
    {
        $ids = WorkRegisterEntry::where('day_id', $entry->day_id)->orderBy('position')->orderBy('id')->pluck('id')->all();
        $ids = array_values(array_filter($ids, fn ($id) => $id !== $entry->id));
        $position = min($position, count($ids) + 1);
        $entry->position = $position;
        $entry->save();
        array_splice($ids, $position - 1, 0, [$entry->id]);
        $this->number($entry->day_id, $ids);
    }

    public function number(int $day, ?array $ids = null): void
    {
        $ids ??= WorkRegisterEntry::where('day_id', $day)->orderBy('position')->orderBy('id')->pluck('id')->all();
        foreach ($ids as $i => $id) WorkRegisterEntry::whereKey($id)->update(['position' => $i + 1]);
    }

    public function report(int $lab, string $date, array $permissions): array
    {
        return DB::transaction(function () use ($lab, $date, $permissions) {
            $day = $this->day($lab, $date);
            $entries = WorkRegisterEntry::where('day_id', $day->id)->orderBy('position')->orderBy('id')->get();
            return ['date' => $date, 'today' => $this->today(), 'timezone' => self::TIMEZONE,
                'lab_name' => User::withTrashed()->find($lab)?->name ?? '', 'version' => (int) $day->version,
                'permissions' => $permissions, 'entries' => $entries,
                'totals' => ['count' => $entries->count(), 'work_register_amount' => $entries->sum('work_register_amount')]];
        });
    }
}
