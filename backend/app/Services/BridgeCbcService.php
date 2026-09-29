<?php

namespace App\Services;

use App\Models\{DeviceResult, Invoice, InvoiceTestRel, LabDevice, Test, User};
use App\Http\Resources\TestResource;
use Illuminate\Support\Facades\DB;

/** Barcode + explicitly configured CBC panel, saved as a draft for staff review. */
class BridgeCbcService
{
    public const INTERFACE_CODE = '12345678';
    private const CODES = [
        'WBC', 'RBC', 'HGB', 'HCT', 'MCV', 'MCH', 'MCHC', 'RDW', 'RDW-SD',
        'PLT', 'MPV', 'LY', 'MO', 'NE', 'EO', 'BA', 'LY#', 'MO#', 'NE#', 'EO#', 'BA#',
    ];

    public function apply(DeviceResult $source): array
    {
        return DB::transaction(function () use ($source) {
            $device = LabDevice::whereKey($source->device_id_fk)->lockForUpdate()->first();
            $result = DeviceResult::whereKey($source->id)->lockForUpdate()->firstOrFail();
            if (! $device || ! $result->delivery_id ||
                ($result->instrument_metadata['adapter'] ?? null) !== 'dxh500') {
                return ['applied' => false, 'message' => 'هذه الرسالة ليست من ربط DxH 500.'];
            }
            if ($result->status === 'applied') {
                return ['applied' => true, 'duplicate' => true,
                    'applied_count' => $result->instrument_metadata['cbc_count'] ?? 0];
            }
            $tenantIds = User::where('creator_id', $device->lab_id_fk)->pluck('id')->all();
            $tenantIds[] = $device->lab_id_fk;
            // Never trust a manual invoice link: independently resolve the exact barcode.
            $invoices = Invoice::whereIn('lab_id_fk', $tenantIds)
                ->where('barcode', $result->specimen_barcode)->limit(2)->lockForUpdate()->get();
            if ($invoices->count() !== 1 ||
                (string) $invoices[0]->barcode !== (string) $result->specimen_barcode) {
                return $this->hold($result, 'لا توجد فاتورة واحدة مطابقة لباركود العينة ضمن المختبر.');
            }
            $invoice = $invoices[0];
            if ($invoice->is_signed || $invoice->signed_by_id_fk || $invoice->sent_to_patient || $invoice->is_done) {
                return $this->hold($result, 'الفاتورة معتمدة أو مكتملة؛ لم يتم استبدال نتائجها.');
            }

            $targets = [];
            foreach (InvoiceTestRel::where('invoice_id_fk', $invoice->id)->lockForUpdate()->get() as $rel) {
                if ($rel->test_id_fk) {
                    $test = Test::find($rel->test_id_fk);
                    if ($test && (string) $test->interface_code === self::INTERFACE_CODE) {
                        $targets[] = [$rel, null, null, null];
                    }
                    continue;
                }
                foreach (['package_tests', 'test_group_tests'] as $field) {
                    if (($field === 'package_tests' && ! $rel->package_id_fk) ||
                        ($field === 'test_group_tests' && ! $rel->test_group_id_fk)) {
                        continue;
                    }
                    $items = $this->decode($rel->$field);
                    if (! $items) {
                        $models = $field === 'package_tests' ? $rel->package?->tests : $rel->testGroup?->tests;
                        $items = TestResource::collection($models ?? [])->resolve();
                        if ($field === 'package_tests') {
                            $seen = array_column($items, 'id');
                            foreach ($rel->package?->testGroups ?? [] as $group) {
                                foreach (TestResource::collection($group->tests)->resolve() as $item) {
                                    if (! in_array($item['id'], $seen, true)) {
                                        $items[] = $item;
                                        $seen[] = $item['id'];
                                    }
                                }
                            }
                        }
                    }
                    foreach ($items as $i => $item) {
                        $testId = $item['test_id_fk'] ?? $item['id'] ?? null;
                        $test = $testId ? Test::find($testId) : null;
                        if ($test && (string) $test->interface_code === self::INTERFACE_CODE) {
                            $targets[] = [$rel, $field, $i, $items];
                        }
                    }
                }
            }
            if (count($targets) !== 1) {
                return $this->hold($result, 'يجب أن تحتوي الفاتورة على تحليل واحد بكود الربط 12345678.');
            }
            [$rel, $field, $index, $items] = $targets[0];
            $existing = $field ? $items[$index] : $rel->toArray();
            if ($rel->is_done || $this->hasValues($existing)) {
                return $this->hold($result, 'توجد نتيجة سابقة لتحليل CBC؛ لم يتم استبدالها تلقائياً.');
            }

            $rows = [];
            $seen = [];
            foreach ($result->parsed_results ?? [] as $observation) {
                $code = (string) ($observation['test_code'] ?? '');
                // Research-only parameters remain in the full device inbox, not the patient report.
                if (($observation['research_only'] ?? false) || str_starts_with($code, '@')) {
                    continue;
                }
                if (! in_array($code, self::CODES, true) || isset($seen[$code])) {
                    return $this->hold($result, 'تحتوي الرسالة على كود فحص غير معروف أو مكرر؛ تحتاج مراجعة.');
                }
                $seen[$code] = true;
                $rows[] = [
                    'name' => $code, 'type' => 3,
                    'value' => (string) ($observation['value'] ?? ''),
                    'unit' => (string) ($observation['unit'] ?? ''),
                    'reference_range' => (string) ($observation['reference_range'] ?? ''),
                ];
            }
            if (! $rows) {
                return $this->hold($result, 'لا توجد نتائج CBC قابلة للعرض في تقرير المريض.');
            }
            $content = [
                'html' => $this->table($rows),
                'bridge_cbc' => true, 'device_result_id' => $result->id,
                'review_required' => true,
                'review_messages' => array_values(array_unique(array_filter(array_merge(
                    ['نتائج مستلمة من الجهاز؛ راجع هوية العينة وتنبيهات الجهاز قبل اعتماد التقرير.'],
                    array_column($result->instrument_metadata['comments'] ?? [], 'text'),
                    array_map(fn ($o) => empty($o['flags']) ? '' : $o['test_code'].': '.$o['flags'], $result->parsed_results ?? [])
                )))),
            ];
            // A four-column read-only snapshot; all raw values, flags and comments stay in the inbox.
            if ($field) {
                $items[$index]['sub_tests'] = $rows;
                $items[$index]['content'] = $content;
                $items[$index]['is_special_test'] = true;
                $items[$index]['is_done'] = false;
                $rel->$field = $items;
            } else {
                $rel->sub_tests = $rows;
                $rel->content = $content;
                $rel->is_special_test = true;
            }
            $rel->save();
            $metadata = $result->instrument_metadata;
            $metadata['cbc_count'] = count($rows);
            $metadata['cbc_invoice_test_rel_id'] = $rel->id;
            $metadata['review_required'] = true;
            $result->update([
                'invoice_id_fk' => $invoice->id, 'matched_at' => $result->matched_at ?? now(),
                'status' => 'applied', 'applied_at' => now(), 'error_message' => null,
                'instrument_metadata' => $metadata,
            ]);
            return ['applied' => true, 'duplicate' => false, 'applied_count' => count($rows)];
        }, 3);
    }

