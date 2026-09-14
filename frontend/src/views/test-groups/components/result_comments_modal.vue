<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { usetestGroupsStore } from "@/store/modules/testGroups";
import { t } from "@/utils/helper";

const testGroupsStore = usetestGroupsStore();
const { resdialog, result_comments } = storeToRefs(testGroupsStore);

const close = () => {
  result_comments.value = [];
  resdialog.value = false;
};
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="resdialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="close"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden">
          <!-- Header -->
          <div class="bg-gradient-to-r from-slate-800 to-slate-900 px-6 py-5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center shadow-lg shadow-amber-500/30">
                  <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                  </svg>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-white">{{ t("Result_Comments") }}</h2>
                  <p class="text-slate-400 text-sm">{{ t("view_result_comments") }}</p>
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
            <div v-if="result_comments?.length > 0" class="space-y-3">
              <div
                v-for="(comment, index) in result_comments"
                :key="index"
                class="flex items-start gap-3 p-4 bg-gradient-to-r from-amber-50 to-orange-50 rounded-xl border border-amber-100"
              >
                <span class="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center text-sm font-semibold flex-shrink-0">
                  {{ index + 1 }}
                </span>
                <p class="text-slate-700 pt-1">{{ comment }}</p>
              </div>
            </div>
            <div v-else class="text-center py-12">
              <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                </svg>
              </div>
              <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ t("no_comments") }}</h3>
              <p class="text-slate-500">{{ t("no_comments_desc") }}</p>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-100 bg-slate-50">
            <button
              @click="close"
              class="w-full py-2.5 bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium rounded-xl transition-all"
            >
              {{ t("close") }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
