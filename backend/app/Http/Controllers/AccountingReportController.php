<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Culture;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Patient;
use App\Models\Test;
use App\Models\TestGroup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Aggregate accounting report.
 *
 * Single endpoint that returns the data needed to render an accounting PDF
 * containing 7 sections:
 *   - meta            (date range, lab name, generated_at)
 *   - invoices        (full listing with itemized tests)
 *   - tests           (per-test aggregation: count + total)
 *   - cultures        (per-culture aggregation)
 *   - packages        (per-package aggregation)
 *   - test_groups     (per-test-group aggregation)
 *   - referrals       (per-referrer: count, total, commission, profit)
 *   - contracts       (per-contract / lab-to-lab: count, total)
 *   - patients        (per-patient: code, name, total, paid, due)
 *
 * Tenant-filtered via getTenantUserIds(). Permission: 'accounting reports view'.
 *
 * Date range:
 *   from / to are inclusive dates (YYYY-MM-DD). Defaults: from=today, to=today.
 *   The query filters on invoices.created_at::date BETWEEN from AND to.
 */
class AccountingReportController extends Controller
{
    public function report(Request $request)
    {
        $user = Auth::user();

        if (! $user->can('accounting reports view') && $user->role_id != 1 && $user->role_id != 2) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'sections' => 'nullable|string',
            'branch_id' => 'nullable|integer|exists:users,id',
            'sample_collector_id' => 'nullable|integer|exists:users,id',
            'referral_id' => 'nullable|integer|exists:users,id',
            'contract_id' => 'nullable|integer|exists:contracts,id',
            'patient_id' => 'nullable|integer|exists:patients,id',
        ]);

        $from = $request->from
            ? Carbon::createFromFormat('Y-m-d', $request->from)->startOfDay()
            : Carbon::today()->startOfDay();
        $to = $request->to
            ? Carbon::createFromFormat('Y-m-d', $request->to)->endOfDay()
            : Carbon::today()->endOfDay();

        $sections = $request->sections
            ? array_filter(array_map('trim', explode(',', $request->sections)))
            : ['invoices', 'tests', 'cultures', 'packages', 'test_groups', 'referrals', 'contracts', 'patients'];

        // Build the base invoice query with tenant scoping
        $tenantIds = $this->getTenantUserIds();
        $isAdmin = $user->role_id == 1;

        $invoiceIdsQuery = Invoice::query()
            ->whereBetween('created_at', [$from, $to]);
        if (! $isAdmin) {
            $invoiceIdsQuery->whereIn('lab_id_fk', $tenantIds);
        }

        // Optional dimension filters — all additive
        if ($request->filled('branch_id')) {
            $invoiceIdsQuery->where('lab_id_fk', $request->branch_id);
        }
        if ($request->filled('sample_collector_id')) {
            $invoiceIdsQuery->where('sample_collector_id_fk', $request->sample_collector_id);
        }
        if ($request->filled('referral_id')) {
            $invoiceIdsQuery->where('referral_id_fk', $request->referral_id);
        }
        if ($request->filled('contract_id')) {
            $invoiceIdsQuery->where('contract_id_fk', $request->contract_id);
        }
        if ($request->filled('patient_id')) {
            $invoiceIdsQuery->where('patient_id_fk', $request->patient_id);
        }

        $invoiceIds = $invoiceIdsQuery->pluck('id')->toArray();

        $output = [
            'meta' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'generated_at' => Carbon::now()->toDateTimeString(),
                'lab_name' => $isAdmin ? 'All Labs' : ($user->lab?->name ?? $user->name),
                'invoice_count' => count($invoiceIds),
            ],
        ];

        if (empty($invoiceIds)) {
            // Short-circuit: no invoices in range, return empty sections
            foreach ($sections as $section) {
                $output[$section] = [];
            }

            return response()->json($output);
        }

        if (in_array('invoices', $sections)) {
            $output['invoices'] = $this->buildInvoicesListing($invoiceIds);
        }

        if (in_array('tests', $sections)) {
            $output['tests'] = $this->aggregateByItem($invoiceIds, 'test_id_fk', Test::class, 'name');
        }

        if (in_array('cultures', $sections)) {
            $output['cultures'] = $this->aggregateByItem($invoiceIds, 'culture_id_fk', Culture::class, 'name');
        }

        if (in_array('packages', $sections)) {
            $output['packages'] = $this->aggregateByItem($invoiceIds, 'package_id_fk', Package::class, 'name');
        }

        if (in_array('test_groups', $sections)) {
            $output['test_groups'] = $this->aggregateByItem($invoiceIds, 'test_group_id_fk', TestGroup::class, 'group_name');
        }

        if (in_array('referrals', $sections)) {
            $output['referrals'] = $this->aggregateReferrals($invoiceIds, $tenantIds, $isAdmin);
        }

        if (in_array('contracts', $sections)) {
            $output['contracts'] = $this->aggregateContracts($invoiceIds);
        }

        if (in_array('patients', $sections)) {
            $output['patients'] = $this->aggregatePatients($invoiceIds);
        }

        // Totals row for quick footer rendering
        $totalsRow = DB::table('invoices')
            ->whereIn('id', $invoiceIds)
            ->selectRaw('
                COALESCE(SUM(sub_total), 0) as sub_total,
                COALESCE(SUM(discount), 0) as discount,
                COALESCE(SUM(total), 0) as total,
                COALESCE(SUM(paid), 0) as paid,
                COALESCE(SUM(COALESCE(total, 0) - COALESCE(paid, 0)), 0) as due,
                COUNT(DISTINCT patient_id_fk) as patient_count
            ')
            ->first();

        $output['totals'] = [
            'sub_total' => (int) $totalsRow->sub_total,
            'discount' => (int) $totalsRow->discount,
            'total' => (int) $totalsRow->total,
            'paid' => (int) $totalsRow->paid,
            'due' => (int) $totalsRow->due,
            'invoice_count' => count($invoiceIds),
            'patient_count' => (int) $totalsRow->patient_count,
        ];

        return response()->json($output);
    }

    /**
     * Build the invoices listing with itemized tests inline.
     * Mirrors the reference PDF layout: # | Branch | Created by | Date | Patient | Referral | Contract | Tests[] | Subtotal | Discount | Total | Paid | Due
     */
    private function buildInvoicesListing(array $invoiceIds): array
    {
        $invoices = Invoice::with([
            'patient:id,code,user_id',
            'patient.user:id,name',
            'lab:id,name',
            'fromLab:id,name',
            'referral:id,name',
            'contract:id,name',
            'invoiceTestRels' => function ($q) {
                $q->select('id', 'invoice_id_fk', 'test_id_fk', 'culture_id_fk', 'package_id_fk', 'test_group_id_fk', 'price');
            },
            'invoiceTestRels.test:id,name',
            'invoiceTestRels.culture:id,name',
            'invoiceTestRels.package:id,name',
            'invoiceTestRels.testGroup:id,group_name',
        ])->whereIn('id', $invoiceIds)
            ->orderBy('id')
            ->get();

        return $invoices->map(function ($i) {
            $items = $i->invoiceTestRels->map(function ($r) {
                $name = $r->test?->name
                    ?? $r->culture?->name
                    ?? $r->package?->name
                    ?? $r->testGroup?->group_name
                    ?? '—';

                return [
                    'name' => $name,
                    'price' => (int) ($r->price ?? 0),
                ];
            })->values();

            return [
                'id' => $i->id,
                'branch' => $i->lab?->name ?? '—',
                'created_by' => $i->fromLab?->name ?? '—',
                'date' => $i->created_at?->format('Y-m-d H:i') ?? '—',
                'patient_name' => $i->patient?->user?->name ?? '—',
                'patient_code' => $i->patient?->code ?? '',
                'referral_name' => $i->referral?->name ?? $i->fromLab?->name ?? '',
                'contract_name' => $i->contract?->name ?? '',
                'items' => $items,
                'sub_total' => (int) ($i->sub_total ?? 0),
                'discount' => (int) ($i->discount ?? 0),
                'total' => (int) ($i->total ?? 0),
                'paid' => (int) ($i->paid ?? 0),
                'due' => (int) (($i->total ?? 0) - ($i->paid ?? 0)),
            ];
        })->toArray();
    }

    /**
     * Aggregate by item type (test/culture/package/test_group).
     * Returns: [{name, count, total}, ...] sorted by name.
     */
    private function aggregateByItem(array $invoiceIds, string $fkColumn, string $modelClass, string $nameColumn): array
    {
        $rows = DB::table('invoice_test_rels as itr')
            ->join((new $modelClass)->getTable().' as m', 'm.id', '=', 'itr.'.$fkColumn)
            ->whereIn('itr.invoice_id_fk', $invoiceIds)
            ->whereNotNull('itr.'.$fkColumn)
            ->groupBy('m.id', 'm.'.$nameColumn)
            ->orderBy('m.'.$nameColumn)
            ->select(
                'm.'.$nameColumn.' as name',
                DB::raw('COUNT(*) as count'),
                DB::raw('COALESCE(SUM(itr.price), 0) as total')
            )
            ->get();

        return $rows->map(fn ($r) => [
            'name' => $r->name,
            'count' => (int) $r->count,
            'total' => (int) $r->total,
        ])->toArray();
    }

    /**
     * Aggregate referrals with commission + profit calculation.
     * Commission stored on rel_labs_referals.commission as a PERCENTAGE (10-20).
     * commission_amount per invoice = invoice.total * (commission_pct / 100)
     */
    private function aggregateReferrals(array $invoiceIds, array $tenantIds, bool $isAdmin): array
    {
        // Map referral_id_fk -> commission_pct for the current lab scope
        // (rel_labs_referals.lab_id_fk corresponds to the lab owner)
        $relsQuery = DB::table('rel_labs_referals')
            ->whereNull('deleted_at');

        if (! $isAdmin) {
            $relsQuery->whereIn('lab_id_fk', $tenantIds);
        }

        $commissionMap = $relsQuery
            ->select('referral_id_fk', DB::raw('MAX(commission) as commission'))
            ->groupBy('referral_id_fk')
            ->pluck('commission', 'referral_id_fk');

        $rows = Invoice::with('referral:id,name')
            ->whereIn('id', $invoiceIds)
            ->whereNotNull('referral_id_fk')
            ->select('referral_id_fk', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(total), 0) as total'))
            ->groupBy('referral_id_fk')
            ->orderByDesc('total')
            ->get();

        return $rows->map(function ($r) use ($commissionMap) {
            $pct = (int) ($commissionMap[$r->referral_id_fk] ?? 0);
            $commission = (int) round(((int) $r->total) * $pct / 100);
            $profit = (int) $r->total - $commission;

            return [
                'name' => $r->referral?->name ?? '—',
                'count' => (int) $r->count,
                'total' => (int) $r->total,
                'commission' => $commission,
                'profit' => $profit,
            ];
        })->toArray();
    }

    /**
     * Aggregate by contract (lab-to-lab partner billing).
     */
    private function aggregateContracts(array $invoiceIds): array
    {
        $rows = Invoice::with('contract:id,name')
            ->whereIn('id', $invoiceIds)
            ->whereNotNull('contract_id_fk')
            ->select('contract_id_fk', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(total), 0) as total'))
            ->groupBy('contract_id_fk')
            ->orderByDesc('total')
            ->get();

        return $rows->map(fn ($r) => [
            'name' => $r->contract?->name ?? '—',
            'count' => (int) $r->count,
            'total' => (int) $r->total,
        ])->toArray();
    }

    /**
     * Aggregate per patient: code, name, total, patient_payment, paid, due.
     * patient_payment = total when no contract; 0 when invoice billed to a contract (lab pays, not patient).
     */
    private function aggregatePatients(array $invoiceIds): array
    {
        // Patient name lives on the linked users row (patients.user_id → users.id)
        $rows = DB::table('invoices')
            ->leftJoin('patients', 'patients.id', '=', 'invoices.patient_id_fk')
            ->leftJoin('users as pu', 'pu.id', '=', 'patients.user_id')
            ->whereIn('invoices.id', $invoiceIds)
            ->whereNotNull('invoices.patient_id_fk')
            ->groupBy('patients.id', 'patients.code', 'pu.name')
            ->orderBy('pu.name')
            ->select(
                'patients.code as code',
                'pu.name as name',
                DB::raw('COALESCE(SUM(invoices.total), 0) as total'),
                DB::raw('COALESCE(SUM(CASE WHEN invoices.contract_id_fk IS NULL THEN invoices.total ELSE 0 END), 0) as patient_payment'),
                DB::raw('COALESCE(SUM(invoices.paid), 0) as paid'),
                DB::raw('COALESCE(SUM(invoices.total - COALESCE(invoices.paid, 0)), 0) as due'),
                DB::raw('COUNT(invoices.id) as invoice_count')
            )
            ->get();

        return $rows->map(fn ($r) => [
            'code' => $r->code ?? '—',
            'name' => $r->name ?? '—',
            'total' => (int) $r->total,
            'patient_payment' => (int) $r->patient_payment,
            'paid' => (int) $r->paid,
            'due' => (int) $r->due,
            'invoice_count' => (int) $r->invoice_count,
        ])->toArray();
    }
}
