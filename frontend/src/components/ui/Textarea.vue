<script setup>
const props = defineProps({
  modelValue: { type: String, default: "" },
  label: { type: String, default: "" },
  placeholder: { type: String, default: "" },
  rows: { type: Number, default: 4 },
  disabled: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  error: { type: String, default: "" },
  hint: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue", "blur", "focus"]);

const textareaId = `textarea-${Math.random().toString(36).slice(2, 9)}`;
</script>

<template>
  <div class="w-full">
    <label v-if="label" :for="textareaId" class="block text-sm font-medium text-slate-700 mb-1.5">
      {{ label }}
      <span v-if="required" class="text-danger-500 ms-0.5">*</span>
    </label>
    <textarea
      :id="textareaId"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :readonly="readonly"
      :required="required"
      :rows="rows"
      :class="[
        'block w-full rounded-lg border transition-colors resize-y',
        'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500',
        'px-3 py-2 text-sm placeholder:text-slate-400',
        error ? 'border-danger-500' : 'border-slate-300',
        disabled ? 'bg-slate-50 text-slate-500 cursor-not-allowed' : 'bg-white text-slate-900',
      ]"
      @input="emit('update:modelValue', $event.target.value)"
      @blur="emit('blur', $event)"
      @focus="emit('focus', $event)"
    ></textarea>
    <p v-if="error" class="mt-1 text-xs text-danger-500">{{ error }}</p>
    <p v-else-if="hint" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
  </div>
</template>
