<script setup>
import {ref, computed, onMounted, onBeforeUnmount, nextTick} from 'vue';
import {$http} from '@/plugins/axios';
import {usePrint} from '@/composables/usePrint';
import {ageUnits, registerAge, registerAmount, registerEntryAmount, workRegisterPrint} from '@/utils/workRegister';

const report = ref(null), selectedDate = ref(''), followToday = ref(true);
const loading = ref(false), saving = ref(false), printing = ref(false), error = ref(''), formError = ref('');
const draft = ref(null), editDialog = ref(null);
const {printWithCustomContent} = usePrint();
const busy = computed(() => loading.value || saving.value || printing.value);
let requestId = 0, timer;
const apply = data => { report.value = data; selectedDate.value = data.date; };
const message = err => Object.values(err.response?.data?.errors || {}).flat()[0] || err.response?.data?.message || 'تعذر الاتصال. حاول مجدداً.';

async function load(today = followToday.value, quiet = false) {
  const id = ++requestId;
  if (!quiet) loading.value = true;
  try {
    const {data} = await $http.get('/work-register', {params: today ? {} : {date: selectedDate.value}});
    if (id !== requestId) return false;
    apply(data); followToday.value = today; error.value = '';
    return true;
  } catch (err) { if (id === requestId) error.value = message(err); return false; }
  finally { if (id === requestId) loading.value = false; }
}
async function changeDate() {
  if (!selectedDate.value) return;
  followToday.value = selectedDate.value === report.value?.today;
  await load(followToday.value);
}
async function edit(row) {
  ++requestId; // A refresh already in flight must not advance the draft's version.
  draft.value = {...row, tests: row.tests.join('\n'), groups: row.groups.join('\n'), packages: row.packages.join('\n')};
  formError.value = '';
  await nextTick(); editDialog.value.showModal();
}
function closeEditor(event) {
  if (saving.value) { event?.preventDefault(); return; }
  editDialog.value?.close(); draft.value = null;
}
async function mutate(row, fields, remove = false) {
  saving.value = true; error.value = ''; formError.value = '';
  ++requestId; // Ignore any older background refresh response.
  try {
    const data = {date: report.value.date, version: report.value.version, ...fields};
    const response = remove ? await $http.delete(`/work-register/${row.id}`, {data}) : await $http.patch(`/work-register/${row.id}`, data);
    apply(response.data);
    return true;
  } catch (err) {
    const text = err.response?.status === 409 ? 'تغيّر السجل من جهاز آخر أو أُضيفت فاتورة. أغلق التعديل وحدّث السجل ثم حاول مجدداً.' : message(err);
    if (draft.value) formError.value = text; else error.value = text;
    return false;
  } finally { saving.value = false; }
}
async function save() {
  const row = draft.value;
  const fields = {patient_name: row.patient_name.trim(), age: row.age === '' ? null : row.age, age_unit: row.age_unit,
    referral_doctor: row.referral_doctor, referral_lab: row.referral_lab, work_register_amount: row.work_register_amount === '' ? null : row.work_register_amount, position: row.position};
  for (const key of ['tests', 'groups', 'packages']) fields[key] = row[key].split('\n').map(text => text.trim()).filter(Boolean);
  if (await mutate(row, fields)) closeEditor();
}
async function remove(row) {
  if (window.confirm(`حذف «${row.patient_name}» من سجل هذا اليوم؟ تبقى الفاتورة وبيانات المريض محفوظة.`)) await mutate(row, {}, true);
}
async function print() {
  const wasFollowingToday = followToday.value;
  printing.value = true;
  try {
    // Refresh the selected day so the printout includes every saved visit and total.
    if (!await load(false)) return;
    const {content, css} = workRegisterPrint(report.value);
    await printWithCustomContent(content, css, `سجل العمل ${report.value.date}`);
  } catch (err) { error.value = message(err); }
  finally { followToday.value = wasFollowingToday; printing.value = false; }
}
function refreshCurrentDay() {
  if (followToday.value && !busy.value && !draft.value && document.visibilityState === 'visible') load(true, true);
}
onMounted(() => {
  load(true);
  timer = setInterval(refreshCurrentDay, 30000);
  document.addEventListener('visibilitychange', refreshCurrentDay);
});
onBeforeUnmount(() => { ++requestId; clearInterval(timer); document.removeEventListener('visibilitychange', refreshCurrentDay); });
</script>

