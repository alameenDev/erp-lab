<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Compare trusted persisted invoice values with the requested edit BEFORE any write. */
class InvoiceChangeReason
{
    public static function enforce(Request $request, array $operations): void
    {
        if (! $operations) return;
        $reason = trim((string) $request->input('change_reason', ''));
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages([
                'change_reason' => 'يجب كتابة سبب واضح للتعديل أو الحذف (10 أحرف على الأقل) قبل تنفيذ العملية.',
            ]);
        }
        // Metadata to the audit writer is derived by the server, never from a client-supplied summary.
        $request->attributes->set('audited_change_reason', $reason);
        $request->attributes->set('audited_reason_operations', array_values($operations));
    }

    public static function editOperations(Invoice $invoice, Request $request): array
    {
        $operations = [];
        foreach (['total' => 'إجمالي الفاتورة', 'sub_total' => 'المجموع قبل الخصم',
            'discount' => 'الخصم', 'discount_type_id_fk' => 'نوع الخصم'] as $field => $label) {
            if (! $request->exists($field)) continue;
            if (self::numeric($invoice->$field) !== self::numeric($request->input($field))) {
                $operations[] = 'تغيير '.$label;
            }
        }
        if ($request->exists('work_register_amount') &&
            self::numeric($request->input('work_register_amount')) !== self::numeric($request->input('work_register_amount_original'))) {
            $operations[] = 'تغيير المبلغ المسجل في سجل العمل اليومي';
        }
        $relations = $invoice->invoiceTestRels()->with(['test:id,name','culture:id,name','package:id,name','testGroup:id,group_name'])->get();
        // The editor replaces the selected test lists as a set. If one list
        // is omitted while another is submitted, its old rows are also removed.
        // A payload without ANY list is an ordinary partial invoice update.
        $submittedSelection = collect(['tests','cultures','packages','test_groups'])
            ->contains(fn ($field) => $request->exists($field));
        foreach ([
            'tests' => ['test_id_fk', 'test', 'تحليل'],
            'cultures' => ['culture_id_fk', 'culture', 'زرع'],
            'packages' => ['package_id_fk', 'package', 'باقة'],
            'test_groups' => ['test_group_id_fk', 'testGroup', 'كروب'],
        ] as $key => [$idKey,$relation,$label]) {
            if (! $submittedSelection) continue;
            $requested = $request->input($key) ?? [];
            if (! is_array($requested)) continue; // The controller separately validates arrays.
            $old = $relations->filter(fn ($r) => $r->$idKey !== null)->values();
            $new = [];
            foreach ($requested as $row) {
                if (! is_array($row)) continue;
                $id = $row[$idKey] ?? $row['id'] ?? null;
                if (! is_numeric($id)) continue;
                $new[(string) (int) $id][] = $row;
            }
            $seen = [];
            foreach ($old as $row) {
                $keyId = (string) (int) $row->$idKey;
                $name = $row->$relation?->name ?? $row->$relation?->group_name ?? $label.' #'.$keyId;
                $offset = $seen[$keyId] ?? 0;
                $seen[$keyId] = $offset + 1;
                $next = $new[$keyId][$offset] ?? null;
                if ($next === null) {
                    $operations[] = 'حذف '.$label.' من الفاتورة: '.$name;
                    continue;
                }
                if (array_key_exists('price',$next) && self::numeric($row->price) !== self::numeric($next['price'])) {
                    $operations[] = 'تغيير سعر '.$label.': '.$name;
                }
                // A nested analysis removed from a group/package must also have a reason.
                foreach ([
                    'test_group_tests' => 'tests', 'package_tests' => 'tests',
                    'test_group_cultures' => 'cultures', 'package_cultures' => 'cultures',
                ] as $storedField => $inputField) {
                    if (! in_array($key, ['packages','test_groups'], true)) continue;
                    $incoming = $next[$inputField] ?? $next[$storedField] ?? null;
                    if (! is_array($incoming)) continue;
                    $previous = $row->$storedField;
                    if (is_string($previous)) $previous = json_decode($previous, true);
                    if (! is_array($previous)) continue;
                    $identify = fn ($a) => is_array($a) ? (string) ($a['id'] ?? $a['test_id_fk'] ?? $a['culture_id_fk'] ?? $a['name'] ?? '') : '';
                    $remaining = array_count_values(array_filter(array_map($identify, $incoming)));
                    foreach ($previous as $nested) {
                        $nestedId = $identify($nested);
                        if ($nestedId === '') continue;
                        if (($remaining[$nestedId] ?? 0) > 0) { $remaining[$nestedId]--; continue; }
                        $operations[] = 'حذف فحص داخل '.$label.' '.$name.': '.($nested['name'] ?? $nestedId);
                    }
                }
            }
        }
        return array_values(array_unique($operations));
    }

    private static function numeric(mixed $value): string
    {
        if ($value === null || $value === '') return '0.0000';
        return is_numeric($value) ? number_format((float) $value, 4, '.', '') : (string) $value;
    }
}
