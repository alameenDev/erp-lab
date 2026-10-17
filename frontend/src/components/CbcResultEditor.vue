<script setup>
defineProps({ test: { type: Object, required: true } });
const editValue = (test, row, value) => {
  row.value = value;
  test.is_done = false;
  test.content.review_required = true;
};
</script>
<template>
  <div>
    <p class="mb-3 text-sm text-slate-600" dir="rtl">عدّل النتيجة المطلوبة، ثم راجع التحليل وحدده كمكتمل واحفظ النتائج. تبقى رسالة الجهاز الأصلية محفوظة في نتائج الأجهزة.</p>
    <div class="overflow-x-auto">
      <table class="w-full border-collapse text-sm" dir="ltr">
        <thead><tr><th class="border p-2 text-left">Test</th><th class="border p-2 text-left">Result</th><th class="border p-2 text-left">Unit</th><th class="border p-2 text-left">Reference Range</th></tr></thead>
        <tbody><tr v-for="(row, index) in test.sub_tests" :key="index">
          <td class="border p-2 font-semibold">{{ row.name }}</td>
          <td class="border p-2"><input type="text" :aria-label="`${row.name} result`" :value="row.value" maxlength="500" class="w-full min-w-24 rounded border border-slate-300 px-3 py-2" @input="editValue(test, row, $event.target.value)"></td>
          <td class="border p-2">{{ row.unit }}</td><td class="border p-2">{{ row.reference_range }}</td>
        </tr></tbody>
      </table>
    </div>
  </div>
</template>
