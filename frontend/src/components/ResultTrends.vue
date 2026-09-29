<script setup>
import { computed, ref } from 'vue';
import Dialog from 'primevue/dialog';
import { Line } from 'vue-chartjs';
import { Chart as ChartJS, LinearScale, PointElement, LineElement, Tooltip, Legend } from 'chart.js';
import { $http } from '@/plugins/axios';
ChartJS.register(LinearScale, PointElement, LineElement, Tooltip, Legend);
const props = defineProps({ endpoint: String, disabled: Boolean, staff: Boolean });
const visible = ref(false), loading = ref(false), error = ref(''), series = ref([]), selected = ref(''), from = ref(''), to = ref('');
let generation = 0;
const current = computed(() => series.value.find(x => x.key === selected.value));
const points = computed(() => (current.value?.points || []).filter(p => (!from.value || p.date.slice(0, 10) >= from.value) && (!to.value || p.date.slice(0, 10) <= to.value)));
const numeric = computed(() => points.value.filter(p => p.number !== null));
const formatDate = value => new Date(value).toLocaleDateString('ar-IQ');
function normalRange(ranges) {
  return (Array.isArray(ranges) ? ranges : []).map(range => {
    if (typeof range === 'string' || typeof range === 'number') return String(range);
    if (!range || typeof range !== 'object') return '';
    const bounds = [range.from ?? '—', range.to ?? '—'].join(' – ');
    const options = (Array.isArray(range.test_reference_options) ? range.test_reference_options : []).filter(x => typeof x === 'string' || typeof x === 'number').join('، ');
    const value = range.notes || (range.from != null || range.to != null ? bounds : options);
    const conditions = [range.gender && !['Both', 'both'].includes(range.gender) ? range.gender : '', range.age_from != null || range.age_to != null ? `${range.age_from ?? '—'} – ${range.age_to ?? '—'} ${range.age_unit || ''}` : ''].filter(Boolean).join('، ');
    return value ? String(value) + (conditions ? ` (${conditions})` : '') : '';
  }).filter(Boolean).join('\n') || 'غير مسجل';
}
const latestPoint = computed(() => points.value.at(-1));
const chartData = computed(() => ({ datasets: [{ label: [current.value?.name, current.value?.field, current.value?.unit].filter(Boolean).join(' · '), data: points.value.map(p => ({ x: new Date(p.date.replace(' ', 'T')).getTime(), y: p.number })), borderColor: '#0f766e', backgroundColor: '#0f766e', pointRadius: 5, pointHoverRadius: 7, tension: 0, spanGaps: false }] }));
const options = { responsive: true, maintainAspectRatio: false, parsing: false, scales: { x: { type: 'linear', ticks: { maxTicksLimit: 6, callback: value => formatDate(value) }, title: { display: true, text: 'التاريخ' } }, y: { title: { display: true, text: 'النتيجة' } } }, plugins: { legend: { display: false }, tooltip: { callbacks: { title: items => items.length ? formatDate(items[0].parsed.x) : '' } } } };
async function load() {
  const ticket = ++generation;
  loading.value = true; error.value = ''; series.value = []; selected.value = ''; from.value = ''; to.value = '';
  try {
    const { data } = await $http.get(props.endpoint);
    if (ticket !== generation) return;
    series.value = data.series || []; selected.value = series.value[0]?.key || '';
  } catch (e) { if (ticket === generation) error.value = e.response?.data?.message || 'تعذر تحميل سجل النتائج'; }
  finally { if (ticket === generation) loading.value = false; }
}
function open() { visible.value = true; load(); }
</script>

