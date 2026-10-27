<script setup>
import { computed } from 'vue';
const props = defineProps({ value: { default: null }, depth: { type: Number, default: 0 } });
const decoded = computed(() => {
  let value = props.value;
  for (let i = 0; i < 2 && typeof value === 'string' && /^[\[{]/.test(value.trim()); i++) {
    try { value = JSON.parse(value); } catch { break; }
  }
  return value;
});
const labels = { name:'الاسم', result:'النتيجة', value:'القيمة', unit:'الوحدة', normal_range:'المعدل الطبيعي', reference:'المرجع',
  is_done:'مكتمل', total:'الإجمالي', paid:'المدفوع', amount:'المبلغ', price:'السعر', discount:'الخصم', name_ar:'الاسم',
  test_name:'التحليل', report_name:'اسم التقرير', sub_tests:'الفحوص الفرعية', comment:'التعليق', notes:'الملاحظات' };
</script>
<template>
  <span v-if="decoded === null || decoded === undefined || decoded === ''" class="ov-muted">—</span>
  <span v-else-if="typeof decoded === 'boolean'">{{ decoded ? 'نعم' : 'لا' }}</span>
  <dl v-else-if="typeof decoded === 'object' && depth < 5" class="ov-values">
    <div v-for="(v, k) in decoded" :key="k"><dt>{{ Array.isArray(decoded) ? Number(k) + 1 : (labels[k] || k) }}</dt><dd><OwnerValue :value="v" :depth="depth + 1" /></dd></div>
  </dl>
  <span v-else class="ov-value">{{ typeof decoded === 'object' ? JSON.stringify(decoded) : decoded }}</span>
</template>
<style scoped>
.ov-values{margin:0;display:grid;gap:6px}.ov-values>div{display:flex;gap:12px;align-items:flex-start;border-bottom:1px solid #eef1f4;padding:5px 0}.ov-values dt{font-size:11px;color:#64748b;min-width:60px;max-width:35%;overflow-wrap:anywhere}.ov-values dd{margin:0;flex:1;min-width:0;font-size:12px;overflow-wrap:anywhere}.ov-muted{color:#94a3b8}.ov-value{white-space:pre-wrap;overflow-wrap:anywhere}
</style>
