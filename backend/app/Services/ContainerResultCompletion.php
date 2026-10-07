<?php
namespace App\Services;

use App\Models\InvoiceTestRel;

class ContainerResultCompletion
{
    /** Derive targets from trusted definitions. Ordinary results keep their approval. */
    public function resolve(InvoiceTestRel $row): ?array
    {
        $prefix = $row->package_id_fk ? 'package' : ($row->test_group_id_fk ? 'test_group' : null);
        if (!$prefix) return null;
        $tests = ReportFormulas::children($row->{$prefix.'_tests'});
        $cultures = ReportFormulas::children($row->{$prefix.'_cultures'});
        if ($tests === null || $cultures === null) return ['complete' => false, 'tests' => null, 'prefix' => $prefix];
        $parent = $prefix === 'package' ? $row->package : $row->testGroup;
        $definitions = ReportFormulas::children($parent?->formula) ?? [];
        $models = $parent?->tests ?? collect();
        if ($prefix === 'package') {
            foreach ($parent?->testGroups ?? [] as $group) {
                $definitions = array_merge($definitions, ReportFormulas::children($group->formula) ?? []);
                $models = $models->merge($group->tests);
            }
        }
        // Old snapshots can lack a shortcut; use the same fallback as the editor.
        foreach ($tests as &$test) {
            if (!is_array($test)) continue;
            $model = $models->first(fn ($m) => (isset($test['id']) && (int) $m->id === (int) $test['id'])
                || (isset($test['test_id_fk']) && (int) $m->id === (int) $test['test_id_fk'])
                || (!empty($test['name']) && $m->name === $test['name']));
            if ($model) { $test['name'] = $test['name'] ?? $model->name; $test['shortcut'] = $test['shortcut'] ?? $model->shortcut; }
        }
        unset($test);
        $results = app(ReportFormulas::class)->evaluate($tests, $definitions);
        foreach ($tests as &$test) {
            if (!is_array($test)) continue;
            foreach ($results as $name => $state) {
                if (!ReportFormulas::matches($test, (string) $name)) continue;
                if ((string) ($test['result'] ?? '') !== (string) ($state['value'] ?? '')) {
                    $test['result_status_id_fk'] = null; $test['result_status_text'] = null;
                }
                $test['result'] = $state['value']; $test['is_done'] = $state['complete'];
                break;
            }
        }
        unset($test);
        $children = array_merge($tests, $cultures);
        $complete = count($children) > 0;
        foreach ($children as $child) if (!is_array($child) || !ReportFormulas::approved($child['is_done'] ?? false)) $complete = false;
        foreach ($results as $state) if (!$state['complete']) $complete = false;
        return compact('complete', 'tests', 'prefix');
    }

    public function reconcile(InvoiceTestRel $row): void
    {
        $state = $this->resolve($row);
        if (!$state) return;
        if ($state['tests'] !== null) $row->{$state['prefix'].'_tests'} = $state['tests'];
        $row->is_done = $state['complete'];
        if ($row->isDirty()) $row->save();
    }
}
