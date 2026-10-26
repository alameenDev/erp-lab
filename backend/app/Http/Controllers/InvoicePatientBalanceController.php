<?php

namespace App\Http\Controllers;

use App\Models\{Invoice, User};
use App\Services\AccountAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoicePatientBalanceController extends Controller
{
    public function __invoke(Request $request, int $id)
    {
        $user = $request->user();
        abort_if(in_array((int) $user->role_id, [3, 5], true) || $user->referral_portal_only, 403);
        abort_unless((int) $user->role_id === 1 || $user->hasAnyPermission([
            'invoices view', 'invoices create', 'invoices edit', 'invoices print',
            'medical reports results view', 'medical reports update', 'invoices send whatsapp',
        ]), 403);

        $visible = Invoice::query();
        if ((int) $user->role_id !== 1) $visible->whereIn('lab_id_fk', $this->getTenantUserIds());
        $invoice = $visible->findOrFail($id);
        $balance = ['invoice_id' => $invoice->id, 'patient_id' => $invoice->patient_id_fk,
            'currency' => 'IQD', 'unpaid_invoice_count' => 0, 'outstanding_amount' => 0,
            'current_invoice_amount' => 0, 'other_invoices_amount' => 0];

        if ($invoice->patient_id_fk) {
            // Anchor the total to this invoice's lab, even for a super administrator.
            // Preserve invoices entered by former staff; never cross independent labs.
            $owner = User::withTrashed()->findOrFail($invoice->lab_id_fk);
            $ids = app(AccountAccess::class)->tenantUserIds($owner);
            $invoices = Invoice::where('patient_id_fk', $invoice->patient_id_fk)->whereIn('lab_id_fk', $ids);
            $payments = DB::table('invoice_paid_details')->whereNull('deleted_at')
                ->whereIn('invoice_id_fk', (clone $invoices)->select('invoices.id'))
                ->select('invoice_id_fk')->selectRaw('SUM(COALESCE(amount, 0)) AS paid')->groupBy('invoice_id_fk');
            // total is already net of discounts. Use the ledger, not the cached paid column.
            // Credits on a different invoice do not silently settle this invoice's debt.
            $due = 'GREATEST(COALESCE(invoices.total, 0) - COALESCE(patient_payments.paid, 0), 0)';
            $row = $invoices->leftJoinSub($payments, 'patient_payments', 'patient_payments.invoice_id_fk', '=', 'invoices.id')
                ->selectRaw("COALESCE(SUM($due), 0) AS outstanding")
                ->selectRaw("COALESCE(SUM(CASE WHEN $due > 0 THEN 1 ELSE 0 END), 0) AS unpaid_count")
                ->selectRaw("COALESCE(SUM(CASE WHEN invoices.id = ? THEN $due ELSE 0 END), 0) AS current_due", [$invoice->id])
                ->first();
            $balance['unpaid_invoice_count'] = (int) $row->unpaid_count;
            $balance['outstanding_amount'] = (float) $row->outstanding;
            $balance['current_invoice_amount'] = (float) $row->current_due;
            $balance['other_invoices_amount'] = max(0, $balance['outstanding_amount'] - $balance['current_invoice_amount']);
        }

        return response()->json($balance)->header('Cache-Control', 'private, no-store');
    }
}
