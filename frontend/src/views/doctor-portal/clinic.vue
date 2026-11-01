<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { $http } from '@/plugins/axios';

const router = useRouter();
const selectedDate = ref(new Date().toLocaleDateString('sv-SE', {timeZone:'Asia/Baghdad'}));
const visits = ref([]);
const stats = ref({});
const patients = ref([]);
const patientSearch = ref('');
const patientPage = ref(1);
const patientLastPage = ref(1);
const currentPatient = ref(null);
const newPatient = ref({name:'', age_value:null, age_unit:'years', phone:'', gender:'unknown', notes:''});
const bookingTime = ref('');
const busy = ref(false);
const loading = ref(false);
const error = ref('');
const success = ref('');
const isAddOpen = ref(false);
const statusLabel = {waiting:'بانتظار الدخول',in_consultation:'داخل العيادة',completed:'تمت الكشفية',cancelled:'ملغاة'};
const openQueue = computed(() => visits.value.filter(v=>!['cancelled','completed'].includes(v.status)));
const formatTime = value => value ? String(value).slice(0,5) : 'حسب الدور';

function message(e) {
  return e.response?.data?.errors ? Object.values(e.response.data.errors).flat().join('، ')
    : e.response?.data?.message || 'تعذر إكمال العملية. حاول مرة أخرى.';
}
async function loadQueue() {
  loading.value=true;error.value='';
  try {
    const {data}=await $http.get('/doctor-portal/clinic/visits',{params:{date:selectedDate.value}});
    visits.value=data.visits || [];
    stats.value=data.stats || {};
  } catch(e){error.value=message(e)}
  finally {loading.value=false}
}
async function loadPatients() {
  try {
    const {data}=await $http.get('/doctor-portal/clinic/patients',{params:{search:patientSearch.value.trim(),page:patientPage.value}});
    patients.value=data.data || []; patientLastPage.value=data.last_page || 1;
  }catch(e){error.value=message(e)}
}
async function registerPatient() {
  if(busy.value)return;
  busy.value=true;error.value='';success.value='';
  try {
    const {data}=await $http.post('/doctor-portal/clinic/patients',newPatient.value);
    currentPatient.value=data;
    isAddOpen.value=false;
    newPatient.value={name:'',age_value:null,age_unit:'years',phone:'',gender:'unknown',notes:''};
    success.value='تم تسجيل المريض. اضغط إضافة إلى الدور لتسجيل الزيارة.';
    await loadPatients();
  }catch(e){error.value=message(e)}
  finally{busy.value=false}
}
async function bookPatient(patient) {
  if(busy.value)return;
  busy.value=true;error.value='';success.value='';
  try {
    const {data}=await $http.post('/doctor-portal/clinic/visits',{
      clinic_patient_id_fk:patient.id,visit_date:selectedDate.value,
      appointment_time:bookingTime.value || null
    });
    success.value='تم تسجيل الزيارة وإصدار رقم الدور '+data.queue_number;
    currentPatient.value=null;bookingTime.value='';
    await loadQueue();
  }catch(e){error.value=message(e)}
  finally{busy.value=false}
}
async function updateStatus(visit,status) {
  if(busy.value)return;
  busy.value=true;error.value='';
  try{
    await $http.put('/doctor-portal/clinic/visits/'+visit.id,{status});
    await loadQueue();
  }catch(e){error.value=message(e)}
  finally{busy.value=false}
}
function choosePatient(patient){currentPatient.value=patient;isAddOpen.value=false;window.scrollTo({top:0,behavior:'smooth'})}
watch(selectedDate,loadQueue);
onMounted(()=>{loadQueue();loadPatients()});
</script>

