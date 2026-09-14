<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useinvoicesStore } from "@/store/modules/invoices";
import { t } from "@/utils/helper";

const invoicesStore = useinvoicesStore();
const { patientdialog, patient } = storeToRefs(invoicesStore);

const lang = computed(() => localStorage.getItem("locale") || "ar");

const close = () => {
  patient.value = [];
  patientdialog.value = false;
};
</script>

<template>
  <UiModal
    v-model="patientdialog"
    :closable="true"
    size="md"
  >
    <template #header>
      <h3 class="text-lg font-semibold text-gray-800">
        {{ patient?.name || t("Patient_details") }}
      </h3>
    </template>

    <div class="p-4" :dir="lang === 'ar' ? 'rtl' : 'ltr'">
      <div v-if="patient" class="overflow-hidden rounded-xl border border-gray-200">
        <table class="w-full">
          <tbody class="divide-y divide-gray-200">
            <tr class="hover:bg-gray-50">
              <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 bg-gray-50 w-1/3">
                {{ t("name") }}
              </th>
              <td class="px-4 py-3 text-sm text-gray-600">
                {{ patient?.name }}
              </td>
            </tr>
            <tr class="hover:bg-gray-50">
              <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 bg-gray-50">
                {{ t("national_id_no") }}
              </th>
              <td class="px-4 py-3 text-sm text-gray-600">
                {{ patient?.national_id_no }}
              </td>
            </tr>
            <tr class="hover:bg-gray-50">
              <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 bg-gray-50">
                {{ t("gender") }}
              </th>
              <td class="px-4 py-3 text-sm text-gray-600">
                {{ patient?.gender }}
              </td>
            </tr>
            <tr class="hover:bg-gray-50">
              <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 bg-gray-50">
                {{ t("dob") }}
              </th>
              <td class="px-4 py-3 text-sm text-gray-600">
                {{ patient?.dob }}
              </td>
            </tr>
            <tr class="hover:bg-gray-50">
              <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 bg-gray-50">
                {{ t("age") }}
              </th>
              <td class="px-4 py-3 text-sm text-gray-600">
                {{ patient?.age_unit }} {{ patient?.age }}
              </td>
            </tr>
            <tr class="hover:bg-gray-50">
              <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 bg-gray-50">
                {{ t("phone_number") }}
              </th>
              <td class="px-4 py-3 text-sm text-gray-600" dir="ltr">
                {{ patient?.phone }}
              </td>
            </tr>
            <tr class="hover:bg-gray-50">
              <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 bg-gray-50">
                {{ t("address") }}
              </th>
              <td class="px-4 py-3 text-sm text-gray-600">
                {{ patient?.address }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else class="bg-blue-50 border border-blue-200 rounded-xl p-6 text-center">
        <svg class="w-12 h-12 text-blue-400 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-blue-700 font-medium">{{ t("noData") }}</p>
      </div>

      <div class="flex justify-end mt-4 pt-4 border-t border-gray-200">
        <button
          @click="close"
          class="px-4 py-2 text-sm font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors"
        >
          {{ t("close") }}
        </button>
      </div>
    </div>
  </UiModal>
</template>
