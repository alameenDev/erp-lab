<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useCategoriesStore } from "@/store/modules/categories";
import { t, alertSuccess, clearObjectValues } from "@/utils/helper";

const categoriesStore = useCategoriesStore();
const { record, dialog } = storeToRefs(categoriesStore);
const { Addcategory, Updatecategory } = categoriesStore;

const create = () => {
  Addcategory().then(() => {
    alertSuccess(t("alertSuccess"));
    clearObjectValues(record.value);
    dialog.value = false;
  });
};

const update = () => {
  Updatecategory().then(() => {
    alertSuccess(t("alertSuccess"));
    clearObjectValues(record.value);
    dialog.value = false;
  });
};

const close = () => {
  clearObjectValues(record.value);
  dialog.value = false;
};
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="dialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="close"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
          <!-- Header -->
          <div class="bg-gradient-to-r from-slate-800 to-slate-900 px-6 py-5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center shadow-lg shadow-primary-500/30">
                  <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                  </svg>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-white">{{ record?.id ? t("update") : t("add") }} {{ t("Category") }}</h2>
                  <p class="text-slate-400 text-sm">{{ t("category_info_desc") }}</p>
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
          <form @submit.prevent="record.id ? update() : create()">
            <div class="p-6">
              <!-- Category Information Card -->
              <div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-primary-50 to-teal-50">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center shadow-lg shadow-primary-500/25">
                      <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </div>
                    <div class="flex-1">
                      <h3 class="font-semibold text-slate-800">{{ t("category_basic_info") }}</h3>
                      <p class="text-sm text-slate-500">{{ t("category_basic_info_desc") }}</p>
                    </div>
                  </div>
                </div>
                <div class="p-5">
                  <!-- Category Name -->
                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">
                      {{ t("name") }} <span class="text-red-500">*</span>
                    </label>
                    <input
                      v-model="record.name"
                      type="text"
                      required
                      maxlength="255"
                      class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                      :placeholder="t('category_name_placeholder')"
                    />
                    <p class="mt-1.5 text-xs text-slate-500">{{ t("category_name_hint") }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex items-center justify-end gap-3">
              <button
                type="button"
                @click="close"
                class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-medium rounded-xl transition-all flex items-center gap-2"
              >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                {{ t("cancel") }}
              </button>
              <button
                type="submit"
                class="px-5 py-2.5 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-medium rounded-xl transition-all shadow-lg shadow-primary-500/25 flex items-center gap-2"
              >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                {{ record?.id ? t("save") : t("add") }}
              </button>
            </div>
          </form>
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
