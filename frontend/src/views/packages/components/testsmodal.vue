<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { usePackagesStore } from "@/store/modules/packages";
import { t } from "@/utils/helper";

const packagesStore = usePackagesStore();
const { testdialog, testsPackage } = storeToRefs(packagesStore);
const tests = computed(() => testsPackage.value?.tests || []);
const groups = computed(() => testsPackage.value?.test_groups || testsPackage.value?.testGroups || []);

const lang = computed(() => localStorage.getItem("locale") || "ar");

const close = () => {
  testsPackage.value = null;
  testdialog.value = false;
};
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="testdialog" class="fixed inset-0 z-50 flex items-center justify-center p-4" :dir="lang === 'ar' ? 'rtl' : 'ltr'">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="close"></div>
        <div role="dialog" aria-modal="true" aria-labelledby="package-contents-title" class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden">
          <!-- Header -->
          <div class="bg-gradient-to-r from-slate-800 to-slate-900 px-6 py-5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-400 to-teal-600 flex items-center justify-center shadow-lg shadow-primary-500/30">
                  <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                  </svg>
                </div>
                <div>
                  <h2 id="package-contents-title" class="text-xl font-bold text-white">{{ lang === 'ar' ? 'تحاليل وكروبات الباقة' : 'Package tests and groups' }}</h2>
                  <p class="text-slate-300 text-sm break-words">{{ testsPackage?.name }}</p>
                </div>
              </div>
              <button @click="close" :aria-label="t('close')" class="p-2 text-slate-400 hover:text-white hover:bg-white/10 rounded-xl transition-all">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Content -->
          <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)]">
            <section v-if="groups.length" class="space-y-3 mb-5" data-package-groups>
              <h3 class="text-sm font-bold text-slate-700">{{ t('test-groups') }} ({{ groups.length }})</h3>
              <article v-for="group in groups" :key="group.id" class="overflow-hidden rounded-xl border border-amber-200">
                <div class="flex flex-wrap items-center justify-between gap-2 bg-amber-50 px-4 py-3">
                  <div class="min-w-0">
                    <h4 class="font-semibold text-slate-800 break-words">{{ group.group_name || group.name }}</h4>
                    <p v-if="group.shortcut" class="text-xs text-amber-700">{{ group.shortcut }}</p>
                  </div>
                  <span class="text-xs text-slate-600">{{ group.tests?.length || 0 }} {{ t('tests') }}<template v-if="group.cultures?.length"> · {{ group.cultures.length }} {{ t('cultures') }}</template></span>
                </div>
                <ul v-if="group.tests?.length || group.cultures?.length" class="divide-y divide-slate-100 px-4">
                  <li v-for="test in group.tests || []" :key="`test-${test.id}`" class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                    <span class="font-medium text-slate-800 break-words">{{ test.name }}</span>
                    <span v-if="test.shortcut" class="text-slate-500" dir="auto">{{ test.shortcut }}</span>
                  </li>
                  <li v-for="culture in group.cultures || []" :key="`culture-${culture.id}`" class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                    <span class="font-medium text-slate-800 break-words">{{ culture.name }}</span>
                    <span class="text-xs text-purple-700">{{ t('cultures') }}</span>
                  </li>
                </ul>
                <p v-else class="px-4 py-3 text-sm text-slate-500">{{ lang === 'ar' ? 'لا توجد تحاليل مضافة داخل هذا الكروب.' : 'No tests added to this group.' }}</p>
              </article>
            </section>
            <div v-if="tests?.length > 0" class="space-y-2">
              <h3 class="text-sm font-bold text-slate-700">{{ lang === 'ar' ? 'التحاليل المضافة بشكل منفرد' : 'Individually added tests' }} ({{ tests.length }})</h3>
              <div
                v-for="(test, index) in tests"
                :key="test.id"
                class="flex items-center gap-4 p-4 bg-gradient-to-r from-primary-50 to-teal-50 border border-primary-100 rounded-xl hover:shadow-md transition-shadow"
              >
                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-primary-500 to-teal-600 flex items-center justify-center text-white font-bold text-sm shadow-lg shadow-primary-500/25">
                  {{ index + 1 }}
                </div>
                <div class="flex-1">
                  <p class="font-semibold text-slate-800">{{ test.name }}</p>
                  <p v-if="test.shortcut" class="text-sm text-primary-600">{{ test.shortcut }}</p>
                </div>
                <div v-if="test.price" class="px-3 py-1.5 bg-white rounded-lg border border-primary-200">
                  <span class="text-sm font-semibold text-primary-700">{{ test.price?.toLocaleString("en-US") }}</span>
                </div>
              </div>
            </div>
            <div v-else-if="!groups.length" class="flex flex-col items-center justify-center py-12 text-center">
              <div class="w-20 h-20 rounded-full bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center mb-4">
                <svg class="w-10 h-10 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
              </div>
              <h3 class="text-lg font-semibold text-slate-800 mb-1">{{ lang === 'ar' ? 'لا توجد تحاليل أو كروبات في هذه الباقة' : 'No tests or groups in this package' }}</h3>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex items-center justify-between">
            <div class="text-sm text-slate-600">
              <span class="font-medium">{{ tests.length }}</span> {{ lang === 'ar' ? 'تحليل منفرد' : 'individual tests' }}
              <span class="mx-2">·</span><span class="font-medium">{{ groups.length }}</span> {{ t('test-groups') }}
            </div>
            <button
              @click="close"
              class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-medium rounded-xl transition-all flex items-center gap-2"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
              {{ t("close") }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: all 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from .relative,
.modal-leave-to .relative {
  transform: scale(0.95);
}
</style>
