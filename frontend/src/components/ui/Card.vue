<script setup>
import { useSlots } from "vue";

defineProps({
  title: { type: String, default: "" },
  subtitle: { type: String, default: "" },
  noPadding: { type: Boolean, default: false },
});

const slots = useSlots();
</script>

<template>
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
    <!-- Header -->
    <div v-if="title || slots.header" class="px-5 py-4 border-b border-slate-100">
      <slot name="header">
        <h3 class="text-base font-semibold text-slate-900">{{ title }}</h3>
        <p v-if="subtitle" class="mt-0.5 text-sm text-slate-500">{{ subtitle }}</p>
      </slot>
    </div>

    <!-- Body -->
    <div :class="noPadding ? '' : 'p-5'">
      <slot />
    </div>

    <!-- Footer -->
    <div v-if="slots.footer" class="px-5 py-4 border-t border-slate-100 bg-slate-50/50 rounded-b-xl">
      <slot name="footer" />
    </div>
  </div>
</template>
