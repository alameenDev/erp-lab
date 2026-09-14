<script setup>
import { ref, computed } from "vue";
import { storeToRefs } from "pinia";
import { useUsersStore } from "@/store/modules/users";
import { t } from "@/utils/helper";
import { useToast } from "@/composables/useToast";
import OtpInput from "./otp-input.vue";

const props = defineProps({
  isReset: {
    type: Boolean,
    default: false,
  },
});

const usersStore = useUsersStore();
const toast = useToast();

const { record, codeDialog, otpCode } = storeToRefs(usersStore);
const password = ref("");
const showPassword = ref(false);

const lang = computed(() => localStorage.getItem("locale") || "ar");

const confirm = async () => {
  try {
    if (props.isReset) {
      await usersStore.reset_password(password.value);
    } else {
      await usersStore.VerifyCode();
    }
    toast.success(t("alertSuccess"));
    clearForm();
  } catch (error) {
    console.error("Error:", error);
    toast.error(t("error_occurred"));
  }
};

const clearForm = () => {
  password.value = "";
  otpCode.value = "";
  codeDialog.value = false;
  // Clear record
  Object.keys(record.value).forEach(key => {
    if (typeof record.value[key] === "string") {
      record.value[key] = "";
    } else if (typeof record.value[key] === "number") {
      record.value[key] = 0;
    } else if (Array.isArray(record.value[key])) {
      record.value[key] = [];
    }
  });
};

const close = () => {
  clearForm();
};
</script>

<template>
  <UiModal
    v-model="codeDialog"
    :closable="true"
    size="md"
  >
    <template #header>
      <h3 class="text-lg font-semibold text-gray-800">
        {{ isReset ? t("reset_password") : t("verify_code") }}
      </h3>
    </template>

    <div class="p-4" :dir="lang === 'ar' ? 'rtl' : 'ltr'">
      <!-- Activation Message -->
      <p class="text-center text-green-700 font-medium mb-6">
        {{ t("Activation_msg") }}
        <span class="font-bold">{{ record.email }}</span>
        , {{ t("please") }}
      </p>

      <form @submit.prevent="confirm" class="space-y-6">
        <!-- New Password (only for reset) -->
        <div v-if="isReset" class="space-y-2">
          <label class="block text-sm font-semibold text-gray-700">
            {{ t("NewPassword") }} <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <input
              :type="showPassword ? 'text' : 'password'"
              v-model="password"
              required
              dir="ltr"
              autocomplete="new-password"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition-all duration-200"
              :placeholder="t('NewPassword')"
            />
            <button
              type="button"
              class="absolute inset-y-0 right-0 pr-3 flex items-center"
              @click="showPassword = !showPassword"
            >
              <svg v-if="!showPassword" class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              <svg v-else class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
              </svg>
            </button>
          </div>
        </div>

        <!-- OTP Code -->
        <div class="space-y-2">
          <label class="block text-sm font-semibold text-gray-700 text-center">
            {{ t("otpCode") }}
          </label>
          <OtpInput
            v-model="otpCode"
            :integer-only="true"
            :length="6"
          />
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
          <button
            type="button"
            @click="close"
            class="px-4 py-2 text-sm font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors"
          >
            {{ t("close") }}
          </button>
          <button
            type="submit"
            :disabled="!otpCode"
            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ isReset ? t("save") : t("activate") }}
          </button>
        </div>
      </form>
    </div>
  </UiModal>
</template>
