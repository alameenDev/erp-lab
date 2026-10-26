<script setup>
import { nextTick, ref, watch } from 'vue';

const props = defineProps({
  id: { type: String, required: true },
  locale: { type: String, default: 'ar' },
  resetKey: { type: [String, Number, Boolean], default: '' },
});
const expanded = ref(false);
watch(() => props.resetKey, () => { expanded.value = false; });

// Keep entered values and selected files mounted when collapsing the section.
// Reveal a hidden invalid field before the browser tries to focus it.
async function revealInvalid(event) {
  if (expanded.value) return;
  event.preventDefault();
  expanded.value = true;
  await nextTick();
  event.target.reportValidity();
}
</script>

<template>
  <section class="min-w-0 rounded-xl border border-slate-200 bg-white" data-patient-additional>
    <button
      type="button"
      class="flex min-h-[48px] w-full items-center justify-between gap-3 rounded-xl px-4 py-3 text-start text-sm font-semibold text-primary-700 hover:bg-primary-50 focus:outline-none focus:ring-4 focus:ring-primary-500/15"
      :aria-expanded="expanded"
      :aria-controls="id"
      @click="expanded = !expanded"
    >
      <span>{{ locale === 'en' ? 'Add more information' : 'إضافة معلومات أخرى' }}</span>
      <svg class="h-5 w-5 shrink-0 transition-transform" :class="{ 'rotate-180': expanded }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
      </svg>
    </button>
    <div v-show="expanded" :id="id" class="border-t border-slate-100 p-4" @invalid.capture="revealInvalid">
      <slot />
    </div>
  </section>
</template>
