<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { storeToRefs } from 'pinia';
import { ActivityStore } from '@/store/modules/activityLogger';
import { eventLabels, sourceLabels, statusLabels, fieldLabel, auditValue } from '@/utils/activityDetails';
const store = ActivityStore();
const { records, pagination, stats, loading, error } = storeToRefs(store);
const filters = reactive({ search: '', invoice_id: '', causer_id: '', event: '', source: '', date_from: '', date_to: '', per_page: 25 });
const selected = ref(null), detailLoading = ref(false), detailError = ref(''), exporting = ref(false), exportError = ref(''), showSnapshots = ref(false), detailsPanel = ref(null);
let returnFocus = null, detailRequest = 0;
const params = () => Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '' && v != null));
const load = (page = 1) => store.GetRecords({ ...params(), page });
const reset = () => { Object.keys(filters).forEach(key => filters[key] = key === 'per_page' ? 25 : ''); load(); };
const date = value => value ? new Intl.DateTimeFormat('ar-IQ', { dateStyle: 'medium', timeStyle: 'medium', timeZone: 'Asia/Baghdad', numberingSystem: 'latn' }).format(new Date(value)) : '—';
const badge = event => ({ created: 'bg-emerald-50 text-emerald-700', updated: 'bg-blue-50 text-blue-700', deleted: 'bg-red-50 text-red-700', failed: 'bg-red-50 text-red-700', clicked: 'bg-violet-50 text-violet-700' }[event] || 'bg-slate-100 text-slate-600');
const shownChanges = computed(() => selected.value?.changes || []);
async function openDetails(record, event) {
  returnFocus = event?.currentTarget;
  const request = ++detailRequest;
  selected.value = record; detailLoading.value = true; detailError.value = ''; showSnapshots.value = false;
  await nextTick(); detailsPanel.value?.focus();
  try { const data = await store.detail(record.id); if (request === detailRequest) selected.value = data; }
  catch { if (request === detailRequest) detailError.value = 'تعذر تحميل التفاصيل. أغلق النافذة وأعد المحاولة.'; }
  finally { if (request === detailRequest) detailLoading.value = false; }
}
function closeDetails() { detailRequest++; selected.value = null; returnFocus?.focus(); }
function dialogKey(event) {
  if (event.key === 'Escape') closeDetails();
  if (event.key !== 'Tab') return;
  const nodes = [...detailsPanel.value.querySelectorAll('button, a, input, select, [tabindex="0"]')].filter(x => !x.disabled);
  const first = nodes[0], last = nodes.at(-1);
  if (event.shiftKey && (document.activeElement === first || document.activeElement === detailsPanel.value)) { event.preventDefault(); last?.focus(); }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
}
async function exportReport() {
  exporting.value = true; exportError.value = '';
  try { await store.exportReport(params()); } catch { exportError.value = 'تعذر تصدير التقرير. أعد المحاولة.'; }
  finally { exporting.value = false; }
}
onMounted(() => load());
onBeforeUnmount(() => { detailRequest++; });
</script>

