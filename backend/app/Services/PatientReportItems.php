<?php
namespace App\Services;

/** Per-analysis publication, independent of the aggregate invoice completion flag. */
class PatientReportItems
{
    private function name(array $item): string
    {
        return ($item['report_name'] ?? '') ?: (($item['group_name'] ?? '') ?: (($item['name'] ?? '') ?: 'فحص'));
    }

    private function same(array $a, array $b, string $field): bool
    {
        $id = $field === 'tests' ? 'test_id_fk' : 'culture_id_fk';
        $left = $a[$id] ?? $a['id'] ?? null; $right = $b[$id] ?? $b['id'] ?? null;
        return $left !== null && $right !== null ? (string)$left === (string)$right
            : !empty($a['name']) && ($a['name'] === ($b['name'] ?? null));
    }

    /** Catalog rows have no approval; formula values are derived from approved inputs. */
    public function normalize(array $data): array
    {
        foreach (['tests', 'cultures', 'test_groups', 'packages'] as $field) $data[$field] ??= [];
        foreach (['test_groups', 'packages'] as $field) {
            foreach ($data[$field] as &$parent) {
                $parent['tests'] ??= []; $parent['cultures'] ??= [];
                $definitions = [];
                foreach ($parent['formula'] ?? [] as $f) $definitions[] = [$f, null];
                foreach ($parent['test_groups'] ?? [] as $group) {
                    foreach ($group['formula'] ?? [] as $f) $definitions[] = [$f, $group];
                    foreach (['tests', 'cultures'] as $children) foreach ($group[$children] ?? [] as $child) {
                        $found = false;
                        foreach ($parent[$children] as &$stored) {
                            if (!$this->same($stored, $child, $children)) continue;
                            $stored['test_group_name'] ??= $group['group_name'] ?? $group['name'] ?? null;
                            $stored['test_group_id_fk'] ??= $group['test_group_id_fk'] ?? $group['id'] ?? null;
                            $found = true; break;
                        }
                        unset($stored);
                        if (!$found) $parent[$children][] = $child + ['is_done'=>false,
                            'test_group_name'=>$group['group_name'] ?? $group['name'] ?? null,
                            'test_group_id_fk'=>$group['test_group_id_fk'] ?? $group['id'] ?? null];
                    }
                }
                $values = app(ReportFormulas::class)->evaluate($parent['tests'], array_column($definitions, 0));
                foreach ($definitions as [$formula, $group]) {
                    $key = ReportFormulas::key($formula['name'] ?? '');
                    if ($key === '') continue;
                    $state = $values[$key] ?? ['value'=>null, 'complete'=>false]; $found = false;
                    foreach ($parent['tests'] as &$test) {
                        if (!ReportFormulas::matches($test, $key)) continue;
                        if ((string)($test['result'] ?? '') !== (string)$state['value']) {
                            $test['result_status_id_fk'] = null; $test['result_status_text'] = null;
                        }
                        $test['result'] = $state['value']; $test['is_done'] = $state['complete']; $found = true;
                    }
                    unset($test);
                    if (!$found) $parent['tests'][] = ['name'=>$key, 'shortcut'=>$key, 'result'=>$state['value'],
                        'is_done'=>$state['complete'], 'unit'=>$formula['unit'] ?? '', 'is_print_alone'=>false,
                        'test_reference_ranges'=>[['from'=>$formula['from'] ?? null, 'to'=>$formula['to'] ?? null]],
                        'test_group_name'=>$group['group_name'] ?? $group['name'] ?? null,
                        'test_group_id_fk'=>$group['test_group_id_fk'] ?? $group['id'] ?? null];
                }
            }
            unset($parent);
        }
        return $data;
    }

    /** Metadata only: draft values never enter portal lists or counters. */
    public function items(array $data): array
    {
        $items = [];
        $add = function(array $item, string $id, string $kind, array $parent = [], string $context = '') use (&$items) {
            $items[] = ['id'=>$id, 'name'=>$this->name($item), 'kind'=>$kind, 'context'=>$context,
                'status'=>ReportFormulas::approved($item['is_done'] ?? false) ? 'ready' : 'pending',
                'sample_received'=>ReportFormulas::approved($item['is_sample_received'] ?? $parent['is_sample_received'] ?? false)];
        };
        foreach (['tests', 'cultures', 'test_groups', 'packages'] as $field) foreach ($data[$field] as $index=>$item) {
            $key = "$field:$index";
            if (in_array($field, ['tests', 'cultures'])) { $add($item, $key, $field === 'tests' ? 'تحليل' : 'زرع'); continue; }
            $count = 0;
            foreach (['tests', 'cultures'] as $children) foreach ($item[$children] as $i=>$child) {
                $context = $this->name($item);
                if ($field === 'packages' && !empty($child['test_group_name'])) $context .= ' · '.$child['test_group_name'];
                $add($child, "$key/$children:$i", $children === 'tests' ? 'تحليل' : 'زرع', $item, $context); $count++;
            }
            if (!$count) $add(array_merge($item, ['is_done'=>false]), $key, $field === 'packages' ? 'باقة' : 'كروب');
        }
        return $items;
    }

