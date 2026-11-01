<?php

namespace App\Http\Controllers;

use App\Models\{Invoice, User};
use App\Services\{AuditSnapshot, InvoiceTestFilter, OwnerAccess, OwnerAccountingReport};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class OwnerPortalController extends Controller
{
    private function context(Request $request): array
    {
        $today = Carbon::now('Asia/Baghdad')->toDateString();
        $data = $request->validate([
            'lab_id' => 'nullable|integer|min:1', 'from' => 'nullable|date_format:Y-m-d|before_or_equal:'.$today,
            'to' => 'nullable|date_format:Y-m-d|before_or_equal:'.$today,
            'search' => 'nullable|string|max:150', 'page' => 'nullable|integer|min:1',
            'status' => 'nullable|in:paid,unpaid,partial,credit', 'result_status' => 'nullable|in:done,pending',
            'doctor_id' => 'nullable|integer|min:1', 'lab_referral_id' => 'nullable|integer|min:1',
            'test_id' => 'nullable|integer|min:1', 'actor_id' => 'nullable|integer|min:1',
            'event' => 'nullable|in:created,updated,deleted,failed,action,clicked,login',
            'change_type' => 'nullable|in:deleted,financial,results', 'invoice_id' => 'nullable|integer|min:1',
        ]);
        $data['from'] = $data['from'] ?? $today; $data['to'] = $data['to'] ?? $today;
        $days = Carbon::parse($data['from'])->diffInDays(Carbon::parse($data['to']), false);
        abort_if($days < 0 || $days > 92, 422, 'اختر فترة صحيحة لا تتجاوز 93 يوماً.');
        $labs = app(OwnerAccess::class)->selected($request->user(), $request->integer('lab_id') ?: null);
        $ids = app(OwnerAccess::class)->accounts($labs);
        return [$data, $labs, $ids, new OwnerAccountingReport($request->user(), $data, $ids)];
    }

    private function dateFilter($query, string $column, array $range): void
    {
        $query->where($column, '>=', Carbon::parse($range['from'], 'Asia/Baghdad')->startOfDay()->setTimezone(config('app.timezone')))
            ->where($column, '<', Carbon::parse($range['to'], 'Asia/Baghdad')->addDay()->startOfDay()->setTimezone(config('app.timezone')));
    }

    public function me(Request $request)
    {
        $labs = app(OwnerAccess::class)->labs($request->user());
        $labs->load('subscription');
        return response()->json(['owner' => $request->user()->only(['id', 'name', 'email']), 'labs' => $labs->map(function ($lab) {
            $sub = $lab->subscription;
            return ['id' => $lab->id, 'name' => $lab->name, 'active' => (bool) $lab->status,
                'subscription' => $sub ? ['plan' => $sub->plan_name, 'status' => $sub->effective_status,
                    'end_date' => $sub->end_date?->toDateString(), 'days_left' => $sub->end_date ? (int) Carbon::today('Asia/Baghdad')->diffInDays(Carbon::parse($sub->end_date->toDateString(), 'Asia/Baghdad'), false) : null] : null];
        })])->header('Cache-Control', 'private, no-store');
    }

    public function overview(Request $request)
    {
        [$range, $labs, $ids, $service] = $this->context($request);
        $report = $service->overview();
        $report['meta']['timezone'] = 'Asia/Baghdad';
        $report['meta']['lab_name'] = $labs->count() === 1 ? $labs->first()->name : 'كل مختبراتي';
        $query = $service->query();
        $report['results'] = ['completed' => (clone $query)->where('invoices.is_done', true)->count(),
            'pending' => (clone $query)->where(fn ($q) => $q->where('invoices.is_done', false)->orWhereNull('invoices.is_done'))->count()];
        $report['comparison'] = $this->comparison($request, $range, $ids, $report);
        // Keep a single lightweight response, without sending full patient account lists.
        unset($report['patients'], $report['contracts'], $report['outbound']);
        return response()->json($report)->header('Cache-Control', 'private, no-store');
    }

    private function comparison(Request $request, array $range, array $ids, array $report): array
    {
        $days = (int) Carbon::parse($range['from'])->diffInDays(Carbon::parse($range['to'])) + 1;
        $previous = ['from' => Carbon::parse($range['from'])->subDays($days)->toDateString(), 'to' => Carbon::parse($range['from'])->subDay()->toDateString()];
        $now = Carbon::now('Asia/Baghdad');
        $openDay = $range['to'] === $now->toDateString();
        $current = (new OwnerAccountingReport($request->user(), $range, $ids))->query();
        $prior = (new OwnerAccountingReport($request->user(), $previous, $ids))->query();
        if ($openDay) {
            $current->where('invoices.created_at', '<=', $now->copy()->setTimezone(config('app.timezone')));
            $prior->where('invoices.created_at', '<=', $now->copy()->subDays($days)->setTimezone(config('app.timezone')));
        }
        $count = (clone $current)->count(); $before = (clone $prior)->count();
        $percent = $before > 0 ? round(($count - $before) * 100 / $before, 1) : null;
        $group = function ($query) {
            return $query->get(['invoices.from_lab_id_fk', 'invoices.referral_id_fk'])->groupBy(fn ($i) => $i->from_lab_id_fk ? 'lab:'.$i->from_lab_id_fk : ($i->referral_id_fk ? 'referral:'.$i->referral_id_fk : 'direct'))
                ->map(fn ($items) => $items->count());
        };
        $currentSources = $group(clone $current); $priorSources = $group(clone $prior);
        $drops = $priorSources->map(function ($number, $key) use ($currentSources) {
            $id = str_contains($key, ':') ? (int) explode(':', $key)[1] : null;
            return ['name' => $id ? (User::withTrashed()->find($id)?->name ?? 'إحالة محذوفة') : 'زيارات مباشرة',
                'previous' => $number, 'current' => $currentSources->get($key, 0), 'decrease' => $number - $currentSources->get($key, 0)];
        })->filter(fn ($r) => $r['decrease'] > 0)->sortByDesc('decrease')->take(5)->values();
        $suggestions = [];
        if ($percent !== null && $percent < 0) $suggestions[] = 'راجع مصادر الإحالة التي انخفض عددها، وتواصل مع الجهات المعنية لفهم السبب قبل اتخاذ قرار.';
        if ($report['results']['pending'] > 0) $suggestions[] = 'تابع التقارير غير المكتملة مع فريق المختبر وحدد موعد إنجازها.';
        if ($report['summary']['balance'] > 0) $suggestions[] = 'راجع الفواتير المتبقية ونظّم متابعة التسديد مع المرضى والجهات المحيلة.';
        if ($before === 0) $suggestions[] = 'لا توجد زيارات في فترة المقارنة؛ اجمع بيانات لأيام إضافية قبل تقييم اتجاه الأداء.';
        if (! $suggestions) $suggestions[] = 'تابع استمرار الأداء، وراجع رضا المراجعين ومصادر الإحالة بصورة منتظمة.';
        return ['current' => $count, 'previous' => $before, 'percent' => $percent, ...$previous,
            'same_elapsed_time' => $openDay, 'sources_decreased' => $drops, 'suggestions' => $suggestions,
            'note' => 'مقارنة بالفترة السابقة المساوية، وبنفس الوقت المنقضي عند شمول اليوم. المؤشرات تصف التغير ولا تثبت سببه أو مسؤولية موظف.'];
    }

    private function invoiceQuery(Request $request, OwnerAccountingReport $service)
    {
        $query = $service->query();
        if ($request->result_status === 'done') $query->where('invoices.is_done', true);
        if ($request->result_status === 'pending') $query->where(fn ($q) => $q->where('invoices.is_done', false)->orWhereNull('invoices.is_done'));
        if ($request->filled('test_id')) app(InvoiceTestFilter::class)->apply($query, [$request->integer('test_id')]);
        return $query;
    }

    private function timestamp(?string $value): ?string
    {
        return $value ? Carbon::parse($value, config('app.timezone'))->toIso8601String() : null;
    }

    public function invoices(Request $request)
    {
        [, , , $service] = $this->context($request);
        $page = $this->invoiceQuery($request, $service)->orderByDesc('invoices.id')->paginate(20);
        $done = $page->getCollection()->keyBy('id');
        $rows = $service->rows($page->getCollection())->map(function ($row) use ($done) {
            unset($row['items'], $row['payments']);
            $row['is_done'] = (bool) $done[$row['id']]->is_done;
            $row['date'] = $this->timestamp($row['date']);
            return $row;
        });
        return response()->json(['data' => $rows, 'pagination' => $this->pagination($page)])->header('Cache-Control', 'private, no-store');
    }

    public function invoice(Request $request, int $id)
    {
        [, , , $service] = $this->context($request);
        // A detail is scoped by ownership, not the dashboard's current date range.
        $invoice = $service->query(false, false)->where('invoices.id', $id)->firstOrFail();
        $row = $service->rows(collect([$invoice]))->first();
        $row['results'] = AuditSnapshot::invoice($invoice)['analyses'];
        $row['is_done'] = (bool) $invoice->is_done;
        $row['date'] = $this->timestamp($row['date']);
        $row['payments'] = collect($row['payments'])->map(fn ($p) => [...$p, 'date' => $this->timestamp($p['date'])])->values();
        return response()->json($row)->header('Cache-Control', 'private, no-store');
    }

    private function auditQuery(Request $request, bool $dates = true)
    {
        [$range, $labs, $ids] = $this->context($request);
        $q = Activity::where(function ($q) use ($labs, $ids) {
            $q->whereIn('audit_tenant_id', $labs->pluck('id'))->orWhere(fn ($legacy) => $legacy->whereNull('audit_tenant_id')->where(fn ($a) => $a->whereIn('creator_id', $ids)->orWhere(fn ($b) => $b->where('causer_type', User::class)->whereIn('causer_id', $ids))));
        });
        if ($dates) $this->dateFilter($q, 'created_at', $range);
        if ($request->filled('actor_id')) $q->where('causer_type', User::class)->where('causer_id', $request->integer('actor_id'));
        if ($request->filled('event')) $q->where('event', $request->event);
        if ($request->filled('invoice_id')) $q->where('audit_invoice_id', $request->integer('invoice_id'));
        if ($request->filled('search')) $q->where(fn ($s) => $s->where('description', 'like', '%'.$request->search.'%')->orWhere('properties', 'like', '%'.$request->search.'%'));
        return $q;
    }

    public function activity(Request $request)
    {
        $q = $this->auditQuery($request);
        $summary = ['total' => (clone $q)->count(), 'failed' => (clone $q)->where('event', 'failed')->count(),
            'employees' => (clone $q)->where('causer_type', User::class)->distinct()->count('causer_id')];
        $page = $q->orderByDesc('id')->paginate(20);
        return response()->json(['data' => $page->getCollection()->map(fn ($r) => $this->auditRow($r)), 'summary' => $summary, 'pagination' => $this->pagination($page)]);
    }

    private function auditRow(Activity $r, bool $detail = false): array
    {
        $p = AuditSnapshot::clean($r->properties?->toArray() ?? []);
        return ['id' => $r->id, 'date' => $r->created_at, 'event' => $r->event, 'description' => $r->description,
            'actor' => $p['actor_name'] ?? 'غير مسجل', 'role' => $p['actor_role'] ?? null, 'source' => $r->audit_source ?? 'legacy',
            'status' => $p['status'] ?? 'legacy', 'invoice' => $p['invoice'] ?? null, 'button' => $p['button'] ?? null,
            'details' => $detail ? $p : null];
    }

    public function activityDetail(Request $request, int $id)
    {
        return response()->json($this->auditRow($this->auditQuery($request, false)->findOrFail($id), true));
    }

    private function changesQuery(Request $request, bool $dates = true)
    {
        [$range, $labs] = $this->context($request);
        $q = DB::table('invoice_change_archive')->whereIn('tenant_id', $labs->pluck('id'));
        if ($dates) $this->dateFilter($q, 'occurred_at', $range);
        if ($request->change_type === 'deleted') $q->where('event', 'deleted');
        if ($request->change_type === 'financial') $q->where('financial_changed', true);
        if ($request->change_type === 'results') $q->where('results_changed', true);
        if ($request->filled('actor_id')) $q->where('actor_id', $request->integer('actor_id'));
        if ($request->filled('invoice_id')) $q->where('invoice_id', $request->integer('invoice_id'));
        if ($request->filled('search')) $q->where(fn ($s) => $s->where('patient_name', 'like', '%'.$request->search.'%')->orWhere('barcode', 'like', '%'.$request->search.'%')->orWhere('actor_name', 'like', '%'.$request->search.'%'));
        return $q;
    }

    public function changes(Request $request)
    {
        $q = $this->changesQuery($request);
        $summary = (clone $q)->selectRaw("COUNT(*) AS changes_count, COUNT(DISTINCT invoice_id) AS invoices_count,
            SUM(CASE WHEN event = 'deleted' THEN 1 ELSE 0 END) AS deleted_count,
            COALESCE(SUM(CASE WHEN event = 'deleted' THEN before_total ELSE 0 END), 0) AS deleted_total,
            COALESCE(SUM(CASE WHEN event <> 'deleted' AND total_delta > 0 THEN total_delta ELSE 0 END), 0) AS increases,
            COALESCE(SUM(CASE WHEN event <> 'deleted' AND total_delta < 0 THEN -total_delta ELSE 0 END), 0) AS decreases,
            COALESCE(SUM(total_delta), 0) AS net_change, COALESCE(SUM(paid_delta), 0) AS payments_change,
            SUM(CASE WHEN total_delta IS NULL THEN 1 ELSE 0 END) AS unknown_amounts")->first();
        $page = $q->orderByDesc('id')->paginate(20, ['id', 'tenant_id', 'invoice_id', 'actor_id', 'actor_name', 'patient_name', 'barcode', 'event', 'source',
            'financial_changed', 'results_changed', 'before_total', 'after_total', 'total_delta', 'paid_delta', 'occurred_at']);
        $page->getCollection()->transform(function ($row) { $row->occurred_at = $this->timestamp($row->occurred_at); return $row; });
        return response()->json(['data' => $page->items(), 'summary' => $summary, 'pagination' => $this->pagination($page),
            'note' => 'الإجماليات تمثل حركة التعديلات المسجلة، وليست إيراداً إضافياً. قيمة المحذوفات منفصلة عن تخفيضات التعديل. السجلات القديمة التي لم تُحفظ سابقاً لا يمكن استعادتها.']);
    }

    public function changeDetail(Request $request, int $id)
    {
        $row = $this->changesQuery($request, false)->where('id', $id)->first(); abort_unless($row, 404);
        $row->snapshot = json_decode($row->snapshot, true);
        $row->occurred_at = $this->timestamp($row->occurred_at);
        return response()->json($row)->header('Cache-Control', 'private, no-store');
    }

    private function pagination($page): array
    {
        return ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()];
    }
}
