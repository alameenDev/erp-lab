<?php
namespace App\Observers;
use App\Models\Invoice;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
class CollectorInvoiceObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Invoice $invoice): void { $this->capture($invoice); }
    public function deleted(Invoice $invoice): void { $this->capture($invoice); }
    private function capture(Invoice $invoice): void
    {
        try { app(\App\Services\CollectorTasks::class)->capture($invoice->id); }
        catch(\Throwable $e) { \Illuminate\Support\Facades\Log::warning('Collection task capture deferred',['invoice_id'=>$invoice->id,'exception'=>get_class($e)]); }
    }
}
