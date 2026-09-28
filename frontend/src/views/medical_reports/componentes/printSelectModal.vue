<script setup>
import { computed, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { useinvoicesStore } from "@/store/modules/invoices";

const props = defineProps({
  modelValue: Boolean,
  canShare: { type: Boolean, default: false },
  hasBackground: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
});
const emit = defineEmits(["update:modelValue", "execute"]);
const { printRecord } = storeToRefs(useinvoicesStore());
const search = ref("");
const action = ref("print");
const withBackground = ref(false);
const phone = ref("");
const phoneValid = computed(() => phone.value.replace(/\D/g, "").length >= 10);
const selected = ref(new Set());
const sections = computed(() => [
  { key: "tests", label: "التحاليل", items: printRecord.value?.tests || [] },
  { key: "cultures", label: "الزروع", items: printRecord.value?.cultures || [] },
  { key: "testGroups", label: "الكروبات", items: printRecord.value?.test_groups || [] },
  { key: "packages", label: "الباقات", items: printRecord.value?.packages || [] },
]);
const keyFor = (section, index) => `${section}:${index}`;
const allKeys = computed(() => sections.value.flatMap(section => section.items.map((_, index) => keyFor(section.key, index))));
const selectedCount = computed(() => selected.value.size);
const itemName = item => item.report_name || item.group_name || item.name || "فحص";
const details = item => [...(item.tests || []), ...(item.cultures || [])].map(itemName).filter(Boolean).join("، ");
const visibleSections = computed(() => sections.value.map(section => ({
  ...section,
  visible: section.items.map((item, index) => ({ item, index })).filter(({ item }) =>
    !search.value.trim() || `${itemName(item)} ${details(item)}`.toLocaleLowerCase().includes(search.value.trim().toLocaleLowerCase())),
})).filter(section => section.visible.length));

watch(() => props.modelValue, open => {
  if (!open) return;
  search.value = "";
  action.value = "print";
  withBackground.value = Boolean(props.hasBackground);
  phone.value = printRecord.value?.patient?.phone || "";
  selected.value = new Set(allKeys.value);
});
function toggle(key) {
  const next = new Set(selected.value);
  if (next.has(key)) next.delete(key); else next.add(key);
  selected.value = next;
}
function toggleSection(section) {
  const keys = section.visible.map(({ index }) => keyFor(section.key, index));
  const next = new Set(selected.value);
  const remove = keys.every(key => next.has(key));
  keys.forEach(key => remove ? next.delete(key) : next.add(key));
  selected.value = next;
}
function close() { if (!props.busy) emit("update:modelValue", false); }
function execute(nextAction = action.value) {
  if (props.busy || !selectedCount.value || (nextAction === "whatsapp" && !phoneValid.value)) return;
  const chosen = key => sections.value.find(section => section.key === key).items.flatMap((_, i) => selected.value.has(keyFor(key, i)) ? [i] : []);
  emit("execute", {
    tests: chosen("tests"), cultures: chosen("cultures"), testGroups: chosen("testGroups"), packages: chosen("packages"),
    action: nextAction, withBackground: withBackground.value, phone: phone.value.trim(),
  });
}
</script>

<template>
  <Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 p-3 sm:p-6" dir="rtl" role="dialog" aria-modal="true" aria-labelledby="report-dialog-title" @keydown.esc="close">
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
          <div class="mb-3 flex flex-wrap gap-2">
            <input v-model="search" type="search" placeholder="بحث في التحاليل والكروبات والباقات..." aria-label="بحث في عناصر التقرير" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-4 py-2.5 text-sm outline-none focus:border-blue-500" />
            <button type="button" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white" @click="selected = new Set(allKeys)">تحديد الكل</button>
            <button type="button" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700" @click="selected = new Set()">إلغاء تحديد الكل</button>
          </div>
          <div class="max-h-64 overflow-y-auto rounded-xl border border-slate-200">
            <template v-for="section in visibleSections" :key="section.key">
              <div class="flex items-center justify-between bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700">
                <span>{{ section.label }} ({{ section.visible.filter(({ index }) => selected.has(keyFor(section.key, index))).length }}/{{ section.items.length }})</span>
                <button type="button" class="text-blue-700" @click="toggleSection(section)">{{ section.visible.every(({ index }) => selected.has(keyFor(section.key, index))) ? 'إلغاء تحديد القسم' : 'تحديد القسم' }}</button>
              </div>
              <label v-for="{ item, index } in section.visible" :key="keyFor(section.key, index)" class="flex cursor-pointer items-center gap-3 border-t border-slate-100 px-4 py-2.5 hover:bg-blue-50/40">
                <input type="checkbox" class="h-4 w-4 accent-blue-600" :checked="selected.has(keyFor(section.key, index))" @change="toggle(keyFor(section.key, index))" />
                <span class="min-w-0 flex-1"><strong class="block text-sm text-slate-800">{{ itemName(item) }}</strong><small v-if="details(item)" class="block whitespace-normal text-slate-500">{{ details(item) }}</small></span>
                <span class="text-xs" :class="item.is_done ? 'text-emerald-700' : 'text-amber-700'">{{ item.is_done ? 'مكتمل' : 'قيد الإجراء' }}</span>
              </label>
            </template>
            <p v-if="!visibleSections.length" class="p-6 text-center text-sm text-slate-500">لا توجد عناصر تطابق البحث.</p>
          </div>
          <h3 class="mb-3 mt-5 text-sm font-bold text-slate-800">طريقة الإخراج</h3>
          <div class="grid gap-2 sm:grid-cols-4">
            <label v-for="option in [{ key:'print', title:'طباعة مباشرة', hint:'إرسال التقرير للطابعة' }, { key:'download', title:'حفظ PDF', hint:'تنزيل التقرير كملف' }, { key:'whatsapp', title:'إرسال واتساب', hint:'إرسال PDF ورابط بوابة المريض' }, { key:'print-download', title:'طباعة + حفظ', hint:'طباعة وتنزيل PDF' }]" :key="option.key" class="cursor-pointer rounded-xl border p-3 text-sm" :class="[action === option.key ? 'border-blue-600 bg-blue-50' : 'border-slate-200', option.key === 'whatsapp' && !canShare ? 'opacity-50' : '']">
              <input v-model="action" type="radio" name="report-action" :value="option.key" :disabled="option.key === 'whatsapp' && !canShare" class="accent-blue-600" /><strong class="ms-1">{{ option.title }}</strong><small class="mt-1 block text-slate-500">{{ option.hint }}</small>
            </label>
          </div>
          <div class="mt-4 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
            <label v-if="action === 'whatsapp'" class="text-sm font-medium text-slate-700">رقم هاتف المريض المسجل<input :value="phone" type="tel" dir="ltr" readonly class="mt-1 block w-full rounded-lg border border-slate-200 bg-slate-100 px-3 py-2" /></label>
            <label v-if="hasBackground" class="flex items-center gap-2 text-sm text-slate-700"><input v-model="withBackground" type="checkbox" class="accent-blue-600" /> الطباعة على فورمة المختبر</label>
            <p v-if="action === 'whatsapp'" class="text-xs text-slate-500 sm:col-span-2">يُرسل ملف PDF والعنوان الخاص ببوابة المريض برسالة واحدة إلى الرقم المسجل. لتغيير الرقم حدّث بيانات المريض.</p>
          </div>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-white px-5 py-4 sm:px-7">
          <button type="button" class="rounded-lg border border-slate-200 px-5 py-2.5 text-sm" @click="close">إلغاء</button>
          <div class="flex gap-2">
            <button type="button" :disabled="!selectedCount || busy" class="rounded-lg border border-blue-600 px-5 py-2.5 text-sm font-semibold text-blue-700 disabled:opacity-50" @click="execute('preview')">معاينة التقرير</button>
            <button type="button" :disabled="!selectedCount || busy || (action === 'whatsapp' && !phoneValid)" class="rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white disabled:opacity-50" @click="execute()">{{ busy ? 'جاري تجهيز التقرير...' : 'تنفيذ' }}</button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
