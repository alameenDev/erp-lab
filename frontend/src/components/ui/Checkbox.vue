<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
  label: { type: String, default: "" },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);
</script>

<template>
  <label class="inline-flex items-center gap-2 cursor-pointer">
    <input
      type="checkbox"
      :checked="modelValue"
      :disabled="disabled"
      :class="[
        'h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-2 focus:ring-primary-500/20 transition-colors',
        disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer',
      ]"
      @change="emit('update:modelValue', $event.target.checked)"
    />
    <span v-if="label || $slots.default" :class="['text-sm text-slate-700', disabled ? 'opacity-50' : '']">
      <slot>{{ label }}</slot>
    </span>
  </label>
</template>
