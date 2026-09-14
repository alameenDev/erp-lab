<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { usetestGroupsStore } from "@/store/modules/testGroups";
import { t } from "@/utils/helper";

const testGroupsStore = usetestGroupsStore();
const { testdialog, tests } = storeToRefs(testGroupsStore);

const close = () => {
  tests.value = [];
  testdialog.value = false;
};
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="testdialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="close"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden">
          <!-- Header -->
          <div class="bg-gradient-to-r from-slate-800 to-slate-900 px-6 py-5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center shadow-lg shadow-primary-500/30">
                  <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                  </svg>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-white">{{ t("tests") }}</h2>
                  <p class="text-slate-400 text-sm">{{ t("view_group_tests") }}</p>
                </div>
              </div>
              <button @click="close" class="p-2 text-slate-400 hover:text-white hover:bg-white/10 rounded-xl transition-all">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Content -->
          <div class="p-6 max-h-[60vh] overflow-y-auto">
            <div v-if="tests?.length > 0" class="space-y-3">
              <div
                v-for="(test, index) in tests"
                :key="test.id"
                class="flex items-center gap-4 p-4 bg-gradient-to-r from-primary-50 to-teal-50 rounded-xl border border-primary-100 hover:shadow-md transition-all"
              >
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center flex-shrink-0 shadow-lg shadow-primary-500/20">
                  <span class="text-white font-bold text-sm">{{ test.name?.charAt(0)?.toUpperCase() || 'T' }}</span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="font-semibold text-slate-800 truncate">{{ test.name }}</p>
                  <p v-if="test.shortcut" class="text-sm text-slate-500">{{ t("Shortcut") }}: {{ test.shortcut }}</p>
                </div>
                <span class="px-2.5 py-1 bg-primary-100 text-primary-700 text-xs font-semibold rounded-lg">
                  #{{ index + 1 }}
                </span>
              </div>
            </div>
            <div v-else class="text-center py-12">
              <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
              </div>
              <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ t("no_tests_in_group") }}</h3>
              <p class="text-slate-500">{{ t("no_tests_in_group_desc") }}</p>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-100 bg-slate-50">
            <div class="flex items-center justify-between">
              <span v-if="tests?.length > 0" class="text-sm text-slate-600">
                {{ t("total") }}: <span class="font-semibold text-slate-800">{{ tests.length }}</span> {{ t("tests") }}
              </span>
              <span v-else></span>
              <button
                @click="close"
                class="px-6 py-2.5 bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium rounded-xl transition-all"
              >
                {{ t("close") }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
