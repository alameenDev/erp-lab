<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { usepaymentMethodstore } from "@/store/modules/payment-methods";
import { t, alertSuccess, clearObjectValues } from "@/utils/helper";

const paymentMethodStore = usepaymentMethodstore();
const { record, dialog } = storeToRefs(paymentMethodStore);
const { AddpaymentMethod, UpdatepaymentMethod } = paymentMethodStore;

const lang = computed(() => localStorage.getItem("locale") || "ar");

const create = () => {
  AddpaymentMethod().then(() => {
    alertSuccess(t("alertSuccess"));
    clearObjectValues(record.value);
    dialog.value = false;
  });
};

const update = () => {
  UpdatepaymentMethod().then(() => {
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
        <div class="fixed inset-0 bg-black/50" @click="close"></div>
        <div
          class="relative bg-white rounded-xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-hidden"
          :dir="lang === 'ar' ? 'rtl' : 'ltr'"
        >
          <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">
              {{ record?.id ? t("update") : t("add") }}
            </h2>
            <button @click="close" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <form @submit.prevent="record.id ? update() : create()" class="p-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">{{ t("name") }} <span class="text-red-500">*</span></label>
              <input
                v-model="record.name"
                type="text"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
              <button
                type="button"
                @click="close"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
              >
                {{ t("close") }}
              </button>
              <button
                type="submit"
                class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700"
              >
                {{ record.id ? t("save") : t("add") }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

