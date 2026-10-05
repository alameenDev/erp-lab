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

    private const NP21_CODES = [
        'WBC', 'LYM%', 'GRAN%', 'MID%', 'LYM#', 'GRAN#', 'MID#', 'RBC', 'HGB',
        'HCT', 'MCV', 'MCH', 'MCHC', 'RDW-CV', 'RDW-SD', 'PLT', 'MPV', 'PDW',
        'PCT', 'P-LCR', 'P-LCC',
    ];

    public function apply(DeviceResult $source): array
    {
        return DB::transaction(function () use ($source) {
            $device = LabDevice::whereKey($source->device_id_fk)->lockForUpdate()->first();
            $result = DeviceResult::whereKey($source->id)->lockForUpdate()->firstOrFail();
            if (! $device || ! $result->delivery_id ||
                ! in_array($result->instrument_metadata['adapter'] ?? null, ['dxh500', 'np21h'], true)) {
                return ['applied' => false, 'message' => 'هذه الرسالة ليست من ربط جهاز CBC مدعوم.'];
            }
            if ($result->status === 'applied') {
                return ['applied' => true, 'duplicate' => true,
                    'applied_count' => $result->instrument_metadata['cbc_count'] ?? 0];
            }
            $settings = $device->bridgeSettings();
            if ($settings['adapter'] !== $result->instrument_metadata['adapter']) {
                return $this->hold($result, 'موديل الرسالة لا يطابق إعدادات الجهاز في هذا المختبر.');
            }
            $interfaceCode = $settings['cbc_interface_code'];
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
            if ($invoice->is_signed || $invoice->signed_by_id_fk || $invoice->sent_to_patient) {
                return $this->hold($result, 'الفاتورة موقّعة أو مرسلة للمريض؛ النتيجة الجديدة محفوظة للمراجعة ولم تستبدل التقرير.');
            }

            $targets = [];
            foreach (InvoiceTestRel::where('invoice_id_fk', $invoice->id)->lockForUpdate()->get() as $rel) {
                if ($rel->test_id_fk) {
                    $test = Test::find($rel->test_id_fk);
                    if ($test && (string) $test->interface_code === $interfaceCode) {
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
                    }
                    // The invoice renderer includes group tests even when a package
                    // already has a partial snapshot of its direct tests.
                    if ($field === 'package_tests') {
                        $seen = array_map(fn ($item) => (string) ($item['test_id_fk'] ?? $item['id'] ?? ''), $items);
                        foreach ($rel->package?->testGroups ?? [] as $group) {
                            foreach (TestResource::collection($group->tests)->resolve() as $item) {
                                if (! in_array((string) $item['id'], $seen, true)) {
                                    $item['test_group_name'] = $group->group_name;
                                    $item['test_group_id_fk'] = $group->id;
                                    $items[] = $item;
                                    $seen[] = (string) $item['id'];
                                }
                            }
                        }
                    }
                    foreach ($items as $i => $item) {
                        $testId = $item['test_id_fk'] ?? $item['id'] ?? null;
                        $test = $testId ? Test::find($testId) : null;
                        if ($test && (string) $test->interface_code === $interfaceCode) {
                            $targets[] = [$rel, $field, $i, $items];
                        }
                    }
                }
            }
            if (count($targets) !== 1) {
                return $this->hold($result, 'يجب أن تحتوي الفاتورة على تحليل واحد بكود الربط '.$interfaceCode.'.');
            }
            [$rel, $field, $index, $items] = $targets[0];
            $existing = $field ? $items[$index] : $rel->toArray();
            if ($this->hasValues($existing)) {
                return $this->hold($result, 'توجد نتيجة سابقة لتحليل CBC؛ لم يتم استبدالها تلقائياً.');
            }

            $isNp21 = ($result->instrument_metadata['adapter'] ?? '') === 'np21h';
            $allowedCodes = $isNp21 ? self::NP21_CODES : self::CODES;
            $rows = [];
            $seen = [];
            foreach ($result->parsed_results ?? [] as $observation) {
                $code = (string) ($observation['test_code'] ?? '');
                // Research-only parameters remain in the full device inbox, not the patient report.
                if (($observation['research_only'] ?? false) || str_starts_with($code, '@')) {
                    continue;
                }
                if (! in_array($code, $allowedCodes, true) || isset($seen[$code])) {
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
            if ($isNp21 && count($rows) !== 21) {
                return $this->hold($result, 'NP-21H: يجب استلام 21 نتيجة CBC كاملة.');
            }
            if ($isNp21) {
                usort($rows, fn ($a, $b) => array_search($a['name'], self::NP21_CODES, true) <=> array_search($b['name'], self::NP21_CODES, true));
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
                    $result->instrument_metadata['warnings'] ?? [],
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
            $previousState = [
                'invoice_is_done' => (bool) $invoice->is_done,
                'relation_is_done' => (bool) $rel->is_done,
                'target_is_done' => (bool) ($existing['is_done'] ?? false),
                'result_date' => $invoice->result_date,
                'result_doc' => $invoice->result_doc,
                'pdf_qr_code' => $invoice->pdf_qr_code,
            ];
            // Completion flags do not constitute a result. Import into an empty
            // CBC only, then require review of this analysis and its invoice.
            $rel->is_done = false;
            $rel->save();
            $invoice->update(['is_done'=>false, 'result_date'=>null, 'result_doc'=>null, 'pdf_qr_code'=>null]);
            $metadata = $result->instrument_metadata;
            $metadata['cbc_previous_invoice_state'] = $previousState;
            $metadata['cbc_count'] = count($rows);
            $metadata['cbc_interface_code'] = $interfaceCode;
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
        if (($item['result'] ?? '') !== '' && ($item['result'] ?? null) !== null) {
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

    /** Normalize presentation in saved CBC snapshots without changing results. */
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
        $content['html'] = self::highlightHighResults(self::withTitle($content['html']));
        return $content;
    }

    /** Compare only explicit numeric ranges; never guess missing or textual limits. */
    private static function aboveRange(string $value, string $range): bool
    {
        $number = '[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?';
        $value = trim($value);
        $range = trim($range);
        if (! preg_match('/^'.$number.'$/D', $value) || ! is_finite((float) $value)) {
            return false;
        }
        if (preg_match('/^('.$number.')\s*(?:to|[-–—])\s*('.$number.')$/iuD', $range, $m)) {
            return is_finite((float) $m[1]) && is_finite((float) $m[2])
                && (float) $m[1] <= (float) $m[2] && (float) $value > (float) $m[2];
        }
        if (preg_match('/^(<=|≤|<)\s*('.$number.')$/uD', $range, $m) && is_finite((float) $m[2])) {
            return $m[1] === '<' ? (float) $value >= (float) $m[2] : (float) $value > (float) $m[2];
        }
        return false;
    }

    /** Decorate the plain four-cell rows generated by this service, including old snapshots. */
    private static function highlightHighResults(string $html): string
    {
        return preg_replace_callback('~<tr>(.*?)</tr>~s', static function ($row) {
            $cell = '<td\b[^>]*>(.*?)</td>';
            if (! preg_match('~^'.$cell.$cell.$cell.$cell.'$~s', $row[1], $cells)) {
                return $row[0];
            }
            // Remove only our own decoration so repeated reads remain idempotent.
            $value = preg_replace('~^<span data-cbc-high="1"[^>]*>([^<]*)</span>$~s', '$1', $cells[2]);
            if (str_contains($value, '<') || str_contains($cells[4], '<')) {
                return $row[0];
            }
            $decode = static fn ($s) => html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $high = self::aboveRange($decode($value), $decode($cells[4]));
            $display = $high
                ? '<span data-cbc-high="1" style="color:#dc2626 !important;font-weight:700;-webkit-print-color-adjust:exact;print-color-adjust:exact">'.$value.'</span>'
                : $value;
            $index = 0;
            return '<tr>'.preg_replace_callback('~(<td\b[^>]*>)(.*?)</td>~s', static function ($match) use (&$index, $display) {
                return ++$index === 2 ? $match[1].$display.'</td>' : $match[0];
            }, $row[1]).'</tr>';
        }, $html);
    }

    private static function withTitle(string $html): string
    {
        if (str_contains($html, '>Complete Blood Count (CBC)</h3>')) {
            return $html;
        }
        return '<h3 dir="ltr" style="margin:0 0 12px;font-size:18px;font-weight:700;text-align:left;break-after:avoid;page-break-after:avoid">Complete Blood Count (CBC)</h3>'.$html;
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
        return self::highlightHighResults(self::withTitle($html.'</tbody></table>'));
    }
}
