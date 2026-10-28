<?php
namespace App\Services;

use App\Models\{User, Invoice, CollectionTask};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CollectorTasks
{
    public function event(int $lab, string $action, array $data=[], ?int $task=null, ?int $submission=null): void
    {
        DB::table('collection_events')->insert(['lab_id'=>$lab,'task_id'=>$task,'submission_id'=>$submission,'actor_id'=>auth()->id(),'actor_name'=>auth()->user()?->name,'action'=>$action,'data'=>json_encode($data,JSON_UNESCAPED_UNICODE),'created_at'=>now()]);
    }
    public function sourceInfo(?int $id): ?array
    {
        $u=$id?User::withTrashed()->find($id):null; if(!$u)return null;
        $p=DB::table('referral_lab_profiles')->where('user_id_fk',$id)->first();
        return ['id'=>$u->id,'name'=>$p?->display_name?:$u->name,'phone'=>$p?->phone?:$u->phone_number,'address'=>$p?->address?:$u->address];
    }
    public function projection(Invoice $invoice,?int $source=null): array
    {
        $invoice->loadMissing(['patient.user','patient.ageUnit','patient.gender','invoiceTestRels']);
        $p=$invoice->patient; $items=[];
        // Snapshot only names and sample types; never serialize results or financial records.
        foreach($invoice->invoiceTestRels as $rel) foreach(CollectorCatalog::KINDS as $kind=>[$class,$field]) if($rel->$field) {
            $m=$class::withTrashed()->find($rel->$field); if(!$m)continue;
            $brief=fn($t)=>['id'=>$t->id,'name'=>$t->name,'sample'=>$t->sample?->sample_name];
            $children=[];
            if($kind==='packages'||$kind==='test_groups') {
                $tests=$m->tests; $cultures=$kind==='packages'?$m->cultures:$m->culture;
                if($kind==='packages'){ $tests=$tests->merge($m->testGroups->flatMap(fn($g)=>$g->tests))->unique('id'); $cultures=$cultures->merge($m->testGroups->flatMap(fn($g)=>$g->culture))->unique('id'); }
                foreach($tests->concat($cultures) as $t){ $t->loadMissing('sample'); $children[]=$brief($t); }
            } else $m->loadMissing('sample');
            if(in_array($kind,['packages','test_groups'])) {
                $a=$kind==='packages'?'package_tests':'test_group_tests';$b=$kind==='packages'?'package_cultures':'test_group_cultures';
                $saved=[];$hasSnapshot=false;
                foreach([$a,$b] as $key) {
                    $list=$rel->$key;for($n=0;is_string($list)&&$n<2;$n++)$list=json_decode($list,true);
                    if(is_array($list)&&count($list)) { $hasSnapshot=true;foreach($list as $child)$saved[]=['id'=>$child['id']??null,'name'=>$child['name']??'تحليل','sample'=>$child['sample_name']??null]; }
                }
                if($hasSnapshot)$children=$saved;
            }
            $items[]=['kind'=>$kind,'id'=>$m->id,'name'=>$kind==='test_groups'?$m->group_name:$m->name,'sample'=>in_array($kind,['tests','cultures'])?$m->sample?->sample_name:null,'children'=>$children];
        }
        return ['patient'=>['name'=>$p?->user?->name,'age'=>$p?->age,'age_unit'=>$p?->ageUnit?->unit_name,'gender'=>$p?->gender?->gender_type,'phone'=>$p?->user?->phone_number,'address'=>$p?->address], 'items'=>$items,'source'=>$this->sourceInfo($source)];
    }
    public function capture(int $id): ?CollectionTask
    {
        return DB::transaction(function()use($id){
            $invoice=Invoice::withTrashed()->lockForUpdate()->find($id); if(!$invoice)return null;
            $existing=CollectionTask::where('invoice_id',$id)->lockForUpdate()->first();
            if($invoice->trashed()) {
                if($existing && !$existing->cancelled_at) { $existing->update(['cancelled_at'=>now(),'status'=>'cancelled','version'=>$existing->version+1]); $this->event($existing->lab_id,'invoice_cancelled',[], $existing->id); $this->notify($existing,'cancelled','تم إلغاء طلب سحب العينة'); }
                return $existing;
            }
            $owner=User::find($invoice->lab_id_fk); if(!$owner)return null;
            $lab=User::find(app(AccountAccess::class)->tenantId($owner)); if(!$lab || (int)$lab->role_id!==2)return null;
            $submission=\App\Models\CollectionSubmission::where('invoice_id',$id)->first();
            $source=$invoice->from_lab_id_fk?:$invoice->referral_id_fk;
            $access=app(CollectorAccess::class);
            if($source && !$access->sources($lab)->whereKey($source)->exists()) $source=null;
            if(!$source && !$submission) {
                if($existing && $existing->status==='requested') { $existing->update(['status'=>'cancelled','cancelled_at'=>now(),'version'=>$existing->version+1]); $this->notify($existing,'source_removed','تم إلغاء جهة استلام الطلب'); }
                return $existing;
            }
            if($existing) {
                // Requested tasks follow invoice edits; collected tasks retain their pickup snapshot.
                if($existing->status==='requested' && (int)$existing->source_lab_id!==(int)$source) {
                    $old=$existing->collector_id; $existing->update(['source_lab_id'=>$source,'collector_id'=>$source?$this->assigned($lab,$source):$submission?->collector_id,'version'=>$existing->version+1]);
                    $this->event($lab->id,'source_changed',['previous_collector'=>$old],$existing->id); $this->notify($existing,'source:'.$existing->version,'تم تحديث جهة استلام العينة');
                }
                return $existing;
            }
            $collector=$source?$this->assigned($lab,$source):null;
            if($submission && $access->collectors($lab)->where('status',1)->whereKey($submission->collector_id)->exists())$collector=$submission->collector_id;
            $task=CollectionTask::create(['lab_id'=>$lab->id,'source_lab_id'=>$source,'invoice_id'=>$id,'collector_id'=>$collector,'snapshot'=>$this->projection($invoice,$source)]);
            $this->event($lab->id,'requested',[], $task->id); $this->notify($task,'requested','طلب جديد لسحب عينة'); return $task;
        });
    }
    /** Recover secondary captures after transient failure without replaying old invoices. */
    public function reconcile(): int
    {
        $count=0;
        foreach(DB::table('collector_accounts')->select('lab_id')->selectRaw('MIN(created_at) AS since')->groupBy('lab_id')->get() as $binding) {
            $lab=User::find($binding->lab_id);if(!$lab||!$binding->since)continue;
            $access=app(CollectorAccess::class);$sources=$access->sources($lab)->pluck('id');
            $ids=Invoice::whereIn('lab_id_fk',$access->accounts($lab))->where('created_at','>=',$binding->since)
                ->whereNotIn('id',CollectionTask::select('invoice_id'))
                ->where(fn($q)=>$q->whereIn('from_lab_id_fk',$sources)->orWhereIn('referral_id_fk',$sources)->orWhereIn('id',\App\Models\CollectionSubmission::where('lab_id',$lab->id)->whereNotNull('invoice_id')->select('invoice_id')))
                ->orderBy('id')->limit(200)->pluck('id');
            foreach($ids as $id) { try { if($this->capture($id))$count++; } catch(\Throwable $e) { \Illuminate\Support\Facades\Log::warning('Collection recovery deferred',['invoice_id'=>$id,'exception'=>get_class($e)]); } }
        }
        return $count;
    }
    public function assigned(User $lab,int $source): ?int
    {
        $id=DB::table('collector_lab_assignments')->where('lab_id',$lab->id)->where('source_lab_id',$source)->value('collector_id');
        return $id && app(CollectorAccess::class)->collectors($lab)->whereKey($id)->where('status',1)->exists()?(int)$id:null;
    }
    public function notify(CollectionTask $task,string $event,string $title): void
    {
        if($task->collector_id)app(CollectorNotifications::class)->create($task->collector_id,$task->lab_id,'task:'.$task->id.':'.$task->collector_id.':'.$event,$title,'افتح التطبيق لمراجعة تفاصيل الطلب.',$task->id);
    }
    public function document(CollectionTask $task): array
    {
        $invoice=Invoice::find($task->invoice_id);
        $details=$task->status==='requested' && $invoice ? $this->projection($invoice,$task->source_lab_id) : ($task->snapshot?:($invoice?$this->projection($invoice,$task->source_lab_id):['patient'=>null,'items'=>[],'source'=>$this->sourceInfo($task->source_lab_id)]));
        return $task->only(['id','invoice_id','collector_id','source_lab_id','status','version','sample_count','collected_at','delivered_at','cancelled_at','note','created_at'])+['details'=>$details,'collector_name'=>User::withTrashed()->find($task->collector_id)?->name];
    }
    public function transition(User $collector,int $id,array $v): CollectionTask
    {
        $lab=app(CollectorAccess::class)->lab($collector);
        return DB::transaction(function()use($collector,$id,$v,$lab){
            $invoiceId=CollectionTask::where('lab_id',$lab->id)->where('collector_id',$collector->id)->whereKey($id)->value('invoice_id');
            abort_unless($invoiceId,404);
            Invoice::whereKey($invoiceId)->lockForUpdate()->firstOrFail();
            $task=CollectionTask::where('lab_id',$lab->id)->where('collector_id',$collector->id)->lockForUpdate()->findOrFail($id);
            abort_if($task->cancelled_at,409,'تم إلغاء هذا الطلب.');
            if($task->status===$v['status'] || ($task->status==='delivered' && $v['status']==='collected'))return $task;
            abort_unless($task->version===$v['version'],409,'تغير الطلب؛ حدّث الصفحة قبل المتابعة.');
            abort_unless(($task->status==='requested'&&$v['status']==='collected')||($task->status==='collected'&&$v['status']==='delivered'),422,'يجب استلام العينة قبل توصيلها.');
            $data=['status'=>$v['status'],'version'=>$task->version+1,'note'=>$v['note']??$task->note];
            if($v['status']==='collected') {
                abort_unless(!empty($v['sample_count']),422,'أدخل عدد العينات المستلمة فعلياً.');
                $invoice=Invoice::findOrFail($task->invoice_id); $data+=['sample_count'=>$v['sample_count'],'collected_at'=>now(),'collected_by'=>$collector->id,'snapshot'=>$this->projection($invoice,$task->source_lab_id)];
            } else $data+=['delivered_at'=>now(),'delivered_by'=>$collector->id];
            $task->update($data); $this->event($lab->id,$v['status'],['sample_count'=>$task->sample_count],$task->id); return $task;
        });
    }
    public function statistics(User $lab,string $from,string $to,?int $collector=null): array
    {
        $start=Carbon::parse($from,'Asia/Baghdad')->startOfDay()->setTimezone(config('app.timezone'));
        $end=Carbon::parse($to,'Asia/Baghdad')->addDay()->startOfDay()->setTimezone(config('app.timezone'));
        $q=CollectionTask::where('lab_id',$lab->id);
        $ids=DB::table('collector_accounts')->where('lab_id',$lab->id)->pluck('user_id');
        $users=User::withTrashed()->whereIn('id',$ids)->when($collector,fn($q)=>$q->whereKey($collector))->get();
        return $users->map(function($u)use($q,$start,$end){
            $collected=(clone $q)->where('collected_by',$u->id)->where('collected_at','>=',$start)->where('collected_at','<',$end);
            $delivered=(clone $q)->where('delivered_by',$u->id)->where('delivered_at','>=',$start)->where('delivered_at','<',$end);
            return ['id'=>$u->id,'name'=>$u->name,'collected_requests'=>(clone $collected)->count(),'collected_samples'=>(int)(clone $collected)->sum('sample_count'),'delivered_requests'=>(clone $delivered)->count(),'delivered_samples'=>(int)(clone $delivered)->sum('sample_count'),'pending'=>(clone $q)->where('collector_id',$u->id)->whereIn('status',['requested','collected'])->count()];
        })->all();
    }
}
