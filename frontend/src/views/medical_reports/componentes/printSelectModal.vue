<script setup>
import { computed, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { useLabSettingsStore } from "@/store/modules/labSettings";
import { reportFormChoice } from "@/utils/medicalReportOutput";
import { useinvoicesStore } from "@/store/modules/invoices";

import PatientDebtWarning from '@/components/PatientDebtWarning.vue';

import ReportSelectionNode from './ReportSelectionNode.vue';
import { reportSelectionModel, reportNodeKeys, searchReportNodes } from '@/utils/reportItemSelection';

const props = defineProps({
  modelValue: Boolean,
  canShare: { type: Boolean, default: false },
  hasBackground: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
});
const emit = defineEmits(["update:modelValue", "execute"]);
const { printRecord } = storeToRefs(useinvoicesStore());
const debtChecking = ref(false);
const search = ref("");
const action = ref("print");
const withBackground = ref(null);
const labSettings = useLabSettingsStore();
const formContext = computed(() => JSON.stringify([printRecord.value?.id, labSettings.settings.lab_id_fk,
  reportFormChoice(labSettings.settings.report_form_default), props.hasBackground]));
let lastFormContext;
function initializeFormChoice() {
  if (lastFormContext === formContext.value) return;
  lastFormContext = formContext.value;
  withBackground.value = props.hasBackground && reportFormChoice(labSettings.settings.report_form_default);
}
watch(formContext, () => { if (props.modelValue && !props.busy) initializeFormChoice(); });
const phone = ref("");
const sharing = value => ["whatsapp", "whatsapp-download"].includes(value);
const phoneValid = computed(() => phone.value.replace(/\D/g, "").length >= 10);
const selected = ref(new Set());
// Freeze the dialog's source while preview/export temporarily filters printRecord.
const snapshot = ref({});
const selectionModel = computed(() => reportSelectionModel(snapshot.value));
const sections = computed(() => selectionModel.value.sections);
const allKeys = computed(() => selectionModel.value.keys);
const selectedCount = computed(() => allKeys.value.filter(k => selected.value.has(k)).length);
const visibleSections = computed(() => sections.value.map(section => ({ ...section,
  visible: searchReportNodes(section.nodes, search.value),
})).filter(section => section.visible.length));
const sectionKeys = section => section.nodes.flatMap(reportNodeKeys);
const sectionCount = section => sectionKeys(section).filter(k => selected.value.has(k)).length;

watch(() => props.modelValue, open => {
  if (!open) return;
  snapshot.value = JSON.parse(JSON.stringify(printRecord.value || {}));
  search.value = "";
  action.value = "print";
  // Keep manual choices for this report; a new invoice/lab/default starts fresh.
  initializeFormChoice();
  phone.value = printRecord.value?.patient?.phone || "";
  selected.value = new Set(allKeys.value);
}, { immediate: true });
function toggle(keys) {
  if (props.busy) return;
  const next = new Set(selected.value);
  const remove = keys.every(key => next.has(key));
  keys.forEach(key => remove ? next.delete(key) : next.add(key));
  selected.value = next;
}
function toggleSection(section) { toggle(sectionKeys(section)); }
function close() { if (!props.busy) emit("update:modelValue", false); }
function execute(nextAction = action.value) {
  if (props.busy || debtChecking.value || !selectedCount.value || (sharing(nextAction) && (!phoneValid.value || !props.canShare))) return;
  const chosen = key => sections.value.find(section => section.key === key).nodes.flatMap((node, i) => reportNodeKeys(node).some(k => selected.value.has(k)) ? [i] : []);
  emit("execute", {
    tests: chosen("tests"), cultures: chosen("cultures"), testGroups: chosen("test_groups"), packages: chosen("packages"),
    selectedKeys: [...selected.value],
    action: nextAction, withBackground: props.hasBackground && Boolean(withBackground.value), phone: phone.value.trim(),
  });
}
</script>

<template>
  <Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 p-3 sm:p-6" dir="rtl" :data-audit-invoice-id="printRecord?.id" role="dialog" aria-modal="true" aria-labelledby="report-dialog-title" @keydown.esc="close">
      <div class="absolute inset-0" @click="close"></div>
      <div class="relative flex max-h-[94vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 sm:px-7">
          <div><h2 id="report-dialog-title" class="text-lg font-bold text-slate-900">اختيار عناصر التقرير الطبي</h2><p class="text-sm text-slate-500">{{ selectedCount }} / {{ allKeys.length }} عنصر محدد</p></div>
          <button type="button" class="rounded-lg px-3 py-1 text-2xl text-slate-500 hover:bg-slate-100" aria-label="إغلاق" @click="close">×</button>
        </div>
        <div class="overflow-y-auto px-5 py-5 sm:px-7">
          <div class="mb-5 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm sm:grid-cols-4">
            <div><span class="block text-slate-500">المريض</span><strong>{{ printRecord?.patient?.name || '—' }}</strong><span class="block text-xs text-slate-500">{{ printRecord?.patient?.age }} {{ printRecord?.patient?.age_unit }} · {{ printRecord?.patient?.gender }}</span></div>
            <div><span class="block text-slate-500">رقم المريض</span><strong>{{ printRecord?.patient?.code || '—' }}</strong></div>
            <div><span class="block text-slate-500">الباركود</span><strong>{{ printRecord?.barcode || '—' }}</strong></div>
            <div><span class="block text-slate-500">تاريخ التسجيل</span><strong>{{ printRecord?.registration_date ? new Date(printRecord.registration_date).toLocaleString('ar-IQ') : '—' }}</strong></div>
          </div>
          <PatientDebtWarning :invoice="printRecord" :active="modelValue" @checking="debtChecking = $event" />
          <div class="mb-3 flex flex-wrap gap-2">
            <input v-model="search" type="search" placeholder="بحث في التحاليل والكروبات والباقات..." aria-label="بحث في عناصر التقرير" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-4 py-2.5 text-sm outline-none focus:border-blue-500" />
            <button type="button" :disabled="busy" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white" @click="selected = new Set(allKeys)">تحديد الكل</button>
            <button type="button" :disabled="busy" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700" @click="selected = new Set()">إلغاء تحديد الكل</button>
          </div>
          <p class="mb-3 text-sm text-slate-600">كل العناصر محددة تلقائياً. أزل علامة الصح عن التحاليل التي لا تريد إظهارها؛ اختيار الكروب أو الباقة يشمل كل التحاليل بداخلها.</p>
          <div class="max-h-[40vh] overflow-y-auto rounded-xl border border-slate-200">
            <template v-for="section in visibleSections" :key="section.key">
              <div class="flex items-center justify-between bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700">
                <span>{{ section.label }} ({{ sectionCount(section) }}/{{ sectionKeys(section).length }})</span>
                <button type="button" :disabled="busy" class="text-blue-700" @click="toggleSection(section)">{{ sectionCount(section) === sectionKeys(section).length ? 'إلغاء تحديد القسم' : 'تحديد القسم' }}</button>
              </div>
              <ReportSelectionNode v-for="node in section.visible" :key="node.key" :node="node" :selected="selected" :busy="busy" @toggle="toggle" />
            </template>
            <p v-if="!visibleSections.length" class="p-6 text-center text-sm text-slate-500">لا توجد عناصر تطابق البحث.</p>
          </div>
          <h3 class="mb-3 mt-5 text-sm font-bold text-slate-800">طريقة الإخراج</h3>
          <div class="grid gap-2 sm:grid-cols-3 lg:grid-cols-5">
            <label v-for="option in [{ key:'print', title:'طباعة مباشرة', hint:'إرسال التقرير للطابعة' }, { key:'download', title:'حفظ PDF', hint:'تنزيل التقرير كملف' }, { key:'whatsapp', title:'إرسال واتساب', hint:'رابط البوابة وملف PDF للإرفاق' }, { key:'print-download', title:'طباعة + حفظ', hint:'طباعة وتنزيل PDF' }, { key:'whatsapp-download', title:'حفظ + إرسال', hint:'حفظ PDF وفتح واتساب' }]" :key="option.key" class="cursor-pointer rounded-xl border p-3 text-sm" :class="[action === option.key ? 'border-blue-600 bg-blue-50' : 'border-slate-200', sharing(option.key) && !canShare ? 'opacity-50' : '']">
              <input v-model="action" type="radio" name="report-action" :value="option.key" :disabled="sharing(option.key) && !canShare" class="accent-blue-600" /><strong class="ms-1">{{ option.title }}</strong><small class="mt-1 block text-slate-500">{{ option.hint }}</small>
            </label>
          </div>
          <div class="mt-4 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
            <label v-if="sharing(action)" class="text-sm font-medium text-slate-700">رقم هاتف المريض المسجل<input :value="phone" type="tel" dir="ltr" readonly class="mt-1 block w-full rounded-lg border border-slate-200 bg-slate-100 px-3 py-2" /></label>
            <label v-if="hasBackground" class="flex items-center gap-2 text-sm text-slate-700"><input v-model="withBackground" type="checkbox" class="accent-blue-600" /> الطباعة على فورمة المختبر</label>
            <p class="text-xs text-slate-500 sm:col-span-2">تظهر العناصر المحددة فقط في المعاينة والطباعة والحفظ وملف الواتساب. تُسجّل الحالة بعد تنفيذ الإجراء. الفورمة والتنسيق المحددان يُطبّقان على المعاينة والطباعة والحفظ وملف الواتساب بنفس الشكل.</p>
            <p v-if="sharing(action)" class="text-xs text-slate-500 sm:col-span-2">يُنزل ملف PDF بالعناصر المحددة وتُفتح محادثة الرقم المسجل. عند اختيار جزء من التقرير لا يُضاف رابط البوابة الذي يعرض باقي النتائج. أرفق الملف بالمحادثة واضغط إرسال؛ واتساب لا يرفق الملفات تلقائياً من رابط wa.me.</p>
          </div>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-white px-5 py-4 sm:px-7">
          <button type="button" class="rounded-lg border border-slate-200 px-5 py-2.5 text-sm" @click="close">إلغاء</button>
          <div class="flex gap-2">
            <button type="button" :disabled="!selectedCount || busy || debtChecking" class="rounded-lg border border-blue-600 px-5 py-2.5 text-sm font-semibold text-blue-700 disabled:opacity-50" @click="execute('preview')">معاينة التقرير</button>
            <button type="button" :disabled="!selectedCount || busy || debtChecking || (sharing(action) && (!phoneValid || !canShare))" class="rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white disabled:opacity-50" @click="execute()">{{ busy ? 'جاري تجهيز التقرير...' : 'تنفيذ' }}</button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
