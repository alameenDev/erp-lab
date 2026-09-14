<script setup>
import { ref, computed } from "vue";
import { storeToRefs } from "pinia";
import { useinvoicesStore } from "@/store/modules/invoices";
import { useresultStatusStore } from "@/store/modules/result-status";
import { t, dateTimeFormat } from "@/utils/helper";

const invoicesStore = useinvoicesStore();
const resultStatusStore = useresultStatusStore();
const { pationtHistoryList, pationtHistoryDialog } = storeToRefs(invoicesStore);
const { resultStatus } = storeToRefs(resultStatusStore);

const lang = computed(() => localStorage.getItem("locale") || "ar");
const activeTab = ref("tests");

const close = () => {
  pationtHistoryDialog.value = false;
};

const print = () => {
  const content = document.getElementById("history");
  if (!content) return;
  const iframe = document.createElement("iframe");
  iframe.style.position = "absolute";
  iframe.style.width = "0";
  iframe.style.height = "0";
  iframe.style.border = "none";
  document.body.appendChild(iframe);
  const doc = iframe.contentWindow.document;
  doc.open();
  doc.write(`<html><head><title>Print</title>
    <style>
      body { font-family: Arial, sans-serif; direction: rtl; }
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #000; padding: 4px 8px; text-align: center; }
    </style>
  </head><body>${content.innerHTML}</body></html>`);
  doc.close();
  iframe.contentWindow.focus();
  iframe.contentWindow.print();
  setTimeout(() => document.body.removeChild(iframe), 1000);
};
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="pationtHistoryDialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="close"></div>
        <div
          class="relative bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-hidden"
          :dir="lang === 'ar' ? 'rtl' : 'ltr'"
        >
          <!-- Header -->
          <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">{{ t("pationtHistory") }}</h2>
            <button @click="close" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Content -->
          <div class="p-6 overflow-y-auto max-h-[calc(90vh-150px)]">
            <template v-if="pationtHistoryList">
              <div class="border border-gray-200 rounded-xl p-6 mb-4">
                <!-- Tabs -->
                <div class="flex gap-2 mb-4 border-b border-gray-200">
                  <button
                    @click="activeTab = 'tests'"
                    :class="[
                      'px-4 py-2 font-medium transition-colors -mb-px',
                      activeTab === 'tests'
                        ? 'text-blue-600 border-b-2 border-blue-600'
                        : 'text-gray-500 hover:text-gray-700'
                    ]"
                  >
                    {{ t("tests") }}
                  </button>
                  <button
                    @click="activeTab = 'cultures'"
                    :class="[
                      'px-4 py-2 font-medium transition-colors -mb-px',
                      activeTab === 'cultures'
                        ? 'text-blue-600 border-b-2 border-blue-600'
                        : 'text-gray-500 hover:text-gray-700'
                    ]"
                  >
                    {{ t("cultures") }}
                  </button>
                </div>

                <!-- Tests Tab -->
                <div v-if="activeTab === 'tests'">
                  <table v-if="pationtHistoryList.tests?.length > 0" class="w-full">
                    <thead>
                      <tr class="bg-gray-700 text-white">
                        <th class="px-4 py-3 text-center text-sm font-semibold">{{ t("name") }}</th>
                        <th class="px-4 py-3 text-center text-sm font-semibold">{{ t("Result") }}</th>
                        <th class="px-4 py-3 text-center text-sm font-semibold">{{ t("Result_Type") }}</th>
                        <th class="px-4 py-3 text-center text-sm font-semibold">{{ t("theDate") }}</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-gray-50">
                      <tr v-for="test in pationtHistoryList.tests" :key="test" class="hover:bg-gray-100">
                        <td class="px-4 py-3 text-center text-blue-600 font-bold">{{ test.name }}</td>
                        <td class="px-4 py-3 text-center text-gray-700">{{ test.result ?? "---" }}</td>
                        <td class="px-4 py-3 text-center text-gray-700">{{ test.status ?? "---" }}</td>
                        <td class="px-4 py-3 text-center text-gray-700">{{ dateTimeFormat(test.updated_at) }}</td>
                      </tr>
                    </tbody>
                  </table>
                  <div v-else class="bg-blue-50 border border-blue-200 rounded-xl p-8 text-center">
                    <svg class="w-12 h-12 text-blue-400 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-blue-700 font-medium">{{ t("noData") }}</p>
                  </div>
                </div>

                <!-- Cultures Tab -->
                <div v-if="activeTab === 'cultures'">
                  <table v-if="pationtHistoryList.cultures?.length > 0" class="w-full">
                    <thead>
                      <tr class="bg-gray-700 text-white">
                        <th class="px-4 py-3 text-center text-sm font-semibold">{{ t("name") }}</th>
                        <th class="px-4 py-3 text-center text-sm font-semibold">{{ t("Result") }}</th>
                        <th class="px-4 py-3 text-center text-sm font-semibold">{{ t("Result_Type") }}</th>
                        <th class="px-4 py-3 text-center text-sm font-semibold">{{ t("theDate") }}</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-gray-50">
                      <tr v-for="culture in pationtHistoryList.cultures" :key="culture" class="hover:bg-gray-100">
                        <td class="px-4 py-3 text-center text-blue-600 font-bold">{{ culture.name }}</td>
                        <td class="px-4 py-3 text-center text-gray-700">{{ culture.result }}</td>
                        <td class="px-4 py-3 text-center text-gray-700">{{ culture.status ?? "---" }}</td>
                        <td class="px-4 py-3 text-center text-gray-700">{{ dateTimeFormat(culture.updated_at) }}</td>
                      </tr>
                    </tbody>
                  </table>
                  <div v-else class="bg-blue-50 border border-blue-200 rounded-xl p-8 text-center">
                    <svg class="w-12 h-12 text-blue-400 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-blue-700 font-medium">{{ t("noData") }}</p>
                  </div>
                </div>
              </div>
            </template>

            <!-- Hidden Print Content -->
            <div id="history" class="hidden">
              <template v-if="pationtHistoryList">
                <div>
                  <h2>{{ t("tests") }}</h2>
                  <table v-if="pationtHistoryList.tests?.length > 0" class="w-full border-collapse">
                    <thead>
                      <tr class="bg-gray-700 text-white">
                        <th class="border p-2">{{ t("name") }}</th>
                        <th class="border p-2">{{ t("Result") }}</th>
                        <th class="border p-2">{{ t("Result_Type") }}</th>
                        <th class="border p-2">{{ t("theDate") }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="test in pationtHistoryList.tests" :key="test">
                        <td class="border p-2 text-blue-600 font-bold">{{ test.name }}</td>
                        <td class="border p-2">{{ test.result ?? "---" }}</td>
                        <td class="border p-2">{{ test.status ?? "---" }}</td>
                        <td class="border p-2">{{ dateTimeFormat(test.updated_at) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div class="mt-6">
                  <h2>{{ t("cultures") }}</h2>
                  <table v-if="pationtHistoryList.cultures?.length > 0" class="w-full border-collapse">
                    <thead>
                      <tr class="bg-gray-700 text-white">
                        <th class="border p-2">{{ t("name") }}</th>
                        <th class="border p-2">{{ t("Result") }}</th>
                        <th class="border p-2">{{ t("Result_Type") }}</th>
                        <th class="border p-2">{{ t("theDate") }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="culture in pationtHistoryList.cultures" :key="culture">
                        <td class="border p-2 text-blue-600 font-bold">{{ culture.name }}</td>
                        <td class="border p-2">{{ culture.result }}</td>
                        <td class="border p-2">{{ culture.status ?? "---" }}</td>
                        <td class="border p-2">{{ dateTimeFormat(culture.updated_at) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </template>
            </div>

            <div v-if="!pationtHistoryList" class="bg-blue-50 border border-blue-200 rounded-xl p-8 text-center">
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
            <button
              @click="print"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
            >
              {{ t("print") }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
