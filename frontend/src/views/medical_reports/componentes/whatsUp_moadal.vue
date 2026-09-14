<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useinvoicesStore } from "@/store/modules/invoices";
import { usePatientsStore } from "@/store/modules/patients";
import { useLabSettingsStore } from "@/store/modules/labSettings";
import { t, alertSuccess } from "@/utils/helper";
import printResult from "./print_Result.vue";

const props = defineProps({
  withBackground: { type: Boolean, default: false },
  background: { type: String, default: "" },
});

const invoicesStore = useinvoicesStore();
const patientsStore = usePatientsStore();
const labSettingsStore = useLabSettingsStore();
const { printRecord, WhatsUpDialog, Pdfurl } = storeToRefs(invoicesStore);

const lang = computed(() => localStorage.getItem("locale") || "ar");

const buildBackgroundHtml = (innerHtml) => {
  const bg = props.background;
  const margins = labSettingsStore.settings.print_margins || { top: 20, bottom: 20, left: 15, right: 15 };

  const resultEl = document.getElementById("Result");
  const headerEl = resultEl?.querySelector(".border-t.border-b.border-black");
  const headerHtml = headerEl ? headerEl.outerHTML : "";

  const wrapper = document.createElement("div");
  wrapper.innerHTML = innerHtml;
  wrapper.querySelectorAll(".border-t.border-b.border-black").forEach((el) => el.remove());
  const contentHtml = wrapper.innerHTML;

  return `
    <div style="position:relative;width:210mm;min-height:297mm;margin:0 auto;">
      <img src="${bg}" style="position:absolute;top:0;left:0;width:100%;height:100%;z-index:0;" />
      <table style="width:100%;border-collapse:collapse;position:relative;z-index:1;">
        <thead><tr><td style="padding:${margins.top}mm ${margins.right}mm 2mm ${margins.left}mm;">${headerHtml}</td></tr></thead>
        <tbody><tr><td style="padding:0 ${margins.right}mm ${margins.bottom}mm ${margins.left}mm;">${contentHtml}</td></tr></tbody>
      </table>
    </div>
  `;
};

const sendMsg = async () => {
  const resultEl = document.getElementById("Result");
  let invoiceContent;

  if (props.withBackground && props.background) {
    invoiceContent = buildBackgroundHtml(resultEl.innerHTML);
  } else {
    invoiceContent = resultEl.outerHTML;
  }

  await invoicesStore.pdf(invoiceContent, printRecord.value?.id);
  await patientsStore.whatsapp(printRecord.value?.patient?.phone, Pdfurl.value.path);
  alertSuccess(t("alertSuccess"));
  close();
};

const close = () => {
  WhatsUpDialog.value = false;
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
            <div class="flex items-center gap-3">
              <h2 class="text-xl font-semibold text-gray-800">{{ t("sendMessage") }}</h2>
              <span v-if="withBackground && background" class="px-2.5 py-1 text-xs font-medium bg-indigo-50 text-indigo-600 rounded-full">{{ t("with_form") }}</span>
              <span v-else class="px-2.5 py-1 text-xs font-medium bg-green-50 text-green-600 rounded-full">{{ t("without_form") }}</span>
            </div>
            <button @click="close" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Content -->
          <div class="p-6 overflow-y-auto max-h-[calc(90vh-150px)]">
            <printResult></printResult>
          </div>

          <!-- Footer -->
          <div class="flex justify-end gap-2 p-4 border-t border-gray-200">
            <button
              @click="close"
              class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
            >
              {{ t("close") }}
            </button>
            <button
              @click="sendMsg"
              class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2"
            >
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
              </svg>
              {{ t("sendMessage") }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
