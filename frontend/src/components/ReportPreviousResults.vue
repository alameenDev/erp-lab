<script setup>
import { historyDisplayDate } from '@/utils/resultHistory';
defineProps({ rows: { type: Array, default: () => [] } });
</script>
<template>
  <div v-if="rows.length" class="report-previous-results" dir="ltr" style="font-size:11px;overflow-wrap:anywhere;">
    <div v-for="(row, index) in rows" :key="index" class="report-previous-result" style="padding:4px 2px;break-inside:avoid;" :style="index ? { borderTop: '1px solid #e2e8f0' } : {}">
      <div style="font-weight:600;">{{ row.value }} <span v-if="row.unit" style="font-weight:400;font-size:10px;">{{ row.unit }}</span></div>
      <time :datetime="row.date" style="display:block;font-size:10px;color:#475569;">{{ historyDisplayDate(row.date) }}</time>
      <small v-if="row.date_source === 'registration'" style="display:block;font-size:9px;">Registration date</small>
    </div>
  </div>
  <span v-else>—</span>
</template>
