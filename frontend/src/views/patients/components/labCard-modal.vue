<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { usePatientsStore } from "@/store/modules/patients";
import { t } from "@/utils/helper";
import QrcodeVue from "qrcode.vue";

const patientsStore = usePatientsStore();
const { labDialog, record } = storeToRefs(patientsStore);

const lang = computed(() => localStorage.getItem("locale") || "ar");
const ImageURL = computed(() => import.meta.env.VITE_ImageURL || "");

const appBaseUrl = import.meta.env.VITE_APP_URL || window.location.origin;

const getPatientReportLink = (patientId) => {
  return `${appBaseUrl}/medical-reports/${patientId}`;
};

const close = () => {
  labDialog.value = false;
};
</script>

<template>
  <UiModal
    v-model="labDialog"
    :closable="true"
    size="lg"
  >
    <template #header>
      <h3 class="text-lg font-semibold text-gray-800">
        {{ t("patient_card") }}
      </h3>
    </template>

    <div class="p-6" :dir="lang === 'ar' ? 'rtl' : 'ltr'">
      <div class="bg-gray-50 rounded-xl p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
          <!-- Patient Image -->
          <div class="flex justify-center">
            <img
              :src="record?.image ? ImageURL + record.image : '/src/assets/user.png'"
              alt="Patient"
              class="w-40 h-52 object-cover rounded-xl shadow-md"
            />
          </div>

          <!-- Patient Info -->
          <div class="space-y-3">
            <div class="flex gap-4">
              <span class="font-semibold text-gray-700 w-28">{{ t("name") }}:</span>
              <span class="text-gray-600">{{ record?.name }}</span>
            </div>
            <div class="flex gap-4">
              <span class="font-semibold text-gray-700 w-28">{{ t("dob") }}:</span>
              <span class="text-gray-600">{{ record?.dob }}</span>
            </div>
            <div class="flex gap-4">
              <span class="font-semibold text-gray-700 w-28">{{ t("gender") }}:</span>
              <span class="text-gray-600">{{ record?.gender }}</span>
            </div>
            <div class="flex gap-4">
              <span class="font-semibold text-gray-700 w-28">{{ t("phone_number") }}:</span>
              <span class="text-gray-600">{{ record?.phone_number }}</span>
            </div>
            <div class="flex gap-4">
              <span class="font-semibold text-gray-700 w-28">{{ t("address") }}:</span>
              <span class="text-gray-600">{{ record?.address }}</span>
            </div>
            <div class="flex gap-4">
              <span class="font-semibold text-gray-700 w-28">{{ t("national_id_no") }}:</span>
              <span class="text-gray-600">{{ record?.national_id_no }}</span>
            </div>
          </div>

          <!-- QR Code -->
          <div class="flex justify-center">
            <div class="bg-white p-4 rounded-xl shadow-md">
              <QrcodeVue
                :value="getPatientReportLink(record?.id)"
                :size="150"
                level="H"
                render-as="svg"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Close Button -->
      <div class="flex justify-center mt-6">
        <button
          @click="close"
          class="px-6 py-2 text-sm font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors"
        >
          {{ t("close") }}
        </button>
      </div>
    </div>
  </UiModal>
</template>
