<script setup>
import { computed } from "vue";

const props = defineProps({
  variant: { type: String, default: "primary" },
  size: { type: String, default: "md" },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  block: { type: Boolean, default: false },
  type: { type: String, default: "button" },
});

const emit = defineEmits(["click"]);

const baseClasses = "inline-flex items-center justify-center gap-2 font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed";

const sizeClasses = computed(() => ({
  xs: "px-2 py-1 text-xs",
  sm: "px-3 py-1.5 text-sm",
  md: "px-4 py-2 text-sm",
  lg: "px-5 py-2.5 text-base",
})[props.size]);

const variantClasses = computed(() => ({
  primary: "bg-primary-600 text-white hover:bg-primary-700 focus:ring-primary-500",
  secondary: "bg-slate-100 text-slate-700 hover:bg-slate-200 focus:ring-slate-500",
  success: "bg-success-600 text-white hover:bg-success-600/90 focus:ring-success-500",
  danger: "bg-danger-600 text-white hover:bg-danger-600/90 focus:ring-danger-500",
  warning: "bg-warning-500 text-white hover:bg-warning-600 focus:ring-warning-500",
  outline: "border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 focus:ring-slate-500",
  ghost: "text-slate-600 hover:bg-slate-100 focus:ring-slate-500",
  link: "text-primary-600 hover:text-primary-700 hover:underline p-0",
})[props.variant]);

const handleClick = (e) => {
  if (!props.disabled && !props.loading) {
    emit("click", e);
  }
};
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="[baseClasses, sizeClasses, variantClasses, { 'w-full': block }]"
    @click="handleClick"
  >
    <svg v-if="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
    </svg>
    <slot />
  </button>
</template>
