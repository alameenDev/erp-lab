<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class ResultTrendService
{
    private function decode($value): array
    {
        for ($i = 0; $i < 2 && is_string($value); $i++) $value = json_decode($value, true);
        return is_array($value) ? $value : [];
    }

    public function series(Builder $query): array
    {
        $series = [];
        foreach ($query->with(['lab:id,name', 'invoiceTestRels.test', 'invoiceTestRels.culture'])
            ->orderByRaw('COALESCE(result_date, created_at) ASC')->orderBy('id')->lazy(100) as $invoice) {
            foreach ($invoice->invoiceTestRels as $rel) {
                if (! $rel->is_done) continue;
                $add = function ($type, $id, $name, $item) use (&$series, $invoice, $rel): void {
                    if (! $id && ! $name) return;
                    $identity = $type.':'.($id ?: 'name:'.mb_strtolower(trim($name)));
                    $append = function ($field, $label, $value, $unit) use (&$series, $invoice, $rel, $identity, $name): void {
                        if (! is_scalar($value) || is_bool($value) || trim((string) $value) === '' || $value === 'null') return;
                        $unit = is_scalar($unit) ? trim((string) $unit) : '';
                        $key = json_encode([$invoice->lab_id_fk, $identity, $field, $unit], JSON_UNESCAPED_UNICODE);
                        if (! isset($series[$key])) $series[$key] = ['key' => $key, 'name' => $name ?: 'فحص', 'field' => $label,
                            'unit' => $unit, 'lab' => $invoice->lab?->name, 'points' => []];
                        $normalized = strtr(trim((string) $value), ['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','٫'=>'.']);
                        $number = preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/D', $normalized) ? (float) $normalized : null;
                        if ($number !== null && ! is_finite($number)) $number = null;
                        $series[$key]['points'][] = ['date' => (string) ($invoice->result_date ?: $invoice->created_at),
                            'date_source' => $invoice->result_date ? 'result' : 'registration',
                            'invoice_id' => $invoice->id, 'item_id' => $rel->id, 'value' => (string) $value, 'number' => $number];
                    };
                    $append('result', '', $item['result'] ?? null, $item['unit'] ?? '');
                    foreach (['sub_tests' => 'value', 'attribute' => 'result'] as $field => $valueKey) {
                        foreach ($this->decode($item[$field] ?? []) as $part) {
                            if (! is_array($part)) continue;
                            $label = trim((string) ($part['name'] ?? $part['attribute_name'] ?? ''));
                            if ($label !== '') $append($field.':'.mb_strtolower($label), $label, $part[$valueKey] ?? null, $part['unit'] ?? $item['unit'] ?? '');
                        }
                    }
                };
                if ($rel->test_id_fk) $add('test', $rel->test_id_fk, $rel->test?->name, ['result'=>$rel->result,'unit'=>$rel->test?->unit,'sub_tests'=>$rel->sub_tests]);
                if ($rel->culture_id_fk) $add('culture', $rel->culture_id_fk, $rel->culture?->name, ['result'=>$rel->result,'attribute'=>$rel->attribute]);
                foreach (['package_tests'=>'test','test_group_tests'=>'test','package_cultures'=>'culture','test_group_cultures'=>'culture'] as $column => $type) {
                    foreach ($this->decode($rel->$column) as $item) {
                        if (! is_array($item) || (array_key_exists('is_done', $item) && ! filter_var($item['is_done'], FILTER_VALIDATE_BOOLEAN))) continue;
                        $add($type, $item[$type.'_id_fk'] ?? $item['id'] ?? null, $item['name'] ?? null, $item);
                    }
                }
            }
        }
        return array_values($series);
    }
}