<template>
  <button type="button" :disabled="disabled || !endpoint" @click="open" class="rounded-xl border border-teal-200 bg-white px-3 py-2 text-sm font-semibold text-teal-700 hover:bg-teal-50 disabled:opacity-40">مخطط النتائج</button>
  <Dialog v-model:visible="visible" modal header="تطور نتائج التحاليل حسب التاريخ" :style="{ width: '950px', maxWidth: '95vw' }" :dismissable-mask="true" @hide="generation++" dir="rtl">
    <p class="mb-4 text-sm text-slate-500">{{ staff ? 'يعرض النتائج المحفوظة والمكتملة. احفظ النتيجة وأكمل الفحص لتظهر في السجل.' : 'يعرض نتائج التقارير المعتمدة فقط.' }} تُعرض كل وحدة قياس ومختبر بصورة مستقلة.</p>
    <p v-if="loading" role="status" class="py-12 text-center">جاري تحميل النتائج...</p>
    <div v-else-if="error" role="alert" class="rounded-xl bg-red-50 p-4 text-red-700">{{ error }} <button type="button" @click="load" class="underline">إعادة المحاولة</button></div>
    <p v-else-if="!series.length" class="py-12 text-center text-slate-500">لا توجد نتائج مكتملة محفوظة لهذا المريض حتى الآن.</p>
    <template v-else>
      <div class="mb-5 grid gap-3 sm:grid-cols-4">
        <label class="text-sm sm:col-span-2">التحليل<select v-model="selected" class="mt-1 w-full rounded-lg border border-slate-300 p-2"><option v-for="item in series" :key="item.key" :value="item.key">{{ [item.name, item.field, item.unit || 'بدون وحدة', item.lab].filter(Boolean).join(' · ') }}</option></select></label>
        <label class="text-sm">من تاريخ<input v-model="from" type="date" :max="to || undefined" class="mt-1 w-full rounded-lg border border-slate-300 p-2" /></label>
        <label class="text-sm">إلى تاريخ<input v-model="to" type="date" :min="from || undefined" class="mt-1 w-full rounded-lg border border-slate-300 p-2" /></label>
      </div>
      <p v-if="!points.length" class="p-6 text-center text-slate-500">لا توجد نتائج ضمن الفترة المحددة.</p>
      <template v-else>
        <div class="mb-4 grid gap-3 rounded-xl border border-teal-100 bg-teal-50 p-4 sm:grid-cols-3">
          <div><span class="block text-xs text-teal-700">الوحدة</span><strong>{{ current.unit || 'غير مسجلة' }}</strong></div>
          <div class="sm:col-span-2"><span class="block text-xs text-teal-700">المجال الطبيعي (Normal Range) · أحدث قراءة ضمن الفترة</span><p class="mt-1 whitespace-pre-line text-sm font-semibold text-slate-800">{{ normalRange(latestPoint?.ranges) }}</p><small v-if="latestPoint?.range_source === 'current'" class="text-slate-500">حسب إعدادات التحليل الحالية؛ لم يُحفظ مجال تاريخي لهذه القراءة.</small></div>
        </div>
        <div v-if="numeric.length" class="h-72 rounded-xl border border-slate-100 p-3" dir="ltr"><Line :data="chartData" :options="options" aria-label="مخطط تغير النتيجة عبر الزمن؛ القراءات متوفرة في الجدول أدناه" /></div>
        <p class="my-3 text-xs text-slate-500">{{ numeric.length === 1 ? 'توجد قراءة رقمية واحدة؛ تحتاج قراءتين على الأقل لمعرفة اتجاه التغير.' : !numeric.length ? 'هذه النتائج وصفية؛ تُعرض في الجدول ولا تُحوّل إلى أرقام.' : 'ارتفاع الخط وانخفاضه يوضح تغير القيمة بين القراءات.' }} لا تُرسم النتائج الوصفية أو القيم مثل &lt;5 و&gt;100 كنقاط رقمية.</p>
        <div class="max-h-72 overflow-auto rounded-xl border border-slate-200"><table class="w-full text-right text-sm"><thead class="sticky top-0 bg-slate-50"><tr><th class="p-3">التاريخ</th><th class="p-3">النتيجة</th><th class="p-3">الوحدة</th><th class="p-3">المجال الطبيعي</th><th class="p-3">الفاتورة</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="(point, i) in [...points].reverse()" :key="i"><td class="p-3">{{ formatDate(point.date) }}<small v-if="point.date_source === 'registration'" class="block text-slate-400">تاريخ التسجيل؛ تاريخ النتيجة غير مسجل</small></td><td class="p-3 font-semibold" dir="auto">{{ point.value }}</td><td class="p-3">{{ current.unit || '—' }}</td><td class="min-w-[180px] p-3"><span class="whitespace-pre-line">{{ normalRange(point.ranges) }}</span><small v-if="point.range_source === 'current' && point.ranges?.length" class="block text-slate-400">الإعدادات الحالية</small></td><td class="p-3">#{{ point.invoice_id }}</td></tr></tbody></table></div>
      </template>
    </template>
  </Dialog>
</template>
