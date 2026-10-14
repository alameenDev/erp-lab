<script setup>
import { computed, ref, nextTick, useId, onBeforeUnmount } from 'vue';
import { useLabSettingsStore } from '@/store/modules/labSettings';
import { $http } from '@/plugins/axios';
import { dateTimeFormat } from '@/utils/helper';
import { labelConfig, barcodePrintCss } from '@/utils/barcodeLabels';
import { barcodeCategories, barcodeCopies, categoryLabelCount, invoiceBarcodeLabels } from '@/utils/barcodeCategories';
import { usePrint } from '@/composables/usePrint';
import { useToast } from '@/composables/useToast';
import BarcodeLabel from './BarcodeLabel.vue';

const settingsStore = useLabSettingsStore();
const { printWithIframe } = usePrint();
const toast = useToast();
const printId = `barcode-labels-${useId()}`;
const titleId = `${printId}-title`;
const dialog = ref(null), record = ref(null), settings = ref({}), copies = ref(null);
const loading = ref(false), printing = ref(false), error = ref('');
const busy = computed(() => loading.value || printing.value);
const config = computed(() => labelConfig(settings.value.barcode_config, settings.value.show_tests_on_barcode !== false));
const categories = computed(() => barcodeCategories(record.value || {}));
const total = computed(() => categoryLabelCount(categories.value, copies.value || {}));
const labels = computed(() => invoiceBarcodeLabels(record.value, settings.value, categories.value, copies.value,
  record.value?.created_at ? dateTimeFormat(record.value.created_at) : ''));
