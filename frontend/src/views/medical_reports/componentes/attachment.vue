<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useinvoicesStore } from "@/store/modules/invoices";
import { t } from "@/utils/helper";

const invoicesStore = useinvoicesStore();
const { attachments, AttachDialog } = storeToRefs(invoicesStore);

const lang = computed(() => localStorage.getItem("locale") || "ar");

const close = () => {
  attachments.value = [];
  AttachDialog.value = false;
};

const openLink = (url) => {
  window.open(url, "_blank");
};
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="AttachDialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="close"></div>
        <div
          class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden"
          :dir="lang === 'ar' ? 'rtl' : 'ltr'"
        >
          <!-- Header -->
          <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">{{ t("attachments") }}</h2>
            <button @click="close" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Content -->
          <div class="p-6 overflow-y-auto max-h-[calc(90vh-150px)]">
            <div v-if="attachments?.length > 0" class="overflow-x-auto">
              <table class="w-full border border-gray-300 rounded-xl overflow-hidden">
                <thead>
                  <tr class="bg-gray-100">
                    <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700 border-b border-gray-300">
                      {{ t("name") }}
                    </th>
                    <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700 border-b border-gray-300">
                      {{ t("file") }}
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                  <tr v-for="attach in attachments" :key="attach" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-center text-gray-700">{{ attach.name }}</td>
                    <td class="px-4 py-3 text-center">
                      <a
                        :href="attach.file"
                        target="_blank"
                        rel="noopener"
                        @click.prevent="openLink(attach.file)"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                      >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                        {{ t("show_result_date") }}
                      </a>
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
