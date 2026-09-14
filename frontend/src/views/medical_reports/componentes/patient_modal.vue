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
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="patientdialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="close"></div>
        <div
          class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden"
          :dir="lang === 'ar' ? 'rtl' : 'ltr'"
        >
          <!-- Header -->
          <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">{{ patient?.name || "" }}</h2>
            <button @click="close" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Content -->
          <div class="p-6 overflow-y-auto max-h-[calc(90vh-150px)]">
            <div v-if="patient" class="overflow-x-auto">
              <table class="w-full border border-gray-300 rounded-xl overflow-hidden">
                <tbody>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-start bg-gray-50 font-semibold text-gray-700 whitespace-nowrap w-1/3">
                      {{ t("name") }}
                    </th>
                    <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                      {{ patient?.name }}
                    </td>
                  </tr>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-start bg-gray-50 font-semibold text-gray-700 whitespace-nowrap">
                      {{ t("national_id_no") }}
                    </th>
                    <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                      {{ patient?.national_id_no }}
                    </td>
                  </tr>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-start bg-gray-50 font-semibold text-gray-700 whitespace-nowrap">
                      {{ t("gender") }}
                    </th>
                    <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                      {{ patient?.gender }}
                    </td>
                  </tr>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-start bg-gray-50 font-semibold text-gray-700 whitespace-nowrap">
                      {{ t("dob") }}
                    </th>
                    <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                      {{ patient?.dob }}
                    </td>
                  </tr>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-start bg-gray-50 font-semibold text-gray-700 whitespace-nowrap">
                      {{ t("age") }}
                    </th>
                    <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                      {{ patient?.age_unit + " " + patient?.age }}
                    </td>
                  </tr>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-start bg-gray-50 font-semibold text-gray-700 whitespace-nowrap">
                      {{ t("phone_number") }}
                    </th>
                    <td class="px-4 py-3 text-gray-700 whitespace-nowrap" dir="ltr">
                      {{ patient?.phone }}
                    </td>
                  </tr>
                  <tr>
                    <th class="px-4 py-3 text-start bg-gray-50 font-semibold text-gray-700 whitespace-nowrap">
                      {{ t("address") }}
                    </th>
                    <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                      {{ patient?.address }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-else class="bg-blue-50 border border-blue-200 rounded-xl p-8 text-center">
              <svg class="w-12 h-12 text-blue-400 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <p class="text-blue-700 font-medium">{{ t("noData") }}</p>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex justify-end gap-2 p-4 border-t border-gray-200">
            <button
              @click="close"
              class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
            >
              {{ t("close") }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
