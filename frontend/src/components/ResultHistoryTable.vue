<script setup>
import { computed } from 'vue';
const props = defineProps({ rows: { type: Array, default: () => [] } });
const visibleRows = computed(() => props.rows.filter(row => row.value !== null && row.value !== undefined && String(row.value).trim() !== ''));
// Preserve the recorded calendar date; do not shift a date-only result across time zones.
const displayDate = raw => {
  const match = String(raw || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
  return match ? `${match[3]}/${match[2]}/${match[1]}` : String(raw || '—');
};
</script>
<template>
  <div v-if="visibleRows.length" class="result-history" dir="ltr" style="margin:8px 0 12px;color:#334155;font-size:11px;text-align:left;">
    <div style="font-weight:700;padding:6px 8px;background:#f1f5f9;border-left:3px solid #64748b;">Previous Results</div>
    <table style="width:100%;border-collapse:collapse;table-layout:auto;font-size:inherit;" aria-label="Previous results">
      <thead style="display:table-header-group;"><tr><th v-for="label in ['Date', 'Test', 'Result', 'Unit']" :key="label" scope="col" style="padding:5px 8px;text-align:left;border-bottom:1px solid #cbd5e1;">{{ label }}</th></tr></thead>
      <tbody><tr v-for="(row, index) in visibleRows" :key="index" style="break-inside:avoid;">
        <td style="padding:5px 8px;border-bottom:1px solid #e2e8f0;white-space:nowrap;">{{ displayDate(row.date) }}<small v-if="row.date_source === 'registration'" style="display:block;font-size:9px;">Registration date</small></td>
        <td style="padding:5px 8px;border-bottom:1px solid #e2e8f0;overflow-wrap:anywhere;">{{ row.field }}</td>
        <td style="padding:5px 8px;border-bottom:1px solid #e2e8f0;font-weight:600;overflow-wrap:anywhere;">{{ row.value }}</td>
        <td style="padding:5px 8px;border-bottom:1px solid #e2e8f0;">{{ row.unit || '—' }}</td>
      </tr></tbody>
    </table>
  </div>
</template>
