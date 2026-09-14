<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useinvoicesStore } from "@/store/modules/invoices";
import { t } from "@/utils/helper";

const invoicesStore = useinvoicesStore();
const { record, Patient_dueDialog } = storeToRefs(invoicesStore);

const lang = computed(() => localStorage.getItem("locale") || "ar");

const close = () => {
  record.value = [];
  Patient_dueDialog.value = false;
};
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="Patient_dueDialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="close"></div>
        <div
          class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden"
          :dir="lang === 'ar' ? 'rtl' : 'ltr'"
        >
          <!-- Header -->
          <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">{{ t("the_tests") }}</h2>
            <button @click="close" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Content -->
          <div class="p-6 overflow-y-auto max-h-[calc(90vh-150px)]">
            <template v-if="record">
              <!-- Test Groups -->
              <div v-for="test_group in record.test_groups" :key="test_group" class="mb-6 text-center">
                <p
                  class="font-bold mb-2"
                  :class="test_group.is_done ? 'text-green-600' : 'text-yellow-600'"
                >
                  {{ test_group.name }}
                </p>
                <table class="w-full border border-gray-200 rounded-xl overflow-hidden">
                  <tbody>
                    <tr
                      v-for="test in test_group.tests"
                      :key="test"
                      class="border-b border-gray-200"
                    >
                      <td
                        class="px-4 py-2 whitespace-nowrap"
                        :class="test_group.is_done ? 'text-green-600' : 'text-yellow-600'"
                      >
                        {{ test.name }}
                      </td>
                    </tr>
                    <tr
                      v-for="culture in test_group.cultures"
                      :key="culture"
                      class="border-b border-gray-200"
                    >
                      <td
                        class="px-4 py-2 whitespace-nowrap"
                        :class="test_group.is_done ? 'text-green-600' : 'text-yellow-600'"
                      >
                        {{ culture.name }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Individual Items -->
              <table v-if="record" class="w-full border border-gray-200 rounded-xl overflow-hidden">
                <tbody>
                  <tr v-for="test in record.tests" :key="test" class="border-b border-gray-200">
                    <td
                      class="px-4 py-2 whitespace-nowrap text-center"
                      :class="test.is_done ? 'text-green-600' : 'text-yellow-600'"
                    >
                      {{ test.name }}
                    </td>
                  </tr>
                  <tr v-for="culture in record.cultures" :key="culture" class="border-b border-gray-200">
                    <td
                      class="px-4 py-2 whitespace-nowrap text-center"
                      :class="culture.is_done ? 'text-green-600' : 'text-yellow-600'"
                    >
                      {{ culture.name }}
                    </td>
                  </tr>
                  <tr v-for="packag in record.packages" :key="packag" class="border-b border-gray-200">
                    <td
                      class="px-4 py-2 whitespace-nowrap text-center"
                      :class="packag.is_done ? 'text-green-600' : 'text-yellow-600'"
                    >
                      {{ packag.name }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </template>

            <div v-else class="bg-blue-50 border border-blue-200 rounded-xl p-8 text-center">
              <svg class="w-12 h-12 text-blue-400 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <p class="text-blue-700 font-medium">{{ t("noData") }}</p>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex justify-center gap-2 p-4 border-t border-gray-200">
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
