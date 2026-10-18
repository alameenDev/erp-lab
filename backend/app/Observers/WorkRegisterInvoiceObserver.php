<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\DailyWorkRegister;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;

class WorkRegisterInvoiceObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Invoice $invoice): void
    {
        // All invoice items and initial payments exist once its transaction commits.
        try { app(DailyWorkRegister::class)->capture($invoice->id); }
        catch (\Throwable $error) {
            // A secondary register must never turn a committed invoice into an
            // apparent failed invoice. Opening that day retries missing captures.
            Log::warning('Work register capture deferred', ['invoice_id' => $invoice->id, 'exception' => get_class($error)]);
        }
    }
}
