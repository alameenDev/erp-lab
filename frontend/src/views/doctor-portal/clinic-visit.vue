<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { $http } from '@/plugins/axios';

const route=useRoute(),router=useRouter();
const visit=ref(null);
const history=ref([]);
const form=ref({});
const revisions=ref([]);
const loading=ref(true),busy=ref(false),error=ref(''),notice=ref('');
const printMode=ref('clinical');
const doctor=JSON.parse(localStorage.getItem('user')||'{}');
const commonSymptoms=['حمّى','صداع','دوخة','تعب عام','سعال','ضيق التنفس','ألم الصدر','خفقان',
  'ألم البطن','غثيان','تقيؤ','إسهال','إمساك','ألم الظهر','آلام المفاصل','التهاب الحلق',
  'طفح جلدي','حكة','ألم أثناء التبول','تورّم الأطراف','فقدان الشهية','نقص الوزن'];
const modalities=[
  {value:'XRAY',label:'X-ray / أشعة سينية'},
  {value:'US',label:'Ultrasound / سونار'},
  {value:'CT',label:'CT / مفراس'},
  {value:'MRI',label:'MRI / رنين'},
  {value:'OTHER',label:'فحص آخر'}
];
const routes=['فموي / Oral','حقن وريدي / IV','حقن عضلي / IM','تحت الجلد / SC','استنشاق / Inhalation','موضعي / Topical','عن طريق العين / Ophthalmic','عن طريق الأنف / Nasal','آخر'];
const frequencies=['مرة يومياً','مرتين يومياً','3 مرات يومياً','4 مرات يومياً','كل 6 ساعات','كل 8 ساعات','كل 12 ساعة','PRN'];
const statuses={waiting:'بانتظار الدخول',in_consultation:'داخل العيادة',completed:'مكتملة',cancelled:'ملغاة'};
const fields=[
  'status','chief_complaint','symptoms','symptom_duration','history_of_present_illness','review_of_systems',
  'medical_history','surgical_history','family_history','allergies','current_medications','vitals',
  'physical_examination','assessment','diagnosis_code','plan','lab_requests','imaging_requests',
  'prescriptions','patient_instructions','follow_up_date'
];
const medKeys=['generic_name','strength','form','dose','route','frequency','duration','quantity','instructions','prn_indication','max_daily_dose'];
const isCompleted=computed(()=>visit.value?.status==='completed');
const canEdit=computed(()=>visit.value?.status!=='cancelled');
const patient=computed(()=>visit.value?.patient||{});
const date=value=>value ? new Date(String(value).slice(0,10)+'T12:00:00').toLocaleDateString('ar-IQ'):'—';

