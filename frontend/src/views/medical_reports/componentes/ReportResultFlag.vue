<script setup>
import { computed } from "vue";
const props = defineProps({ statusId: [Number, String], label: { type: String, default: '' } });
// Use the report's existing status IDs, never recalculate a clinical flag here.
const status = computed(() => ({
  1: { tone: 'high', icon: 'arrow-up', fallback: 'High' },
  2: { tone: 'normal', icon: 'check', fallback: 'Normal' },
  4: { tone: 'low', icon: 'arrow-down', fallback: 'Low' },
}[Number(props.statusId)]));
</script>

<template>
  <span v-if="label || status" :class="['mr-flag', 'mr-flag-' + (status?.tone || 'other')]">
    <i v-if="status" :class="['pi', 'pi-' + status.icon]" aria-hidden="true"></i>
    {{ label || status?.fallback }}
  </span>
  <span v-else class="mr-empty-flag">—</span>
</template>