    /** Keep completed analyses, including individual children of unfinished containers. */
    public function document(array $data, array $items): array
    {
        $ready = fn ($item) => ReportFormulas::approved($item['is_done'] ?? false);
        foreach (['tests', 'cultures'] as $field) $data[$field] = array_values(array_filter($data[$field], $ready));
        foreach (['test_groups', 'packages'] as $field) {
            $data[$field] = array_values(array_filter(array_map(function ($parent) use ($ready) {
                foreach (['tests', 'cultures'] as $children) $parent[$children] = array_values(array_filter($parent[$children], $ready));
                // Calculations are materialized above; do not ship hidden operands or regenerate unapproved targets.
                $parent['formula'] = [];
                $parent['test_groups'] = array_values(array_filter(array_map(function ($group) use ($parent) {
                    $group['formula'] = [];
                    foreach (['tests', 'cultures'] as $children) $group[$children] = array_values(array_filter($parent[$children], fn ($child) =>
                        isset($child['test_group_id_fk']) ? (string)$child['test_group_id_fk'] === (string)($group['test_group_id_fk'] ?? $group['id'] ?? '')
                            : ($child['test_group_name'] ?? null) === ($group['group_name'] ?? $group['name'] ?? '')));
                    return $group;
                }, $parent['test_groups'] ?? []), fn ($group) => $group['tests'] || $group['cultures']));
                if (!$ready($parent)) foreach (['result', 'comment', 'result_status_text'] as $key) unset($parent[$key]);
                return $parent;
            }, $data[$field]), fn ($parent) => $parent['tests'] || $parent['cultures']));
        }
        $historyKeys = [];
        $historyItems = function (array $owner) use (&$historyItems, &$historyKeys) {
            foreach (['tests'=>'test', 'cultures'=>'culture'] as $field=>$type) foreach ($owner[$field] ?? [] as $item) {
                $id = $item[$type.'_id_fk'] ?? $item['id'] ?? null;
                if ($id) $historyKeys[$type.'_'.$id] = true;
            }
            foreach (['test_groups', 'packages'] as $field) foreach ($owner[$field] ?? [] as $parent) $historyItems($parent);
        };
        $historyItems($data);
        $data['result_history'] = array_intersect_key($data['result_history'] ?? [], $historyKeys);
        $readyCount = count(array_filter($items, fn ($item) => $item['status'] === 'ready'));
        $partial = $readyCount < count($items);
        $allowed = ['id','patient_id_fk','patient','lab_id_fk','lab','registration_date','result_date','barcode',
            'referral','from_lab','show_result_date','show_patient_card_id','show_patient_pic','public_with_background','selection_order',
            'tests','cultures','test_groups','packages','created_at','updated_at','result_history'];
        if (!$partial) $allowed = array_merge($allowed, ['is_signed','signed_by','tests_comment','cultures_comment','package_comments',
            'result_comments_tests','result_comments_cultures','result_package_comments','test_group_comments','attachments']);
        $data = array_intersect_key($data, array_flip($allowed));
        if (isset($data['referral'])) $data['referral'] = ['name'=>$data['referral']['name'] ?? null];
        $clean = function($value) use (&$clean) {
            if (!is_array($value)) return $value;
            foreach (['price','prices','for_customer_price','price_for_customer','original_price','to_lab','to_lab_id_fk',
                'report_calculation_tests','calculation_tests','calculation_formulas','default_result'] as $key) unset($value[$key]);
            return array_map($clean, $value);
        };
        $data = $clean($data);
        $data['is_done'] = !$partial && $readyCount > 0;
        $data['portal_report'] = ['total'=>count($items), 'ready'=>$readyCount, 'pending'=>count($items)-$readyCount, 'items'=>$items];
        return $data;
    }
}
