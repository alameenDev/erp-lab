<?php

namespace App\Observers;

use App\Models\Invoice;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class PortalInvoiceObserver implements ShouldHandleEventsAfterCommit
{
    public function updated(Invoice $invoice): void
    {
        if (!$invoice->wasChanged('is_done') || !$invoice->is_done) return;
        try { app(\App\Services\PortalNotifications::class)->ready($invoice); }
        catch (\Throwable $e) {
            // Notification delivery must not prevent saving a clinical result.
            \Illuminate\Support\Facades\Log::warning('Portal notification could not be queued', ['invoice_id' => $invoice->id]);
        }
    }
}
