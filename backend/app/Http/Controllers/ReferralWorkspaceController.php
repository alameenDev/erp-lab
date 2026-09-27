<?php
namespace App\Http\Controllers;
use App\Models\{User, Patient, Invoice, InvoiceTestRel, Referal, PriceListRel};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Hash};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReferralWorkspaceController extends Controller {
 use \App\Traits\SecureFileUpload;
 private const KINDS = ['tests'=>['test','test_id_fk'], 'cultures'=>['culture','culture_id_fk'], 'packages'=>['package','package_id_fk'], 'test_groups'=>['testGroup','test_group_id_fk']];
 private function connection(Request $r, bool $lock = false): Referal {
  $q=Referal::with('priceList')->where('referral_id_fk', Auth::id())->where('lab_id_fk', $r->input('destination_lab_id'));
  if($lock) $q->lockForUpdate();
  $c=$q->first(); abort_unless($c,403,'اختر مختبراً مرتبطاً بجهة الإحالة');
  return $c;
 }
 private function ownInvoice($id): Invoice {
  return Invoice::where('from_lab_id_fk', Auth::id())->whereIn('lab_id_fk',Referal::where('referral_id_fk',Auth::id())->select('lab_id_fk'))->findOrFail($id);
 }
 private function catalogRows(Referal $c) {
  if(!$c->priceList || (int)$c->priceList->lab_id_fk !== (int)$c->lab_id_fk) return collect();
  return PriceListRel::where('lab_id_fk',$c->lab_id_fk)->where('price_list_id_fk',$c->price_list_id_fk)
   ->with(['test','culture','package.tests','package.cultures','package.testGroups.tests','testGroup.tests','testGroup.culture'])->get();
 }
 private function price($row, Referal $c): int {
  $v=$row->price_for_customer ?? $row->original_price;
  abort_unless(is_numeric($v) && $v>=0,422,'سعر الفحص غير صالح');
  return (int)round($v*(1-max(0,min(100,(float)$c->priceList->discount))/100));
 }
 private function allowed($row, $relation, Referal $c): bool {
  $m=$row->$relation;
  return $m && !$m->trashed() && (int)$m->lab_id_fk === (int)$c->lab_id_fk;
 }
 private function item($row, string $kind, Referal $c): array {
  [$relation,$field]=self::KINDS[$kind]; $m=$row->$relation; $price=$this->price($row,$c);
  $brief=fn($t)=>['id'=>$t->id,'name'=>$t->name,'price'=>0,'for_customer_price'=>0,'sample_name'=>null];
  $d=['id'=>$m->id,'name'=>$m->name ?? $m->group_name,'group_name'=>$m->group_name ?? null,'type'=>$kind==='test_groups'?'group':'test','shortcut'=>$m->shortcut ?? null,'price'=>$price,'for_customer_price'=>$price,'original_price'=>$price,'prices'=>[]];
  if(in_array($kind,['packages','test_groups'])) {
   $tests=$m->tests;
   if($kind==='packages') $tests=$tests->merge($m->testGroups->flatMap(fn($g)=>$g->tests))->unique('id');
   $d['tests']=$tests->map($brief)->values();
   $d['cultures']=($kind==='packages'?$m->cultures:$m->culture)->map($brief)->values(); $d['culture']=$d['cultures'];
  }
  return $d;
 }
 public function lookup(Request $r, string $resource) {
  $references=['genders'=>\App\Models\Gender::class,'age-units'=>\App\Models\AgeUnit::class,'titles'=>\App\Models\Title::class,'nationalities'=>\App\Models\Nationality::class,'payment-methods'=>\App\Models\PaymentMethod::class,'result-status'=>\App\Models\ResultStatus::class];
  if(isset($references[$resource])) return response()->json($references[$resource]::all());
  if(in_array($resource,['labs','collectors','contracts','referrals','templates'])) return response()->json([]);
  $c=$this->connection($r);
  abort_unless(in_array($resource,['tests','cultures','packages']),404);
  $kinds=$resource==='tests'?['tests','test_groups']:[$resource]; $out=[];
  foreach($this->catalogRows($c) as $row) foreach($kinds as $kind) {
   if($this->allowed($row,self::KINDS[$kind][0],$c)) $out[]=$this->item($row,$kind,$c);
  }
  if($search=trim((string)$r->input('search'))) $out=array_values(array_filter($out,fn($i)=>mb_stripos($i['name'],$search)!==false));
  return response()->json($out);
 }
 public function patients(Request $r) {
  $c=$this->connection($r); $name=trim((string)$r->input('name'));
  if(mb_strlen($name)<3) return response()->json([]);
  $patients=Patient::with(['user','gender','ageUnit'])->where('creator_id',$c->lab_id_fk)
   ->whereIn('id',Invoice::where('from_lab_id_fk',Auth::id())->where('lab_id_fk',$c->lab_id_fk)->select('patient_id_fk'))
   ->whereHas('user',fn($q)=>$q->whereNull('deleted_at')->where('name','like','%'.$name.'%'))->limit(20)->get();
  return response()->json($patients->map(fn($p)=>['id'=>$p->id,'name'=>$p->user->name,'code'=>$p->code,'phone'=>$p->user->phone_number,'dob'=>$p->dob?->format('Y-m-d'),'age'=>$p->age,'age_unit_id_fk'=>$p->age_unit_id_fk,'age_unit'=>$p->ageUnit?->unit_name,'gender_type_id_fk'=>$p->gender_id_fk,'gender_id_fk'=>$p->gender_id_fk,'gender'=>$p->gender?->gender_type,'title_id_fk'=>$p->title_id_fk,'address'=>$p->address]));
 }
 public function questions(Request $r) {
  $c=$this->connection($r); $ids=$r->input('tests_ids',[]);
  $allowed=$this->catalogRows($c)->filter(fn($row)=>$this->allowed($row,'test',$c))->pluck('test_id_fk')->all();
  abort_unless(is_array($ids) && count(array_diff($ids,$allowed))===0,422);
  $questions=[];
  foreach(\App\Models\Test::whereIn('id',$ids)->where('lab_id_fk',$c->lab_id_fk)->with('questions.answerType')->get() as $test) foreach($test->questions as $q) {
   $questions[]=['id'=>$q->id,'test_id_fk'=>$test->id,'question'=>$q->question,'answer_type'=>$q->answerType?->answer ?? 'text','answer_type_id_fk'=>$q->answer_type_id_fk,'answer_type_selection_values'=>$q->answer_type_selection_values];
  }
  return response()->json(['questions'=>$questions,'result_comments'=>[]]);
 }
 public function show(Request $r, $id) { return response()->json($this->document($this->ownInvoice($id), $r->input('document')==='report')); }
 private function document(Invoice $i, bool $report=false): array {
  $data=app(InvoiceController::class)->referralDocument($i,$report);
  $own=DB::table('referral_invoice_details')->where('invoice_id',$i->id)->where('referral_id',Auth::id())->first();
  if($own) {
   foreach(['sub_total','total','paid','discount','discount_type_id_fk','notes'] as $key) $data[$key]=$own->$key;
   $data['paidDetails']=json_decode($own->payments ?? '[]',true);
  } else { $data['paid']=0; $data['paidDetails']=[]; }
  $data['referral_document']=true;
  return $data;
 }
 public function store(Request $r) {
  $rules=['image'=>'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:5120','request_id'=>'required|uuid','destination_lab_id'=>'required|integer','patient_id_fk'=>'nullable|integer',
   'inline_patient'=>'nullable|array','inline_patient.name'=>'required_without:patient_id_fk|string|max:255',
   'inline_patient.phone_number'=>'nullable|string|max:50','inline_patient.gender_id_fk'=>'nullable|exists:genders,id',
   'inline_patient.age'=>'nullable|integer|min:0|max:50000','inline_patient.age_unit_id_fk'=>'nullable|exists:age_units,id',
   'inline_patient.dob'=>'nullable|date|before_or_equal:today','inline_patient.title_id_fk'=>'nullable|exists:titles,id',
   'inline_patient.address'=>'nullable|string|max:255',
   'inline_patient.nationality_id_fk'=>'nullable|exists:nationalities,id',
   'inline_patient.national_id_no'=>'nullable|string|max:100','inline_patient.passport_no'=>'nullable|string|max:100',
   'show_result_date'=>'nullable|boolean','show_patient_card_id'=>'nullable|boolean','show_patient_pic'=>'nullable|boolean',
   'notes'=>'nullable|string|max:255',
   'discount'=>'nullable|integer|min:0','discount_type_id_fk'=>'nullable|in:2,3',
   'payment_details'=>'nullable|array|max:20','payment_details.*.amount'=>'nullable|integer|min:0',
   'payment_details.*.payment_method_id_fk'=>'nullable|exists:payment_methods,id'];
  foreach(self::KINDS as $key=>[$rel,$field]) { $rules[$key]='nullable|array|max:100'; $rules[$key.'.*.'.$field]='required|integer|distinct'; }
  $rules['tests.*.questions']='nullable|array|max:100';
  $rules['tests.*.questions.*.question.id']='required|integer';
  $rules['tests.*.questions.*.answer']='nullable';
  $v=$r->validate($rules); $hashData=$v; unset($hashData['image']);
  $hashData['image_hash']=$r->hasFile('image')?hash_file('sha256',$r->file('image')->getRealPath()):null;
  $hash=hash('sha256',json_encode($hashData)); $uploaded=null;
  try {
  return DB::transaction(function()use($r,$v,$hash,&$uploaded){
   User::whereKey(Auth::id())->lockForUpdate()->firstOrFail(); $c=$this->connection($r,true);
   $existing=Invoice::withTrashed()->where('from_lab_id_fk',Auth::id())->where('referral_request_uuid',$v['request_id'])->first();
   if($existing) { abort_unless(!$existing->trashed() && hash_equals($existing->referral_request_hash,$hash),409,'الطلب محفوظ سابقاً بمعلومات مختلفة'); return response()->json($this->document($existing)); }
   $rows=$this->catalogRows($c); $selected=[]; $total=0;
   foreach(self::KINDS as $kind=>[$relation,$field]) foreach($v[$kind]??[] as $input) {
    $row=$rows->first(fn($row)=>(int)$row->$field===(int)$input[$field] && $this->allowed($row,$relation,$c));
    if(!$row) throw ValidationException::withMessages([$kind=>'الفحص غير مسموح ضمن قائمة أسعار الجهة']);
    $price=$this->price($row,$c); $questions=[];
    if($kind==='tests') {
     $valid=$row->test->questions()->with('answerType')->get()->keyBy('id');
     foreach($input['questions']??[] as $q) {
      $question=$valid->get($q['question']['id']);
      abort_unless($question && (is_scalar($q['answer']??null)||is_null($q['answer']??null)),422,'إجابة غير صالحة');
      abort_unless(mb_strlen((string)($q['answer']??''))<=2000,422,'الإجابة طويلة');
      $questions[]=['question'=>['id'=>$question->id,'question'=>$question->question,'answer_type'=>$question->answerType?->answer ?? 'text','answer_type_id_fk'=>$question->answer_type_id_fk,'answer_type_selection_values'=>$question->answer_type_selection_values],'answer'=>$q['answer']??null];
     }
    }
    $selected[]=[$field,$input[$field],$price,$questions]; $total+=$price;
   }
   abort_unless(count($selected)>0,422,'اختر فحصاً واحداً على الأقل');
   $patient=null;
   if(!empty($v['patient_id_fk'])) $patient=Patient::where('creator_id',$c->lab_id_fk)->whereIn('id',Invoice::where('from_lab_id_fk',Auth::id())->where('lab_id_fk',$c->lab_id_fk)->select('patient_id_fk'))->lockForUpdate()->findOrFail($v['patient_id_fk']);
   if(!$patient) {
    if($r->hasFile('image')) { $upload=$this->secureUploadImage($r->file('image'),'referral-patients'); abort_unless($upload['success'],422,$upload['error']??'تعذر رفع الصورة'); $uploaded=$upload['path']; }
    $pr=$v['inline_patient']; $u=User::create(['name'=>$pr['name'],'phone_number'=>$pr['phone_number']??null,'email'=>'ref-'.Str::uuid().'@example.invalid','password'=>Hash::make(Str::random(40)),'role_id'=>3,'creator_id'=>$c->lab_id_fk]);
    $patient=Patient::create(array_merge(\Illuminate\Support\Arr::only($pr,['dob','gender_id_fk','age','age_unit_id_fk','title_id_fk','address','nationality_id_fk','national_id_no','passport_no']),['image'=>$uploaded,'user_id'=>$u->id,'creator_id'=>$c->lab_id_fk,'code'=>'R'.$u->id,'barcode'=>'P'.$u->id]));
   }
   $discount=(int)($v['discount']??0); $type=$v['discount_type_id_fk']??null;
   $amount=$type==2?(int)round($total*$discount/100):($type==3?$discount:0);
   abort_unless($amount<=$total && ($type!=2||$discount<=100),422,'الخصم يتجاوز قيمة الفاتورة');
   $payments=collect($v['payment_details']??[])->filter(fn($p)=>($p['amount']??0)>0)->map(fn($p)=>['amount'=>(int)$p['amount'],'payment_method_id_fk'=>$p['payment_method_id_fk']??null,'payment_method'=>isset($p['payment_method_id_fk'])?DB::table('payment_methods')->where('id',$p['payment_method_id_fk'])->value('name'):null])->values();
   $paid=$payments->sum('amount'); abort_unless($paid<=$total-$amount,422,'المبلغ المدفوع يتجاوز المستحق');
   $invoice=Invoice::create(['patient_id_fk'=>$patient->id,'lab_id_fk'=>$c->lab_id_fk,'from_lab_id_fk'=>Auth::id(),'sub_total'=>$total,'total'=>$total,'paid'=>0,'discount'=>0,'notes'=>$v['notes']??null,'is_done'=>false,'referral_request_uuid'=>$v['request_id'],'referral_request_hash'=>$hash,'registration_date'=>now(),'show_result_date'=>$v['show_result_date']??false,'show_patient_card_id'=>$v['show_patient_card_id']??false,'show_patient_pic'=>$v['show_patient_pic']??false]);
   $invoice->update(['barcode'=>'R'.$invoice->id]);
   foreach($selected as [$field,$id,$price,$questions]) InvoiceTestRel::create(['invoice_id_fk'=>$invoice->id,$field=>$id,'price'=>$price,'questions'=>$questions,'is_done'=>false,'is_sample_received'=>false]);
   DB::table('referral_invoice_details')->insert(['invoice_id'=>$invoice->id,'referral_id'=>Auth::id(),'sub_total'=>$total,'total'=>$total-$amount,'discount'=>$discount,'discount_type_id_fk'=>$type,'paid'=>$paid,'payments'=>$payments->toJson(),'notes'=>$v['notes']??null,'created_at'=>now(),'updated_at'=>now()]);
   ActivityLogController::registerActivity('إنشاء فاتورة بوابة إحالة رقم '.$invoice->id);
   return response()->json($this->document($invoice),201);
  });
  } catch (\Throwable $e) { if($uploaded) \Illuminate\Support\Facades\Storage::disk('public')->delete($uploaded); throw $e; }
 }
}

