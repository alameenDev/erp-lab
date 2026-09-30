<script setup>
defineProps({ modelValue: { type: String, default: 'classic' }, lang: { type: String, default: 'ar' } });
defineEmits(['update:modelValue']);
</script>

<template>
  <fieldset class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
    <legend class="float-left w-full text-base font-semibold text-slate-800 mb-2">{{ lang === 'en' ? 'Medical report template' : 'نموذج نتيجة المريض' }}</legend>
    <p class="clear-both text-sm text-slate-500 mb-4">{{ lang === 'en' ? 'Applies to report preview, printing, PDF and WhatsApp attachments for this lab.' : 'يُطبق على المعاينة والطباعة وملف PDF وملف النتيجة المرسل بالواتساب لهذا المختبر.' }}</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <label v-for="option in ['classic', 'modern']" :key="option" class="flex items-start gap-3 rounded-xl border-2 p-4 cursor-pointer transition-colors" :class="modelValue === option ? 'border-teal-600 bg-teal-50' : 'border-slate-200 hover:border-slate-400'">
        <input type="radio" name="report_template" :value="option" :checked="modelValue === option" @change="$emit('update:modelValue', option)" class="mt-1 accent-teal-600" />
        <span>
          <strong class="block text-sm text-slate-800">{{ option === 'classic' ? (lang === 'en' ? 'Original template' : 'النموذج القديم') : (lang === 'en' ? 'New template' : 'النموذج الجديد') }}</strong>
          <span class="block mt-1 text-xs leading-5 text-slate-600">{{ option === 'classic' ? (lang === 'en' ? 'The current report layout.' : 'تنسيق التقرير الحالي.') : (lang === 'en' ? 'Patient card, organized results and clear status badges.' : 'بطاقة معلومات المريض وجدول منظم وشارات لحالة النتائج.') }}</span>
        </span>
      </label>
    </div>
  </fieldset>
</template>
