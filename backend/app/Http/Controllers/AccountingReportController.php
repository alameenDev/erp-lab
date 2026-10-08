<?php

namespace App\Http\Controllers;

use App\Models\{Invoice, InvoicePaidDetail, InvoiceTestRel};
use App\Services\AccountingReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccountingReportController extends Controller
{
    private function service(Request $request): AccountingReportService
    {
        $user = $request->user();
        abort_unless(in_array((int) $user->role_id, [1, 2], true) || $user->can('accounting reports view'), 403);
        $request->merge(['from' => $request->input('from') ?: now()->startOfMonth()->toDateString(), 'to' => $request->input('to') ?: now()->toDateString()]);
        $filters = $request->validate([
            'from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from',
            'search' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in(['paid', 'partial', 'unpaid', 'credit'])],
            'referral_type' => ['nullable', Rule::in(['lab', 'doctor'])],
            'branch_id' => 'nullable|integer|min:1', 'created_by' => 'nullable|integer|min:1',
            'lab_referral_id' => 'nullable|integer|min:1', 'doctor_id' => 'nullable|integer|min:1',
            'contract_id' => 'nullable|integer|min:1', 'patient_id' => 'nullable|integer|min:1',
            'sample_collector_id' => 'nullable|integer|min:1', 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return new AccountingReportService($user, $filters);
    }

    public function report(Request $request)
    {
        return response()->json($this->service($request)->overview())->header('Cache-Control', 'private, no-store');
    }

    public function options(Request $request)
    {
        return response()->json($this->service($request)->options())->header('Cache-Control', 'private, no-store');
    }

    public function invoices(Request $request)
    {
        $service = $this->service($request);
        $page = $service->query()->orderByDesc('invoices.id')->paginate($request->integer('per_page', 25));
        $page->setCollection($service->rows($page->getCollection())->map(fn ($row) => collect($row)->except(['items', 'payments'])->all()));
        return response()->json($page)->header('Cache-Control', 'private, no-store');
    }

    public function payments(Request $request)
    {
        $service = $this->service($request);
        $page = $service->paymentsQuery()->orderByDesc('p.created_at')->orderByDesc('p.id')->paginate($request->integer('per_page', 25));
        $page->setCollection($service->paymentRows($page->getCollection()));
        return response()->json($page)->header('Cache-Control', 'private, no-store');
    }

    public function detail(Request $request, int $id)
    {
        $service = $this->service($request);
        $invoice = $service->query(false, false)->where('invoices.id', $id)->firstOrFail();
        $row = $service->rows(collect([$invoice]))->first();
        // Include financial changes only. Medical values and raw request payloads
        // are deliberately not part of an accounting permission.
        $fields = ['sub_total', 'discount', 'discount_type_id_fk', 'loyalty_discount', 'total', 'paid', 'amount', 'price', 'payment_method_id_fk', 'to_lab_id_fk', 'from_lab_id_fk', 'referral_id_fk', 'contract_id_fk'];
        $row['activity'] = DB::table('activity_log as a')->leftJoin('users as u', 'u.id', '=', 'a.causer_id')
            ->where(fn ($q) => $q->where('a.audit_invoice_id', $id)->orWhere(fn ($q) => $q->where('a.subject_type', Invoice::class)->where('a.subject_id', $id)))
            ->whereIn('a.subject_type', [Invoice::class, InvoicePaidDetail::class, InvoiceTestRel::class])
            ->whereIn('a.event', ['created', 'updated', 'deleted'])->orderByDesc('a.id')->get(['a.id', 'a.created_at', 'a.event', 'a.description', 'a.properties', 'u.name'])
            ->map(function ($entry) use ($fields) {
                $properties = json_decode($entry->properties ?? '{}', true) ?: [];
                return ['id' => $entry->id, 'date' => $entry->created_at, 'event' => $entry->event, 'description' => $entry->description,
                    'actor' => $properties['actor_name'] ?? $entry->name ?? 'غير مسجل',
                    'changes' => collect($properties['changes'] ?? [])->filter(fn ($change) => preg_match('/^(?:invoice\.)?('.implode('|',$fields).')$|^payments\.[^.]+\.(amount|payment_method_id_fk)$|^analyses\.[^.]+\.(price|to_lab_id_fk)$/', $change['field'] ?? ''))->values()->all()];
            })->filter(fn ($entry) => count($entry['changes']) > 0)->values();
        return response()->json($row)->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request)
    {
        $service = $this->service($request);
        $request->validate(['section' => ['required', Rule::in(array_keys(AccountingReportService::COLUMNS))], 'format' => ['required', Rule::in(['csv', 'print'])]]);
        $section = $request->input('section');
        $columns = AccountingReportService::COLUMNS[$section];
        $print = $request->input('format') === 'print';
        if ($print && $section === 'invoices') {
            $columns = array_intersect_key($columns, array_flip(['id','date','patient_name','lab_name','referral_lab','doctor','created_by','total','paid','balance','credit','payment_status']));
        }
        // Every matching row is streamed, not merely the current table page.
        return response()->stream(function () use ($service, $section, $columns, $print, $request) {
            $out = fopen('php://output', 'w');
            if ($print) {
                echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><title>تقرير المحاسبة</title><style>body{font:12px Arial,sans-serif;color:#172b40;margin:24px}h1{font-size:23px}p{line-height:1.7}table{border-collapse:collapse;table-layout:fixed;width:100%;font-size:11px}th,td{border:1px solid #d5dce3;padding:8px;text-align:right;overflow-wrap:anywhere}th{background:#eef3f7}thead{display:table-header-group}tr{break-inside:avoid}small{color:#526173}button{padding:12px 20px;cursor:pointer}@page{size:A4 landscape;margin:12mm}@media print{button{display:none}body{margin:0}}</style><body><button onclick="window.print()">طباعة / حفظ PDF</button><h1>تقارير المحاسبة — '.e(AccountingReportService::TITLES[$section]).'</h1><p>'.e($service->reportLabel()).' | من '.e($request->input('from')).' إلى '.e($request->input('to')).'<br>أُعد في '.e(now()->format('Y-m-d H:i')).' — '.e(config('app.timezone')).' | المبالغ بالدينار العراقي</p><p>'.e($service->filterLabel()).'</p><p><small>'.e(AccountingReportService::BASIS_NOTE).'</small></p><table><thead><tr>';
                foreach ($columns as $label) echo '<th>'.e($label).'</th>';
                echo '</tr></thead><tbody>';
            } else {
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, array_values($columns), ',', '"', '');
            }
            $count = 0;
            $service->eachExportRow($section, function ($row) use ($out, $columns, $print, &$count) {
                $count++;
                $values = array_map(fn ($key) => AccountingReportService::displayValue($key, $row[$key] ?? null), array_keys($columns));
                if ($print) {
                    echo '<tr>';
                    foreach ($values as $value) echo '<td>'.e((string) $value).'</td>';
                    echo '</tr>';
                } else {
                    $values = array_map(fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@\-]/u', $value) ? "'".$value : $value, $values);
                    fputcsv($out, $values, ',', '"', '');
                }
            });
            if ($print) echo '</tbody></table><p>عدد السجلات: '.$count.'</p></body></html>';
            fclose($out);
        }, 200, ['Content-Type' => $print ? 'text/html; charset=UTF-8' : 'text/csv; charset=UTF-8',
            'Content-Disposition' => ($print ? 'inline' : 'attachment').'; filename="accounting-'.$section.'-'.$request->input('from').'.'.($print ? 'html' : 'csv').'"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
