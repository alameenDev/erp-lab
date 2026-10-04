<script setup>
import { computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useinvoicesStore } from '@/store/modules/invoices';
import { useLabSettingsStore } from '@/store/modules/labSettings';
import { dateTimeFormat } from '@/utils/helper';
import { labelConfig, labelData, sampleGroups } from '@/utils/barcodeLabels';
import BarcodeLabel from './BarcodeLabel.vue';
const { printRecord } = storeToRefs(useinvoicesStore());
const { settings } = storeToRefs(useLabSettingsStore());
const config = computed(() => labelConfig(settings.value.barcode_config, settings.value.show_tests_on_barcode !== false));
const labels = computed(() => {
  const record = printRecord.value;
  if (!record?.barcode) return [];
  return sampleGroups(record).flatMap(group => Array.from({ length: config.value.copies }, () => labelData(record, group, settings.value.lab_display_name, record.created_at ? dateTimeFormat(record.created_at) : '')));
});
</script>
<template>
  <div class="hidden" id="parcode"><BarcodeLabel v-for="(data, index) in labels" :key="index" :config="config" :data="data" /></div>
</template>
