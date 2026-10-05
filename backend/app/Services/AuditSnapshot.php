<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Model;

class AuditSnapshot
{
    public const HIDDEN = '[محجوب]';

    public static function clean(mixed $value, string $key = ''): mixed
    {
        if (preg_match('/password|secret|token|api[_-]?key|credential|authorization|cookie|otp|verification_code|remember|qr_code|signature/i', $key)) {
            return self::HIDDEN;
        }
        if ($value instanceof Model) {
            $value = $value->getAttributes();
        }
        if (is_string($value) && preg_match('/^\s*[\[{]/', $value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $field => $item) {
                if (in_array($field, ['updated_at', 'created_at', 'last_seen_at'], true)) {
                    continue;
                }
                $result[$field] = self::clean($item, (string) $field);
            }
            return $result;
        }
        if (is_string($value)) {
            if (in_array($key, ['raw_message', 'request_payload', 'response_payload'], true)) {
                return '[بيانات تقنية محجوبة]';
            }
            if (str_starts_with($value, 'data:')) {
                return '[ملف مرفق]';
            }
            // Never store public patient access links or URL credentials in logs.
            $value = preg_replace('~(https?://[^\s]+/(?:portal|result)/)[^\s"<>]+~u', '$1[محجوب]', $value);
            $value = preg_replace('/([?&](?:token|key|signature|password)=)[^&\s]+/i', '$1[محجوب]', $value);
        }
        return $value;
    }

    public static function invoice(Invoice $invoice): array
    {
        $invoice->load(['patient.user', 'invoiceTestRels.test', 'invoiceTestRels.culture', 'invoiceTestRels.package', 'invoiceTestRels.testGroup', 'paidDetails']);
        $analyses = [];
        $occurrences = [];
        foreach ($invoice->invoiceTestRels->sortBy('id') as $rel) {
            $type = $rel->test_id_fk ? 'test' : ($rel->culture_id_fk ? 'culture' : ($rel->package_id_fk ? 'package' : 'test_group'));
            $id = $rel->getAttribute($type.'_id_fk');
            $base = $type.'_'.$id;
            $occurrences[$base] = ($occurrences[$base] ?? 0) + 1;
            $data = self::clean($rel);
            unset($data['id'], $data['invoice_id_fk']);
            $data['name'] = $rel->test?->name ?? $rel->culture?->name ?? $rel->package?->name ?? $rel->testGroup?->group_name;
            $analyses[$base.'_'.$occurrences[$base]] = $data;
        }
        return [
            'invoice' => self::clean($invoice),
            'patient' => ['id' => $invoice->patient_id_fk, 'name' => $invoice->patient?->user?->name],
            'analyses' => $analyses,
            'payments' => $invoice->paidDetails->mapWithKeys(fn ($payment) => [(string) $payment->id => self::clean($payment)])->all(),
        ];
    }

    public static function resource(Model $model): array
    {
        $data = self::clean($model);
        $relations = [
            'Test' => ['testReferenceRanges', 'questions'],
            'TestGroup' => ['tests', 'culture', 'testGroupComments'],
            'Package' => ['tests', 'cultures', 'testGroups'],
            'Culture' => ['attribute'],
            'User' => ['roles', 'permissions'],
            'Role' => ['permissions'],
        ][class_basename($model)] ?? [];
        foreach ($relations as $name) {
            if (! method_exists($model, $name)) {
                continue;
            }
            $items = $model->$name()->get();
            $data[$name] = $items->mapWithKeys(function ($item) {
                $value = self::clean($item);
                if ($item->relationLoaded('pivot')) {
                    $value['pivot'] = self::clean($item->getRelation('pivot'));
                }
                return [(string) $item->getKey() => $value];
            })->all();
        }
        return $data;
    }

    public static function changes(mixed $before, mixed $after, string $path = '', string $label = ''): array
    {
        if ($before === $after) {
            return [];
        }
        if ((is_array($before) || $before === null) && (is_array($after) || $after === null)) {
            $changes = [];
            $name = $after['name'] ?? $before['name'] ?? $after['group_name'] ?? $before['group_name'] ?? null;
            $prefix = $name ? $label.' ('.$name.')' : $label;
            foreach (array_unique(array_merge(array_keys($before ?? []), array_keys($after ?? []))) as $key) {
                $field = (string) $key;
                $next = $path === '' ? $field : $path.'.'.$field;
                $nextLabel = $prefix === '' ? $field : $prefix.' / '.$field;
                array_push($changes, ...self::changes($before[$key] ?? null, $after[$key] ?? null, $next, $nextLabel));
            }
            return $changes;
        }
        return [['field' => $path, 'label' => $label, 'before' => $before, 'after' => $after,
            'kind' => $before === null ? 'added' : ($after === null ? 'removed' : 'changed')]];
    }
}
