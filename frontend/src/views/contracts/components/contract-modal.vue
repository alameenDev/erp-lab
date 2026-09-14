<script setup>
import { ref, computed } from "vue";
import { storeToRefs } from "pinia";
import { useContractsStore } from "@/store/modules/contract";
import { t, alertSuccess, clearObjectValues } from "@/utils/helper";

const contractsStore = useContractsStore();
const { record, dialog } = storeToRefs(contractsStore);
const { AddContract, UpdateContract } = contractsStore;

const lang = computed(() => localStorage.getItem("locale") || "ar");
const showPassword = ref(false);

const handleSubmit = () => {
  record.value.discount_percentage = record.value.discount_percentage || 0;
  record.value.payment_percent = record.value.payment_percent || 0;
  record.value.credit_limit = record.value.credit_limit || 0;
  record.value.price_limit = record.value.price_limit || 0;
  record.value.maximum_payment_per_invoice = record.value.maximum_payment_per_invoice || 0;

  if (record.value.id) {
    UpdateContract().then(() => {
      alertSuccess(t("alertSuccess"));
      clearObjectValues(record.value);
      dialog.value = false;
    });
  } else {
    AddContract().then(() => {
      alertSuccess(t("alertSuccess"));
      dialog.value = false;
    });
  }
};

const close = () => {
  dialog.value = false;
  clearObjectValues(record.value);
};
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="dialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="close"></div>
        <div
          class="relative bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-hidden"
          :dir="lang === 'ar' ? 'rtl' : 'ltr'"
        >
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">
              {{ record?.id ? t("update") : t("add") }}
            </h2>
            <button @click="close" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
            <form @submit.prevent="handleSubmit">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Name -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("name") }} <span class="text-red-500">*</span></label>
                  <input
                    v-model="record.name"
                    type="text"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>

                <!-- Email -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("email") }} <span class="text-red-500">*</span></label>
                  <input
                    v-model="record.email"
                    type="email"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>

                <!-- Password -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("password") }} <span v-if="!record.id" class="text-red-500">*</span></label>
                  <div class="relative">
                    <input
                      v-model="record.password"
                      :type="showPassword ? 'text' : 'password'"
                      :required="!record.id"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                    <button
                      type="button"
                      @click="showPassword = !showPassword"
                      class="absolute top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                      :class="lang === 'ar' ? 'left-3' : 'right-3'"
                    >
                      <svg v-if="showPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                      </svg>
                      <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </button>
                  </div>
                </div>

                <!-- Phone Number -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("phone_number") }} <span class="text-red-500">*</span></label>
                  <input
                    v-model="record.phone_number"
                    type="tel"
                    required
                    maxlength="11"
                    minlength="11"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>

                <!-- Address -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("address") }} <span class="text-red-500">*</span></label>
                  <input
                    v-model="record.address"
                    type="text"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>

                <!-- Discount Percentage -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("discount_percentage") }} <span class="text-red-500">*</span></label>
                  <input
                    v-model.number="record.discount_percentage"
                    type="number"
                    required
                    min="0"
                    max="100"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>

                <!-- Payment Percent -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("payment_percent") }} <span class="text-red-500">*</span></label>
                  <input
                    v-model.number="record.payment_percent"
                    type="number"
                    required
                    min="0"
                    max="100"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>

                <!-- Maximum Payment Per Invoice -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("maximum_payment_per_invoice") }} <span class="text-red-500">*</span></label>
                  <input
                    v-model.number="record.maximum_payment_per_invoice"
                    type="number"
                    required
                    min="0"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>

                <!-- Credit Limit -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("credit_limit") }}</label>
                  <input
                    v-model.number="record.credit_limit"
                    type="number"
                    min="0"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>

                <!-- Price Limit -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("price_limit") }} <span class="text-red-500">*</span></label>
                  <input
                    v-model.number="record.price_limit"
                    type="number"
                    required
                    min="0"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>
              </div>

              <!-- Footer -->
              <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                <button
                  type="button"
                  @click="close"
                  class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                >
                  {{ t("close") }}
                </button>
                <button
                  type="submit"
                  class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors"
                >
                  {{ record.id ? t("save") : t("add") }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

