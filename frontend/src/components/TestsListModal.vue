<script setup>
import { computed } from "vue";
import { t } from "@/utils/helper";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  invoice: { type: Object, default: null },
  loading: { type: Boolean, default: false },
});
const emit = defineEmits(["update:modelValue"]);

const close = () => emit("update:modelValue", false);

const tests = computed(() => props.invoice?.tests || []);
const cultures = computed(() => props.invoice?.cultures || []);
const packages = computed(() => props.invoice?.packages || []);
const testGroups = computed(() => props.invoice?.test_groups || []);

const totalItems = computed(() =>
  tests.value.length + cultures.value.length + packages.value.length + testGroups.value.length
);
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="fixed inset-0 z-50 flex items-start justify-center p-4 overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="close"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl my-8 overflow-hidden">
          <!-- Header -->
          <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-primary-50 to-primary-100/50 flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center shadow-lg shadow-primary-500/25">
              <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
              </svg>
            </div>
            <div class="flex-1">
              <h3 class="font-bold text-slate-800">{{ t("invoice_items") || "عناصر الفاتورة" }}</h3>
              <p class="text-xs text-slate-500">
                {{ invoice?.patient?.name }} · <span class="font-mono">{{ invoice?.patient?.code }}</span>
              </p>
            </div>
            <span class="px-2.5 py-1 bg-primary-500/15 border border-primary-500/30 rounded-full text-xs font-bold text-primary-700">
              {{ totalItems }} {{ t("items") || "عنصر" }}
            </span>
            <button @click="close" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-white rounded-lg transition-all">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="p-5 max-h-[70vh] overflow-y-auto space-y-5">
            <!-- Loading -->
            <div v-if="loading" class="py-16 text-center">
              <div class="inline-block w-10 h-10 border-4 border-primary-200 border-t-primary-500 rounded-full animate-spin"></div>
              <p class="text-sm text-slate-500 mt-3">{{ t("loading") || "Loading..." }}</p>
            </div>

            <template v-else>
              <!-- Empty state -->
              <div v-if="totalItems === 0" class="py-16 text-center text-slate-400">
                <svg class="w-12 h-12 mx-auto mb-2 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-sm">{{ t("no_data") || "No data" }}</p>
              </div>

              <!-- Tests -->
              <div v-if="tests.length > 0">
                <div class="flex items-center gap-2 mb-2">
                  <span class="w-2 h-2 rounded-full bg-primary-500"></span>
                  <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">{{ t("tests") || "Tests" }}</h4>
                  <span class="text-xs text-slate-400">({{ tests.length }})</span>
                </div>
                <div class="space-y-1.5">
                  <div
                    v-for="(test, i) in tests"
                    :key="'t-' + i"
                    class="flex items-center gap-3 p-3 bg-slate-50 hover:bg-primary-50/50 border border-slate-200 rounded-xl transition-colors"
                  >
                    <span class="w-7 h-7 rounded-lg bg-primary-100 text-primary-700 text-xs font-bold flex items-center justify-center shrink-0">{{ i + 1 }}</span>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-semibold text-slate-800 truncate">{{ test.name || test.report_name }}</p>
                      <p v-if="test.shortcut" class="text-[11px] text-slate-500 font-mono">{{ test.shortcut }}</p>
                    </div>
                    <span
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                      :class="test.is_done ? 'bg-success-100 text-success-700 border border-success-200' : 'bg-warning-100 text-warning-700 border border-warning-200'"
                    >
                      <span class="w-1.5 h-1.5 rounded-full" :class="test.is_done ? 'bg-success-500' : 'bg-warning-500'"></span>
                      {{ test.is_done ? (t('done') || 'Done') : (t('pendening') || 'Pending') }}
                    </span>
                    <span class="text-sm font-bold text-slate-700">{{ test.price ?? 0 }}</span>
                  </div>
                </div>
              </div>

              <!-- Cultures -->
              <div v-if="cultures.length > 0">
                <div class="flex items-center gap-2 mb-2">
                  <span class="w-2 h-2 rounded-full bg-danger-500"></span>
                  <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">{{ t("cultures") || "Cultures" }}</h4>
                  <span class="text-xs text-slate-400">({{ cultures.length }})</span>
                </div>
                <div class="space-y-1.5">
                  <div
                    v-for="(c, i) in cultures"
                    :key="'c-' + i"
                    class="flex items-center gap-3 p-3 bg-slate-50 hover:bg-danger-50/50 border border-slate-200 rounded-xl transition-colors"
                  >
                    <span class="w-7 h-7 rounded-lg bg-danger-100 text-danger-700 text-xs font-bold flex items-center justify-center shrink-0">{{ i + 1 }}</span>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-semibold text-slate-800 truncate">{{ c.name }}</p>
                    </div>
                    <span
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                      :class="c.is_done ? 'bg-success-100 text-success-700 border border-success-200' : 'bg-warning-100 text-warning-700 border border-warning-200'"
                    >
                      <span class="w-1.5 h-1.5 rounded-full" :class="c.is_done ? 'bg-success-500' : 'bg-warning-500'"></span>
                      {{ c.is_done ? (t('done') || 'Done') : (t('pendening') || 'Pending') }}
                    </span>
                    <span class="text-sm font-bold text-slate-700">{{ c.price ?? 0 }}</span>
                  </div>
                </div>
              </div>

              <!-- Test groups -->
              <div v-if="testGroups.length > 0">
                <div class="flex items-center gap-2 mb-2">
                  <span class="w-2 h-2 rounded-full bg-warning-500"></span>
                  <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">{{ t("test_groups") || "Test Groups" }}</h4>
                  <span class="text-xs text-slate-400">({{ testGroups.length }})</span>
                </div>
                <div class="space-y-2">
                  <div
                    v-for="(g, gi) in testGroups"
                    :key="'g-' + gi"
                    class="bg-slate-50 border border-slate-200 rounded-xl overflow-hidden"
                  >
                    <div class="px-3 py-2 bg-warning-50 border-b border-slate-200 flex items-center gap-2">
                      <span class="w-6 h-6 rounded-md bg-warning-100 text-warning-700 text-[11px] font-bold flex items-center justify-center">{{ gi + 1 }}</span>
                      <span class="text-sm font-bold text-slate-800 flex-1 truncate">{{ g.group_name }}</span>
                      <span
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                        :class="g.is_done ? 'bg-success-100 text-success-700 border border-success-200' : 'bg-warning-100 text-warning-700 border border-warning-200'"
                      >
                        <span class="w-1.5 h-1.5 rounded-full" :class="g.is_done ? 'bg-success-500' : 'bg-warning-500'"></span>
                        {{ g.is_done ? (t('done') || 'Done') : (t('pendening') || 'Pending') }}
                      </span>
                      <span class="text-sm font-bold text-slate-700">{{ g.price ?? 0 }}</span>
                    </div>
                    <div v-if="g.tests?.length || g.cultures?.length" class="p-2 space-y-1">
                      <div v-for="(t2, ti) in g.tests || []" :key="'gt-' + ti" class="flex items-center gap-2 px-2 py-1 text-xs">
                        <svg class="w-3 h-3 shrink-0" :class="t2.is_done ? 'text-success-500' : 'text-warning-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                          <path v-if="t2.is_done" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                          <circle v-else cx="12" cy="12" r="8" stroke-width="2" />
                        </svg>
                        <span class="text-slate-600 flex-1 truncate">{{ t2.name || t2.report_name }}</span>
                        <span v-if="t2.shortcut" class="text-[10px] text-slate-400 font-mono">{{ t2.shortcut }}</span>
                      </div>
                      <div v-for="(c2, ci) in g.cultures || []" :key="'gc-' + ci" class="flex items-center gap-2 px-2 py-1 text-xs">
                        <svg class="w-3 h-3 shrink-0" :class="c2.is_done ? 'text-success-500' : 'text-warning-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                          <path v-if="c2.is_done" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                          <circle v-else cx="12" cy="12" r="8" stroke-width="2" />
                        </svg>
                        <span class="text-slate-600 flex-1 truncate">{{ c2.name }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Packages -->
              <div v-if="packages.length > 0">
                <div class="flex items-center gap-2 mb-2">
                  <span class="w-2 h-2 rounded-full bg-info-500"></span>
                  <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">{{ t("packages") || "Packages" }}</h4>
                  <span class="text-xs text-slate-400">({{ packages.length }})</span>
                </div>
                <div class="space-y-2">
                  <div
                    v-for="(p, pi) in packages"
                    :key="'p-' + pi"
                    class="bg-slate-50 border border-slate-200 rounded-xl overflow-hidden"
                  >
                    <div class="px-3 py-2 bg-info-50 border-b border-slate-200 flex items-center gap-2">
                      <span class="w-6 h-6 rounded-md bg-info-100 text-info-700 text-[11px] font-bold flex items-center justify-center">{{ pi + 1 }}</span>
                      <span class="text-sm font-bold text-slate-800 flex-1 truncate">{{ p.name }}</span>
                      <span
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                        :class="p.is_done ? 'bg-success-100 text-success-700 border border-success-200' : 'bg-warning-100 text-warning-700 border border-warning-200'"
                      >
                        <span class="w-1.5 h-1.5 rounded-full" :class="p.is_done ? 'bg-success-500' : 'bg-warning-500'"></span>
                        {{ p.is_done ? (t('done') || 'Done') : (t('pendening') || 'Pending') }}
                      </span>
                      <span class="text-sm font-bold text-slate-700">{{ p.price ?? 0 }}</span>
                    </div>
                    <div v-if="p.tests?.length || p.cultures?.length" class="p-2 space-y-1">
                      <div v-for="(t2, ti) in p.tests || []" :key="'pt-' + ti" class="flex items-center gap-2 px-2 py-1 text-xs">
                        <svg class="w-3 h-3 shrink-0" :class="t2.is_done ? 'text-success-500' : 'text-warning-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                          <path v-if="t2.is_done" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                          <circle v-else cx="12" cy="12" r="8" stroke-width="2" />
                        </svg>
                        <span class="text-slate-600 flex-1 truncate">{{ t2.name || t2.report_name }}</span>
                        <span v-if="t2.shortcut" class="text-[10px] text-slate-400 font-mono">{{ t2.shortcut }}</span>
                      </div>
                      <div v-for="(c2, ci) in p.cultures || []" :key="'pc-' + ci" class="flex items-center gap-2 px-2 py-1 text-xs">
                        <svg class="w-3 h-3 shrink-0" :class="c2.is_done ? 'text-success-500' : 'text-warning-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                          <path v-if="c2.is_done" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                          <circle v-else cx="12" cy="12" r="8" stroke-width="2" />
                        </svg>
                        <span class="text-slate-600 flex-1 truncate">{{ c2.name }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </div>

          <!-- Footer -->
          <div class="px-6 py-3 border-t border-slate-100 bg-slate-50 flex justify-end">
            <button
              @click="close"
              class="px-5 py-2 text-sm font-semibold text-slate-600 hover:text-slate-900 transition-colors"
            >{{ t("close") || "Close" }}</button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
