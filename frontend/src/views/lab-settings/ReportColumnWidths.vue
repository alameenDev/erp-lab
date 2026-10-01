<script setup>
import { computed } from 'vue';
import { customReportColumns, reportColumnDefaults, reportColumnKeys, reportColumnWidths, resizeReportColumn } from '@/utils/medicalReportColumns';
const props = defineProps({ settings: { type: Object, required: true }, lang: { type: String, default: 'ar' } });
const emit = defineEmits(['update:config']);
const english = computed(() => props.lang === 'en');
const enabled = computed(() => customReportColumns(props.settings.print_table_config));
const keys = computed(() => reportColumnKeys(props.settings));
const widths = computed(() => reportColumnWidths(props.settings));
const labels = { test: ['اسم التحليل', 'Test name'], result: ['النتيجة', 'Result'], unit: ['الوحدة', 'Unit'], reference: ['المدى الطبيعي', 'Reference range'], status: ['حالة النتيجة', 'Status / Flag'], last_result: ['النتيجة السابقة', 'Last result'] };
const label = key => labels[key][english.value ? 1 : 0];
const update = values => emit('update:config', { ...props.settings.print_table_config, ...values });
const toggle = event => update({ custom_column_widths: event.target.checked, column_widths: { ...reportColumnDefaults, ...props.settings.print_table_config?.column_widths } });
const resize = (key, event) => {
  const next = resizeReportColumn(props.settings, key, event.target.value);
  update({ column_widths: { ...reportColumnDefaults, ...props.settings.print_table_config?.column_widths, ...next } });
  event.target.value = next[key];
};
const typeWidth = (key, event) => {
  const value = Number(event.target.value);
  // Allow clearing/typing multi-digit values without clamping the first digit.
  if (value >= 5 && value <= 100 - (keys.value.length - 1) * 5) resize(key, event);
};
</script>
<template>
  <section class="column-width-editor rounded-xl border border-slate-200 bg-slate-50 p-4 sm:p-5" :dir="english ? 'ltr' : 'rtl'">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div><h4 class="font-bold text-slate-800">{{ english ? 'Column widths' : 'عرض أعمدة التقرير' }}</h4>
        <p class="mt-1 text-xs leading-6 text-slate-500">{{ english ? 'The same widths apply to preview, print, saved PDF and WhatsApp.' : 'نفس القياسات تنطبق على المعاينة والطباعة والحفظ وملف الواتساب.' }}</p></div>
      <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700">
        <input type="checkbox" :checked="enabled" @change="toggle" class="accent-teal-600">{{ english ? 'Custom widths' : 'تخصيص العرض' }}
      </label>
    </div>
    <template v-if="enabled">
      <p class="my-4 text-xs leading-6 text-slate-600">{{ english ? 'Choose a percentage for any column. The remaining space is redistributed automatically. Hidden columns take no space.' : 'حدد نسبة العرض لأي عمود، وتتوزع المساحة الباقية تلقائياً. الأعمدة المخفية لا تحجز مساحة.' }}</p>
      <div class="space-y-3">
        <div v-for="key in keys" :key="key" class="grid grid-cols-[minmax(0,1fr)_88px] items-center gap-x-4 gap-y-2 rounded-lg border border-slate-200 bg-white p-3 sm:grid-cols-[145px_minmax(0,1fr)_88px]">
          <label :for="'report-width-' + key" class="text-sm font-semibold text-slate-700">{{ label(key) }}<span v-if="!english" class="block text-[11px] font-normal text-slate-400" dir="ltr">{{ labels[key][1] }}</span></label>
          <input type="range" :aria-label="label(key) + (english ? ' slider' : ' شريط العرض')" :value="widths[key]" min="5" :max="100 - (keys.length - 1) * 5" step="1" @input="resize(key, $event)" class="col-span-2 row-start-2 w-full accent-teal-600 sm:col-span-1 sm:row-start-auto">
          <div class="flex items-center gap-1" dir="ltr"><input :id="'report-width-' + key" type="number" :value="widths[key]" min="5" :max="100 - (keys.length - 1) * 5" step="1" @input="typeWidth(key, $event)" @change="resize(key, $event)" @blur="resize(key, $event)" class="w-full min-w-0 rounded-md border border-slate-300 px-2 py-2 text-center text-sm focus:ring-2 focus:ring-teal-500"><span class="text-xs text-slate-500">%</span></div>
        </div>
      </div>
      <div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs">
        <span class="font-semibold text-teal-700">{{ english ? 'Total: 100%' : 'مجموع العرض: 100%' }}</span>
        <button type="button" @click="update({ column_widths: { ...reportColumnDefaults } })" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-600 hover:bg-slate-100">{{ english ? 'Reset widths' : 'إعادة ضبط القياسات' }}</button>
      </div>
    </template>
    <p v-else class="mt-3 text-xs text-slate-500">{{ english ? 'The selected template uses its original column widths.' : 'يُستخدم حالياً توزيع الأعمدة الأصلي للنموذج المختار.' }}</p>
  </section>
</template>
