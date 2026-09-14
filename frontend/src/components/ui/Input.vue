<script setup>
import { computed } from "vue";

const props = defineProps({
  modelValue: { type: [String, Number], default: "" },
  type: { type: String, default: "text" },
  label: { type: String, default: "" },
  placeholder: { type: String, default: "" },
  size: { type: String, default: "md" },
  error: { type: String, default: "" },
  hint: { type: String, default: "" },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "blur", "focus"]);

const inputId = `input-${Math.random().toString(36).slice(2, 9)}`;

const sizeClasses = computed(() => ({
  sm: "px-3 py-1.5 text-sm",
  md: "px-3 py-2 text-sm",
  lg: "px-4 py-2.5 text-base",
})[props.size]);

const inputClasses = computed(() => [
  "block w-full rounded-lg border bg-white transition-colors",
  "placeholder:text-slate-400",
  "focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500",
  sizeClasses.value,
  props.error ? "border-danger-500" : "border-slate-300",
  props.disabled ? "bg-slate-50 text-slate-500 cursor-not-allowed" : "text-slate-900",
]);
</script>

<template>
  <div class="w-full">
    <label v-if="label" :for="inputId" class="block text-sm font-medium text-slate-700 mb-1.5">
      {{ label }}
      <span v-if="required" class="text-danger-500 ml-0.5">*</span>
    </label>

    <input
      :id="inputId"
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :readonly="readonly"
      :required="required"
      :class="inputClasses"
      @input="emit('update:modelValue', $event.target.value)"
      @blur="emit('blur', $event)"
      @focus="emit('focus', $event)"
    />

    <p v-if="error" class="mt-1 text-xs text-danger-500">{{ error }}</p>
    <p v-else-if="hint" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
  </div>
</template>
