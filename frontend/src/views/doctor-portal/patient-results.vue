<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { $http } from '@/plugins/axios';

const route = useRoute();
const patient = ref(null);
const invoices = ref([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(false);
const error = ref('');
const date = value => value ? new Date(value).toLocaleDateString('ar-IQ') : '—';
const shown = value => value !== null && value !== undefined && value !== '' ? value : '—';
function normal(ranges) {
  return (ranges || []).map(range => {
    if (typeof range === 'string') return range;
    const value = range.notes || [range.from, range.to].filter(v => v !== null && v !== undefined && v !== '').join(' – ');
    const details = [range.gender && range.gender !== 'Both' ? range.gender : '', range.age_from != null || range.age_to != null ? [range.age_from, range.age_to].filter(v => v != null).join('–') + ' ' + (range.age_unit || '') : ''].filter(Boolean);
    return value ? value + (details.length ? ' (' + details.join('، ') + ')' : '') : '';
  }).filter(Boolean).join('؛ ') || '—';
}
async function load() {
  loading.value = true; error.value = '';
  try {
    const { data } = await $http.get(`/doctor-portal/patients/${route.params.id}`, { params: { page: page.value } });
    patient.value = data.patient;
    invoices.value = data.invoices?.data || [];
    lastPage.value = data.invoices?.last_page || 1;
    total.value = data.invoices?.total || 0;
  } catch (e) { error.value = e.response?.status === 404 ? 'هذا المريض غير موجود ضمن إحالاتك.' : e.response?.data?.message || 'تعذر تحميل النتائج'; }
  finally { loading.value = false; }
}
function changePage(next) { if (next < 1 || next > lastPage.value) return; page.value = next; load(); }
onMounted(load);
</script>

<template>
  <main class="mx-auto max-w-7xl space-y-5 px-4 py-7 pb-16" dir="rtl">
    <router-link to="/doctor-portal" class="text-sm font-semibold text-teal-700">→ العودة إلى المرضى</router-link>
    <p v-if="error" role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ error }}</p>
    <section v-if="patient" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <p class="text-xs font-semibold text-teal-700">الملف الطبي / إحالات الطبيب</p>
      <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ patient.name }}</h1>
      <div class="mt-4 flex flex-wrap gap-x-7 gap-y-2 text-sm text-slate-600"><span>رقم المريض: <strong>{{ patient.code || '—' }}</strong></span><span>العمر: <strong>{{ patient.age ?? '—' }} {{ patient.age_unit || '' }}</strong></span><span>الجنس: <strong>{{ patient.gender || '—' }}</strong></span><span>عدد الفواتير: <strong>{{ total }}</strong></span></div>
    </section>
    <p v-if="loading" class="rounded-xl bg-white p-10 text-center text-slate-500">جاري تحميل النتائج...</p>
    <p v-else-if="patient && !invoices.length" class="rounded-xl bg-white p-10 text-center text-slate-500">لا توجد فواتير مسجلة لهذا المريض ضمن إحالاتك.</p>

    <section v-for="invoice in invoices" :key="invoice.id" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/70 p-5">
        <div><h2 class="font-bold text-slate-900">فاتورة #{{ invoice.id }} · {{ invoice.barcode }}</h2><p class="mt-1 text-xs text-slate-500">{{ invoice.lab_name || 'المختبر' }} · تسجيل {{ date(invoice.registration_date || invoice.created_at) }} · النتيجة {{ date(invoice.result_date) }}</p></div>
        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="invoice.is_done ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">{{ invoice.is_done ? 'النتائج مكتملة' : 'قيد الفحص' }}</span>
      </div>
      <div v-for="(group, g) in invoice.groups" :key="g" class="border-b border-slate-100 last:border-0">
        <div class="flex items-center gap-2 bg-teal-50/40 px-5 py-2 text-sm font-bold text-slate-800"><span>{{ group.name }}</span><span v-if="group.kind === 'package' || group.kind === 'group'" class="rounded bg-white px-2 py-0.5 text-[11px] text-teal-700">{{ group.kind === 'package' ? 'باقة' : 'كروب' }}</span></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[860px] text-right text-sm">
          <thead class="bg-slate-50 text-xs text-slate-600"><tr><th class="p-3">التحليل</th><th class="p-3">النتيجة</th><th class="p-3">المجال الطبيعي</th><th class="p-3">الوحدة</th><th class="p-3">الحالة / الملاحظات</th></tr></thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="(row, i) in group.rows" :key="i"><th class="p-3 font-medium text-slate-800">{{ row.name }}</th><td class="p-3 font-semibold" :class="row.ready ? 'text-slate-900' : 'text-slate-400'">{{ row.ready ? shown(row.result) : 'قيد الفحص' }}</td><td class="max-w-xs whitespace-pre-line p-3 text-xs text-slate-600">{{ normal(row.ranges) }}</td><td class="p-3 text-slate-600" dir="auto">{{ row.unit || '—' }}</td><td class="p-3 text-xs text-slate-600"><span v-if="row.ready">{{ row.status || '—' }}<span v-if="row.comment" class="mt-1 block">{{ row.comment }}</span></span><span v-else>لم تعتمد النتيجة بعد</span></td></tr>
            <tr v-if="!group.rows.length"><td colspan="5" class="p-4 text-center text-xs text-slate-500">لا توجد نتائج مفصلة لهذا العنصر بعد.</td></tr>
          </tbody>
        </table></div>
      </div>
    </section>
    <div v-if="lastPage > 1" class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-5 py-4 text-sm">
      <button type="button" :disabled="page <= 1 || loading" @click="changePage(page - 1)" class="rounded-lg border border-slate-200 px-4 py-2 disabled:opacity-40">السابق</button><span>{{ page }} / {{ lastPage }}</span><button type="button" :disabled="page >= lastPage || loading" @click="changePage(page + 1)" class="rounded-lg border border-slate-200 px-4 py-2 disabled:opacity-40">التالي</button>
    </div>
  </main>
</template>
