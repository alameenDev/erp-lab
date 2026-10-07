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
        $container = app(ContainerResultCompletion::class)->resolve($row);
        if ($container !== null) return $container['complete'];
        return true;
    }

}
