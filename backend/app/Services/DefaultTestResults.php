<?php

namespace App\Services;

use App\Models\{Invoice, Test};
use Illuminate\Http\Request;

class DefaultTestResults
{
    /** Seed only newly added analyses; existing results (including blanks) are authoritative. */
    public function prepare(Request $request, ?array $labIds, ?Invoice $invoice = null): void
    {
        $existing = $invoice ? $invoice->invoiceTestRels()->get() : collect();
        $sets = [];
        if (is_array($request->input('tests'))) {
            $sets['tests'] = $request->input('tests');
        }
        $bundles = [];
        foreach (['packages' => ['package_id_fk', 'package_tests'], 'test_groups' => ['test_group_id_fk', 'test_group_tests']] as $kind => [$idKey, $testsKey]) {
            if (! is_array($request->input($kind))) continue;
            $bundles[$kind] = $request->input($kind);
            foreach ($bundles[$kind] as $index => $bundle) {
                $sets[$kind.'.'.$index] = $this->items($bundle['tests'] ?? $bundle[$testsKey] ?? []);
            }
        }
        $ids = collect($sets)->flatten(1)->map(fn ($test) => $test['test_id_fk'] ?? $test['id'] ?? null)->filter()->unique();
        $definitions = Test::whereIn('id', $ids)
            ->when($labIds !== null, fn ($query) => $query->whereIn('lab_id_fk', $labIds))->get()->keyBy('id');
        $seed = function (array $tests, array $oldIds) use ($definitions) {
            foreach ($tests as &$test) {
                $id = $test['test_id_fk'] ?? $test['id'] ?? null;
                if (! in_array($id, $oldIds) && ($test['result'] ?? '') === '' && empty($test['is_done'])) {
                    $default = $definitions->get($id)?->configuredDefaultResult();
                    if ($default !== null) $test['result'] = $default;
                }
            }
            return $tests;
        };
        if (isset($sets['tests'])) {
            $request->merge(['tests' => $seed($sets['tests'], $existing->pluck('test_id_fk')->filter()->all())]);
        }
        foreach (['packages' => ['package_id_fk', 'package_tests'], 'test_groups' => ['test_group_id_fk', 'test_group_tests']] as $kind => [$idKey, $testsKey]) {
            if (! isset($bundles[$kind])) continue;
            foreach ($bundles[$kind] as $index => &$bundle) {
                $old = $existing->firstWhere($idKey, $bundle[$idKey] ?? null);
                $oldTests = $old ? $this->items($old->$testsKey) : [];
                $oldIds = array_map(fn ($test) => $test['test_id_fk'] ?? $test['id'] ?? null, $oldTests);
                // Legacy bundles without snapshots must not acquire results on an ordinary edit.
                if ($old && ! $oldTests) $oldIds = array_map(fn ($test) => $test['test_id_fk'] ?? $test['id'] ?? null, $sets[$kind.'.'.$index]);
                $bundle['tests'] = $seed($sets[$kind.'.'.$index], $oldIds);
                $bundle[$testsKey] = $bundle['tests'];
            }
            unset($bundle);
            $request->merge([$kind => $bundles[$kind]]);
        }
    }

    private function items(mixed $value): array
    {
        // Older snapshots were JSON encoded before assignment to a JSON-cast column.
        for ($i = 0; is_string($value) && $i < 2; $i++) $value = json_decode($value, true);
        return is_array($value) ? $value : [];
    }
}
