<?php
namespace App\Http\Controllers;

use App\Models\{CollectionTask, CollectionSubmission, User};
use App\Services\{CollectorAccess, CollectorCatalog, CollectorTasks, CollectorSubmissions};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Validation\Rule;

class CollectorPortalController extends Controller
{
    private function lab(Request $r,bool $management=false,string $permission='sample logistics view'): User
    { return $management?app(CollectorAccess::class)->manager($r,$permission):app(CollectorAccess::class)->lab($r->user()); }
    private function json($data,int $code=200){return response()->json($data,$code)->header('Cache-Control','private, no-store');}
    public function context(Request $r) {
        $lab=$this->lab($r);return $this->json(['lab'=>['id'=>$lab->id,'name'=>$lab->name],'collector'=>$r->user()->only(['id','name']), 'sources'=>app(CollectorAccess::class)->assignedSources($r->user())->get()->map(fn($u)=>app(CollectorTasks::class)->sourceInfo($u->id))]);
    }
    public function tasks(Request $r,bool $management=false) {
        $lab=$this->lab($r,$management); $r->validate(['status'=>'nullable|in:requested,collected,delivered,cancelled','collector_id'=>'nullable|integer','page'=>'nullable|integer|min:1','search'=>'nullable|string|max:100']);
        $q=CollectionTask::where('lab_id',$lab->id);
        if(!$management)$q->where('collector_id',$r->user()->id);elseif($r->filled('collector_id'))$q->where('collector_id',$r->integer('collector_id'));
        if($r->filled('status'))$q->where('status',$r->status);
        if($r->filled('search'))$q->where(fn($q)=>$q->where('invoice_id',$r->search)->orWhereHas('invoice.patient.user',fn($q)=>$q->where('name','like','%'.$r->search.'%')));
        $page=$q->latest('id')->paginate(20);
        return $this->json(['data'=>collect($page->items())->map(fn($t)=>app(CollectorTasks::class)->document($t)),'page'=>$page->currentPage(),'last_page'=>$page->lastPage(),'total'=>$page->total()]);
    }
    public function managementTasks(Request $r){return $this->tasks($r,true);}
    public function transition(Request $r,int $id) {
        $v=$r->validate(['status'=>'required|in:collected,delivered','version'=>'required|integer|min:1','sample_count'=>'required_if:status,collected|nullable|integer|min:1|max:10000','note'=>'nullable|string|max:2000']);
        return $this->json(app(CollectorTasks::class)->document(app(CollectorTasks::class)->transition($r->user(),$id,$v)));
    }
    public function statistics(Request $r,bool $management=false) {
        $lab=$this->lab($r,$management);$v=$r->validate(['from'=>'required|date_format:Y-m-d','to'=>'required|date_format:Y-m-d|after_or_equal:from']);
        abort_if(\Carbon\Carbon::parse($v['from'])->diffInDays(\Carbon\Carbon::parse($v['to']))>366,422,'اختر فترة لا تتجاوز سنة.');
        return $this->json(['timezone'=>'Asia/Baghdad','data'=>app(CollectorTasks::class)->statistics($lab,$v['from'],$v['to'],$management?null:$r->user()->id)]);
    }
    public function managementStatistics(Request $r){return $this->statistics($r,true);}
    public function accounts(Request $r) {
        $lab=$this->lab($r,true);$access=app(CollectorAccess::class);
        return $this->json(['data'=>$access->collectors($lab)->orderBy('name')->get()->map(fn($u)=>$u->only(['id','name','email','phone_number','status'])),'sources'=>$access->sources($lab)->get()->map(fn($u)=>app(CollectorTasks::class)->sourceInfo($u->id)), 'assignments'=>DB::table('collector_lab_assignments')->where('lab_id',$lab->id)->get(['source_lab_id','collector_id'])]);
    }
    public function saveAccount(Request $r,?int $id=null) {
        $lab=$this->lab($r,true,'sample logistics manage');$access=app(CollectorAccess::class);
        $account=$id?$access->collectors($lab)->findOrFail($id):null;
        $v=$r->validate(['name'=>'required|string|max:255','email'=>['required','email','max:255',Rule::unique('users')->ignore($account?->id)],'phone_number'=>'nullable|string|max:50','password'=>($id?'nullable':'required').'|string|min:10|max:128','status'=>'required|boolean']);
        return DB::transaction(function()use($r,$lab,$account,$v){
            $u=$account??new User; $u->fill(\Illuminate\Support\Arr::only($v,['name','email','phone_number','status']));
            if(!empty($v['password']))$u->password=Hash::make($v['password']);
            $u->forceFill(['creator_id'=>$lab->id,'collector_portal_only'=>true,'owner_portal_only'=>false,'referral_portal_only'=>false,'role_id'=>null,'email_verified_at'=>now()])->save();
            DB::table('collector_accounts')->updateOrInsert(['user_id'=>$u->id],['lab_id'=>$lab->id,'updated_at'=>now()]);
            DB::table('collector_accounts')->where('user_id',$u->id)->whereNull('created_at')->update(['created_at'=>now()]);
            $u->syncRoles([]);$u->syncPermissions([]);
            if(!$u->status) {
                foreach(CollectionTask::where('lab_id',$lab->id)->where('collector_id',$u->id)->where('status','requested')->lockForUpdate()->get() as $task) { $task->update(['collector_id'=>null,'version'=>$task->version+1]); app(CollectorTasks::class)->event($lab->id,'unassigned',['collector_id'=>$u->id],$task->id); }
            }
            app(CollectorTasks::class)->event($lab->id,$account?'account_updated':'account_created',['collector_id'=>$u->id]);
            return $this->json($u->only(['id','name','email','phone_number','status']),$account?200:201);
        });
    }
    public function assignSource(Request $r) {
        $lab=$this->lab($r,true,'sample logistics manage');$v=$r->validate(['source_lab_id'=>'required|integer','collector_id'=>'nullable|integer']);$access=app(CollectorAccess::class);
        $access->source($lab,$v['source_lab_id']); if(!empty($v['collector_id']))$access->collectors($lab)->where('status',1)->findOrFail($v['collector_id']);
        DB::transaction(function()use($lab,$v){
            User::whereKey($lab->id)->lockForUpdate()->firstOrFail();
            $q=DB::table('collector_lab_assignments')->where('lab_id',$lab->id)->where('source_lab_id',$v['source_lab_id']);
            if(empty($v['collector_id']))$q->delete();else {
                DB::table('collector_lab_assignments')->updateOrInsert(['lab_id'=>$lab->id,'source_lab_id'=>$v['source_lab_id']],['collector_id'=>$v['collector_id'],'created_at'=>now(),'updated_at'=>now()]);
                foreach(CollectionTask::where('lab_id',$lab->id)->where('source_lab_id',$v['source_lab_id'])->whereNull('collector_id')->where('status','requested')->lockForUpdate()->get() as $task){$task->update(['collector_id'=>$v['collector_id'],'version'=>$task->version+1]);app(CollectorTasks::class)->notify($task,'assigned:'.$task->version,'تم إسناد طلب سحب عينة إليك');}
            }
            app(CollectorTasks::class)->event($lab->id,'source_assigned',$v);
        }); return $this->json(['ok'=>true]);
    }
    public function assignTask(Request $r,int $id) {
        $lab=$this->lab($r,true,'sample logistics manage');$v=$r->validate(['collector_id'=>'required|integer','version'=>'required|integer|min:1']);app(CollectorAccess::class)->collectors($lab)->where('status',1)->findOrFail($v['collector_id']);
        $task=DB::transaction(function()use($lab,$v,$id){
            $t=CollectionTask::where('lab_id',$lab->id)->lockForUpdate()->findOrFail($id);abort_unless($t->status==='requested'&&$t->version===$v['version'],409,'يمكن إسناد الطلب قبل الاستلام فقط؛ حدّث القائمة.');
            $t->update(['collector_id'=>$v['collector_id'],'version'=>$t->version+1]);app(CollectorTasks::class)->event($lab->id,'task_assigned',$v,$t->id);app(CollectorTasks::class)->notify($t,'assigned:'.$t->version,'تم إسناد طلب سحب عينة إليك');return $t;
        });return $this->json(app(CollectorTasks::class)->document($task));
    }
    public function submissions(Request $r,bool $management=false) {
        $lab=$this->lab($r,$management,$management?'sample logistics review':'sample logistics view');$r->validate(['status'=>'nullable|in:pending,approved,rejected','page'=>'nullable|integer|min:1']);
        $q=CollectionSubmission::where('lab_id',$lab->id);if(!$management)$q->where('collector_id',$r->user()->id);if($r->filled('status'))$q->where('status',$r->status);
        $p=$q->latest('id')->paginate(20);
        return $this->json(['data'=>collect($p->items())->map(fn($s)=>$s->only(['id','collector_id','source_lab_id','status','invoice_id','reviewed_at','review_note','created_at'])+['patient_name'=>$s->payload['inline_patient']['name'],'total'=>$s->payload['total'],'collector_name'=>User::withTrashed()->find($s->collector_id)?->name]),'page'=>$p->currentPage(),'last_page'=>$p->lastPage(),'total'=>$p->total()]);
    }
    public function waiting(Request $r){return $this->submissions($r,true);}
    public function showSubmission(Request $r,int $id,bool $management=false) {
        $lab=$this->lab($r,$management,$management?'sample logistics review':'sample logistics view');$q=CollectionSubmission::where('lab_id',$lab->id);if(!$management)$q->where('collector_id',$r->user()->id);$s=$q->findOrFail($id);
        $catalog=app(CollectorCatalog::class)->all($lab,$s->source_lab_id);$items=[];
        foreach(CollectorCatalog::KINDS as $kind=>[$class,$field])foreach($s->payload[$kind]??[] as $row)$items[]=['kind'=>$kind,'price'=>$row['price'],'name'=>$catalog[$kind][$row[$field]]['name']??('#'.$row[$field]),'children'=>$row['tests']??[],'cultures'=>$row['cultures']??[],'questions'=>$row['questions']??[]];
        return $this->json(['submission'=>$s,'items'=>$items,'source'=>app(CollectorTasks::class)->sourceInfo($s->source_lab_id),
            'image'=>$s->image_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($s->image_path)?'data:image/'.pathinfo($s->image_path,PATHINFO_EXTENSION).';base64,'.base64_encode(\Illuminate\Support\Facades\Storage::disk('local')->get($s->image_path)):null,
            'references'=>[
                'age_unit'=>\App\Models\AgeUnit::find($s->payload['inline_patient']['age_unit_id_fk']??null)?->unit_name,
                'gender'=>\App\Models\Gender::find($s->payload['inline_patient']['gender_id_fk']??null)?->gender_type,
                'title'=>\App\Models\Title::find($s->payload['inline_patient']['title_id_fk']??null)?->title,
                'nationality'=>\App\Models\Nationality::find($s->payload['inline_patient']['nationality_id_fk']??null)?->country_name,
                'contract'=>\App\Models\Contract::find($s->payload['contract_id_fk']??null)?->name,
                'referral'=>User::find($s->payload['referral_id_fk']??null)?->name,
                'payments'=>\App\Models\PaymentMethod::whereIn('lab_id_fk',app(CollectorAccess::class)->accounts($lab))->pluck('name','id'),
            ]]);
    }
    public function waitingShow(Request $r,int $id){return $this->showSubmission($r,$id,true);}
    public function submit(Request $r){return $this->json(app(CollectorSubmissions::class)->submit($r)->only(['id','status','created_at']),201);}
    public function review(Request $r,int $id){return $this->json(app(CollectorSubmissions::class)->review($r,$id)->only(['id','status','invoice_id','review_note']));}
    public function lookup(Request $r,string $resource) {
        $lab=$this->lab($r);$source=$r->integer('source_lab_id')?:null;app(CollectorAccess::class)->assertSource($r->user(),$source);
        $refs=['genders'=>\App\Models\Gender::class,'age-units'=>\App\Models\AgeUnit::class,'titles'=>\App\Models\Title::class,'nationalities'=>\App\Models\Nationality::class,'result-status'=>\App\Models\ResultStatus::class];
        if(isset($refs[$resource]))return $this->json($refs[$resource]::all());
        $ids=app(CollectorAccess::class)->accounts($lab);
        if($resource==='payment-methods')return $this->json(\App\Models\PaymentMethod::whereIn('lab_id_fk',$ids)->get());
        if($resource==='contracts')return $this->json(\App\Models\Contract::whereIn('lab_id_fk',$ids)->get());
        if($resource==='referrals')return $this->json(\App\Models\Referal::whereIn('lab_id_fk',$ids)->with('user')->get()->map(fn($ref)=>['id'=>$ref->id,'user_id'=>$ref->referral_id_fk,'name'=>$ref->user?->name,'role_id'=>$ref->user?->role_id,'role'=>(int)$ref->user?->role_id===5?'Doctor':'Lab','commission'=>(float)$ref->commission,'phone_number'=>$ref->user?->phone_number]));
        if(in_array($resource,['labs','collectors','templates']))return $this->json([]);
        abort_unless(in_array($resource,['tests','cultures','packages']),404);$all=app(CollectorCatalog::class)->all($lab,$source);
        $rows=$resource==='tests'?array_merge(array_values($all['tests']),array_values($all['test_groups'])):array_values($all[$resource]);
        return $this->json($rows);
    }
    public function patients(Request $r){$this->lab($r);return $this->json([]);}
    public function questions(Request $r){$lab=$this->lab($r);$v=$r->validate(['tests_ids'=>'required|array|max:100','tests_ids.*'=>'integer']);return $this->json(['questions'=>app(CollectorCatalog::class)->questions($lab,$v['tests_ids']),'result_comments'=>[]]);}
}
