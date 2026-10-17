<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class InvoiceTestFilter
{
    /** Require every selected test in the same invoice, across any of its rows. */
    public function apply(Builder $invoices, array $testIds): void
    {
        foreach ($testIds as $testId) {
            $invoices->whereHas('invoiceTestRels', function (Builder $rows) use ($testId) {
                $rows->where(function (Builder $matches) use ($testId) {
                    $matches->where('test_id_fk', $testId)
                        ->orWhere(function (Builder $groups) use ($testId) {
                            $groups->whereNotNull('test_group_id_fk')->where(function (Builder $members) use ($testId) {
                                $this->bundleContains($members, 'test_group_tests', ['testGroup.tests'], $testId);
                            });
                        })
                        ->orWhere(function (Builder $packages) use ($testId) {
                            $packages->whereNotNull('package_id_fk')->where(function (Builder $members) use ($testId) {
                                $this->bundleContains($members, 'package_tests', ['package.tests'], $testId);
                                // transformPackage appends group members even when
                                // the package has a saved direct-test snapshot.
                                $members->orWhereHas('package.testGroups.tests', fn (Builder $tests) => $tests->where('tests.id', $testId));
                            });
                        });
                });
            });
        }
    }

    private function bundleContains(Builder $query, string $column, array $catalogRelations, int $testId): void
    {
        // Older saves JSON-encoded the snapshot before the model's JSON cast.
        // Unquoting accepts both array JSON and these encoded arrays. Guard invalid
        // inner strings so a legacy record cannot break the whole filtered list.
        $column = 'invoice_test_rels.'.$column;
        $snapshot = "(CASE WHEN JSON_VALID(JSON_UNQUOTE($column)) THEN JSON_UNQUOTE($column) ELSE 'null' END)";

        $query->where(function (Builder $stored) use ($snapshot, $testId) {
            foreach (['id', 'test_id_fk'] as $key) {
                foreach ([$testId, (string) $testId] as $value) {
                    $stored->orWhereRaw("JSON_CONTAINS($snapshot, ?)", [json_encode([$key => $value])]);
                }
            }
        })->orWhere(function (Builder $legacy) use ($snapshot, $catalogRelations, $testId) {
            // Saved membership is authoritative, including an explicitly empty
            // array. Catalog edits must not add tests to historical reports.
            $legacy->whereRaw("COALESCE(JSON_TYPE($snapshot), '') <> 'ARRAY'")
                ->where(function (Builder $catalog) use ($catalogRelations, $testId) {
                    foreach ($catalogRelations as $relation) {
                        $catalog->orWhereHas($relation, fn (Builder $tests) => $tests->where('tests.id', $testId));
                    }
                });
        });
    }
}
