<?php

namespace App\Services;

use App\Models\{Invoice, LabDevice, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/** Request-scoped audit: compare committed database state, never trust submitted old values. */
class AuditTrail
{
    private ?Request $request = null;
    private ?User $actor = null;
    private ?LabDevice $device = null;
    private array $models = [];
    private array $invoices = [];
    private bool $writing = false;
    private string $requestId = '';

    public function begin(Request $request): void
    {
        $this->request = $request;
        $this->actor = $request->user();
        $this->device = null;
        $this->models = $this->invoices = [];
        $this->requestId = (string) Str::uuid();
        if ($this->active() && $request->is('api/invoices/*', 'api/referral-portal/*invoices/*')) {
            $id = $request->route('invoiceId') ?? $request->route('id') ?? $request->input('invoice_id') ?? $request->input('id');
            // Patient-history route IDs identify a patient, not an invoice.
            if (! preg_match('/patient-|search-|get-samples/', $request->path()) && is_numeric($id)) {
                $this->watchInvoice((int) $id);
            }
        }
        if ($this->active() && ! $request->isMethod('GET')) {
            $controller = class_basename($request->route()?->getControllerClass() ?? '');
            $name = str_replace('Controller', '', $controller);
            $classes = ['App\\Models\\'.$name, 'App\\Models\\'.Str::singular($name)];
            $id = $request->input('id') ?? $request->route('id');
            foreach ($classes as $class) {
                if ($class === Invoice::class || ! class_exists($class) || ! is_subclass_of($class, Model::class) || ! is_numeric($id)) {
                    continue;
                }
                $model = (new $class)->newQueryWithoutScopes()->find($id);
                if ($model) {
                    $attrs = $model->getAttributes();
                    $ownerId = $model instanceof User ? self::tenantId($model) : ($attrs['lab_id_fk'] ?? $attrs['creator_id'] ?? null);
                    $owner = $ownerId ? User::withTrashed()->find($ownerId) : null;
                    if ((int) $this->actor->role_id === 1 || ($owner && self::tenantId($owner) === self::tenantId($this->actor)) || ($model instanceof User && $model->id === $this->actor->id)) {
                        $this->models[$class.':'.$id] = ['class' => $class, 'id' => $id, 'before' => AuditSnapshot::resource($model), 'invoice_id' => null, 'owner' => $ownerId];
                    }
                }
                break;
            }
        }
    }

    public function useDevice(LabDevice $device): void
    {
        if ($this->request && ! $this->actor) {
            $this->device = $device;
            $this->actor = User::find($device->lab_id_fk);
        }
    }

    public function active(): bool
    {
        return $this->request !== null && $this->actor !== null && ! $this->writing;
    }

    public static function tenantId(User $user): int
    {
        return app(InventoryService::class)->labId($user);
    }

    public static function invoiceVisible(User $user, Invoice $invoice): bool
    {
        if ((int) $user->role_id === 1) {
            return true;
        }
        $owner = User::withTrashed()->find($invoice->lab_id_fk);
        return $owner && self::tenantId($user) === self::tenantId($owner);
    }

    public function watchInvoice(int $id, bool $trusted = false, bool $created = false): void
    {
        if (array_key_exists($id, $this->invoices) || ! $this->active()) {
            return;
        }
        $invoice = Invoice::withTrashed()->find($id);
        if (! $invoice || (! $trusted && ! self::invoiceVisible($this->actor, $invoice))) {
            return;
        }
        $this->invoices[$id] = [
            'before' => $created || $invoice->trashed() ? null : AuditSnapshot::invoice($invoice),
            'owner' => $invoice->lab_id_fk,
        ];
    }

    public function observe(string $event, array $payload): void
    {
        $model = $payload[0] ?? null;
        if (! $this->active() || ! $model instanceof Model || ! str_starts_with(get_class($model), 'App\\Models\\')) {
            return;
        }
        $verb = explode(':', substr($event, strlen('eloquent.')))[0];
        $attrs = $model->getAttributes();
        $invoiceId = $model instanceof Invoice ? $model->getKey() : ($attrs['invoice_id_fk'] ?? null);
        if ($invoiceId) {
            $this->watchInvoice((int) $invoiceId, true, $model instanceof Invoice && $verb === 'created');
        }
        if (! $model->getKey()) {
            return;
        }
        $key = get_class($model).':'.$model->getKey();
        if (! isset($this->models[$key])) {
            $before = $verb === 'created' ? null : AuditSnapshot::resource($model->newFromBuilder($model->getRawOriginal()));
            $this->models[$key] = ['class' => get_class($model), 'id' => $model->getKey(), 'before' => $before,
                'invoice_id' => $invoiceId, 'owner' => $model instanceof User ? self::tenantId($model) : ($attrs['lab_id_fk'] ?? $attrs['creator_id'] ?? null)];
        }
        if ($invoiceId) {
            $this->models[$key]['invoice_id'] = $invoiceId;
        }
        if ($verb === 'updating') {
            foreach (array_keys($model->getDirty()) as $field) {
                if (AuditSnapshot::clean('value', $field) === AuditSnapshot::HIDDEN) {
                    $this->models[$key]['protected_fields'][$field] = true;
                }
            }
        }
    }

    public function finish(int $status): void
    {
        if (! $this->active()) {
            $this->reset();
            return;
        }
        $this->writing = true;
        try {
            $written = false;
            $contexts = [];
            foreach ($this->invoices as $id => $item) {
                $invoice = Invoice::withTrashed()->find($id);
                $after = $invoice && ! $invoice->trashed() ? AuditSnapshot::invoice($invoice) : null;
                $before = $item['before'];
                $details = $after ?? $before;
                $contexts[$id] = ['id' => $id, 'barcode' => $details['invoice']['barcode'] ?? null,
                    'patient_id' => $details['patient']['id'] ?? null, 'patient_name' => $details['patient']['name'] ?? null];
                if ($before === $after) {
                    continue;
                }
                $written = $this->writeChange(Invoice::class, $id, $before, $after, $item['owner'], $status, $contexts[$id]) || $written;
            }
            foreach ($this->models as $item) {
                if ($item['invoice_id'] && isset($this->invoices[$item['invoice_id']]) &&
                    in_array($item['class'], [Invoice::class, \App\Models\InvoiceTestRel::class, \App\Models\InvoicePaidDetail::class], true)) {
                    continue;
                }
                $model = (new $item['class'])->newQueryWithoutScopes()->find($item['id']);
                $after = $model && ! ($model->getAttributes()['deleted_at'] ?? null) ? AuditSnapshot::resource($model) : null;
                $written = $this->writeChange($item['class'], $item['id'], $item['before'], $after, $item['owner'], $status, $contexts[$item['invoice_id']] ?? null,
                    $status < 400 ? array_keys($item['protected_fields'] ?? []) : []) || $written;
            }
            // Also retain denied/failed attempts and actions without a model change.
            if (! $written || $status >= 400) {
                $id = array_key_first($this->invoices);
                $details = $id ? $this->invoices[$id]['before'] : null;
                $context = $id ? ['invoice' => ['id' => $id, 'barcode' => $details['invoice']['barcode'] ?? null,
                    'patient_name' => $details['patient']['name'] ?? null]] : [];
                $this->write($status >= 400 ? 'failed' : 'action', $this->operationLabel(), $id ? Invoice::class : null, $id, $context, $status);
            }
        } finally {
            $this->reset();
        }
    }

    public function reset(): void
    {
        $this->request = null;
        $this->actor = null;
        $this->device = null;
        $this->models = $this->invoices = [];
        $this->writing = false;
    }

    private function writeChange(string $class, mixed $id, ?array $before, ?array $after, mixed $owner, int $status, ?array $invoice = null, array $protectedFields = []): bool
    {
        $changes = AuditSnapshot::changes($before, $after);
        foreach ($protectedFields as $field) {
            $changes[] = ['field' => $field, 'label' => $field, 'before' => AuditSnapshot::HIDDEN,
                'after' => AuditSnapshot::HIDDEN, 'kind' => 'changed'];
        }
        if (! $changes) {
            return false;
        }
        $event = $before === null ? 'created' : ($after === null ? 'deleted' : 'updated');
        $this->write($event, $this->operationLabel(), $class, $id, [
            'old_value' => $before, 'new_value' => $after, 'changes' => $changes, 'invoice' => $invoice,
            'subject_label' => $after['name'] ?? $before['name'] ?? $after['group_name'] ?? $before['group_name'] ?? class_basename($class),
        ], $status, $owner);
        return true;
    }

    private function operationLabel(): string
    {
        $operation = class_basename($this->request->route()?->getControllerClass() ?? '').'@'.($this->request->route()?->getActionMethod() ?? '');
        if ($operation === 'InvoiceController@recordReportAction') {
            return [
                'print' => 'طباعة التقرير الطبي', 'download' => 'حفظ التقرير الطبي',
                'print-download' => 'طباعة وحفظ التقرير الطبي',
                'whatsapp' => 'بدء إرسال النتيجة بالواتساب',
                'whatsapp-download' => 'حفظ وبدء إرسال التقرير بالواتساب',
            ][$this->request->input('action')] ?? 'إخراج التقرير الطبي';
        }
        $labels = [
            'InvoiceController@store' => 'إنشاء فاتورة', 'InvoiceController@update' => 'تعديل فاتورة',
            'InvoiceController@destroy' => 'حذف فاتورة', 'InvoiceController@updateResult' => 'تعديل نتائج التحاليل',
            'InvoiceController@addPayment' => 'إضافة تسديد للفاتورة', 'InvoiceController@signInvoice' => 'تغيير توقيع التقرير',
            'InvoiceController@sendInvoice' => 'بدء إرسال النتيجة بالواتساب', 'InvoiceController@savePdf' => 'حفظ ملف التقرير',
            'BridgeResultController@receive' => 'استلام نتائج الجهاز', 'DeviceResultController@applyResults' => 'تطبيق نتائج الجهاز',
            'LabSettingController@update' => 'تعديل إعدادات المختبر',
            'AuthController@logout' => 'تسجيل الخروج',
        ];
        if (isset($labels[$operation])) {
            return $labels[$operation];
        }
        [$controller, $method] = explode('@', $operation);
        $resource = [
            'PatientsController' => 'بيانات المريض', 'TestController' => 'التحاليل', 'TestGroupController' => 'كروبات التحاليل',
            'PackageController' => 'الباقات', 'CultureController' => 'الزروع', 'UserController' => 'المستخدمين',
            'LabSettingController' => 'إعدادات المختبر', 'ReferalController' => 'الإحالات', 'InventoryController' => 'المخزون',
            'LabDeviceController' => 'الأجهزة', 'DeviceResultController' => 'نتائج الأجهزة', 'SampleController' => 'العينات',
            'AntibioticsController' => 'المضادات الحيوية', 'DoctorController' => 'الأطباء', 'ContractController' => 'العقود',
        ][$controller] ?? str_replace('Controller', '', $controller);
        $verb = ['store' => 'إنشاء', 'update' => 'تعديل', 'destroy' => 'حذف', 'delete' => 'حذف', 'show' => 'عرض', 'index' => 'عرض', 'import' => 'استيراد', 'export' => 'تصدير'][$method] ?? 'إجراء';
        return $verb.' / '.$resource;
    }

    private function write(string $event, string $description, ?string $class, mixed $id, array $properties, int $status, mixed $owner = null): void
    {
        $ownerUser = $owner ? User::withTrashed()->find($owner) : null;
        $tenant = self::tenantId($ownerUser ?? $this->actor);
        $actorName = $this->device ? 'جهاز: '.$this->device->name : $this->actor->name;
        Activity::create([
            'log_name' => ['created' => 'إنشاء', 'updated' => 'تعديل', 'deleted' => 'حذف', 'failed' => 'فشل', 'action' => 'إجراء'][$event],
            'event' => $event, 'description' => $description,
            'creator_id' => $tenant, 'audit_tenant_id' => $tenant,
            'audit_invoice_id' => $properties['invoice']['id'] ?? null,
            'audit_request_id' => $this->requestId, 'audit_source' => $this->device ? 'device' : 'server',
            'causer_id' => $this->device?->id ?? $this->actor->id, 'causer_type' => $this->device ? LabDevice::class : User::class,
            'subject_type' => $class, 'subject_id' => $id,
            'properties' => array_merge($properties, [
                'schema_version' => 2, 'actor_name' => $actorName, 'actor_role' => $this->device ? 'جهاز مختبري' : $this->actor->role?->name,
                'status' => $status >= 400 ? 'failed' : 'success',
                'method' => $this->request->method(), 'path' => '/'.$this->request->path(), 'http_status' => $status,
                'action_id' => Str::isUuid($this->request->header('X-Audit-Action-Id', '')) ? $this->request->header('X-Audit-Action-Id') : null,
                'button' => mb_substr(strip_tags(rawurldecode($this->request->header('X-Audit-Label', ''))), 0, 160),
                'page' => self::safePage($this->request->header('X-Audit-Page', '')),
            ]),
        ]);
    }

    public static function safePage(string $page): string
    {
        $path = parse_url($page, PHP_URL_PATH) ?: '';
        if (preg_match('~/(portal|result|invoice)/~', $path)) {
            return '/public-report';
        }
        return mb_substr($path, 0, 200);
    }
}
