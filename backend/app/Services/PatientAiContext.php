<?php

namespace App\Services;

/** An allowlist over the already published portal document, never a raw invoice. */
class PatientAiContext
{
    public function fromReport(array $document): array
    {
        $analyses = [];
        $append = function (array $owner, string $container = '') use (&$analyses): void {
            foreach (['tests', 'cultures'] as $kind) {
                foreach ($owner[$kind] ?? [] as $item) {
                    $analyses[] = $this->analysis($item, $kind, $container);
                }
            }
        };
        $append($document);
        foreach (['test_groups', 'packages'] as $kind) {
            foreach ($document[$kind] ?? [] as $container) {
                // Package children are already flattened by PatientReportItems; don't duplicate nested groups.
                $append($container, $this->text($container['group_name'] ?? $container['name'] ?? '', 200) ?? '');
            }
        }

        $patient = $document['patient'] ?? [];
        return [
            'patient' => [
                'age' => $this->text($patient['age'] ?? null, 20),
                'age_unit' => $this->text($patient['age_unit'] ?? null, 30),
                'gender' => $this->text($patient['gender'] ?? null, 30),
            ],
            'report' => [
                'registration_date' => $this->text($document['registration_date'] ?? $document['created_at'] ?? null, 40),
                'ready' => (int) ($document['portal_report']['ready'] ?? 0),
                'pending' => (int) ($document['portal_report']['pending'] ?? 0),
                'analyses' => $analyses,
                // Attachments and rich HTML templates are intentionally not sent to the provider.
                'attachments_not_read' => !empty($document['attachments']),
            ],
        ];
    }

    /** Opaque keys are bound to the published content and its position, including duplicate tests. */
    public function choices(array $context): array
    {
        return array_map(fn ($item, $index) => [
            'key' => hash('sha256', $index.':'.json_encode($item)),
            'name' => $item['name'],
            'container' => $item['container'],
            'kind' => $item['kind'],
            'readable' => $item['readable_result'],
        ], $context['report']['analyses'], array_keys($context['report']['analyses']));
    }

    public function select(array $context, array $keys): array
    {
        $choices = $this->choices($context);
        abort_if(count(array_diff($keys, array_column($choices, 'key'))) > 0, 422, 'تغيّرت قائمة التحاليل؛ أعد اختيار التحاليل من التقرير.');
        $context['report']['analyses'] = array_values(array_filter($context['report']['analyses'],
            fn ($index) => in_array($choices[$index]['key'], $keys, true), ARRAY_FILTER_USE_KEY));
        return $context;
    }

    private function analysis(array $item, string $kind, string $container): array
    {
        $unitVisible = $this->visible($item['show_unit'] ?? true);
        $rangeVisible = $this->visible($item['show_reference_range'] ?? true);
        $fields = [];
        foreach (['sub_tests' => 'value', 'attribute' => 'result'] as $key => $valueKey) {
            foreach ($this->decode($item[$key] ?? []) as $part) {
                if (!is_array($part)) continue;
                $value = $this->text($part[$valueKey] ?? null);
                if ($value === null) continue;
                $fields[] = [
                    'name' => $this->text($part['name'] ?? $part['attribute_name'] ?? '', 200),
                    'value' => $value,
                    'unit' => $unitVisible ? $this->text($part['unit'] ?? $item['unit'] ?? null, 100) : null,
                    'reference_range' => $rangeVisible ? $this->text($part['reference_range'] ?? null, 500) : null,
                    'reference_ranges' => $rangeVisible ? $this->ranges($part['test_reference_ranges'] ?? $part['sup_test_reference_options'] ?? []) : [],
                    'comment' => $this->text($part['comment'] ?? null),
                ];
            }
        }
        $value = $this->text($item['result'] ?? null);
        return [
            'name' => $this->text(($item['report_name'] ?? '') ?: ($item['name'] ?? 'فحص'), 200),
            'kind' => $kind === 'cultures' ? 'زرع' : 'تحليل',
            'container' => $container,
            'group' => $this->text($item['test_group_name'] ?? null, 200),
            'value' => $value,
            'unit' => $unitVisible ? $this->text($item['unit'] ?? null, 100) : null,
            'reference_ranges' => $rangeVisible ? $this->ranges($item['test_reference_ranges'] ?? []) : [],
            'unit_hidden' => !$unitVisible,
            'reference_range_hidden' => !$rangeVisible,
            'lab_status' => $this->text($item['result_status_text'] ?? $item['status'] ?? null, 200),
            'comment' => $this->text($item['comment'] ?? null),
            'fields' => $fields,
            'readable_result' => $value !== null || count($fields) > 0,
        ];
    }

    private function ranges(mixed $value): array
    {
        $ranges = [];
        foreach ($this->decode($value) as $range) {
            if (is_scalar($range)) {
                $ranges[] = ['notes' => $this->text($range, 500)];
                continue;
            }
            if (!is_array($range)) continue;
            $clean = [];
            foreach (['from', 'to', 'gender', 'age_from', 'age_to', 'age_unit', 'notes', 'value', 'name'] as $key) {
                if (array_key_exists($key, $range)) $clean[$key] = $this->text($range[$key], 500);
            }
            foreach (['test_reference_options', 'selection_type_options'] as $key) {
                if (isset($range[$key])) $clean[$key] = array_values(array_filter(array_map(
                    fn ($option) => $this->text(is_array($option) ? ($option['value'] ?? $option['name'] ?? null) : $option, 200),
                    $this->decode($range[$key])
                ), fn ($option) => $option !== null));
            }
            if ($clean) $ranges[] = $clean;
        }
        return $ranges;
    }

    private function decode(mixed $value): array
    {
        for ($i = 0; $i < 2 && is_string($value); $i++) $value = json_decode($value, true);
        return is_array($value) ? $value : [];
    }

    private function visible(mixed $value): bool
    {
        return !in_array($value, [false, 0, '0', 'false'], true);
    }

    private function text(mixed $value, int $limit = 2000): ?string
    {
        if (!is_scalar($value) || is_bool($value)) return null;
        // Preserve clinical comparisons such as <5; remove actual tags, never execute HTML.
        $text = trim(html_entity_decode(preg_replace('/<\/?[a-zA-Z][^>]*>/', ' ', (string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($text === '' || $text === 'null') return null;
        // Never silently truncate a medical value or reference interval.
        return mb_strlen($text) > $limit ? '[نص طويل غير متاح بالكامل؛ اطلب مراجعة التقرير الأصلي]' : $text;
    }
}
