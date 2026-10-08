<template>
  <div class="min-w-0" data-analysis-name>
    <span class="block font-medium [overflow-wrap:anywhere]" dir="auto">{{ name }}</span>
    <span v-if="shortcut" class="block mt-0.5 text-xs text-slate-500 [overflow-wrap:anywhere]" dir="auto" data-analysis-shortcut>{{ shortcut }}</span>
  </div>
</template>

<script setup>
defineProps({ name: String, shortcut: String });
</script>
