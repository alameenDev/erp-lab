<script setup>
import { computed } from 'vue';
import { customReportColumns, reportColumnKeys, reportColumnWidths } from '@/utils/medicalReportColumns';
const props = defineProps({ settings: { type: Object, required: true } });
const columns = computed(() => reportColumnKeys(props.settings));
const widths = computed(() => reportColumnWidths(props.settings));
</script>
<template>
  <colgroup v-if="customReportColumns(settings.print_table_config)">
    <col v-for="key in columns" :key="key" :data-report-column="key" :style="{ width: widths[key] + '%' }">
  </colgroup>
</template>
