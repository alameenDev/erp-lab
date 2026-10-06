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
            $tests = $this->children($row->{$prefix.'_tests'});
            $cultures = $this->children($row->{$prefix.'_cultures'});
            if ($tests === null || $cultures === null) return false;
            $children = array_merge($tests, $cultures);
            if (!$children) return false;
            foreach ($children as $child) {
                if (!is_array($child) || !filter_var($child['is_done'] ?? false, FILTER_VALIDATE_BOOLEAN)) return false;
            }
        }
        return true;
    }

    private function children(mixed $value): ?array
    {
        // Older imported invoices can contain double-encoded snapshots, which
        // the report renderer already supports. Malformed data fails closed.
        for ($depth = 0; is_string($value) && $depth < 2; $depth++) {
            $value = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) return null;
        }
        return $value === null ? [] : (is_array($value) ? $value : null);
    }
}
