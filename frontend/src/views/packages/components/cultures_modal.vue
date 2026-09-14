<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { usePackagesStore } from "@/store/modules/packages";
import { t } from "@/utils/helper";

const packagesStore = usePackagesStore();
const { resdialog, cultures } = storeToRefs(packagesStore);

const lang = computed(() => localStorage.getItem("locale") || "ar");

const close = () => {
  cultures.value = [];
  resdialog.value = false;
};
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="resdialog" class="fixed inset-0 z-50 flex items-center justify-center p-4" :dir="lang === 'ar' ? 'rtl' : 'ltr'">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="close"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden">
          <!-- Header -->
          <div class="bg-gradient-to-r from-slate-800 to-slate-900 px-6 py-5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center shadow-lg shadow-primary-500/30">
                  <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                  </svg>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-white">{{ t("cultures") }}</h2>
                  <p class="text-slate-400 text-sm">{{ t("view_package_cultures") }}</p>
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
            <div v-if="cultures?.length > 0" class="space-y-2">
              <div
                v-for="(culture, index) in cultures"
                :key="culture.id"
                class="flex items-center gap-4 p-4 bg-gradient-to-r from-primary-50 to-teal-50 border border-primary-100 rounded-xl hover:shadow-md transition-shadow"
              >
                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white font-bold text-sm shadow-lg shadow-primary-500/25">
                  {{ index + 1 }}
                </div>
                <div class="flex-1">
                  <p class="font-semibold text-slate-800">{{ culture.name }}</p>
                </div>
                <div v-if="culture.price" class="px-3 py-1.5 bg-white rounded-lg border border-primary-200">
                  <span class="text-sm font-semibold text-primary-700">{{ culture.price?.toLocaleString("en-US") }}</span>
                </div>
              </div>
            </div>
            <div v-else class="flex flex-col items-center justify-center py-12 text-center">
              <div class="w-20 h-20 rounded-full bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center mb-4">
                <svg class="w-10 h-10 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                </svg>
              </div>
              <h3 class="text-lg font-semibold text-slate-800 mb-1">{{ t("no_cultures_in_package") }}</h3>
              <p class="text-slate-500">{{ t("no_cultures_in_package_desc") }}</p>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex items-center justify-between">
            <div class="text-sm text-slate-600">
              <span class="font-medium">{{ cultures?.length || 0 }}</span> {{ t("cultures") }}
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
