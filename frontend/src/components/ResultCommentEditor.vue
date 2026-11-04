<script setup>
import { computed, useId } from 'vue';
import { appendComment, commentOptions } from '@/utils/resultComments';
const props = defineProps({ modelValue: { default: '' }, options: { default: () => [] }, name: { type: String, default: '' }, compact: Boolean });
const emit = defineEmits(['update:modelValue']);
const id = useId();
const choices = computed(() => commentOptions(props.options));
</script>

<template>
  <details class="result-comment-editor min-w-0 text-sm" :open="!compact" dir="rtl">
    <summary class="cursor-pointer py-2 text-slate-600 font-medium">تعليق النتيجة <span v-if="modelValue" class="text-primary-700">• يوجد تعليق</span></summary>
    <div class="space-y-2 pb-2">
      <label :for="id" class="block text-xs text-slate-500">{{ name ? `تعليق ${name}` : 'تعليق النتيجة' }}</label>
      <select v-if="choices.length" :aria-label="`اختر تعليقاً جاهزاً ${name}`" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
        @change="emit('update:modelValue', appendComment(modelValue, $event.target.value)); $event.target.value = ''">
        <option value="">إضافة تعليق جاهز…</option>
        <option v-for="choice in choices" :key="choice" :value="choice">{{ choice }}</option>
      </select>
      <textarea :id="id" :value="modelValue" @input="emit('update:modelValue', $event.target.value)" rows="2" dir="auto"
        placeholder="اكتب تعليقاً خاصاً أو عدّل التعليق المختار" class="w-full min-w-0 rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-primary-500" />
      <p class="text-xs text-slate-500">يُحفظ لهذا التحليل عند المريض فقط. ظهوره بالتقرير حسب إعدادات الطباعة.</p>
    </div>
  </details>
</template>