function makeForm(v) {
  const f={};
  for(const key of fields)f[key]=v[key]??(key==='symptoms'||key==='lab_requests'||key==='imaging_requests'||key==='prescriptions'?[]:key==='vitals'?{}:'');
  f.symptoms=Array.isArray(f.symptoms)?[...f.symptoms]:[];
  f.lab_requests=(f.lab_requests||[]).map(item=>({...item}));
  f.imaging_requests=(f.imaging_requests||[]).map(item=>({...item}));
  f.prescriptions=(f.prescriptions||[]).map(item=>({...item}));
  f.vitals={...f.vitals};
  return f;
}
function message(e){
  return e.response?.data?.errors ? Object.values(e.response.data.errors).flat().join('، ')
    : e.response?.data?.message || 'تعذر حفظ الكشفية. تحقق من الحقول.';
}
async function load(){
  loading.value=true;error.value='';
  try{
    const {data}=await $http.get('/doctor-portal/clinic/visits/'+route.params.id);
    visit.value=data;form.value=makeForm(data);
    const [patientHistory,revisionList]=await Promise.all([
      $http.get('/doctor-portal/clinic/patients/'+data.clinic_patient_id_fk),
      $http.get('/doctor-portal/clinic/visits/'+data.id+'/revisions')
    ]);
    history.value=patientHistory.data.visits?.data||[];
    revisions.value=revisionList.data||[];
  }catch(e){error.value=e.response?.status===404?'هذه الكشفية غير موجودة ضمن سجلاتك.':message(e)}
  finally{loading.value=false}
}
function toggleSymptom(s){
  const i=form.value.symptoms.indexOf(s);
  if(i>=0)form.value.symptoms.splice(i,1);else form.value.symptoms.push(s);
}
function addLab(){form.value.lab_requests.push({name:'',notes:''})}
function addImaging(){form.value.imaging_requests.push({modality:'XRAY',body_part:'',notes:''})}
function addMedicine(){form.value.prescriptions.push(Object.fromEntries(medKeys.map(key=>[key,''])))}
function validatePrescription(){
  for(const [i,med] of (form.value.prescriptions||[]).entries()){
    for(const field of ['generic_name','strength','form','dose','route','frequency','duration','quantity']){
      if(!String(med[field]||'').trim())return 'الدواء '+(i+1)+': يجب إكمال الاسم العلمي والتركيز والشكل والجرعة والطريق والتكرار والمدة والكمية.';
    }
    if(String(med.frequency).toUpperCase()==='PRN'&&(!med.prn_indication||!med.max_daily_dose))
      return 'الدواء '+(i+1)+': العلاج عند الحاجة يتطلب تحديد السبب والحد الأقصى اليومي.';
  }
  return '';
}
async function save(complete=false){
  if(busy.value||!canEdit.value)return;
  error.value='';notice.value='';
  if(complete){
    if(!String(form.value.assessment||'').trim()){error.value='يرجى كتابة التقييم السريري قبل إكمال الكشفية.';return}
    const message=validatePrescription();if(message){error.value=message;return}
  }
  const payload={};
  for(const key of fields)payload[key]=form.value[key]=== '' ? null : form.value[key];
  if(complete)payload.status='completed';
  if(isCompleted.value)payload.status='completed';
  busy.value=true;
  try{
    const {data}=await $http.put('/doctor-portal/clinic/visits/'+visit.value.id,payload);
    visit.value=data;form.value=makeForm(data);
    notice.value=complete?'تم اعتماد اكتمال الكشفية وحفظ معلوماتها.':'تم حفظ الكشفية.';
    if(isCompleted.value) {
      const res=await $http.get('/doctor-portal/clinic/visits/'+data.id+'/revisions');
      revisions.value=res.data||[];
    }
  }catch(e){error.value=message(e)}
  finally{busy.value=false}
}
function printReport(mode){
  printMode.value=mode;
  setTimeout(()=>window.print(),80);
}
onMounted(load);
</script>

