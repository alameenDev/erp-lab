<?php
namespace App\Services;

use App\Models\{CollectionSubmission, User, Patient};
use Illuminate\Http\Request;
use Illuminate\Support\{Arr, Str};
use Illuminate\Support\Facades\{DB, Hash, Storage};
use Illuminate\Validation\Rule;

class CollectorSubmissions
{
    use \App\Traits\SecureFileUpload;
    public function validatePayload(Request $r, User $lab, ?int $source): array
    {
        $ids=app(CollectorAccess::class)->accounts($lab);
        $rules=['request_id'=>'required|uuid','inline_patient'=>'required|array', 'patient_id_fk'=>'prohibited',
            'inline_patient.name'=>'required|string|max:255','inline_patient.phone_number'=>'nullable|string|max:50',
            'inline_patient.email'=>'nullable|email|max:255','inline_patient.gender_id_fk'=>'nullable|exists:genders,id',
            'inline_patient.age'=>'nullable|integer|min:0|max:50000','inline_patient.age_unit_id_fk'=>'nullable|exists:age_units,id',
            'inline_patient.dob'=>'nullable|date|before_or_equal:'.today('Asia/Baghdad')->toDateString(), 'inline_patient.dob_is_estimated'=>'sometimes|boolean',
            'inline_patient.title_id_fk'=>'nullable|exists:titles,id','inline_patient.address'=>'nullable|string|max:255','inline_patient.nationality_id_fk'=>'nullable|exists:nationalities,id',
            'inline_patient.national_id_no'=>'nullable|string|max:100','inline_patient.passport_no'=>'nullable|string|max:100',
            'show_result_date'=>'nullable|boolean','show_patient_card_id'=>'nullable|boolean','show_patient_pic'=>'nullable|boolean',
            'notes'=>'nullable|string|max:5000','registration_date'=>'required|date_format:Y-m-d',
            'discount'=>'nullable|integer|min:0','discount_type_id_fk'=>'nullable|in:2,3',
            'work_register_amount'=>'nullable|integer|min:0|max:1000000000000',
            'payment_details'=>'nullable|array|max:20','payment_details.*.amount'=>'nullable|integer|min:0|max:1000000000000',
            'payment_details.*.payment_method_id_fk'=>['nullable',Rule::exists('payment_methods','id')->whereIn('lab_id_fk',$ids)->whereNull('deleted_at')],
            'contract_id_fk'=>['nullable',Rule::exists('contracts','id')->whereIn('lab_id_fk',$ids)->whereNull('deleted_at')],
            'referral_id_fk'=>['nullable',Rule::in(\App\Models\Referal::whereIn('lab_id_fk',$ids)->pluck('referral_id_fk')->all())],
            'selection_order'=>'nullable|array|max:400','selection_order.*.field'=>'required|in:tests,cultures,test_groups,packages','selection_order.*.id'=>'required|integer',
            'tests.*.questions'=>'nullable|array|max:100','tests.*.questions.*.question.id'=>'required|integer','tests.*.questions.*.answer'=>'nullable'];
        foreach(CollectorCatalog::KINDS as $kind=>[$class,$field]){$rules[$kind]='nullable|array|max:100';$rules[$kind.'.*.'.$field]='required|integer|distinct';}
        $v=$r->validate($rules);
        $v['inline_patient']=app(PatientAge::class)->normalize(Arr::only($v['inline_patient'],['name','phone_number','email','dob','dob_is_estimated','age','age_unit_id_fk','gender_id_fk','title_id_fk','address','nationality_id_fk','national_id_no','passport_no']),null,'inline_patient.');
        $catalog=app(CollectorCatalog::class)->all($lab,$source); $subTotal=0; $count=0;
        foreach(CollectorCatalog::KINDS as $kind=>[$class,$field]) {
            $safe=[];
            foreach($v[$kind]??[] as $input) {
                $item=$catalog[$kind][$input[$field]]??null; abort_unless($item,422,'أحد التحاليل لم يعد متاحاً في المختبر.');
                $row=[$field=>$item['id'],'price'=>$item['price'],'is_done'=>false,'is_sample_received'=>false];
                if($kind==='tests') {
                    $questions=collect(app(CollectorCatalog::class)->questions($lab,[$item['id']]))->keyBy('id'); $row['questions']=[];
                    foreach($input['questions']??[] as $answer) {
                        $q=$questions->get($answer['question']['id']); $a=$answer['answer']??null;
                        abort_unless($q && (is_null($a)||is_scalar($a)) && mb_strlen((string)$a)<=2000,422,'إجابة السؤال غير صالحة.');
                        $row['questions'][]=['question'=>$q,'answer'=>$a];
                    }
                }
                if(in_array($kind,['packages','test_groups'])) { $row['tests']=$item['tests']; $row['cultures']=$item['cultures']; }
                $safe[]=$row; $subTotal+=$item['price']; $count++;
            }
            $v[$kind]=$safe;
        }
        abort_unless($count>0,422,'اختر تحليلاً واحداً على الأقل.');
        $discount=(int)($v['discount']??0); $type=$v['discount_type_id_fk']??null;
        $amount=$type==2?(int)round($subTotal*$discount/100):($type==3?$discount:0);
        abort_unless($amount<=$subTotal && ($type!=2||$discount<=100),422,'الخصم يتجاوز مبلغ الفاتورة.');
        $v['payment_details']=collect($v['payment_details']??[])->filter(fn($p)=>($p['amount']??0)>0)->map(fn($p)=>Arr::only($p,['amount','payment_method_id_fk']))->values()->all();
        $contract=!empty($v['contract_id_fk'])?\App\Models\Contract::findOrFail($v['contract_id_fk']):null;
        $referral=!empty($v['referral_id_fk'])?\App\Models\Referal::whereIn('lab_id_fk',$ids)->where('referral_id_fk',$v['referral_id_fk'])->first():null;
        $total=(int)round($subTotal-$amount+(float)($contract?->payment_percent??0)+(float)($referral?->commission??0));
        abort_unless($total>=0,422,'الإجمالي غير صالح.');
        if($contract)abort_if($total>(float)$contract->maximum_payment_per_invoice,422,'الإجمالي يتجاوز الحد المسموح للعقد.');
        $paid=collect($v['payment_details'])->sum('amount'); abort_unless($paid<=$total,422,'المدفوع يتجاوز المستحق.');
        $v['sub_total']=$subTotal; $v['total']=$total; $v['paid']=$paid; $v['from_lab_id_fk']=$source;
        $v['selection_order']=array_values(array_filter($v['selection_order']??[],fn($o)=>isset($catalog[$o['field']][$o['id']]) && collect($v[$o['field']])->contains(CollectorCatalog::KINDS[$o['field']][1],$o['id'])));
        return $v;
    }
    public function submit(Request $r): CollectionSubmission
    {
        $user=$r->user(); $access=app(CollectorAccess::class); $lab=$access->lab($user);
        $r->validate(['source_lab_id'=>'nullable|integer','image'=>'nullable|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $source=$r->integer('source_lab_id')?:null; $access->assertSource($user,$source);
        $payload=$this->validatePayload($r,$lab,$source);
        $hash=hash('sha256',json_encode($payload).($r->hasFile('image')?hash_file('sha256',$r->file('image')->getRealPath()):'')); $uploaded=null;
        try {
            return DB::transaction(function()use($r,$user,$lab,$source,$payload,$hash,&$uploaded){
                User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $old=CollectionSubmission::where('collector_id',$user->id)->where('request_uuid',$payload['request_id'])->first();
                if($old){abort_unless(hash_equals($old->request_hash,$hash),409,'هذا الطلب محفوظ بمعلومات مختلفة.');return $old;}
                if($r->hasFile('image')) {
                    $upload=$this->secureUploadImage($r->file('image'),'collector-patients','local'); abort_unless($upload['success'],422,$upload['error']??'تعذر رفع الصورة');
                    $uploaded=$upload['path'];
                }
                $s=CollectionSubmission::create(['lab_id'=>$lab->id,'collector_id'=>$user->id,'source_lab_id'=>$source,'request_uuid'=>$payload['request_id'],'request_hash'=>$hash,'payload'=>$payload,'image_path'=>$uploaded]);
                app(CollectorTasks::class)->event($lab->id,'submitted',[] ,null,$s->id);return $s;
            });
        } catch(\Throwable $e){if($uploaded)Storage::disk('local')->delete($uploaded);throw $e;}
    }
    public function review(Request $r,int $id): CollectionSubmission
    {
        $lab=app(CollectorAccess::class)->manager($r,'sample logistics review');
        abort_if((int)$r->user()->role_id===1,403,'اعتماد الفواتير يتم من حساب المختبر أو موظفيه.');
        $v=$r->validate(['decision'=>'required|in:approved,rejected','note'=>'required_if:decision,rejected|nullable|string|max:2000']);
        return DB::transaction(function()use($r,$lab,$id,$v){
            $s=CollectionSubmission::where('lab_id',$lab->id)->lockForUpdate()->findOrFail($id);
            if($s->status===$v['decision'])return $s;
            abort_unless($s->status==='pending',409,'تمت مراجعة الطلب سابقاً.');
            $updates=['status'=>$v['decision'],'reviewer_id'=>$r->user()->id,'reviewed_at'=>now(),'review_note'=>$v['note']??null];
            if($v['decision']==='approved') {
                abort_unless($r->user()->can('invoices create'),403,'اعتماد الطلب يحتاج صلاحية إنشاء الفواتير.');
                if($s->source_lab_id)app(CollectorAccess::class)->source($lab,$s->source_lab_id);
                $payload=$this->validatePayload(new Request($s->payload),$lab,$s->source_lab_id);
                // Changed prices require a new, explicitly reviewed submission, never a silent increase.
                abort_unless($payload['total']===$s->payload['total'] && $payload['sub_total']===$s->payload['sub_total'] && Arr::only($payload,array_keys(CollectorCatalog::KINDS))===Arr::only($s->payload,array_keys(CollectorCatalog::KINDS)),409,'تغيرت الأسعار؛ ارفض الطلب واطلب إعادة إرساله بالأسعار الحالية.');
                $pr=$payload['inline_patient'];
                if(!empty($pr['email']))abort_if(User::withTrashed()->where('email',$pr['email'])->exists(),422,'البريد الإلكتروني مستخدم؛ عدّل معلومات المريض في طلب جديد.');
                $u=User::create(['name'=>$pr['name'],'phone_number'=>$pr['phone_number']??null,'email'=>$pr['email']??('collector-'.Str::uuid().'@example.invalid'),'password'=>Hash::make(Str::random(50)),'role_id'=>3,'creator_id'=>$lab->id]);
                $image=null;
                if($s->image_path) {
                    abort_unless(Storage::disk('local')->exists($s->image_path),422,'صورة المريض غير متاحة؛ أعد إرسال الطلب.');
                    $image=$s->image_path;
                    abort_unless(Storage::disk('public')->put($image,Storage::disk('local')->get($image)),500,'تعذر حفظ صورة المريض.');
                }
                $p=Patient::create(Arr::only($pr,['dob','dob_is_estimated','gender_id_fk','age','age_unit_id_fk','title_id_fk','address','nationality_id_fk','national_id_no','passport_no'])+['user_id'=>$u->id,'creator_id'=>$lab->id,'image'=>$image,'code'=>'C'.$u->id,'barcode'=>'P'.$u->id]);
                unset($payload['inline_patient'],$payload['request_id']);
                $payload['patient_id_fk']=$p->id; $payload['sample_collector_id_fk']=$s->collector_id;
                $request=new Request($payload);$request->setUserResolver(fn()=>$r->user());
                $response=app(\App\Http\Controllers\InvoiceController::class)->store($request);
                abort_unless($response->getStatusCode()<400,$response->getStatusCode(),$response->getData(true)['message']??'تعذر اعتماد الفاتورة.');
                $invoiceId=$response->getData(true)['id']??null; abort_unless($invoiceId,500,'لم يُرجع النظام رقم الفاتورة.');
                $updates['invoice_id']=$invoiceId;
                \App\Models\Invoice::whereKey($invoiceId)->update(['registration_date'=>$payload['registration_date']]);
            }
            $s->update($updates); app(CollectorTasks::class)->event($lab->id,$v['decision'],['invoice_id'=>$s->invoice_id,'note'=>$s->review_note],null,$s->id);
            app(CollectorNotifications::class)->create($s->collector_id,$lab->id,'submission:'.$s->id.':'.$s->status,$s->status==='approved'?'تم اعتماد الفاتورة':'طلب الفاتورة يحتاج مراجعة','افتح قائمة فواتيرك لمراجعة القرار.',null,$s->id);
            return $s;
        });
    }
}
