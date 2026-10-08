<?php

namespace App\Services;

use App\Models\{Invoice, InvoiceTestRel, User};
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Read-only accounting projections. Never alter invoices, balances or results. */
class AccountingReportService
{
    private array $owners = [];
    private array $accountScopes = [];
    public function __construct(private User $actor, private array $filters) {}

    private function accounts(int $owner): array
    {
        if (isset($this->accountScopes[$owner])) return $this->accountScopes[$owner];
        // Include deleted staff without crossing an independent child lab.
        $ids = [$owner]; $frontier = [$owner];
        while ($frontier) {
            $frontier = User::withTrashed()->whereIn('creator_id', $frontier)
                ->whereNotIn('role_id', [1, 2, 3, 5])->where('referral_portal_only', false)
                ->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }
        return $this->accountScopes[$owner] = $ids;
    }

    public function scope(): Builder
    {
        $query = Invoice::query();
        if ((int) $this->actor->role_id !== 1) {
            $query->whereIn('invoices.lab_id_fk', $this->accounts(app(InventoryService::class)->labId($this->actor)));
        }
        return $query;
    }

    public function query(bool $invoiceDates = true, bool $dimensions = true): Builder
    {
        $payments = DB::table('invoice_paid_details')->whereNull('deleted_at')
            ->select('invoice_id_fk')->selectRaw('SUM(COALESCE(amount,0)) AS amount')->groupBy('invoice_id_fk');
        $creations = DB::table('activity_log')->where('subject_type', Invoice::class)
            ->where('event', 'created')->where('causer_type', User::class)
            ->select('subject_id')->selectRaw('MIN(id) AS creation_id')->groupBy('subject_id');
        $actorSql = 'COALESCE(accounting_audit.causer_id, CASE WHEN invoices.referral_request_uuid IS NOT NULL THEN invoices.from_lab_id_fk ELSE invoices.lab_id_fk END)';
        $query = $this->scope()->leftJoinSub($payments, 'accounting_paid', 'accounting_paid.invoice_id_fk', '=', 'invoices.id')
            ->leftJoinSub($creations, 'accounting_creation', 'accounting_creation.subject_id', '=', 'invoices.id')
            ->leftJoin('activity_log as accounting_audit', 'accounting_audit.id', '=', 'accounting_creation.creation_id')
            ->leftJoin('users as accounting_actor', 'accounting_actor.id', '=', DB::raw($actorSql))
            ->select('invoices.*')->selectRaw('COALESCE(accounting_paid.amount,0) AS recorded_paid')
            ->selectRaw($actorSql.' AS entry_actor_id')
            ->selectRaw('accounting_actor.name AS entry_actor_name, accounting_audit.id AS entry_audit_id');
        if ($invoiceDates) $this->dateRange($query, 'invoices.created_at');
        if (! $dimensions) return $query;
        if (!empty($this->filters['owner_id'])) $query->whereIn('invoices.lab_id_fk', $this->accounts((int)$this->filters['owner_id']));
        foreach (['branch_id'=>'lab_id_fk', 'sample_collector_id'=>'sample_collector_id_fk', 'contract_id'=>'contract_id_fk', 'patient_id'=>'patient_id_fk'] as $filter=>$column) {
            if (! empty($this->filters[$filter])) $query->where('invoices.'.$column, $this->filters[$filter]);
        }
        if (! empty($this->filters['created_by'])) $query->whereRaw($actorSql.' = ?', [$this->filters['created_by']]);
        if (! empty($this->filters['lab_referral_id'])) $query->where(fn ($q) => $q->where('invoices.from_lab_id_fk', $this->filters['lab_referral_id'])
            ->orWhere(fn ($q) => $q->whereNull('invoices.from_lab_id_fk')->where('invoices.referral_id_fk', $this->filters['lab_referral_id'])->whereHas('referral', fn ($q) => $q->whereIn('role_id', [2,4]))));
        if (! empty($this->filters['doctor_id'])) $query->where('invoices.referral_id_fk', $this->filters['doctor_id'])->whereHas('referral', fn ($q) => $q->where('role_id',5));
        if (($this->filters['referral_type'] ?? '') === 'lab') $query->where(fn ($q) => $q->whereNotNull('invoices.from_lab_id_fk')->orWhereHas('referral', fn ($r) => $r->whereIn('role_id',[2,4])));
        if (($this->filters['referral_type'] ?? '') === 'doctor') $query->whereHas('referral', fn ($q) => $q->where('role_id',5));
        $paid = 'COALESCE(accounting_paid.amount,0)';
        match ($this->filters['status'] ?? '') {
            'unpaid' => $query->whereRaw("$paid <= 0 AND invoices.total > 0"),
            'partial' => $query->whereRaw("$paid > 0 AND $paid < invoices.total"),
            'paid' => $query->whereRaw("$paid = COALESCE(invoices.total,0)"),
            'credit' => $query->whereRaw("$paid > COALESCE(invoices.total,0)"),
            default => null,
        };
        if ($search = trim($this->filters['search'] ?? '')) {
            $term = '%'.str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $search).'%';
            $query->where(fn ($q) => $q->where('invoices.id', $search)->orWhere('invoices.barcode','like',$term)
                ->orWhereHas('patient', fn ($p) => $p->where('code','like',$term)->orWhereHas('user',fn($u)=>$u->where('name','like',$term))));
        }
        return $query;
    }

    private function dateRange($query, string $column): void
    {
        $query->where($column, '>=', $this->filters['from'].' 00:00:00')
            ->where($column, '<', Carbon::parse($this->filters['to'])->addDay()->startOfDay());
    }

    public function paymentsQuery()
    {
        // Payment dates are independent of invoice creation dates: an old debt
        // collected today belongs in today's cash movement.
        $ids = $this->query(false)->select('invoices.id');
        $query = DB::table('invoice_paid_details as p')->whereNull('p.deleted_at')
            ->whereIn('p.invoice_id_fk', $ids)->leftJoin('payment_methods as m','m.id','=','p.payment_method_id_fk')
            ->leftJoin('users as u','u.id','=','p.lab_id_fk')
            ->select('p.*','m.name as method_name','u.name as payment_account');
        $this->dateRange($query,'p.created_at');
        return $query;
    }

    public function options(): array
    {
        $rows = $this->query(false, false)->get()->map(fn($i)=>$i->only(['lab_id_fk','entry_actor_id','from_lab_id_fk','referral_id_fk','sample_collector_id_fk','contract_id_fk']));
        $rows = $rows->map(fn($i)=>(object)$i);
        $userIds = $rows->flatMap(fn($i)=>[$i->lab_id_fk,$i->entry_actor_id,$i->from_lab_id_fk,$i->referral_id_fk,$i->sample_collector_id_fk])->filter()->unique();
        $users = User::withTrashed()->whereIn('id',$userIds)->get()->keyBy('id');
        $pairs = fn($ids)=>collect($ids)->filter()->unique()->map(fn($id)=>['id'=>(int)$id,'name'=>$users->get($id)?->name ?? 'حساب محذوف'])->sortBy('name')->values();
        return [
            'owner_labs'=>$rows->pluck('lab_id_fk')->unique()->map(fn($id)=>$this->owner((int)$id))->filter()->unique('id')->map(fn($u)=>['id'=>$u->id,'name'=>$u->name])->values(),
            'branches'=>$pairs($rows->pluck('lab_id_fk')),
            'operators'=>$pairs($rows->pluck('entry_actor_id')),
            'labs'=>$pairs($rows->flatMap(fn($i)=>[$i->from_lab_id_fk,in_array((int)$users->get($i->referral_id_fk)?->role_id,[2,4])?$i->referral_id_fk:null])),
            'doctors'=>$pairs($rows->pluck('referral_id_fk')->filter(fn($id)=>(int)$users->get($id)?->role_id===5)),
            'collectors'=>$pairs($rows->pluck('sample_collector_id_fk')),
            'contracts'=>DB::table('contracts')->whereIn('id',$rows->pluck('contract_id_fk')->filter()->unique())->orderBy('name')->get(['id','name']),
        ];
    }

    private function owner(int $id): ?User
    {
        if (! array_key_exists($id,$this->owners)) {
            $user = User::withTrashed()->find($id);
            $this->owners[$id] = $user ? User::withTrashed()->find(app(InventoryService::class)->labId($user)) : null;
        }
        return $this->owners[$id];
    }

    public function rows(Collection $invoices): Collection
    {
        if ($invoices->isEmpty()) return collect();
        $invoices = new \Illuminate\Database\Eloquent\Collection($invoices->all());
        $invoices->load(['patient.user','lab','fromLab','referral','contract','sampleCollector',
            'paidDetails'=>fn($q)=>$q->whereNull('deleted_at'), 'paidDetails.paymentMethod',
            'invoiceTestRels.test','invoiceTestRels.culture','invoiceTestRels.toLab',
            'invoiceTestRels.testGroup.tests','invoiceTestRels.testGroup.culture',
            'invoiceTestRels.package.tests','invoiceTestRels.package.cultures',
            'invoiceTestRels.package.testGroups.tests','invoiceTestRels.package.testGroups.culture']);
        $ownerIds = $invoices->map(fn($i)=>$this->owner((int)$i->lab_id_fk)?->id)->filter()->unique();
        $rates = DB::table('rel_labs_referals')->whereNull('deleted_at')->whereIn('lab_id_fk',$ownerIds)
            ->whereIn('referral_id_fk',$invoices->pluck('referral_id_fk')->filter())->orderBy('id')->get()
            ->keyBy(fn($r)=>$r->lab_id_fk.':'.$r->referral_id_fk);
        return $invoices->map(function($invoice) use($rates) {
            $owner = $this->owner((int)$invoice->lab_id_fk);
            $doctor = (int)$invoice->referral?->role_id===5 ? $invoice->referral : null;
            $refLab = $invoice->fromLab ?? (in_array((int)$invoice->referral?->role_id,[2,4]) ? $invoice->referral : null);
            $rate = $invoice->referral_id_fk ? ($rates->get($owner?->id.':'.$invoice->referral_id_fk)?->commission) : 0;
            $rate = is_numeric($rate) && $rate >= 0 && $rate <= 100 ? (float)$rate : null;
            $net = (int)($invoice->total ?? 0); $paid = (int)$invoice->recorded_paid;
            $commission = $rate === null ? null : (int)round($net * $rate / 100);
            $items = $invoice->invoiceTestRels->sortBy('id')->map(fn($r)=>$this->item($r))->values()->all();
            $allocations = self::allocate($net, array_column($items,'price'));
            $commissions = self::allocate($commission ?? 0, array_column($items,'price'));
            foreach ($items as $index=>&$item) {
                $item['net_allocated'] = $allocations[$index] ?? 0;
                $item['commission_allocated'] = $commission === null ? null : ($commissions[$index] ?? 0);
                $item['profit_estimate'] = $item['cost_estimate'] !== null && $commission !== null
                    ? $item['net_allocated'] - $item['cost_estimate'] - $item['commission_allocated'] : null;
            }
            unset($item);
            $known = count($items)>0 && collect($items)->every(fn($item)=>$item['cost_estimate']!==null);
            $cost = $known ? array_sum(array_column($items,'cost_estimate')) : null;
            $discount = (int)$invoice->discount_type_id_fk === 2 ? (int)round((int)$invoice->sub_total * (int)$invoice->discount / 100) : (int)($invoice->discount ?? 0);
            $loyalty = (int)($invoice->loyalty_discount ?? 0);
            return [
                'id'=>$invoice->id,'barcode'=>$invoice->barcode,'date'=>$invoice->created_at?->format('Y-m-d H:i:s'),
                'registration_date'=>$invoice->registration_date?->format('Y-m-d'),
                'patient_id'=>$invoice->patient_id_fk,'patient_name'=>$invoice->patient?->user?->name ?? 'مريض محذوف','patient_code'=>$invoice->patient?->code,
                'lab_id'=>$owner?->id,'lab_name'=>$owner?->name ?? 'غير مسجل','branch_id'=>$invoice->lab_id_fk,'branch_name'=>$invoice->lab?->name,
                'created_by_id'=>$invoice->entry_actor_id,'created_by'=>$invoice->entry_actor_name ?? 'غير مسجل',
                'creator_source'=>$invoice->entry_audit_id ? 'audit' : ($invoice->referral_request_uuid ? 'portal_account' : 'invoice_account'),
                'referral_lab_id'=>$refLab?->id,'referral_lab'=>$refLab?->name,'doctor_id'=>$doctor?->id,'doctor'=>$doctor?->name,
                'contract_id'=>$invoice->contract_id_fk,'contract'=>$invoice->contract?->name,'collector'=>$invoice->sampleCollector?->name,
                'sub_total'=>(int)$invoice->sub_total,'discount_amount'=>$discount,'loyalty_discount'=>$loyalty,
                'adjustment'=>$net - ((int)$invoice->sub_total-$discount-$loyalty),
                'total'=>$net,'paid'=>$paid,'balance'=>max(0,$net-$paid),'credit'=>max(0,$paid-$net),
                'payment_status'=>$paid>$net?'credit':($paid>=$net?'paid':($paid>0?'partial':'unpaid')),
                'commission_rate'=>$rate,'commission_estimate'=>$commission,'cost_estimate'=>$cost,
                'known_cost'=>array_sum(array_column($items,'known_cost')),
                'profit_estimate'=>$cost!==null&&$commission!==null ? $net-$cost-$commission : null,
                'analysis_count'=>array_sum(array_column($items,'analysis_count')),'billed_items'=>count($items),
                'membership_estimated'=>collect($items)->contains('membership_estimated',true),
                'items'=>$items,'payments'=>$invoice->paidDetails->sortBy('created_at')->map(fn($p)=>[
                    'id'=>$p->id,'date'=>$p->created_at?->format('Y-m-d H:i:s'),'amount'=>(int)$p->amount,'method'=>$p->paymentMethod?->name ?? 'غير محدد',
                ])->values()->all(),
            ];
        });
    }

    /** Largest remainders keep item totals exactly equal to the invoice, even after discounts. */
    public static function allocate(int $amount, array $weights): array
    {
        if (! $weights) return [];
        $weights = array_map(fn($x)=>max(0,(int)$x),$weights);
        if (array_sum($weights)===0) $weights=array_fill(0,count($weights),1);
        $sum=array_sum($weights);$sign=$amount<0?-1:1;$amount=abs($amount);$result=[];$remainders=[];
        foreach($weights as $index=>$weight){$raw=$amount*$weight;$result[$index]=intdiv($raw,$sum);$remainders[$index]=$raw%$sum;}
        arsort($remainders,SORT_NUMERIC);$left=$amount-array_sum($result);
        foreach(array_keys($remainders) as $index){if($left--<=0)break;$result[$index]++;}
        ksort($result);return array_map(fn($x)=>$x*$sign,$result);
    }

    private function decoded(mixed $value): array
    {
        for($n=0;$n<2 && is_string($value);$n++)$value=json_decode($value,true);
        return is_array($value)?$value:[];
    }

    private function item(InvoiceTestRel $rel): array
    {
        $kind=$rel->test_id_fk?'test':($rel->culture_id_fk?'culture':($rel->test_group_id_fk?'group':'package'));
        $model=match($kind){'test'=>$rel->test,'culture'=>$rel->culture,'group'=>$rel->testGroup,default=>$rel->package};
        $leaves=[];$estimated=false;
        $catalog=['test'=>collect(), 'culture'=>collect()];
        if($kind==='group'){$catalog=['test'=>$model?->tests??collect(),'culture'=>$model?->culture??collect()];}
        if($kind==='package'){
            $catalog=['test'=>$model?->tests??collect(),'culture'=>$model?->cultures??collect()];
            foreach($model?->testGroups??[] as $group){$catalog['test']=$catalog['test']->concat($group->tests);$catalog['culture']=$catalog['culture']->concat($group->culture);}
        }
        $add=function($type,$test)use(&$leaves,$catalog){
            $a=is_array($test)?$test:$test->toArray();$id=$a['id']??$a[$type.'_id_fk']??null;
            $match=$id?$catalog[$type]->firstWhere('id',$id):null;
            if(!$id&&!empty($a['name'])){ $matches=$catalog[$type]->where('name',$a['name'])->unique('id');if($matches->count()===1){$match=$matches->first();$id=$match->id;} }
            if($match)$a=array_replace($match->toArray(),$a);
            $name=$a['name']??'فحص محذوف';$key=$type.':'.($id??$name);
            $value=$a['price']??null;$cost=is_numeric($value)&&$value>=0?(int)$value:null;
            $leaves[$key]=['key'=>$key,'id'=>$id,'kind'=>$type,'name'=>$name,'shortcut'=>$a['shortcut']??null,'cost'=>$cost];
        };
        if(in_array($kind,['test','culture'])){if($model)$add($kind,$model);else $add($kind,['id'=>$rel->{$kind.'_id_fk'}]);}
        else {
            $testKey=$kind==='package'?'package_tests':'test_group_tests';$cultureKey=$kind==='package'?'package_cultures':'test_group_cultures';
            $storedTests=$this->decoded($rel->$testKey);$storedCultures=$this->decoded($rel->$cultureKey);
            $tests=$storedTests?:($model?->tests??collect());$cultures=$storedCultures?:($kind==='package'?($model?->cultures??collect()):($model?->culture??collect()));
            $estimated=!$storedTests&&!$storedCultures;
            foreach($tests as $test)$add('test',$test);
            foreach($cultures as $culture)$add('culture',$culture);
            if($kind==='package')foreach($model?->testGroups??[] as $group){
                foreach($group->tests as $test){$key='test:'.$test->id;if(!isset($leaves[$key])){$add('test',$test);$estimated=true;}}
                foreach($group->culture as $culture){$key='culture:'.$culture->id;if(!isset($leaves[$key])){$add('culture',$culture);$estimated=true;}}
            }
        }
        $allKnown=count($leaves)>0&&collect($leaves)->every(fn($x)=>$x['cost']!==null);
        $known=array_sum(array_column($leaves,'cost'));
        $cost=$allKnown?$known:null;
        if($kind==='group'&&$model?->original_price!==null&&$model->original_price>=0)$cost=(int)$model->original_price;
        return ['id'=>$rel->id,'item_id'=>$model?->id ?? $rel->{$kind==='group'?'test_group_id_fk':$kind.'_id_fk'},'kind'=>$kind,
            'name'=>$model?->name??$model?->group_name??'عنصر محذوف','shortcut'=>$model?->shortcut,'price'=>(int)($rel->price??0),
            'to_lab_id'=>$rel->to_lab_id_fk,'to_lab'=>$rel->toLab?->name,'is_done'=>(bool)$rel->is_done,
            'cost_estimate'=>$cost,'known_cost'=>$cost??$known,'analysis_count'=>count($leaves),'analyses'=>array_values($leaves),'membership_estimated'=>$estimated];
    }

    public const BASIS_NOTE = 'الفواتير غير المحذوفة حسب تاريخ الإنشاء. المدفوع والمتبقي رصيد حالي من سجل الدفعات، والتحصيل حسب تاريخ الدفعة وقد يخص فاتورة أقدم. الربح تقديري = صافي الفاتورة − تكلفة الفحوص المسجلة/الحالية − عمولة الإحالة الحالية؛ لا يشمل المصروفات التشغيلية ولا يمثل صافي الربح المحاسبي. غير مكتمل يعني وجود تكلفة أو عمولة غير مسجلة. أسماء المدخل تعتمد سجل الإنشاء عند توفره، وإلا حساب الفاتورة. الإحالات تعرض رصيد الفواتير وليست إثبات تسوية عمولات أو مستحقات مختبر خارجي.';
    public const TITLES = ['overview'=>'ملخص حركة المختبر','invoices'=>'كشف الفواتير','payments'=>'حركة التحصيل','lab_referrals'=>'إحالات المختبرات','doctor_referrals'=>'إحالات الأطباء','items'=>'إيراد وربح البنود','analyses'=>'عدد الفحوصات','operators'=>'حركة الموظفين','contracts'=>'العقود','patients'=>'حسابات المرضى','outbound'=>'الفحوص المحولة للخارج'];
    private const ACCOUNT_COLUMNS = ['name'=>'الاسم','lab_name'=>'المختبر','invoice_count'=>'عدد الفواتير','total'=>'صافي الفواتير','paid'=>'المدفوع','balance'=>'المتبقي','credit'=>'الرصيد الزائد','commission_estimate'=>'عمولة تقديرية','profit_estimate'=>'ربح تقديري'];
    public const COLUMNS = [
        'overview'=>['metric'=>'المؤشر','value'=>'القيمة'],
        'invoices'=>['id'=>'الفاتورة','date'=>'تاريخ الإنشاء','registration_date'=>'تاريخ التسجيل','patient_name'=>'المريض','patient_code'=>'كود المريض','lab_name'=>'المختبر','referral_lab'=>'المختبر المحيل','doctor'=>'الطبيب المحيل','created_by'=>'مدخل العملية','creator_source'=>'مصدر المدخل','branch_name'=>'حساب الفاتورة','contract'=>'العقد','collector'=>'جامع العينة','sub_total'=>'قبل الخصم','discount_amount'=>'الخصم','loyalty_discount'=>'خصم الولاء','adjustment'=>'فرق التسوية','total'=>'صافي الفاتورة','paid'=>'المدفوع','balance'=>'المتبقي','credit'=>'الرصيد الزائد','payment_status'=>'الحالة','analysis_count'=>'عدد الفحوص','cost_estimate'=>'تكلفة تقديرية','commission_estimate'=>'عمولة تقديرية','profit_estimate'=>'ربح تقديري'],
        'payments'=>['id'=>'رقم الدفعة','date'=>'تاريخ الدفعة','invoice_id'=>'رقم الفاتورة','invoice_date'=>'تاريخ الفاتورة','patient_name'=>'المريض','referral_lab'=>'المختبر المحيل','doctor'=>'الطبيب','created_by'=>'مدخل الفاتورة','payment_account'=>'حساب التسديد','amount'=>'المبلغ','method'=>'طريقة الدفع'],
        'lab_referrals'=>self::ACCOUNT_COLUMNS,'doctor_referrals'=>self::ACCOUNT_COLUMNS,'operators'=>self::ACCOUNT_COLUMNS,'contracts'=>self::ACCOUNT_COLUMNS,'patients'=>self::ACCOUNT_COLUMNS,
        'items'=>['name'=>'البند','shortcut'=>'المختصر','kind'=>'النوع','count'=>'عدد الطلبات','price'=>'قبل خصم الفاتورة','net_allocated'=>'صافي الإيراد الموزع','cost_estimate'=>'التكلفة التقديرية','commission_allocated'=>'عمولة موزعة تقديرية','profit_estimate'=>'الربح التقديري'],
        'analyses'=>['name'=>'الفحص','shortcut'=>'المختصر','kind'=>'النوع','count'=>'العدد الكلي','direct'=>'مباشر','in_groups'=>'داخل الكروبات','in_packages'=>'داخل الباقات'],
        'outbound'=>['name'=>'المختبر المستلم','lab_name'=>'مختبر الفاتورة','count'=>'عدد البنود','net_allocated'=>'الإيراد الموزع','cost_estimate'=>'التكلفة التقديرية'],
    ];

    public function reportLabel(): string
    {
        return (int)$this->actor->role_id===1 ? 'جميع المختبرات' : ($this->owner($this->actor->id)?->name ?? 'المختبر');
    }

    public function filterLabel(): string
    {
        $labels=['owner_id'=>'المختبر','search'=>'البحث','status'=>'حالة التسديد','referral_type'=>'نوع الإحالة','branch_id'=>'حساب الفاتورة','created_by'=>'مدخل العملية','lab_referral_id'=>'المختبر المحيل','doctor_id'=>'الطبيب','contract_id'=>'العقد','patient_id'=>'المريض','sample_collector_id'=>'جامع العينة'];
        $options=$this->options();$lists=['owner_id'=>'owner_labs','branch_id'=>'branches','created_by'=>'operators','lab_referral_id'=>'labs','doctor_id'=>'doctors','contract_id'=>'contracts','sample_collector_id'=>'collectors'];$parts=[];
        foreach($labels as $key=>$label)if(!empty($this->filters[$key])){
            $value=$this->filters[$key];
            if(isset($lists[$key]))$value=collect($options[$lists[$key]])->firstWhere('id',$value)['name']??$value;
            if($key==='status')$value=self::displayValue('payment_status',$value);
            if($key==='referral_type')$value=$value==='lab'?'مختبر':'طبيب';
            $parts[]=$label.': '.$value;
        }
        return $parts ? implode(' | ',$parts) : 'جميع السجلات ضمن الفترة';
    }

    public static function displayValue(string $key, mixed $value): mixed
    {
        if($value===null)return in_array($key,['cost_estimate','commission_estimate','commission_allocated','profit_estimate'])?'غير مكتمل':'—';
        return match($key){
            'payment_status'=>['paid'=>'مسدد','partial'=>'مسدد جزئياً','unpaid'=>'غير مسدد','credit'=>'رصيد زائد'][$value]??$value,
            'creator_source'=>['audit'=>'سجل الإنشاء','portal_account'=>'حساب بوابة الإحالة','invoice_account'=>'حساب الفاتورة (بديل تاريخي)'][$value]??$value,
            'kind'=>['test'=>'تحليل','culture'=>'زرع','group'=>'كروب','package'=>'باقة'][$value]??$value,
            default=>$value,
        };
    }

    public function paymentRows(Collection $payments): Collection
    {
        $invoices=$this->query(false,false)->whereIn('invoices.id',$payments->pluck('invoice_id_fk'))->with(['patient.user','fromLab','referral'])->get()->keyBy('id');
        return $payments->map(function($p)use($invoices){
            $i=$invoices->get($p->invoice_id_fk);$lab=$i?->fromLab??(in_array((int)$i?->referral?->role_id,[2,4])?$i->referral:null);
            return ['id'=>$p->id,'date'=>$p->created_at,'invoice_id'=>$p->invoice_id_fk,'invoice_date'=>$i?->created_at?->format('Y-m-d H:i:s'),
                'patient_name'=>$i?->patient?->user?->name??'مريض محذوف','referral_lab'=>$lab?->name,'doctor'=>(int)$i?->referral?->role_id===5?$i->referral?->name:null,
                'created_by'=>$i?->entry_actor_name??'غير مسجل','payment_account'=>$p->payment_account??'غير مسجل','amount'=>(int)$p->amount,'method'=>$p->method_name??'غير محدد'];
        });
    }

    public function eachExportRow(string $section, callable $write): void
    {
        if($section==='overview'){
            $labels=['invoice_count'=>'عدد الفواتير','patient_count'=>'عدد المرضى','analysis_count'=>'عدد الفحوص','sub_total'=>'قبل الخصم (د.ع)','discount_amount'=>'الخصومات (د.ع)','loyalty_discount'=>'خصومات الولاء (د.ع)','adjustment'=>'فرق التسوية (د.ع)','total'=>'صافي الفواتير (د.ع)','paid'=>'المدفوع على فواتير الفترة (د.ع)','balance'=>'المتبقي الحالي (د.ع)','credit'=>'الرصيد الزائد (د.ع)','collections_in_period'=>'التحصيل خلال الفترة (د.ع)','cost_estimate'=>'التكلفة التقديرية (د.ع)','commission_estimate'=>'العمولات التقديرية (د.ع)','profit_estimate'=>'الربح التقديري قبل المصروفات (د.ع)','incomplete_profit_invoices'=>'فواتير ببيانات ربح غير مكتملة','estimated_membership_invoices'=>'فواتير بمكونات مستكملة من التعريف الحالي'];
            $overview=$this->overview();foreach($labels as $key=>$label)$write(['metric'=>$label,'value'=>$overview['summary'][$key]??'غير مكتمل']);
            foreach($overview['trend'] as $period){$write(['metric'=>$period['period'].' / صافي الفواتير (د.ع)','value'=>$period['total']]);$write(['metric'=>$period['period'].' / التحصيل (د.ع)','value'=>$period['collections']]);}
        }elseif($section==='invoices'){
            $this->query()->chunkById(200,function($batch)use($write){foreach($this->rows($batch) as $row)$write($row);},'invoices.id','id');
        }elseif($section==='payments'){
            $this->paymentsQuery()->orderBy('p.id')->chunk(500,function($batch)use($write){foreach($this->paymentRows($batch)as$row)$write($row);});
        }else foreach($this->overview()[$section] as $row)$write($row);
    }

    public function overview(): array
    {
        $summary=['invoice_count'=>0,'patient_count'=>0,'sub_total'=>0,'discount_amount'=>0,'loyalty_discount'=>0,'adjustment'=>0,'total'=>0,'paid'=>0,'balance'=>0,'credit'=>0,'analysis_count'=>0,'billed_items'=>0,'known_cost'=>0,'commission_estimate'=>0,'profit_estimate'=>0,'incomplete_profit_invoices'=>0,'estimated_membership_invoices'=>0,'incomplete_cost_invoices'=>0,'incomplete_commission_invoices'=>0,'unallocated_revenue'=>0];
        $patients=[];$labs=[];$doctors=[];$operators=[];$contracts=[];$patientAccounts=[];$items=[];$analyses=[];$outbound=[];$trend=[];$aging=[0,0,0,0];$status=['paid'=>0,'partial'=>0,'unpaid'=>0,'credit'=>0];
        $monthly=Carbon::parse($this->filters['from'])->diffInDays(Carbon::parse($this->filters['to']))>90;
        $period=fn($date)=>substr($date,0,$monthly?7:10);
        $this->query()->chunkById(200,function($batch)use(&$summary,&$patients,&$labs,&$doctors,&$operators,&$contracts,&$patientAccounts,&$items,&$analyses,&$outbound,&$trend,&$aging,&$status,$period){
            foreach($this->rows($batch) as $row){
                $summary['invoice_count']++;if($row['patient_id'])$patients[$row['patient_id']]=true;
                foreach(['sub_total','discount_amount','loyalty_discount','adjustment','total','paid','balance','credit','analysis_count','billed_items','known_cost','commission_estimate','profit_estimate'] as $key)$summary[$key]+=$row[$key]??0;
                if($row['profit_estimate']===null)$summary['incomplete_profit_invoices']++;
                if($row['cost_estimate']===null)$summary['incomplete_cost_invoices']++;
                if($row['commission_estimate']===null)$summary['incomplete_commission_invoices']++;
                if(!$row['items'])$summary['unallocated_revenue']+=$row['total'];
                if($row['membership_estimated'])$summary['estimated_membership_invoices']++;
                $status[$row['payment_status']]++;
                $days=max(0,Carbon::parse($row['date'])->diffInDays(now(),false));$aging[$days<=30?0:($days<=60?1:($days<=90?2:3))]+=$row['balance'];
                $day=$period($row['date']);$trend[$day]??=['period'=>$day,'total'=>0,'collections'=>0,'count'=>0];$trend[$day]['total']+=$row['total'];$trend[$day]['count']++;
                foreach(['labs'=>['referral_lab_id','referral_lab'],'doctors'=>['doctor_id','doctor'],'operators'=>['created_by_id','created_by'],'contracts'=>['contract_id','contract'],'patientAccounts'=>['patient_id','patient_name']] as $target=>[$id,$name]){
                    if(!$row[$id])continue;$key=$row['lab_id'].':'.$row[$id];$map=&$$target;
                    $map[$key]??=['id'=>(int)$row[$id],'name'=>$row[$name],'lab_id'=>$row['lab_id'],'lab_name'=>$row['lab_name'],'invoice_count'=>0,'total'=>0,'paid'=>0,'balance'=>0,'credit'=>0,'commission_estimate'=>0,'profit_estimate'=>0,'missing_profit'=>0,'missing_commission'=>0];
                    $map[$key]['invoice_count']++;foreach(['total','paid','balance','credit','commission_estimate','profit_estimate']as$k)$map[$key][$k]+=$row[$k]??0;
                    if($row['profit_estimate']===null)$map[$key]['missing_profit']++;
                    if($row['commission_estimate']===null)$map[$key]['missing_commission']++;
                    unset($map);
                }
                foreach($row['items'] as $item){
                    $key=$item['kind'].':'.$item['item_id'];$items[$key]??=['kind'=>$item['kind'],'id'=>$item['item_id'],'name'=>$item['name'],'shortcut'=>$item['shortcut'],'count'=>0,'price'=>0,'net_allocated'=>0,'cost_estimate'=>0,'commission_allocated'=>0,'profit_estimate'=>0,'missing_cost'=>0,'missing_commission'=>0,'missing_profit'=>0];
                    $items[$key]['count']++;foreach(['price','net_allocated','cost_estimate','commission_allocated','profit_estimate']as$k)$items[$key][$k]+=$item[$k]??0;
                    if($item['cost_estimate']===null)$items[$key]['missing_cost']++;
                    if($item['commission_allocated']===null)$items[$key]['missing_commission']++;
                    if($item['profit_estimate']===null)$items[$key]['missing_profit']++;
                    foreach($item['analyses']as$a){$analyses[$a['key']]??=['id'=>$a['id'],'kind'=>$a['kind'],'name'=>$a['name'],'shortcut'=>$a['shortcut'],'count'=>0,'direct'=>0,'in_groups'=>0,'in_packages'=>0];$analyses[$a['key']]['count']++;$analyses[$a['key']][$item['kind']==='package'?'in_packages':($item['kind']==='group'?'in_groups':'direct')]++;}
                    if($item['to_lab_id']){$k=$row['lab_id'].':'.$item['to_lab_id'];$outbound[$k]??=['id'=>$item['to_lab_id'],'name'=>$item['to_lab'],'lab_name'=>$row['lab_name'],'count'=>0,'net_allocated'=>0,'cost_estimate'=>0,'missing_cost'=>0];$outbound[$k]['count']++;$outbound[$k]['net_allocated']+=$item['net_allocated'];$outbound[$k]['cost_estimate']+=$item['cost_estimate']??0;if($item['cost_estimate']===null)$outbound[$k]['missing_cost']++;}
                }
            }
        },'invoices.id','id');
        $summary['patient_count']=count($patients);if($summary['incomplete_profit_invoices'])$summary['profit_estimate']=null;
        foreach(['labs','doctors','operators','contracts','patientAccounts'] as $target) {
            $map=&$$target;
            foreach($map as &$row) {
                if($row['missing_profit'])$row['profit_estimate']=null;
                if($row['missing_commission'])$row['commission_estimate']=null;
            }
            unset($row, $map);
        }
        unset($map,$row);
        foreach($items as &$item){if($item['missing_cost'])$item['cost_estimate']=null;if($item['missing_commission'])$item['commission_allocated']=null;if($item['missing_profit'])$item['profit_estimate']=null;}unset($item);
        $methods=[];$collections=0;
        $this->paymentsQuery()->orderBy('p.id')->chunk(500,function($rows)use(&$methods,&$collections,&$trend,$period){foreach($rows as$p){$amount=(int)$p->amount;$collections+=$amount;$key=$p->payment_method_id_fk??0;$methods[$key]??=['id'=>$key,'name'=>$p->method_name??'غير محدد','count'=>0,'amount'=>0];$methods[$key]['count']++;$methods[$key]['amount']+=$amount;$day=$period($p->created_at);$trend[$day]??=['period'=>$day,'total'=>0,'collections'=>0,'count'=>0];$trend[$day]['collections']+=$amount;}});
        $summary['collections_in_period']=$collections;
        $summary['cost_estimate']=$summary['incomplete_cost_invoices']?null:$summary['known_cost'];
        if($summary['incomplete_commission_invoices'])$summary['commission_estimate']=null;
        foreach($outbound as &$row)if($row['missing_cost'])$row['cost_estimate']=null;unset($row);
        $cursor=Carbon::parse($this->filters['from']);$end=Carbon::parse($this->filters['to']);if($monthly)$cursor->startOfMonth();
        while($cursor<=$end){$key=$period($cursor->toDateString());$trend[$key]??=['period'=>$key,'total'=>0,'collections'=>0,'count'=>0];$monthly?$cursor->addMonth():$cursor->addDay();}
        ksort($trend);
        $sort=fn($rows,$key)=>collect($rows)->sortByDesc($key)->values()->all();
        return ['meta'=>['from'=>$this->filters['from'],'to'=>$this->filters['to'],'generated_at'=>now()->toIso8601String(),'currency'=>'IQD','timezone'=>config('app.timezone'),'trend_interval'=>$monthly?'month':'day','profit_basis'=>'estimated_catalog_cost','lab_name'=>(int)$this->actor->role_id===1?'جميع المختبرات':$this->owner($this->actor->id)?->name],
            'summary'=>$summary,'trend'=>array_values($trend),'payment_status'=>$status,'aging'=>$aging,
            'lab_referrals'=>$sort($labs,'total'),'doctor_referrals'=>$sort($doctors,'total'),'operators'=>$sort($operators,'total'),'contracts'=>$sort($contracts,'total'),'patients'=>$sort($patientAccounts,'total'),
            'items'=>$sort($items,'net_allocated'),'analyses'=>$sort($analyses,'count'),'outbound'=>$sort($outbound,'count'),'payment_methods'=>$sort($methods,'amount')];
    }
}