const setCopies = (key, value) => { copies.value[key] = barcodeCopies(value); };
const resetCopies = () => { copies.value = Object.fromEntries(categories.value.map(category => [category.key, config.value.copies])); };
let request = 0, disposed = false;
onBeforeUnmount(() => { disposed = true; request++; dialog.value?.close(); });
function close() {
  if (printing.value) return;
  request++; loading.value = false;
  dialog.value?.close();
  record.value = null; copies.value = null; error.value = '';
}
async function print() {
  if (busy.value || !labels.value.length) return;
  printing.value = true; error.value = '';
  try {
    await nextTick();
    await printWithIframe(printId, barcodePrintCss(config.value), 'Print Barcode');
  } catch {
    error.value = 'تعذرت طباعة الباركود. حاول مرة أخرى.';
    if (!dialog.value?.open) toast.error(error.value);
  } finally { printing.value = false; }
}
async function open(invoice) {
  if (busy.value || dialog.value?.open) return;
  const version = ++request;
  loading.value = true; record.value = null; copies.value = null; error.value = '';
  try {
    await settingsStore.GetSettings();
    if (disposed || version !== request) return;
    settings.value = JSON.parse(JSON.stringify(settingsStore.settings));
    if (config.value.show_category_dialog) dialog.value?.showModal();
    // Use an isolated invoice snapshot; other report actions cannot change these labels.
    const { data } = await $http.get(`/invoices/${invoice.id}`);
    if (disposed || version !== request) return;
    record.value = data;
    if (!record.value?.barcode) throw new Error('missing barcode');
    loading.value = false;
    if (config.value.show_category_dialog) resetCopies();
    else await print();
  } catch {
    if (disposed || version !== request) return;
    record.value = null;
    error.value = 'تعذر تحميل بيانات الباركود. أغلق النافذة وحاول مرة أخرى.';
    if (!dialog.value?.open) toast.error(error.value);
  } finally { if (version === request) loading.value = false; }
}
defineExpose({ open, busy });
</script>
<template>
  <div class="hidden" :id="printId"><BarcodeLabel v-for="(data, index) in labels" :key="index" :config="config" :data="data" /></div>
  <Teleport to="body">
    <dialog ref="dialog" class="barcode-copy-dialog" dir="rtl" :aria-labelledby="titleId" @cancel.prevent="close" @click="event => { if (event.target === dialog) close(); }" @keydown.stop>
      <div class="flex max-h-[88dvh] flex-col">
        <header class="flex items-start justify-between gap-3 border-b border-slate-200 p-5">
          <div><h2 :id="titleId" class="text-lg font-bold text-slate-900">طباعة الباركود حسب الكاتگري</h2>
            <p class="mt-1 text-sm text-slate-500">حدد عدد النسخ لكل كاتگري. اختر صفر إذا ما تحتاج طباعته.</p>
            <p v-if="record" class="mt-2 text-sm font-semibold text-teal-800">{{ record.patient?.name }} <span class="mx-2 text-slate-300">|</span> رقم العينة: <bdi>{{ record.barcode }}</bdi></p>
          </div>
          <button type="button" aria-label="إغلاق" :disabled="printing" class="rounded-lg px-3 py-1 text-2xl text-slate-500 hover:bg-slate-100 disabled:opacity-40" @click="close">×</button>
        </header>
        <div class="min-h-0 space-y-4 overflow-y-auto p-5">
          <p v-if="loading" role="status" class="py-8 text-center text-slate-500">جاري تحميل الكاتگريات والتحاليل…</p>
          <p v-else-if="error" role="alert" class="rounded-xl bg-red-50 p-4 text-red-700">{{ error }}</p>
          <template v-else>
            <p v-if="!categories.length" role="status" class="rounded-xl bg-slate-50 p-4 text-slate-600">لا توجد تحاليل في هذه الفاتورة لطباعة الباركود.</p>
            <section v-for="category in categories" :key="category.key" :data-barcode-category="category.key" class="rounded-xl border border-slate-200 p-4" :class="copies?.[category.key] === 0 ? 'bg-slate-50' : 'bg-white'">
              <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="font-bold text-slate-800">{{ category.name }}</h3>
                <div class="flex items-center gap-2">
                  <span class="text-xs text-slate-500">عدد النسخ</span>
                  <div class="flex items-center rounded-lg border border-slate-300 bg-white" dir="ltr">
                    <button type="button" class="h-10 w-10 rounded-l-lg text-xl text-teal-800 hover:bg-teal-50 disabled:opacity-30" :aria-label="`تقليل نسخ ${category.name}`" :disabled="printing || !copies?.[category.key]" @click="setCopies(category.key, copies[category.key] - 1)">−</button>
                    <input type="number" min="0" max="20" step="1" :aria-label="`عدد نسخ ${category.name}`" :disabled="printing" class="h-10 w-14 border-x border-y-0 border-slate-200 px-1 text-center font-bold focus:ring-2 focus:ring-teal-600" :value="copies?.[category.key] ?? 0" @input="setCopies(category.key, $event.target.value)" @change="$event.target.value = copies[category.key]">
                    <button type="button" class="h-10 w-10 rounded-r-lg text-xl text-teal-800 hover:bg-teal-50 disabled:opacity-30" :aria-label="`زيادة نسخ ${category.name}`" :disabled="printing || copies?.[category.key] >= 20" @click="setCopies(category.key, copies[category.key] + 1)">+</button>
                  </div>
                </div>
              </div>
              <div v-for="sample in category.samples" :key="sample.sampleName" class="mt-3 border-t border-slate-100 pt-3">
                <p class="mb-2 text-xs font-medium text-slate-500">نوع العينة: <bdi>{{ sample.sampleName === 'Other' ? 'غير محدد' : sample.sampleName }}</bdi></p>
                <ul class="flex flex-wrap gap-2"><li v-for="analysis in sample.analyses" :key="analysis.key" class="max-w-full break-words rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-700"><bdi>{{ analysis.name }}</bdi><span v-if="analysis.shortcut && analysis.shortcut !== analysis.name" class="ms-2 text-xs text-teal-700" dir="ltr">{{ analysis.shortcut }}</span></li></ul>
              </div>
              <p v-if="category.samples.length > 1" class="mt-3 text-xs leading-5 text-amber-800">يحتوي هذا الكاتگري على {{ category.samples.length }} أنواع عينات؛ العدد المختار يُطبع لكل نوع عينة بشكل منفصل ({{ (copies?.[category.key] || 0) * category.samples.length }} ملصق).</p>
            </section>
          </template>
        </div>
        <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 p-5">
          <div aria-live="polite" class="text-sm text-slate-700">إجمالي الملصقات: <strong class="text-lg text-teal-800">{{ total }}</strong></div>
          <div class="flex flex-wrap gap-2">
            <button type="button" :disabled="busy || !categories.length" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-600 disabled:opacity-40" @click="resetCopies">إعادة العدد الافتراضي</button>
            <button type="button" :disabled="printing" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700 disabled:opacity-40" @click="close">إلغاء</button>
            <button type="button" :disabled="busy || !total || !!error" class="rounded-lg bg-teal-700 px-5 py-2 text-sm font-bold text-white hover:bg-teal-800 disabled:opacity-40" @click="print">{{ printing ? 'جاري الطباعة…' : 'طباعة الباركود' }}</button>
          </div>
        </footer>
      </div>
    </dialog>
  </Teleport>
</template>
<style scoped>
.barcode-copy-dialog { width: min(760px, calc(100vw - 24px)); max-width: none; margin: auto; padding: 0; border: 1px solid #e2e8f0; border-radius: 18px; background: white; box-shadow: 0 24px 80px #0f172a40; }
.barcode-copy-dialog::backdrop { background: #0f172a88; }
.barcode-copy-dialog input[type=number] { appearance: textfield; -moz-appearance: textfield; }
.barcode-copy-dialog input::-webkit-inner-spin-button, .barcode-copy-dialog input::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
</style>
