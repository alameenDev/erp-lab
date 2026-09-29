<script setup>
import { computed, ref, watch } from 'vue';
import { $http } from '@/plugins/axios';

const props = defineProps({ patientId: [Number, String], selected: { type: Array, default: () => [] } });
const emit = defineEmits(['add']);
const invoice = ref(null);
const catalog = ref({ test: [], culture: [], package: [], testGroup: [] });
const checked = ref([]);
const loading = ref(false);
const error = ref('');
let request = 0;
const key = item => `${item.type}:${item.id}`;
const labels = { test: 'تحليل', culture: 'زرع', package: 'باقة', testGroup: 'كروب' };
const money = value => Number(value || 0).toLocaleString('ar-IQ');
const rows = computed(() => (invoice.value?.items || []).map(item => ({
  ...item,
  current: !item.deleted && catalog.value[item.type]?.find(x => Number(x.id) === Number(item.id)),
  added: props.selected.some(x => x.type === item.type && Number(x.id) === Number(item.id)),
})));
const available = computed(() => rows.value.filter(x => x.current && !x.added));
const chosen = computed(() => available.value.filter(x => checked.value.includes(key(x))));
async function load() {
  const ticket = ++request;
  invoice.value = null; checked.value = []; error.value = ''; loading.value = false;
  catalog.value = { test: [], culture: [], package: [], testGroup: [] };
  if (!props.patientId) return;
  loading.value = true;
  try {
    const { data } = await $http.get(`/invoices/patient-latest/${props.patientId}`);
    if (ticket !== request) return;
    invoice.value = data.invoice;
    if (!data.invoice) return;
    const results = await Promise.allSettled([
      $http.get('/tests', { params: { include_groups: 1 } }),
      $http.get('/cultures'), $http.get('/packages'),
    ]);
    if (ticket !== request) return;
    const list = result => result.status === 'fulfilled' ? (Array.isArray(result.value.data) ? result.value.data : result.value.data?.data || []) : [];
    const tests = list(results[0]);
    catalog.value = { test: tests.filter(x => x.type !== 'group'), testGroup: tests.filter(x => x.type === 'group'), culture: list(results[1]), package: list(results[2]) };
    if (results.some(x => x.status === 'rejected')) error.value = 'تعذر تحميل بعض الفحوصات الحالية. أعد المحاولة لتفعيل إضافتها.';
  } catch { if (ticket === request) error.value = 'تعذر تحميل الفاتورة السابقة. يمكنك متابعة إنشاء الفاتورة أو إعادة المحاولة.'; }
  finally { if (ticket === request) loading.value = false; }
}
function add(items) {
  for (const item of items) emit('add', item.type, JSON.parse(JSON.stringify(item.current)));
  checked.value = [];
}
watch(() => props.patientId, load, { immediate: true });
</script>

<template>
  <section v-if="patientId && (loading || error || invoice)" class="overflow-hidden rounded-2xl border border-teal-200 bg-white shadow-sm" aria-label="الفاتورة السابقة للمريض" :aria-busy="loading">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-teal-100 bg-teal-50 px-5 py-4">
      <div><h2 class="font-bold text-teal-900">آخر فاتورة للمريض</h2><p class="mt-1 text-xs text-teal-700">للتذكير وإعادة طلب الفحوصات بسرعة</p></div>
      <span v-if="invoice" class="rounded-full bg-white px-3 py-1 text-xs text-slate-600">{{ invoice.is_done ? 'النتائج مكتملة' : 'قيد الفحص' }}</span>
    </div>
    <p v-if="loading" role="status" class="p-5 text-sm text-slate-500">جاري تحميل تفاصيل الفاتورة السابقة...</p>
    <div v-if="error" role="alert" class="flex flex-wrap items-center gap-3 p-5 text-sm text-amber-800">{{ error }}<button type="button" @click="load" :disabled="loading" class="font-semibold underline">إعادة المحاولة</button></div>
    <template v-if="invoice && !loading">
      <div class="grid grid-cols-2 gap-4 border-b border-slate-100 px-5 py-4 text-sm sm:grid-cols-5">
        <div><span class="block text-xs text-slate-500">رقم الفاتورة</span><strong>#{{ invoice.id }}</strong></div>
        <div><span class="block text-xs text-slate-500">التاريخ</span><strong>{{ invoice.date ? new Date(invoice.date).toLocaleDateString('ar-IQ') : '—' }}</strong></div>
        <div><span class="block text-xs text-slate-500">الإجمالي السابق</span><strong>{{ money(invoice.total) }}</strong></div>
        <div><span class="block text-xs text-slate-500">المدفوع</span><strong>{{ money(invoice.paid) }}</strong></div>
        <div><span class="block text-xs text-slate-500">المتبقي</span><strong>{{ money(Math.max(0, Number(invoice.total || 0) - Number(invoice.paid || 0))) }}</strong></div>
      </div>
      <div class="overflow-x-auto"><table class="w-full text-start text-sm">
        <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="p-3 text-start">اختيار</th><th class="p-3 text-start">الفحص</th><th class="p-3 text-start">النوع</th><th class="p-3 text-start">السعر السابق</th><th class="p-3 text-start">الإضافة</th></tr></thead>
        <tbody class="divide-y divide-slate-100"><tr v-for="(item, index) in rows" :key="key(item) + ':' + index">
          <td class="p-3"><input v-model="checked" type="checkbox" :value="key(item)" :disabled="!item.current || item.added" :aria-label="'اختيار ' + item.name" /></td>
          <th class="p-3 text-start font-medium text-slate-800">{{ item.name }}</th><td class="p-3 text-slate-500">{{ labels[item.type] }}</td><td class="p-3 text-slate-500">{{ money(item.price) }}</td>
          <td class="p-3"><span v-if="item.added" class="text-xs text-teal-700">مضاف للفاتورة</span><button v-else-if="item.current" type="button" @click="add([item])" class="rounded-lg border border-teal-200 px-3 py-2 text-xs font-semibold text-teal-700">إضافة</button><span v-else class="text-xs text-slate-400">غير متاح حالياً</span></td>
        </tr></tbody>
      </table></div>
      <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4">
        <p class="text-xs text-slate-500">تُضاف الفحوصات بالأسعار الحالية، بدون نسخ النتائج أو دفعات الفاتورة السابقة.</p>
        <div class="flex flex-wrap gap-2"><button type="button" :disabled="!chosen.length" @click="add(chosen)" class="rounded-lg border border-teal-300 px-4 py-2 text-sm text-teal-700 disabled:opacity-40">إضافة المحدد ({{ chosen.length }})</button><button type="button" :disabled="!available.length" @click="add(available)" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40">إضافة جميع الفحوصات المتاحة</button></div>
      </div>
    </template>
  </section>
</template>