<template>
  <main class="mx-auto max-w-7xl space-y-5 px-4 py-6 pb-20" dir="rtl">
    <header class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-sm font-semibold text-teal-700">بوابة الطبيب / إدارة العيادة</p>
        <h1 class="mt-1 text-2xl font-black text-slate-900">الحجوزات والكشفيات</h1>
        <p class="mt-1 text-sm text-slate-500">سجّل المرضى، تابع ترتيب الدخول، واحتفظ بتاريخ زيارة مستقل لكل كشفية.</p>
      </div>
      <button type="button" @click="isAddOpen=!isAddOpen;currentPatient=null" class="rounded-xl bg-teal-700 px-5 py-3 font-bold text-white hover:bg-teal-800">+ تسجيل مريض جديد</button>
    </header>

    <p v-if="error" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
    <p v-if="success" role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ success }}</p>

    <section v-if="isAddOpen" class="rounded-2xl border border-teal-200 bg-white p-5 shadow-sm">
      <h2 class="mb-4 text-lg font-bold text-slate-900">إضافة مريض إلى ملف العيادة</h2>
      <form @submit.prevent="registerPatient" class="grid gap-4 md:grid-cols-3">
        <label class="text-sm font-semibold text-slate-700">اسم المريض الكامل *
          <input v-model.trim="newPatient.name" required maxlength="255" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5" placeholder="الاسم الثلاثي أو الرباعي"/>
        </label>
        <label class="text-sm font-semibold text-slate-700">رقم الهاتف *
          <input v-model.trim="newPatient.phone" required maxlength="24" type="tel" inputmode="tel" dir="ltr" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5" placeholder="07xxxxxxxxx"/>
        </label>
        <div class="flex items-end gap-2">
          <label class="min-w-0 flex-1 text-sm font-semibold text-slate-700">العمر *
            <input v-model.number="newPatient.age_value" required type="number" min="0" :max="newPatient.age_unit==='years'?130:1560" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5"/>
          </label>
          <select v-model="newPatient.age_unit" class="rounded-xl border border-slate-200 px-3 py-2.5"><option value="years">سنة</option><option value="months">شهر</option></select>
        </div>
        <label class="text-sm font-semibold text-slate-700">الجنس (اختياري)
          <select v-model="newPatient.gender" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5"><option value="unknown">غير مذكور</option><option value="male">ذكر</option><option value="female">أنثى</option><option value="other">آخر</option></select>
        </label>
        <label class="text-sm font-semibold text-slate-700 md:col-span-2">ملاحظات الاستقبال (اختياري)
          <input v-model="newPatient.notes" maxlength="3000" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5" placeholder="مثل جهة الإحالة أو ملاحظات الحجز"/>
        </label>
        <div class="flex justify-end gap-2 md:col-span-3">
          <button type="button" @click="isAddOpen=false" class="rounded-xl border border-slate-200 px-5 py-2.5">إلغاء</button>
          <button type="submit" :disabled="busy" class="rounded-xl bg-teal-700 px-6 py-2.5 font-semibold text-white disabled:opacity-50">حفظ المريض</button>
        </div>
      </form>
    </section>

    <section v-if="currentPatient" class="flex flex-wrap items-end justify-between gap-4 rounded-2xl border border-sky-200 bg-sky-50 p-5">
      <div>
        <h2 class="font-bold text-slate-900">تسجيل زيارة: {{ currentPatient.name }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ currentPatient.phone }} · {{ currentPatient.age_value }} {{ currentPatient.age_unit==='months'?'شهر':'سنة' }}</p>
        <p class="mt-2 text-xs text-slate-600">يُخصص رقم دور جديد تلقائياً لكل يوم، ويمكن تسجيل زيارات متعددة لنفس المريض.</p>
      </div>
      <div class="flex flex-wrap items-end gap-2">
        <label class="text-xs text-slate-700">وقت الموعد (اختياري)<input v-model="bookingTime" type="time" class="mt-1 block rounded-lg border border-slate-200 bg-white px-3 py-2" /></label>
        <button type="button" :disabled="busy" @click="bookPatient(currentPatient)" class="rounded-xl bg-teal-700 px-5 py-3 font-bold text-white disabled:opacity-50">إضافة إلى الدور</button>
        <button type="button" @click="currentPatient=null" class="rounded-xl border border-slate-300 px-4 py-3">إلغاء</button>
      </div>
    </section>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
      <div v-for="metric in [{label:'بانتظار الدخول',value:stats.waiting||0},{label:'داخل العيادة',value:stats.in_consultation||0},{label:'تمت الكشفية',value:stats.completed||0},{label:'مجموع الزيارات',value:visits.length}]" :key="metric.label" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-sm text-slate-500">{{ metric.label }}</p><strong class="mt-2 block text-3xl text-teal-800">{{ metric.value }}</strong>
      </div>
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5">
        <div><h2 class="text-lg font-bold text-slate-900">جدول الدور</h2><p class="mt-1 text-sm text-slate-500">{{ openQueue.length }} بانتظار الكشفية أو داخل العيادة</p></div>
        <label class="text-sm text-slate-600">تاريخ الزيارة <input v-model="selectedDate" type="date" class="mr-2 rounded-lg border border-slate-200 px-3 py-2" /></label>
      </div>
      <div v-if="loading" class="p-8 text-center text-slate-500">جاري تحميل جدول الدور...</div>
      <div v-else-if="!visits.length" class="p-10 text-center text-slate-500">لا توجد حجوزات في هذا اليوم.</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-right text-sm">
          <thead class="bg-slate-50 text-slate-600"><tr><th class="p-4">رقم الدور</th><th class="p-4">المريض</th><th class="p-4">الموعد</th><th class="p-4">الحالة</th><th class="p-4">الإجراء</th></tr></thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="visit in visits" :key="visit.id" class="hover:bg-slate-50/60">
              <td class="p-4"><span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-teal-50 text-lg font-black text-teal-700">{{ visit.queue_number }}</span></td>
              <td class="p-4"><strong class="block text-slate-900">{{ visit.patient?.name }}</strong><span class="text-xs text-slate-500">{{ visit.patient?.phone }}</span></td>
              <td class="p-4 text-slate-600">{{ formatTime(visit.appointment_time) }}</td>
              <td class="p-4"><span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">{{ statusLabel[visit.status] }}</span></td>
              <td class="flex flex-wrap gap-2 p-4">
                <button v-if="visit.status==='waiting'" type="button" :disabled="busy" @click="updateStatus(visit,'in_consultation')" class="rounded-lg border border-sky-200 px-3 py-2 font-semibold text-sky-700">إدخال المريض</button>
                <button v-if="visit.status==='waiting'" type="button" :disabled="busy" @click="updateStatus(visit,'cancelled')" class="rounded-lg border border-red-200 px-3 py-2 text-red-700">إلغاء</button>
                <button type="button" @click="router.push('/doctor-portal/clinic/visits/'+visit.id)" class="rounded-lg bg-teal-700 px-3 py-2 font-bold text-white">{{ visit.status==='completed'?'عرض الكشفية':'فتح الكشفية' }}</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5">
        <h2 class="font-bold text-slate-900">سجل مرضى العيادة</h2>
        <form class="flex gap-2" @submit.prevent="patientPage=1;loadPatients()">
          <input v-model="patientSearch" class="min-w-[150px] rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="بحث بالاسم أو الهاتف"/>
          <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white">بحث</button>
        </form>
      </div>
      <div v-if="!patients.length" class="p-8 text-center text-sm text-slate-500">لم يتم تسجيل مرضى بعد.</div>
      <div v-for="patient in patients" :key="patient.id" class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <div><strong class="block text-slate-900">{{ patient.name }}</strong><span class="text-xs text-slate-500">{{ patient.phone }} · {{ patient.age_value }} {{ patient.age_unit==='months'?'شهر':'سنة' }} · {{ patient.visits_count }} زيارات</span></div>
        <div class="flex gap-2">
          <button type="button" @click="choosePatient(patient)" class="rounded-lg border border-teal-300 px-3 py-2 text-sm font-semibold text-teal-800">حجز زيارة</button>
          <button type="button" @click="router.push('/doctor-portal/clinic/visits/'+(visits.find(v=>v.clinic_patient_id_fk===patient.id)?.id||''))" v-if="visits.some(v=>v.clinic_patient_id_fk===patient.id)" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">كشفية اليوم</button>
        </div>
      </div>
      <div v-if="patientLastPage>1" class="flex justify-between p-4 text-sm"><button type="button" :disabled="patientPage===1" @click="patientPage--;loadPatients()">السابق</button><span>{{ patientPage }} / {{ patientLastPage }}</span><button type="button" :disabled="patientPage>=patientLastPage" @click="patientPage++;loadPatients()">التالي</button></div>
    </section>
  </main>
</template>