    private function hold(DeviceResult $result, string $message): array
    {
        $result->update(['error_message' => $message]);
        return ['applied' => false, 'message' => $message];
    }

    private function decode($value): array
    {
        for ($i = 0; $i < 2 && is_string($value); $i++) {
            $value = json_decode($value, true);
        }
        return is_array($value) ? $value : [];
    }

    private function hasValues(array $item): bool
    {
        if (! empty($item['is_done']) || ($item['result'] ?? '') !== '' && ($item['result'] ?? null) !== null) {
            return true;
        }
        if (($this->decode($item['content'] ?? [])['bridge_cbc'] ?? false)) {
            return true;
        }
        foreach ($this->decode($item['sub_tests'] ?? []) as $sub) {
            if (isset($sub['value']) && (string) $sub['value'] !== '') {
                return true;
            }
        }
        return false;
    }

    /** Normalize headings in previously saved CBC snapshots without changing results. */
    public static function displayContent($content)
    {
        if (! is_array($content) || empty($content['bridge_cbc']) || ! is_string($content['html'] ?? null)) {
            return $content;
        }
        $content['html'] = str_replace(
            ['>اسم الفحص</th>', '>النتيجة</th>', '>الوحدة</th>', '>المعدل الطبيعي</th>'],
            ['>Test</th>', '>Result</th>', '>Unit</th>', '>Reference Range</th>'],
            $content['html']
        );
        return $content;
    }

    private function table(array $rows): string
    {
        $escape = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<table dir="ltr" style="width:100%;border-collapse:collapse;text-align:left"><thead><tr>';
        foreach (['Test', 'Result', 'Unit', 'Reference Range'] as $heading) {
            $html .= '<th style="border:1px solid #cbd5e1;padding:8px">'.$heading.'</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach (['name', 'value', 'unit', 'reference_range'] as $key) {
                $html .= '<td style="border:1px solid #cbd5e1;padding:8px">'.$escape($row[$key]).'</td>';
            }
            $html .= '</tr>';
        }
        return $html.'</tbody></table>';
    }
}
