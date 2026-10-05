<?php

namespace App\Http\Controllers;

use App\Models\{Invoice, User};
use App\Services\{AuditSnapshot, AuditTrail};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    private function scopedQuery()
    {
        $user = Auth::user();
        abort_unless($user?->hasPermissionTo('activities view'), 403);
        $query = Activity::query();
        if ((int) $user->role_id !== 1) {
            $ids = $this->getTenantUserIds();
            $tenant = AuditTrail::tenantId($user);
            $query->where(function ($q) use ($ids, $tenant) {
                $q->where('audit_tenant_id', $tenant)->orWhere(function ($legacy) use ($ids) {
                    $legacy->whereNull('audit_tenant_id')->where(function ($scope) use ($ids) {
                        $scope->whereIn('creator_id', $ids)->orWhereIn('causer_id', $ids);
                    });
                });
            });
        }
        return $query;
    }

    private function filteredQuery(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:200', 'invoice_id' => 'nullable|integer|min:1',
            'causer_id' => 'nullable|integer|min:1', 'event' => 'nullable|in:created,updated,deleted,failed,action,clicked,login',
            'source' => 'nullable|in:server,device,interface,legacy',
            'date_from' => 'nullable|date_format:Y-m-d', 'date_to' => 'nullable|date_format:Y-m-d'.($request->filled('date_from') ? '|after_or_equal:date_from' : ''),
            'per_page' => 'nullable|integer|min:10|max:100', 'page' => 'nullable|integer|min:1',
            'action_id' => 'nullable|uuid',
        ]);
        $q = $this->scopedQuery();
        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $q->where(fn ($x) => $x->where('description', 'like', $term)->orWhere('properties', 'like', $term));
        }
        if ($request->filled('invoice_id')) {
            $id = $request->integer('invoice_id');
            $q->where(fn ($x) => $x->where('audit_invoice_id', $id)->orWhere(fn ($old) =>
                $old->whereNull('audit_invoice_id')->where('subject_type', Invoice::class)->where('subject_id', $id)));
        }
        if ($request->filled('causer_id')) {
            $q->where('causer_id', $request->integer('causer_id'))->where(function ($x) {
                $x->where('causer_type', User::class)->orWhereNull('causer_type');
            });
        }
        if ($request->filled('event')) {
            $event = $request->string('event')->toString();
            $legacy = ['created' => 'إنشاء', 'updated' => 'تعديل', 'deleted' => 'حذف', 'failed' => 'خطأ', 'login' => 'تسجيل الدخول'][$event] ?? null;
            $q->where(fn ($x) => $x->where('event', $event)->when($legacy, fn ($y) => $y->orWhere('log_name', $legacy)));
        }
        if ($request->filled('source')) {
            $request->source === 'legacy' ? $q->whereNull('audit_source') : $q->where('audit_source', $request->source);
        }
        if ($request->filled('action_id')) {
            $q->where('properties->action_id', $request->action_id);
        }
        if ($request->filled('date_from')) {
            $q->where('created_at', '>=', Carbon::parse($request->date_from, 'Asia/Baghdad')->startOfDay()->utc());
        }
        if ($request->filled('date_to')) {
            $q->where('created_at', '<=', Carbon::parse($request->date_to, 'Asia/Baghdad')->endOfDay()->utc());
        }
        return $q;
    }

    public function index(Request $request)
    {
        $q = $this->filteredQuery($request);
        $stats = [
            'total' => (clone $q)->count(),
            'changed' => (clone $q)->where(fn ($x) => $x->whereIn('event', ['created', 'updated', 'deleted'])->orWhereIn('log_name', ['إنشاء', 'تعديل', 'حذف']))->count(),
            'failed' => (clone $q)->where(fn ($x) => $x->where('event', 'failed')->orWhere('log_name', 'خطأ'))->count(),
            'clicks' => (clone $q)->where('audit_source', 'interface')->count(),
        ];
        $page = $q->orderByDesc('id')->paginate($request->integer('per_page', 25));
        $users = $this->usersFor($page->getCollection());
        return response()->json([
            'data' => $page->getCollection()->map(fn ($activity) => $this->format($activity, $users, false)),
            'pagination' => ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(),
                'total' => $page->total(), 'last_page' => $page->lastPage(), 'from' => $page->firstItem(), 'to' => $page->lastItem()],
            'stats' => $stats,
        ]);
    }

    public function show(int $id)
    {
        $activity = $this->scopedQuery()->findOrFail($id);
        return response()->json($this->format($activity, $this->usersFor(collect([$activity])), true));
    }

    private function usersFor($activities)
    {
        $ids = $activities->filter(fn ($a) => ! $a->causer_type || $a->causer_type === User::class)->pluck('causer_id')->filter()->unique();
        return User::withTrashed()->with('role')->whereIn('id', $ids)->get()->keyBy('id');
    }

    private function format(Activity $activity, $users, bool $details): array
    {
        $props = AuditSnapshot::clean($activity->properties?->toArray() ?? []);
        $user = $activity->causer_type === User::class || ! $activity->causer_type ? $users->get($activity->causer_id) : null;
        $event = $activity->event ?: ['إنشاء' => 'created', 'تعديل' => 'updated', 'حذف' => 'deleted', 'خطأ' => 'failed', 'تسجيل الدخول' => 'login'][$activity->log_name] ?? 'action';
        $changes = $props['changes'] ?? AuditSnapshot::changes($props['old_value'] ?? null, $props['new_value'] ?? null);
        $invoice = $props['invoice'] ?? null;
        if (! $invoice && $activity->subject_type === Invoice::class) {
            $old = $props['new_value'] ?? $props['old_value'] ?? [];
            $invoice = ['id' => $activity->subject_id, 'barcode' => $old['barcode'] ?? null];
        }
        return [
            'id' => $activity->id, 'event' => $event, 'log_name' => $activity->log_name,
            'description' => $activity->description, 'created_at' => $activity->created_at,
            'causer_id' => $activity->causer_id, 'causer_name' => $props['actor_name'] ?? $user?->name ?? 'النظام',
            'causer_role' => $props['actor_role'] ?? $user?->role?->name,
            'subject_id' => $activity->subject_id, 'subject_type' => class_basename($activity->subject_type ?? ''),
            'subject_label' => $props['subject_label'] ?? null, 'invoice' => $invoice,
            'source' => $activity->audit_source ?? 'legacy', 'request_id' => $activity->audit_request_id,
            'action_id' => $props['action_id'] ?? null, 'button' => $props['button'] ?? null,
            'page' => $props['page'] ?? null, 'status' => $props['status'] ?? 'legacy',
            'http_status' => $props['http_status'] ?? null, 'change_count' => count($changes),
            'changes' => $details ? $changes : array_slice($changes, 0, 3),
            'properties' => $details ? $props : null,
        ];
    }

    public function export(Request $request)
    {
        $query = $this->filteredQuery($request);
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['رقم السجل', 'التاريخ UTC', 'المستخدم', 'الإجراء', 'المصدر', 'الحالة', 'رقم الفاتورة', 'الباركود', 'المريض', 'الزر', 'الحقل', 'قبل', 'بعد'], ',', '"', '');
            $query->orderBy('id')->chunkById(100, function ($rows) use ($out) {
                $users = $this->usersFor($rows);
                foreach ($rows as $row) {
                    $item = $this->format($row, $users, true);
                    foreach ($item['changes'] ?: [[]] as $change) {
                        $cells = [$item['id'], $item['created_at']?->toIso8601String(), $item['causer_name'], $item['description'],
                            $item['source'], $item['status'], $item['invoice']['id'] ?? '', $item['invoice']['barcode'] ?? '',
                            $item['invoice']['patient_name'] ?? '', $item['button'], $change['label'] ?? '', $change['before'] ?? '', $change['after'] ?? ''];
                        $cells = array_map(function ($cell) {
                            $text = is_array($cell) ? json_encode($cell, JSON_UNESCAPED_UNICODE) : (is_bool($cell) ? ($cell ? 'نعم' : 'لا') : (string) $cell);
                            return preg_match('/^[\s]*[=+@\-]/u', $text) ? "'".$text : $text;
                        }, $cells);
                        fputcsv($out, $cells, ',', '"', '');
                    }
                }
            });
            fclose($out);
        }, 'activity-report-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    public function actions(Request $request)
    {
        $data = $request->validate([
            'actions' => 'required|array|min:1|max:20', 'actions.*.action_id' => 'required|uuid',
            'actions.*.label' => 'required|string|max:160', 'actions.*.page' => 'required|string|max:200',
            'actions.*.invoice_id' => 'nullable|integer|min:1',
        ]);
        $user = $request->user();
        abort_if((int) $user->role_id === 6 || $user->referral_portal_only, 403);
        $tenant = AuditTrail::tenantId($user);
        foreach ($data['actions'] as $action) {
            $invoice = isset($action['invoice_id']) ? Invoice::withTrashed()->find($action['invoice_id']) : null;
            if ($invoice && ! AuditTrail::invoiceVisible($user, $invoice)) {
                $invoice = null;
            }
            // A retried batch cannot duplicate a click in the same account.
            if (Activity::where('causer_id', $user->id)->where('audit_source', 'interface')->where('audit_request_id', $action['action_id'])->exists()) {
                continue;
            }
            Activity::create([
                'event' => 'clicked', 'log_name' => 'نقرة زر', 'description' => mb_substr(strip_tags($action['label']), 0, 160),
                'creator_id' => $tenant, 'audit_tenant_id' => $tenant, 'audit_invoice_id' => $invoice?->id,
                'audit_request_id' => $action['action_id'], 'audit_source' => 'interface',
                'causer_id' => $user->id, 'causer_type' => User::class,
                'properties' => ['schema_version' => 2, 'actor_name' => $user->name, 'actor_role' => $user->role?->name,
                    'status' => 'clicked', 'action_id' => $action['action_id'], 'button' => strip_tags($action['label']),
                    'page' => AuditTrail::safePage($action['page']),
                    'invoice' => $invoice ? ['id' => $invoice->id, 'barcode' => $invoice->barcode] : null,
                ],
            ]);
        }
        return response()->json(['recorded' => true]);
    }

    // Backward-compatible entry points for existing controllers. Request audit
    // already captures their database changes, including related rows.
    private static function legacy(string $event, string $description, mixed $old = null, mixed $new = null): void
    {
        if (app(AuditTrail::class)->active()) {
            return;
        }
        $user = Auth::user();
        $subject = $new ?? $old;
        $before = AuditSnapshot::clean($old);
        $after = AuditSnapshot::clean($new);
        Activity::create([
            'log_name' => ['created' => 'إنشاء', 'updated' => 'تعديل', 'deleted' => 'حذف', 'failed' => 'خطأ', 'login' => 'تسجيل الدخول'][$event] ?? 'إجراء',
            'event' => $event, 'description' => $event === 'failed' ? 'فشل تنفيذ العملية' : $description,
            'creator_id' => $user ? AuditTrail::tenantId($user) : null,
            'audit_tenant_id' => $user ? AuditTrail::tenantId($user) : null, 'audit_source' => 'server',
            'causer_id' => $user?->id, 'causer_type' => $user ? User::class : null,
            'subject_id' => $subject instanceof \Illuminate\Database\Eloquent\Model ? $subject->getKey() : null,
            'subject_type' => $subject instanceof \Illuminate\Database\Eloquent\Model ? get_class($subject) : null,
            'properties' => ['old_value' => $before, 'new_value' => $after, 'changes' => AuditSnapshot::changes($before, $after),
                'actor_name' => $user?->name, 'actor_role' => $user?->role?->name, 'status' => $event === 'failed' ? 'failed' : 'success'],
        ]);
    }

    public static function loginActivity($description) { self::legacy('login', $description); }
    public static function registerActivity($description) { self::legacy('created', $description); }
    public static function storeActivity($description, $value) { self::legacy('created', $description, null, $value); }
    public static function errorActivity($description) { self::legacy('failed', $description); }
    public static function updateActivity($description, $old_value, $new_value) { self::legacy('updated', $description, $old_value, $new_value); }
    public static function deleteActivity($description, $deleted_value) { self::legacy('deleted', $description, $deleted_value); }
}
