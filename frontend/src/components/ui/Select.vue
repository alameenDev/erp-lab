<script setup>
import { computed } from "vue";

const props = defineProps({
  modelValue: { type: [String, Number, Boolean, Object], default: "" },
  options: { type: Array, default: () => [] },
  optionLabel: { type: String, default: "label" },
  optionValue: { type: String, default: "value" },
  label: { type: String, default: "" },
  placeholder: { type: String, default: "Select an option" },
  size: { type: String, default: "md" },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  error: { type: String, default: "" },
  hint: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue"]);

const selectId = `select-${Math.random().toString(36).slice(2, 9)}`;

const sizeClasses = computed(() => ({
  sm: "px-3 py-1.5 text-sm pe-10",
  md: "px-3 py-2 text-sm pe-10",
  lg: "px-4 py-2.5 text-base pe-10",
})[props.size]);

const normalizedOptions = computed(() =>
  props.options.map(opt => {
    if (typeof opt === "object") {
      return {
        value: opt[props.optionValue],
        label: opt[props.optionLabel],
      };
    }
    return { value: opt, label: opt };
  })
);
</script>

<template>
  <div class="w-full">
    <label v-if="label" :for="selectId" class="block text-sm font-medium text-slate-700 mb-1.5">
      {{ label }}
      <span v-if="required" class="text-danger-500 ms-0.5">*</span>
    </label>
    <div class="relative">
      <select
        :id="selectId"
        :value="modelValue"
        :disabled="disabled"
        :required="required"
        :class="[
          'block w-full rounded-lg border bg-white transition-colors appearance-none cursor-pointer',
          'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500',
          'placeholder:text-slate-400',
          sizeClasses,
          error ? 'border-danger-500' : 'border-slate-300',
          disabled ? 'bg-slate-50 text-slate-500 cursor-not-allowed' : 'text-slate-900',
        ]"
        @change="emit('update:modelValue', $event.target.value)"
      >
        <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
        <option
          v-for="option in normalizedOptions"
          :key="option.value"
          :value="option.value"
        >
          {{ option.label }}
        </option>
      </select>
      <div class="absolute inset-y-0 end-0 flex items-center pe-3 pointer-events-none">
        <svg class="h-5 w-5 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
        </svg>
      </div>
    </div>
    <p v-if="error" class="mt-1 text-xs text-danger-500">{{ error }}</p>
    <p v-else-if="hint" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
  </div>
</template>
