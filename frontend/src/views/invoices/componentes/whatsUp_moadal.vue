<script setup>
import { computed, ref } from "vue";
import { storeToRefs } from "pinia";
import { useinvoicesStore } from "@/store/modules/invoices";
import { usePatientsStore } from "@/store/modules/patients";
import { t, alertSuccess } from "@/utils/helper";
import printInvoice from "./print_invoice.vue";

const invoicesStore = useinvoicesStore();
const patientsStore = usePatientsStore();
const { printRecord, WhatsUpDialog, Pdfurl } = storeToRefs(invoicesStore);
const { pdf } = invoicesStore;
const { whatsapp } = patientsStore;

const lang = computed(() => localStorage.getItem("locale") || "ar");
const printInvoiceRef = ref(null);
const sending = ref(false);

const close = () => {
  WhatsUpDialog.value = false;
};

const sendMsg = async () => {
  if (sending.value) return;
  sending.value = true;
  try {
    // Build a real, styled PDF (base template + the lab's saved Document
    // Settings) instead of uploading the raw, unstyled page HTML — that
    // old approach produced a file with none of the lab's print settings
    // applied (colors, fonts, margins, logo, barcode/QR visibility, etc.).
    const pdfBlob = await printInvoiceRef.value?.generatePdfBlob();
    if (!pdfBlob) {
      alertSuccess(t("download_failed") || "Failed to generate PDF");
      sending.value = false;
      return;
    }
    await pdf(pdfBlob, printRecord.value?.id);
    await whatsapp(printRecord.value?.patient?.phone, Pdfurl.value.path);
    alertSuccess(t("alertSuccess"));
    close();
  } finally {
    sending.value = false;
  }
};
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="WhatsUpDialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="close"></div>
        <div
          class="relative bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-hidden"
          :dir="lang === 'ar' ? 'rtl' : 'ltr'"
        >
          <!-- Header -->
          <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">{{ t("sendMessage") }}</h2>
            <button @click="close" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Content -->
          <div class="p-6 overflow-y-auto max-h-[calc(90vh-150px)]">
            <printInvoice ref="printInvoiceRef"></printInvoice>
          </div>

          <!-- Footer -->
          <div class="flex justify-end gap-2 p-4 border-t border-gray-200">
            <button
              @click="close"
              class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
            >
              {{ t('close') }}
            </button>
            <button
              @click="sendMsg"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700"
            >
              {{ t('sendMessage') }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