<template>
  <main class="mx-auto max-w-7xl space-y-5 px-4 py-6 pb-24" dir="rtl">
    <div class="screen-only flex flex-wrap items-center justify-between gap-3">
      <button type="button" @click="router.push('/doctor-portal/clinic')" class="text-sm font-semibold text-teal-700">→ جدول العيادة</button>
      <div class="flex flex-wrap gap-2">
        <button type="button" :disabled="!visit" @click="printReport('clinical')" class="rounded-xl border border-slate-200 bg-white px-4 py-2 font-semibold text-slate-700">طباعة التقرير الطبي</button>
        <button type="button" :disabled="!visit" @click="printReport('prescription')" class="rounded-xl border border-slate-200 bg-white px-4 py-2 font-semibold text-slate-700">طباعة الوصفة</button>
      </div>
    </div>
    <p v-if="loading" class="rounded-xl bg-white p-6 text-slate-600">جاري فتح الكشفية...</p>
    <p v-if="error" role="alert" class="screen-only rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ error }}</p>
    <p v-if="notice" role="status" class="screen-only rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ notice }}</p>

    <template v-if="visit">
      <section class="screen-only rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="text-xs font-bold text-teal-700">ملف الكشفية #{{ visit.id }} · الدور {{ visit.queue_number }}</p>
            <h1 class="mt-1 text-2xl font-black text-slate-900">{{ patient.name }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ patient.phone }} · العمر وقت الزيارة: {{ visit.age_at_visit }} {{ visit.age_unit_at_visit==='months'?'شهر':'سنة' }} · {{ date(visit.visit_date) }}</p>
          </div>
          <span class="rounded-full bg-teal-50 px-3 py-1.5 text-xs font-bold text-teal-800">{{ statuses[visit.status] }}</span>
        </div>
        <div v-if="isCompleted" class="mt-4 rounded-xl bg-amber-50 p-3 text-xs leading-6 text-amber-900">الكشفية مكتملة. أي تعديل لاحق يُسجل في سجل المراجعات. هذا النظام لا يوفر توقيعاً إلكترونياً قانونياً، والمستند المطبوع غير موقّع إلكترونياً.</div>
        <div v-if="visit.status==='cancelled'" class="mt-4 rounded-xl bg-slate-50 p-3 text-sm">تم إلغاء هذه الزيارة، ولا يمكن تحرير كشفية ملغاة.</div>
      </section>

      <div class="screen-only grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_255px]">
        <form class="space-y-5" @submit.prevent="save(false)">
          <!-- S: Subjective -->
          <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-5"><p class="text-xs font-bold tracking-widest text-teal-700">S — SUBJECTIVE</p><h2 class="text-lg font-black text-slate-900">1. الشكوى والأعراض والتاريخ المرضي</h2><p class="mt-1 text-xs text-slate-500">تُكتب المعلومات كما يذكرها المريض، دون تحويل العرض تلقائياً إلى تشخيص.</p></div>
            <label class="block text-sm font-semibold text-slate-700">الشكوى الرئيسية (Chief Complaint)
              <textarea v-model="form.chief_complaint" :disabled="!canEdit" rows="2" maxlength="4000" placeholder="ما السبب الرئيسي للمراجعة؟ متى بدأ؟" class="mt-1 w-full rounded-xl border border-slate-200 p-3 font-normal"></textarea>
            </label>
            <div class="mt-4">
              <p class="mb-2 text-sm font-semibold text-slate-700">الأعراض الشائعة — اختر أو اكتب تفاصيل إضافية</p>
              <div class="flex flex-wrap gap-2">
                <button v-for="s in commonSymptoms" :key="s" :disabled="!canEdit" type="button" @click="toggleSymptom(s)" :class="form.symptoms.includes(s)?'border-teal-600 bg-teal-50 text-teal-800':'border-slate-200 bg-white text-slate-600'" class="rounded-full border px-3 py-1.5 text-xs font-semibold">{{ s }}</button>
              </div>
              <p class="mt-2 text-xs text-slate-400">هذه قائمة مساعدة للتوثيق، وليست أداة تشخيص أو توصية علاجية.</p>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
              <label class="text-sm font-semibold text-slate-700">مدة الأعراض<input v-model="form.symptom_duration" :disabled="!canEdit" maxlength="255" placeholder="مثال: منذ ثلاثة أيام" class="mt-1 w-full rounded-xl border border-slate-200 p-3 font-normal"/></label>
              <label class="text-sm font-semibold text-slate-700">الحساسيات الدوائية / الأخرى<input v-model="form.allergies" :disabled="!canEdit" maxlength="3000" placeholder="نوع الحساسية وطبيعة التفاعل، أو لا توجد حساسية معروفة بعد التحقق" class="mt-1 w-full rounded-xl border border-slate-200 p-3 font-normal"/></label>
            </div>
            <div class="mt-4 grid gap-4">
              <label v-for="field in [
                ['history_of_present_illness','تاريخ الشكوى الحالية (HPI)','البداية، المكان، الشدة، العوامل المؤثرة، الأعراض المرافقة...'],
                ['review_of_systems','مراجعة أجهزة الجسم (ROS)','راجع الأعراض ذات الصلة بكل جهاز حسب الحالة'],
                ['medical_history','الأمراض السابقة والمزمنة','السكري، الضغط، الأمراض المهمة؛ وثّق الحالة الفعلية فقط'],
                ['surgical_history','العمليات السابقة','نوع العملية وتاريخها إن وجد'],
                ['family_history','التاريخ العائلي','أمراض مهمة لدى أفراد العائلة'],
                ['current_medications','العلاجات الحالية','اسم الدواء والجرعة إن توفرت']
              ]" :key="field[0]" class="text-sm font-semibold text-slate-700">{{ field[1] }}
                <textarea v-model="form[field[0]]" :disabled="!canEdit" rows="2" :placeholder="field[2]" class="mt-1 w-full rounded-xl border border-slate-200 p-3 font-normal"></textarea>
              </label>
            </div>
          </section>

          <!-- O: Objective -->
          <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold tracking-widest text-teal-700">O — OBJECTIVE</p>
            <h2 class="mb-4 text-lg font-black text-slate-900">2. العلامات الحيوية والفحص السريري</h2>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
              <label v-for="vital in [
                ['blood_pressure','الضغط (mmHg)','120/80'],['heart_rate','النبض (bpm)',''],['temperature_c','الحرارة (°C)',''],
                ['respiratory_rate','التنفس / دقيقة',''],['spo2','الأكسجة (%)',''],
                ['weight_kg','الوزن (kg)',''],['height_cm','الطول (cm)','']
              ]" :key="vital[0]" class="text-xs font-semibold text-slate-700">{{ vital[1] }}
                <input v-model="form.vitals[vital[0]]" :disabled="!canEdit" :placeholder="vital[2]" maxlength="30" dir="ltr" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 font-normal"/>
              </label>
            </div>
            <label class="mt-4 block text-sm font-semibold text-slate-700">نتائج الفحص السريري
              <textarea v-model="form.physical_examination" :disabled="!canEdit" rows="3" maxlength="10000" placeholder="الحالة العامة، فحص القلب والرئتين والبطن، والنتائج ذات الصلة..." class="mt-1 w-full rounded-xl border border-slate-200 p-3 font-normal"></textarea>
            </label>
          </section>

          <!-- A: Assessment -->
          <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold tracking-widest text-teal-700">A — ASSESSMENT</p>
            <h2 class="mb-4 text-lg font-black text-slate-900">3. التقييم السريري والتشخيص</h2>
            <label class="block text-sm font-semibold text-slate-700">التقييم / التشخيص التفريقي *
              <textarea v-model="form.assessment" :disabled="!canEdit" rows="4" maxlength="10000" placeholder="الانطباع السريري للطبيب، التشخيصات المحتملة والأسباب الداعمة..." class="mt-1 w-full rounded-xl border border-slate-200 p-3 font-normal"></textarea>
            </label>
            <label class="mt-4 block text-sm font-semibold text-slate-700">رمز ICD-11 (اختياري، يُدخل بعد التحقق من الرمز)
              <input v-model="form.diagnosis_code" :disabled="!canEdit" maxlength="64" placeholder="رمز التصنيف المعتمد إن توفر" dir="ltr" class="mt-1 w-full rounded-xl border border-slate-200 p-3 font-normal"/>
            </label>
            <a class="mt-2 inline-block text-xs font-semibold text-teal-700 underline" href="https://icd.who.int/browse/2025-01/mms/en" target="_blank" rel="noopener noreferrer">البحث في مصنّف ICD-11 التابع لمنظمة الصحة العالمية</a>
          </section>

          <!-- P: Plan -->
          <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold tracking-widest text-teal-700">P — PLAN</p>
            <h2 class="mb-4 text-lg font-black text-slate-900">4. الخطة العلاجية والطلبات الطبية</h2>
            <label class="block text-sm font-semibold text-slate-700">خطة التدبير الطبي
              <textarea v-model="form.plan" :disabled="!canEdit" rows="3" maxlength="10000" placeholder="الإجراءات التي قررها الطبيب، الإحالات، ونقاط المتابعة..." class="mt-1 w-full rounded-xl border border-slate-200 p-3 font-normal"></textarea>
            </label>
            <div class="mt-6 rounded-xl border border-slate-200 p-4">
              <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="font-black text-slate-800">طلبات التحاليل المختبرية</h3>
                <button type="button" :disabled="!canEdit" @click="addLab" class="rounded-lg bg-teal-50 px-3 py-2 text-xs font-bold text-teal-700">+ إضافة تحليل</button>
              </div>
              <p v-if="!form.lab_requests.length" class="text-xs text-slate-400">لا توجد طلبات تحاليل.</p>
              <div v-for="(test,i) in form.lab_requests" :key="i" class="mb-2 flex flex-wrap gap-2 rounded-lg bg-slate-50 p-2">
                <input v-model="test.name" :disabled="!canEdit" maxlength="255" placeholder="اسم التحليل أو الفحص" class="min-w-0 flex-1 rounded-lg border border-slate-200 p-2 text-sm"/>
                <input v-model="test.notes" :disabled="!canEdit" maxlength="2000" placeholder="ملاحظات/تعليمات (اختياري)" class="min-w-0 flex-1 rounded-lg border border-slate-200 p-2 text-sm"/>
                <button type="button" :disabled="!canEdit" @click="form.lab_requests.splice(i,1)" class="rounded-lg px-3 text-red-600">حذف</button>
              </div>
            </div>
            <div class="mt-4 rounded-xl border border-slate-200 p-4">
              <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="font-black text-slate-800">طلبات الأشعة والتصوير</h3>
                <button type="button" :disabled="!canEdit" @click="addImaging" class="rounded-lg bg-teal-50 px-3 py-2 text-xs font-bold text-teal-700">+ إضافة تصوير</button>
              </div>
              <p v-if="!form.imaging_requests.length" class="text-xs text-slate-400">لا توجد طلبات أشعة.</p>
              <div v-for="(img,i) in form.imaging_requests" :key="i" class="mb-2 grid gap-2 rounded-lg bg-slate-50 p-3 md:grid-cols-[145px_1fr_1fr_auto]">
                <select v-model="img.modality" :disabled="!canEdit" class="rounded-lg border border-slate-200 p-2 text-sm"><option v-for="m in modalities" :key="m.value" :value="m.value">{{ m.label }}</option></select>
                <input v-model="img.body_part" :disabled="!canEdit" maxlength="255" placeholder="العضو أو منطقة التصوير" class="rounded-lg border border-slate-200 p-2 text-sm"/>
                <input v-model="img.notes" :disabled="!canEdit" maxlength="2000" placeholder="ملاحظات طبية/مادة تباين..." class="rounded-lg border border-slate-200 p-2 text-sm"/>
                <button type="button" :disabled="!canEdit" @click="form.imaging_requests.splice(i,1)" class="rounded-lg px-3 text-red-600">حذف</button>
              </div>
            </div>
            <div class="mt-4 rounded-xl border border-slate-200 p-4">
              <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="font-black text-slate-800">الوصفة الطبية (Rx)</h3>
                <button type="button" :disabled="!canEdit" @click="addMedicine" class="rounded-lg bg-teal-50 px-3 py-2 text-xs font-bold text-teal-700">+ إضافة دواء</button>
              </div>
              <p class="mb-4 text-xs leading-6 text-amber-800">أدخل الدواء العلمي والجرعة والطريق والتكرار والمدة والكمية بوضوح. اختيار العلاج وملاءمته مسؤولية الطبيب. لا يحدد البرنامج علاجاً تلقائياً.</p>
              <p v-if="!form.prescriptions.length" class="text-xs text-slate-400">لم تُضف أي أدوية إلى الوصفة.</p>
              <div v-for="(med,i) in form.prescriptions" :key="i" class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="mb-3 flex justify-between"><h4 class="font-bold text-slate-800">الدواء {{ i+1 }}</h4><button type="button" :disabled="!canEdit" @click="form.prescriptions.splice(i,1)" class="text-xs text-red-700">حذف الدواء</button></div>
                <div class="grid gap-3 md:grid-cols-2">
                  <label class="text-xs font-semibold">الاسم العلمي (INN) *<input v-model="med.generic_name" :disabled="!canEdit" maxlength="255" placeholder="يكتبه الطبيب" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2.5 font-normal"/></label>
                  <label class="text-xs font-semibold">التركيز (Strength) *<input v-model="med.strength" :disabled="!canEdit" maxlength="100" placeholder="المقدار والوحدة" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2.5 font-normal"/></label>
                  <label class="text-xs font-semibold">الشكل الدوائي *<input v-model="med.form" :disabled="!canEdit" maxlength="100" placeholder="قرص / شراب / حقنة..." class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2.5 font-normal"/></label>
                  <label class="text-xs font-semibold">جرعة المرة الواحدة *<input v-model="med.dose" :disabled="!canEdit" maxlength="100" placeholder="الكمية والوحدة لكل مرة" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2.5 font-normal"/></label>
                  <label class="text-xs font-semibold">طريقة الإعطاء *<select v-model="med.route" :disabled="!canEdit" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2.5 font-normal"><option value="">اختر الطريقة</option><option v-for="r in routes" :key="r" :value="r">{{ r }}</option></select></label>
                  <label class="text-xs font-semibold">التكرار / عدد المرات *<select v-model="med.frequency" :disabled="!canEdit" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2.5 font-normal"><option value="">اختر التكرار</option><option v-for="f in frequencies" :key="f" :value="f">{{ f }}</option></select></label>
                  <label class="text-xs font-semibold">مدة العلاج *<input v-model="med.duration" :disabled="!canEdit" maxlength="100" placeholder="مثال: 5 أيام (يحدده الطبيب)" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2.5 font-normal"/></label>
                  <label class="text-xs font-semibold">الكمية المصروفة *<input v-model="med.quantity" :disabled="!canEdit" maxlength="100" placeholder="عدد الأقراص / حجم العبوة" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2.5 font-normal"/></label>
                  <template v-if="med.frequency==='PRN'">
                    <label class="text-xs font-semibold">سبب الاستخدام عند الحاجة *<input v-model="med.prn_indication" :disabled="!canEdit" maxlength="255" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2.5"/></label>
                    <label class="text-xs font-semibold">أقصى جرعة في اليوم *<input v-model="med.max_daily_dose" :disabled="!canEdit" maxlength="100" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2.5"/></label>
                  </template>
                  <label class="text-xs font-semibold md:col-span-2">تعليمات الاستعمال والتحذيرات<input v-model="med.instructions" :disabled="!canEdit" maxlength="2000" placeholder="مع الطعام / قبل الطعام / تعليمات خاصة..." class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2.5 font-normal"/></label>
                </div>
              </div>
            </div>
            <div class="mt-4 grid gap-4">
              <label class="text-sm font-semibold text-slate-700">إرشادات للمريض / علامات تستلزم المراجعة
                <textarea v-model="form.patient_instructions" :disabled="!canEdit" rows="2" maxlength="8000" class="mt-1 w-full rounded-xl border border-slate-200 p-3 font-normal"></textarea>
              </label>
              <label class="max-w-xs text-sm font-semibold text-slate-700">موعد المراجعة القادمة<input v-model="form.follow_up_date" :disabled="!canEdit" type="date" class="mt-1 w-full rounded-xl border border-slate-200 p-3 font-normal"/></label>
            </div>
          </section>
          <div v-if="canEdit" class="sticky bottom-3 z-10 flex flex-wrap items-center justify-end gap-3 rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-lg backdrop-blur">
            <button type="submit" :disabled="busy" class="rounded-xl border border-teal-700 px-5 py-3 font-bold text-teal-800 disabled:opacity-50">{{ busy?'جاري الحفظ':'حفظ الكشفية' }}</button>
            <button type="button" :disabled="busy||isCompleted.value" @click="save(true)" class="rounded-xl bg-teal-700 px-5 py-3 font-bold text-white disabled:opacity-50">إكمال الكشفية</button>
          </div>
        </form>
        <aside class="space-y-4">
          <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="font-bold text-slate-900">زيارات المريض السابقة</h3>
            <p class="mt-1 text-xs text-slate-500">يُحفظ سجل مستقل لكل تاريخ زيارة.</p>
            <div v-for="item in history" :key="item.id" class="mt-3 border-t border-slate-100 pt-3">
              <router-link :to="'/doctor-portal/clinic/visits/'+item.id" class="block text-sm font-bold text-teal-700">كشفية {{ date(item.visit_date) }}</router-link>
              <small class="text-slate-500">الدور {{ item.queue_number }} · {{ statuses[item.status] }}</small>
            </div>
          </section>
          <section class="rounded-2xl border border-slate-200 bg-white p-4 text-xs leading-6 text-slate-600 shadow-sm">
            <h3 class="font-bold text-slate-900">سلامة التوثيق الطبي</h3>
            <p>دوّن الحساسية، الأدوية الحالية، التاريخ والفحص والتقييم. لا تعتمد على اقتراحات الأعراض كتشخيص. راجع الاسم العلمي والجرعة والتداخلات قبل إكمال الوصفة.</p>
            <p class="mt-2">عدد سجلات المراجعة بعد الإكمال: {{ revisions.length }}</p>
          </section>
        </aside>
      </div>

      <!-- Print-only clinical document. Vue-escaped text, no unsafe HTML injection. -->
      <article class="clinic-print hidden rounded-none bg-white p-4 text-black" :data-mode="printMode">
        <header class="border-b-2 border-black pb-4 text-center">
          <h1 class="text-2xl font-bold">{{ printMode==='prescription'?'الوصفة الطبية / Medical Prescription':'التقرير الطبي / Clinical Visit' }}</h1>
          <p class="mt-1 text-sm">الطبيب: {{ doctor.name || '—' }} · {{ date(visit.visit_date) }}</p>
          <p class="mt-1 text-xs">نسخة غير موقّعة إلكترونياً — {{ visit.status==='completed'?'كشفية مكتملة':'مسودة' }}</p>
        </header>
        <div class="my-5 grid grid-cols-2 gap-2 text-sm">
          <p><strong>المريض:</strong> {{ patient.name }}</p><p><strong>العمر:</strong> {{ visit.age_at_visit }} {{ visit.age_unit_at_visit==='months'?'شهر':'سنة' }}</p>
          <p><strong>رقم الهاتف:</strong> {{ patient.phone }}</p><p><strong>رقم الكشفية:</strong> {{ visit.id }}</p>
        </div>
        <div v-if="printMode==='clinical'" class="space-y-3 text-sm">
          <div><h2 class="border-b font-bold">الشكوى الرئيسية والأعراض / S</h2><p class="whitespace-pre-wrap">{{ form.chief_complaint || '—' }}</p><p>{{ form.symptoms.join('، ') }} · {{ form.symptom_duration }}</p></div>
          <div><h2 class="border-b font-bold">القصة المرضية والحساسية</h2><p class="whitespace-pre-wrap">{{ form.history_of_present_illness || '—' }}</p><p class="whitespace-pre-wrap">الحساسية: {{ form.allergies || 'غير موثّقة' }}</p><p class="whitespace-pre-wrap">العلاجات الحالية: {{ form.current_medications || 'غير موثّقة' }}</p></div>
          <div><h2 class="border-b font-bold">العلامات والفحص / O</h2><p>BP: {{ form.vitals.blood_pressure||'—' }} · HR: {{ form.vitals.heart_rate||'—' }} · Temp: {{ form.vitals.temperature_c||'—' }} · SpO₂: {{ form.vitals.spo2||'—' }}</p><p class="whitespace-pre-wrap">{{ form.physical_examination }}</p></div>
          <div><h2 class="border-b font-bold">التقييم / A</h2><p class="whitespace-pre-wrap">{{ form.assessment || '—' }}</p><p v-if="form.diagnosis_code">ICD-11: {{ form.diagnosis_code }}</p></div>
          <div><h2 class="border-b font-bold">الخطة / P</h2><p class="whitespace-pre-wrap">{{ form.plan || '—' }}</p></div>
          <div v-if="form.lab_requests.length"><h2 class="border-b font-bold">طلبات التحاليل</h2><p v-for="(item,i) in form.lab_requests" :key="i">{{ i+1 }}. {{ item.name }} — {{ item.notes }}</p></div>
          <div v-if="form.imaging_requests.length"><h2 class="border-b font-bold">طلبات الأشعة</h2><p v-for="(item,i) in form.imaging_requests" :key="i">{{ i+1 }}. {{ item.modality }} — {{ item.body_part }} — {{ item.notes }}</p></div>
        </div>
        <section v-if="printMode==='prescription'||form.prescriptions.length" class="mt-5">
          <h2 class="mb-3 border-b-2 border-black pb-1 text-lg font-bold">Rx — الوصفة الطبية</h2>
          <p v-if="!form.prescriptions.length" class="text-sm">لا توجد أدوية مدوّنة.</p>
          <div v-for="(med,i) in form.prescriptions" :key="i" class="mb-4 border-b border-slate-300 pb-3 text-sm">
            <p class="font-bold">{{ i+1 }}. {{ med.generic_name }} — {{ med.strength }} ({{ med.form }})</p>
            <p>الجرعة: {{ med.dose }} · الطريق: {{ med.route }} · التكرار: {{ med.frequency }}</p>
            <p>المدة: {{ med.duration }} · الكمية: {{ med.quantity }}</p>
            <p v-if="med.frequency==='PRN'">عند الحاجة: {{ med.prn_indication }} · الحد الأقصى باليوم: {{ med.max_daily_dose }}</p>
            <p v-if="med.instructions">التعليمات: {{ med.instructions }}</p>
          </div>
        </section>
        <div v-if="form.patient_instructions" class="mt-3 whitespace-pre-wrap text-sm">إرشادات: {{ form.patient_instructions }}</div>
        <p v-if="form.follow_up_date" class="mt-3 text-sm">تاريخ المراجعة: {{ form.follow_up_date }}</p>
        <footer class="mt-10 border-t pt-3 text-xs">اسم الطبيب: {{ doctor.name||'—' }} · التوقيع والختم: ____________________</footer>
      </article>
    </template>
  </main>
</template>

<style scoped>
@media print {
  @page { size: A4; margin: 14mm; }
  :global(body) { background:#fff!important; }
  :global(.sticky), :global(header.sticky), :global(nav), :global(aside) { display:none!important; }
  .screen-only { display:none!important; }
  main { padding:0!important; max-width:none!important; margin:0!important; }
  .clinic-print { display:block!important; font-family:Arial,sans-serif; font-size:12px; }
  .clinic-print section, .clinic-print div { break-inside:avoid; page-break-inside:avoid; }
}
</style>