<template>
  <main dir="rtl" class="space-y-5" data-work-register>
    <header class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-6">
      <div><RouterLink to="/patients" class="text-sm text-primary-600">← العودة إلى المرضى</RouterLink><h1 class="mt-2 text-2xl font-bold text-slate-900">سجل العمل</h1><p class="mt-2 text-sm text-slate-500">تُضاف الزيارة تلقائياً عند إنشاء الفاتورة. كل يوم محفوظ بسجل مستقل.</p></div>
      <button class="register-primary" :disabled="busy || !report?.entries.length || !!draft || !!error" @click="print"><i class="pi pi-print" aria-hidden="true"></i> {{ printing ? 'جارٍ تجهيز الطباعة…' : 'طباعة سجل اليوم المحدد' }}</button>
    </header>
    <section class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-5">
      <label class="space-y-2"><span class="block text-sm font-semibold">تاريخ العمل</span><input v-model="selectedDate" aria-label="تاريخ العمل" type="date" class="register-input" :disabled="busy || !!draft" @change="changeDate"></label>
      <button class="register-secondary" :disabled="busy || !!draft" @click="load(true)">اليوم</button>
      <button class="register-secondary" :disabled="busy || !!draft" @click="load()">{{ loading ? 'جارٍ التحميل…' : 'تحديث' }}</button>
      <p class="mr-auto text-xs text-slate-500">يبدأ اليوم الجديد الساعة 12 ليلاً بتوقيت بغداد، وتبقى الأيام السابقة محفوظة.</p>
    </section>
    <p class="rounded-xl border border-teal-100 bg-teal-50 px-4 py-3 text-sm text-teal-800">المبلغ المدفوع في السجل يُدخل يدوياً من الفاتورة أو من هنا. لا يُنسخ السعر الأصلي أو المدفوعات العادية، ولا يتأثر أي تقرير مالي بتعديله.</p>
    <p v-if="error" role="alert" class="rounded-xl bg-red-50 p-4 text-red-700">{{ error }}</p>
    <template v-if="report">
      <section class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="register-stat"><span>عدد الزيارات</span><strong data-total-count>{{ report.totals.count }}</strong></div>
        <div class="register-stat"><span>إجمالي المدفوع في السجل · د.ع</span><strong class="text-primary-600" data-total-paid>{{ registerAmount(report.totals.work_register_amount) }}</strong></div>
      </section>
      <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white" :aria-busy="loading">
        <div class="border-b border-slate-100 p-4"><h2 class="font-bold">{{ report.lab_name }} · {{ report.date }}</h2></div>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[1060px] text-right text-sm">
            <thead class="bg-slate-50 text-slate-600"><tr><th>ت</th><th>اسم المريض الكامل</th><th>العمر</th><th>الإحالة</th><th class="w-[30%]">التحاليل والكروبات والباقات</th><th>المدفوع في السجل · د.ع</th><th v-if="report.permissions.edit || report.permissions.delete">الإجراءات</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="row in report.entries" :key="row.id" data-work-row class="align-top hover:bg-slate-50/60">
                <td><span class="font-bold text-slate-700">{{ row.position }}</span><div v-if="report.permissions.edit" class="mt-2 flex gap-1"><button :aria-label="`رفع ترتيب ${row.patient_name}`" title="رفع" class="order-button" :disabled="busy || row.position === 1" @click="mutate(row, {position: row.position - 1})">↑</button><button :aria-label="`خفض ترتيب ${row.patient_name}`" title="خفض" class="order-button" :disabled="busy || row.position === report.entries.length" @click="mutate(row, {position: row.position + 1})">↓</button></div></td>
                <td class="font-semibold">{{ row.patient_name }}</td><td class="whitespace-nowrap">{{ registerAge(row) }}</td>
                <td><div v-if="row.referral_doctor">طبيب: {{ row.referral_doctor }}</div><div v-if="row.referral_lab">مختبر: {{ row.referral_lab }}</div><span v-if="!row.referral_doctor && !row.referral_lab" class="text-slate-400">مراجعة مباشرة</span></td>
                <td><div v-for="[label, key] in [['تحاليل', 'tests'], ['كروبات', 'groups'], ['باقات', 'packages']]" :key="key"><p v-if="row[key].length" class="mb-1 leading-6"><b class="text-slate-500">{{ label }}:</b> <span dir="auto">{{ row[key].join('، ') }}</span></p></div></td>
                <td class="whitespace-nowrap font-semibold text-primary-700" data-register-amount>{{ registerEntryAmount(row.work_register_amount) }}</td>
                <td v-if="report.permissions.edit || report.permissions.delete"><div class="flex gap-2"><button v-if="report.permissions.edit" class="text-primary-700" :disabled="busy" @click="edit(row)">تعديل</button><button v-if="report.permissions.delete" class="text-red-600" :disabled="busy" @click="remove(row)">حذف</button></div></td>
              </tr>
            </tbody>
            <tfoot v-if="report.entries.length" class="border-t bg-slate-50 font-bold"><tr><td colspan="5">المجموع الكلي</td><td dir="ltr">{{ registerAmount(report.totals.work_register_amount) }}</td><td v-if="report.permissions.edit || report.permissions.delete"></td></tr></tfoot>
          </table>
        </div>
        <div v-if="!report.entries.length" class="px-5 py-14 text-center"><h3 class="font-semibold text-slate-700">لا توجد زيارات مسجّلة لهذا اليوم</h3><p class="mt-2 text-sm text-slate-500">تظهر الزيارة بعد إنشاء فاتورتها، أو اختر تاريخاً آخر لعرض سجل سابق.</p></div>
      </section>
    </template>
    <dialog ref="editDialog" class="register-dialog" aria-labelledby="work-edit-title" @cancel="closeEditor">
      <form v-if="draft" class="space-y-5 p-6" @submit.prevent="save">
        <div class="flex items-center justify-between gap-3"><h2 id="work-edit-title" class="text-xl font-bold">تعديل سطر سجل العمل</h2><button type="button" aria-label="إغلاق التعديل" :disabled="saving" @click="closeEditor">✕</button></div>
        <p class="text-sm text-slate-500">هذه التعديلات تخص السجل فقط.</p>
        <p v-if="formError" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ formError }}</p>
        <fieldset :disabled="saving" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <label class="sm:col-span-2">اسم المريض الكامل<input v-model="draft.patient_name" class="register-input mt-1 w-full" required maxlength="255"></label>
          <label>العمر<input v-model.number="draft.age" class="register-input mt-1 w-full" type="number" min="0" max="100000" step="1"></label>
          <label>وحدة العمر<select v-model="draft.age_unit" class="register-input mt-1 w-full"><option v-for="(label, unit) in ageUnits" :key="unit" :value="unit">{{ label }}</option></select></label>
          <label>الطبيب المحيل<input v-model="draft.referral_doctor" class="register-input mt-1 w-full" maxlength="255"></label>
          <label>المختبر المحيل<input v-model="draft.referral_lab" class="register-input mt-1 w-full" maxlength="255"></label>
          <label v-for="[label, key] in [['التحاليل', 'tests'], ['الكروبات', 'groups'], ['الباقات', 'packages']]" :key="key" class="sm:col-span-2">{{ label }}<textarea v-model="draft[key]" class="register-input mt-1 w-full" rows="2" placeholder="كل اسم بسطر مستقل"></textarea></label>
          <label>المدفوع في السجل · د.ع<input v-model.number="draft.work_register_amount" class="register-input mt-1 w-full" type="number" min="0" max="1000000000000" step="1" placeholder="غير مدخل"></label>
          <label>الترتيب<input v-model.number="draft.position" class="register-input mt-1 w-full" type="number" required min="1" :max="report?.entries.length" step="1"></label>
        </fieldset>
        <div class="flex justify-end gap-3"><button type="button" class="register-secondary" :disabled="saving" @click="closeEditor">إلغاء</button><button class="register-primary" type="submit" :disabled="saving">{{ saving ? 'جارٍ الحفظ…' : 'حفظ التعديل' }}</button></div>
      </form>
    </dialog>
  </main>
