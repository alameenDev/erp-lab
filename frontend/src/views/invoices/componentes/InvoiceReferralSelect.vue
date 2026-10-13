<script setup>
import { computed } from 'vue';
import Dropdown from 'primevue/dropdown';
import { FilterService } from '@primevue/core/api';
import { referralContains, sortInvoiceReferrals } from '@/utils/invoiceReferralOptions';

const props = defineProps({
  modelValue: { type: [Number, String], default: null },
  options: { type: Array, default: () => [] },
  inputId: { type: String, required: true },
  kind: { type: String, default: 'doctor' },
  lang: { type: String, default: 'ar' },
  placeholder: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue', 'change']);
FilterService.register('invoiceReferralContains', referralContains);
const english = computed(() => props.lang === 'en');
const sortedOptions = computed(() => sortInvoiceReferrals(props.options, props.lang));
const searchLabel = computed(() => english.value
  ? `Search ${props.kind === 'lab' ? 'laboratories' : 'doctors'}`
  : props.kind === 'lab' ? 'ابحث عن اسم المختبر' : 'ابحث عن اسم الطبيب');
</script>

<template>
  <Dropdown class="w-full" :inputId="inputId" :modelValue="modelValue"
    @update:modelValue="emit('update:modelValue', $event)" @change="emit('change', $event)"
    :options="sortedOptions" optionLabel="name" optionValue="user_id" :placeholder="placeholder"
    showClear filter :filterFields="['name']" filterMatchMode="invoiceReferralContains"
    :filterPlaceholder="searchLabel" resetFilterOnHide autoFilterFocus scrollHeight="16rem"
    :emptyFilterMessage="english ? 'No matching referrals' : 'لا توجد نتائج مطابقة'"
    :emptyMessage="english ? 'No referrals available' : 'لا توجد إحالات متاحة'"
    :pt="{ overlay: { dir: english ? 'ltr' : 'rtl' }, pcFilter: { root: { 'aria-label': searchLabel } } }">
    <template #option="{ option }">
      <div class="flex w-full min-w-0 items-center justify-between gap-3">
        <span class="whitespace-normal break-words">{{ option.name }}</span>
        <span v-if="Number(option.invoice_usage_count) > 0" class="shrink-0 text-xs text-slate-500">
          {{ english ? 'Invoices:' : 'الفواتير:' }} {{ option.invoice_usage_count }}
        </span>
      </div>
    </template>
  </Dropdown>
  <p class="mt-1 text-xs text-slate-500">{{ english ? 'Most used first' : 'الأكثر استخداماً أولاً' }}</p>
</template>
