<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useculturesStore } from "@/store/modules/cultures";
import { t } from "@/utils/helper";

const culturesStore = useculturesStore();
const { attrdialog, attributesList } = storeToRefs(culturesStore);

const lang = computed(() => localStorage.getItem("locale") || "ar");

const close = () => {
  attributesList.value = [];
  attrdialog.value = false;
};
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="attrdialog" class="fixed inset-0 z-50 flex items-center justify-center p-4" :dir="lang === 'ar' ? 'rtl' : 'ltr'">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="close"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden">
          <!-- Header -->
          <div class="bg-gradient-to-r from-slate-800 to-slate-900 px-6 py-5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-400 to-indigo-600 flex items-center justify-center shadow-lg shadow-blue-500/30">
                  <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                  </svg>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-white">{{ t("attributes") }}</h2>
                  <p class="text-slate-400 text-sm">{{ t("view_culture_attributes") }}</p>
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
          <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)]">
            <div v-if="attributesList?.length > 0" class="space-y-3">
              <div
                v-for="(attr, index) in attributesList"
                :key="attr.id"
                class="flex items-center gap-4 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-100 rounded-xl hover:shadow-md transition-shadow"
              >
                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold text-sm shadow-lg shadow-blue-500/25">
                  {{ index + 1 }}
                </div>
                <div class="flex-1 min-w-0">
                  <p class="font-semibold text-slate-800">{{ attr.attribute_name }}</p>
                  <div class="flex items-center gap-3 mt-1">
                    <span class="text-xs text-slate-500">{{ t("Order") }}: <span class="font-medium text-slate-700">{{ attr.order }}</span></span>
                    <span class="text-xs text-slate-400">|</span>
                    <span class="text-xs text-slate-500">{{ t("Result_Type") }}: <span class="font-medium text-slate-700">{{ attr.result_type }}</span></span>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="flex flex-col items-center justify-center py-12 text-center">
              <div class="w-20 h-20 rounded-full bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center mb-4">
                <svg class="w-10 h-10 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                </svg>
              </div>
              <h3 class="text-lg font-semibold text-slate-800 mb-1">{{ t("no_attributes") }}</h3>
              <p class="text-slate-500">{{ t("no_attributes_desc") }}</p>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex items-center justify-between">
            <div class="text-sm text-slate-600">
              <span class="font-medium">{{ attributesList?.length || 0 }}</span> {{ t("attributes") }}
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
