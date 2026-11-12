<script setup>
import { computed } from 'vue';
import { showsUnit, showsReferenceRange } from '@/utils/testFieldVisibility';
import { cbcHistoryEnabled, cbcPreviousResult } from '@/utils/cbcResultHistory';
import { historyDisplayDate } from '@/utils/resultHistory';
const props = defineProps({ test: { type: Object, required: true }, record: { type: Object, default: () => ({}) }, settings: { type: Object, default: () => ({}) } });
const historyVisible = computed(() => cbcHistoryEnabled(props.test, props.settings));
const rows = computed(() => (props.test.sub_tests || []).map(row => ({ row, previous: cbcPreviousResult(props.record, props.test, row.name, props.settings) })));
const editValue = (test, row, value) => {
  row.value = value;
  test.is_done = false;
  test.content.review_required = true;
};
</script>
<template>
  <div data-cbc-result-editor>
    <label class="mb-3 flex items-center gap-2 text-sm font-semibold" dir="rtl"><input type="checkbox" :checked="historyVisible" @change="test.last_result = $event.target.checked">إظهار النتائج السابقة لهذا CBC في التعديل والتقرير</label>
    <p class="mb-3 text-xs text-slate-500" dir="rtl">احفظ النتائج لتثبيت اختيار الإظهار أو الإخفاء. مكان ظهور السابقة في التقرير يُحدّد من إعدادات الطباعة.</p>
    <p class="mb-3 text-sm text-slate-600" dir="rtl">عدّل النتيجة المطلوبة، ثم راجع التحليل وحدده كمكتمل واحفظ النتائج. تبقى رسالة الجهاز الأصلية محفوظة في نتائج الأجهزة.</p>
    <div class="overflow-x-auto">
      <table class="w-full border-collapse text-sm" dir="ltr">
        <thead><tr><th class="border p-2 text-left">Test</th><th class="border p-2 text-left">Result</th><th v-if="historyVisible" class="border p-2 text-left">Last Result</th><th class="border p-2 text-left">{{ showsUnit(test) ? 'Unit' : '' }}</th><th class="border p-2 text-left">{{ showsReferenceRange(test) ? 'Reference Range' : '' }}</th></tr></thead>
        <tbody><tr v-for="({ row, previous }, index) in rows" :key="index">
          <td class="border p-2 font-semibold">{{ row.name }}</td>
          <td class="border p-2"><input type="text" :aria-label="`${row.name} result`" :value="row.value" maxlength="500" class="w-full min-w-24 rounded border border-slate-300 px-3 py-2" @input="editValue(test, row, $event.target.value)"></td>
          <td v-if="historyVisible" class="border p-2" data-cbc-last-result>
            <span v-if="previous?.ambiguous" class="text-xs" dir="rtl">توجد أكثر من نتيجة سابقة؛ راجع سجل المريض</span>
            <template v-else-if="previous"><strong>{{ previous.value }}</strong><small v-if="showsUnit(test) && previous.unit" class="block">{{ previous.unit }}</small><time class="block text-xs text-slate-500" :datetime="previous.date">{{ historyDisplayDate(previous.date) }}</time><small v-if="previous.date_source === 'registration'" class="block">Registration date</small></template>
            <span v-else class="text-xs text-slate-500" dir="rtl">لا توجد نتيجة سابقة</span>
          </td>
          <td class="border p-2">{{ showsUnit(test) ? row.unit : '' }}</td><td class="border p-2">{{ showsReferenceRange(test) ? row.reference_range : '' }}</td>
        </tr></tbody>
      </table>
    </div>
  </div>
</template>