<template>
  <main class="audit-page space-y-5" dir="rtl">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div><p class="text-sm font-medium text-blue-600">المتابعة والتدقيق</p><h1 class="mt-1 text-2xl font-bold text-slate-900">سجل النشاط</h1><p class="mt-2 text-sm text-slate-500">من نفّذ الإجراء؟ على أي فاتورة؟ وما الذي تغيّر؟ جميع الأوقات بتوقيت بغداد.</p></div>
      <div class="flex gap-2"><button class="audit-btn" :disabled="loading" @click="load(pagination.current_page)"><i class="pi pi-refresh" aria-hidden="true"></i> تحديث</button><button class="audit-btn audit-primary" :disabled="exporting || loading" @click="exportReport"><i class="pi pi-download" aria-hidden="true"></i> {{ exporting ? 'جاري التصدير…' : 'تصدير التقرير المفصّل' }}</button></div>
    </header>
    <p v-if="exportError" role="alert" class="audit-error">{{ exportError }}</p>
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="ملخص النتائج المطابقة للفلاتر">
      <div v-for="card in [{ key:'total', title:'جميع السجلات', icon:'pi-list' }, { key:'changed', title:'عمليات غيّرت البيانات', icon:'pi-pencil' }, { key:'failed', title:'طلبات لم تنجح', icon:'pi-exclamation-circle' }, { key:'clicks', title:'نقرات الأزرار', icon:'pi-mouse' }]" :key="card.key" class="rounded-xl border border-slate-200 bg-white p-4"><div class="flex items-center justify-between text-sm text-slate-500"><span>{{ card.title }}</span><i :class="['pi', card.icon]" aria-hidden="true"></i></div><p class="mt-3 text-2xl font-bold tabular-nums text-slate-800">{{ (stats[card.key] || 0).toLocaleString('en') }}</p></div>
    </section>
    <form class="rounded-xl border border-slate-200 bg-white p-4" @submit.prevent="load()">
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <label class="audit-field sm:col-span-2">بحث في السجل<input v-model="filters.search" type="search" placeholder="اسم الموظف، المريض، التحليل، الباركود أو تفاصيل التغيير" /></label>
        <label class="audit-field">رقم الفاتورة<input v-model="filters.invoice_id" type="number" min="1" placeholder="مثال: 1007046" /></label>
        <label class="audit-field">رقم المستخدم<input v-model="filters.causer_id" type="number" min="1" placeholder="كل المستخدمين" /></label>
        <label class="audit-field">نوع الحركة<select v-model="filters.event"><option value="">كل الحركات</option><option v-for="(label, value) in eventLabels" :value="value" :key="value">{{ label }}</option></select></label>
        <label class="audit-field">المصدر<select v-model="filters.source"><option value="">كل المصادر</option><option v-for="(label, value) in sourceLabels" :value="value" :key="value">{{ label }}</option></select></label>
        <label class="audit-field">من تاريخ<input v-model="filters.date_from" type="date" /></label>
        <label class="audit-field">إلى تاريخ<input v-model="filters.date_to" type="date" :min="filters.date_from" /></label>
      </div>
      <div class="mt-4 flex flex-wrap items-center justify-between gap-3"><p class="text-xs text-slate-500">نقرة الزر تعني بدء الإجراء؛ سجل النظام يوضح النتيجة والتغييرات المحفوظة.</p><div class="flex gap-2"><button type="button" class="audit-btn" @click="reset">مسح الفلاتر</button><button class="audit-btn audit-primary" :disabled="loading">تطبيق الفلاتر</button></div></div>
    </form>
    <p v-if="error" role="alert" class="audit-error">{{ error }}</p>
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white" :aria-busy="loading">
      <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3"><h2 class="font-semibold text-slate-800">تفاصيل الحركات</h2><span class="text-sm text-slate-500" role="status">{{ loading ? 'جاري تحميل السجل…' : `${pagination.total || 0} سجل مطابق` }}</span></div>
      <div class="overflow-x-auto">
        <table class="w-full min-w-[1000px] text-sm">
          <thead class="bg-slate-50 text-right text-xs text-slate-500"><tr><th>الوقت / المستخدم</th><th>الحركة</th><th>الفاتورة / المريض</th><th>ملخص التغيير</th><th>الحالة / المصدر</th><th><span class="sr-only">التفاصيل</span></th></tr></thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="!records.length && !loading"><td colspan="6" class="!py-14 text-center text-slate-500">لا توجد حركات مطابقة للفلاتر.</td></tr>
            <tr v-for="record in records" :key="record.id" :data-audit-invoice-id="record.invoice?.id" class="hover:bg-slate-50/70">
              <td><p class="whitespace-nowrap text-xs text-slate-500">{{ date(record.created_at) }}</p><p class="mt-1 font-semibold text-slate-800">{{ record.causer_name }}</p><p class="mt-1 text-xs text-slate-400">{{ record.causer_role || '—' }} · #{{ record.causer_id || '—' }}</p></td>
              <td><span class="rounded-md px-2 py-1 text-xs font-semibold" :class="badge(record.event)">{{ eventLabels[record.event] || record.log_name }}</span><p class="mt-2 max-w-56 break-words font-medium text-slate-700">{{ record.description }}</p><p v-if="record.button && record.source !== 'interface'" class="mt-1 text-xs text-slate-500">الزر: {{ record.button }}</p></td>
              <td><template v-if="record.invoice"><p class="font-bold text-blue-700">#{{ record.invoice.id }}</p><p class="mt-1 max-w-48 break-words text-slate-600">{{ record.invoice.patient_name }}</p><p class="mt-1 font-mono text-xs text-slate-400">{{ record.invoice.barcode }}</p></template><span v-else class="text-slate-400">{{ record.subject_label || '—' }}</span></td>
              <td class="max-w-72"><p v-for="change in record.changes.slice(0, 2)" :key="change.field" class="mb-1 truncate text-xs text-slate-600" :title="fieldLabel(change.label)">{{ fieldLabel(change.label) }}</p><p v-if="record.change_count" class="text-xs font-medium text-blue-600">{{ record.change_count }} حقل · عرض قبل وبعد</p><p v-else class="text-xs text-slate-400">{{ record.source === 'interface' ? 'نقرة مسجّلة؛ لا تؤكد تغيير البيانات' : record.source === 'legacy' ? 'التفاصيل بحسب السجل القديم' : 'لا يوجد تغيير محفوظ بالحقول' }}</p></td>
              <td><p class="text-xs font-semibold" :class="record.status === 'failed' ? 'text-red-600' : record.status === 'success' ? 'text-emerald-700' : 'text-slate-500'">{{ statusLabels[record.status] || record.status }}</p><p class="mt-1 text-xs text-slate-400">{{ sourceLabels[record.source] }}</p></td>
              <td><button class="audit-btn whitespace-nowrap" :aria-label="`تفاصيل السجل ${record.id}`" @click="openDetails(record, $event)">التفاصيل <i class="pi pi-angle-left" aria-hidden="true"></i></button></td>
            </tr>
          </tbody>
        </table>
      </div>
      <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-sm text-slate-500">
        <label class="flex items-center gap-2">عدد السجلات<select v-model="filters.per_page" class="rounded-lg border border-slate-200 p-1.5" @change="load()"><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select></label>
        <div class="flex items-center gap-3"><button class="audit-btn" :disabled="loading || pagination.current_page <= 1" @click="load(pagination.current_page - 1)">السابق</button><span>صفحة {{ pagination.current_page }} من {{ pagination.last_page }}</span><button class="audit-btn" :disabled="loading || pagination.current_page >= pagination.last_page" @click="load(pagination.current_page + 1)">التالي</button></div>
      </footer>
    </section>
    <Teleport to="body">
      <div v-if="selected" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/50 p-3 sm:p-6" dir="rtl" @click.self="closeDetails">
        <section ref="detailsPanel" role="dialog" aria-modal="true" aria-labelledby="audit-detail-title" tabindex="-1" class="flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl outline-none" @keydown="dialogKey">
          <header class="flex items-start justify-between gap-3 border-b border-slate-200 p-5"><div><p class="text-xs text-slate-400">سجل #{{ selected.id }} · {{ date(selected.created_at) }}</p><h2 id="audit-detail-title" class="mt-1 text-xl font-bold text-slate-900">{{ selected.description }}</h2><p class="mt-2 text-sm text-slate-500">{{ selected.causer_name }} · {{ statusLabels[selected.status] }}<span v-if="selected.invoice"> · الفاتورة #{{ selected.invoice.id }}</span></p></div><button class="audit-btn" aria-label="إغلاق التفاصيل" @click="closeDetails"><i class="pi pi-times" aria-hidden="true"></i></button></header>
          <div class="overflow-y-auto p-5">
            <p v-if="detailLoading" role="status" class="py-8 text-center text-slate-500">جاري تحميل التفاصيل…</p><p v-else-if="detailError" role="alert" class="audit-error">{{ detailError }}</p>
            <template v-else>
              <div class="mb-5 grid gap-3 rounded-xl bg-slate-50 p-4 text-sm sm:grid-cols-3"><div><span class="text-slate-400">المستخدم</span><p class="mt-1 font-medium">{{ selected.causer_name }} (#{{ selected.causer_id || '—' }})</p></div><div><span class="text-slate-400">المريض / الباركود</span><p class="mt-1 font-medium">{{ selected.invoice?.patient_name || '—' }}<br />{{ selected.invoice?.barcode }}</p></div><div><span class="text-slate-400">الزر / الصفحة</span><p class="mt-1 break-all font-medium">{{ selected.button || '—' }}<br /><span dir="ltr" class="text-xs">{{ selected.page || '—' }}</span></p></div></div>
              <h3 class="mb-3 font-bold text-slate-800">التفاصيل قبل وبعد <span class="text-sm font-normal text-slate-400">({{ selected.change_count }} حقل)</span></h3>
              <p v-if="selected.source === 'legacy'" class="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">هذا سجل سابق؛ تُعرض المعلومات التي كانت محفوظة وقت العملية. التفاصيل غير المسجّلة سابقاً لا يمكن استعادتها.</p>
              <div v-if="shownChanges.length" class="overflow-x-auto rounded-xl border border-slate-200"><table class="audit-compare w-full min-w-[650px] table-fixed text-sm"><thead><tr><th class="w-[36%] bg-slate-50 text-right">الحقل / التحليل</th><th class="w-[32%] bg-red-50 text-right text-red-700">شنو جان — قبل</th><th class="w-[32%] bg-emerald-50 text-right text-emerald-700">شنو صار — بعد</th></tr></thead><tbody><tr v-for="(change, index) in shownChanges" :key="index" class="border-t border-slate-100 align-top"><td class="break-words font-medium text-slate-700">{{ fieldLabel(change.label) }}</td><td class="bg-red-50/30"><pre class="whitespace-pre-wrap break-words font-sans" dir="auto">{{ auditValue(change.before) }}</pre></td><td class="bg-emerald-50/30"><pre class="whitespace-pre-wrap break-words font-sans" dir="auto">{{ auditValue(change.after) }}</pre></td></tr></tbody></table></div>
              <p v-else class="rounded-xl border border-dashed border-slate-200 p-8 text-center text-slate-500">{{ selected.source === 'interface' ? 'تم تسجيل الضغط على الزر. نتيجة التنفيذ تُسجّل بشكل مستقل من النظام.' : 'لم تُسجّل فروقات في حقول البيانات لهذه الحركة.' }}</p>
              <div v-if="selected.properties?.old_value || selected.properties?.new_value" class="mt-5"><button class="audit-btn" :aria-expanded="showSnapshots" @click="showSnapshots = !showSnapshots">{{ showSnapshots ? 'إخفاء' : 'عرض' }} البيانات الكاملة المحفوظة</button><div v-if="showSnapshots" class="mt-3 grid gap-3 lg:grid-cols-2"><div v-for="[key, title] in [['old_value', 'البيانات قبل العملية'], ['new_value', 'البيانات بعد العملية']]" :key="key" class="min-w-0 rounded-xl border border-slate-200 p-3"><h4 class="mb-2 font-semibold">{{ title }}</h4><pre class="max-h-80 overflow-auto whitespace-pre-wrap break-words text-xs" dir="ltr">{{ auditValue(selected.properties[key]) }}</pre></div></div></div>
            </template>
          </div>
        </section>
      </div>
    </Teleport>
  </main>
</template>

<style scoped>
.audit-btn { display:inline-flex;align-items:center;justify-content:center;gap:.5rem;min-height:40px;border:1px solid #e2e8f0;border-radius:9px;background:white;padding:.5rem .85rem;font-size:.875rem;font-weight:600;color:#475569; }
.audit-btn:hover { background:#f8fafc; }.audit-btn:focus-visible { outline:3px solid #93c5fd;outline-offset:2px; }.audit-btn:disabled { opacity:.5;cursor:not-allowed; }
.audit-primary { background:#2563eb;color:white;border-color:#2563eb; }.audit-primary:hover { background:#1d4ed8; }
.audit-field { display:flex;flex-direction:column;gap:.4rem;font-size:.8rem;font-weight:600;color:#475569; }.audit-field input,.audit-field select { width:100%;min-height:42px;border:1px solid #e2e8f0;border-radius:8px;padding:.55rem .7rem;color:#0f172a;background:white;font-weight:400; }
.audit-field input:focus,.audit-field select:focus { outline:2px solid #bfdbfe;outline-offset:1px; }
.audit-page th,.audit-page td,.audit-compare th,.audit-compare td { padding:.85rem 1rem; }.audit-error { border-radius:10px;background:#fef2f2;padding:12px;color:#b91c1c;font-size:.875rem; }
.audit-compare pre { overflow-wrap:anywhere; }
</style>