</template>

<style scoped>
.register-primary,.register-secondary{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;border-radius:.75rem;padding:.7rem 1rem;font-size:.875rem;font-weight:600}.register-primary{background:var(--color-primary-600,#0d9488);color:white}.register-secondary{border:1px solid #dbe3ec;background:white;color:#334155}.register-input{border:1px solid #cbd5e1;border-radius:.65rem;padding:.65rem .8rem;background:white;color:#1e293b;min-height:42px}.register-input:focus{outline:2px solid #5eead4;outline-offset:1px}.register-stat{border:1px solid #e2e8f0;border-radius:1rem;background:white;padding:1.2rem}.register-stat span{display:block;font-size:.85rem;color:#64748b}.register-stat strong{display:block;font-size:1.8rem;margin-top:.5rem}th,td{padding:.9rem .75rem}button:disabled{opacity:.45;cursor:not-allowed}.order-button{width:25px;height:25px;border:1px solid #e2e8f0;border-radius:6px;background:white}.register-dialog{width:min(700px,calc(100vw - 24px));max-height:90vh;overflow:auto;border:0;border-radius:1rem;padding:0;color:#1e293b;box-shadow:0 20px 70px #0f172a33}.register-dialog::backdrop{background:#0f172a80}.register-dialog label{font-size:.875rem;font-weight:500}
</style>
