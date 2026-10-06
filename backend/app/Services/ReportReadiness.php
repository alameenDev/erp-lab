<?php
namespace App\Services;

use App\Models\{Invoice, InvoiceTestRel};

/** Publication requires approval of every stored analysis, including containers. */
class ReportReadiness
{
    public function isReady(Invoice $invoice): bool
    {
        return $invoice->is_done && !$invoice->trashed() && $this->analysesComplete($invoice);
    }

    public function analysesComplete(Invoice $invoice): bool
    {
        $rows = $invoice->relationLoaded('invoiceTestRels') ? $invoice->invoiceTestRels : $invoice->invoiceTestRels()->get();
        if ($rows->isEmpty()) return false;
        foreach ($rows as $row) if (!$this->relationComplete($row)) return false;
        return true;
    }

    public function relationComplete(InvoiceTestRel $row): bool
    {
        if (!$row->is_done) return false;
        foreach (['package' => 'package_id_fk', 'test_group' => 'test_group_id_fk'] as $prefix => $field) {
            if (!$row->$field) continue;
            $tests = $row->{$prefix.'_tests'} ?? [];
            $cultures = $row->{$prefix.'_cultures'} ?? [];
            if (!is_array($tests) || !is_array($cultures)) return false;
            $children = array_merge($tests, $cultures);
            if (!$children) return false;
            foreach ($children as $child) {
                if (!is_array($child) || !filter_var($child['is_done'] ?? false, FILTER_VALIDATE_BOOLEAN)) return false;
            }
        }
        return true;
    }
}
