<script setup>
import { computed } from 'vue';
import { reportActionStatus } from '@/utils/reportActionStatus';
const props = defineProps({ record: { type: Object, default: () => ({}) } });
const status = computed(() => reportActionStatus(props.record));
const colors = {
  pending: 'bg-red-50 text-red-700 ring-red-200',
  printed: 'bg-blue-50 text-blue-700 ring-blue-200',
  saved: 'bg-violet-50 text-violet-700 ring-violet-200',
  combined: 'bg-indigo-50 text-indigo-700 ring-indigo-200',
  sent: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
};
</script>
<template>
  <span class="inline-flex max-w-full items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold leading-5 ring-1 ring-inset"
    :class="colors[status.tone]" :title="status.detail" :aria-label="status.label + '، ' + status.detail" tabindex="0" role="status">
    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-current" aria-hidden="true"></span>
    <span>{{ status.label }}</span>
  </span>
</template>
