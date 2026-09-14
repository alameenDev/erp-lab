<script setup>
import { ref, computed, watch, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useinvoicesStore } from "@/store/modules/invoices";
import { useresultStatusStore } from "@/store/modules/result-status";
import { useLabSettingsStore } from "@/store/modules/labSettings";
import { t, dateTimeFormat } from "@/utils/helper";
import JsBarcode from "jsbarcode";

const props = defineProps({
  withBackground: { type: Boolean, default: false },
  backgroundImage: { type: String, default: "" },
});

const invoicesStore = useinvoicesStore();
const resultStatusStore = useresultStatusStore();
const labSettingsStore = useLabSettingsStore();
const { printRecord, print_work_sheetDialog, selectedItems, isAllSelected } = storeToRefs(invoicesStore);
const { resultStatus } = storeToRefs(resultStatusStore);

const lang = computed(() => localStorage.getItem("locale") || "ar");

// Resolve status name from result_status_id_fk (nested tests in packages/test_groups only have the ID)
const getStatusName = (item) => {
  if (item.status) return item.status;
  if (item.result_status_id_fk && resultStatus.value?.length) {
    const found = resultStatus.value.find((s) => s.value == item.result_status_id_fk);
    return found?.label || null;
  }
  return null;
};

// Flatten all items from tests, cultures, packages, and test_groups into a single list
const allItems = computed(() => {
  const items = [];

  // Standalone tests
  if (printRecord.value?.tests?.length > 0) {
    printRecord.value.tests.forEach((item) => {
      items.push({ ...item, _type: "test" });
    });
  }

  // Test group items (nested tests and cultures)
  if (printRecord.value?.test_groups?.length > 0) {
    printRecord.value.test_groups.forEach((group) => {
      // Add a group header marker
      items.push({ _type: "group_header", name: group.group_name, is_done: group.is_done });
      if (group.tests?.length > 0) {
        group.tests.forEach((item) => {
          items.push({ ...item, _type: "test_group_test", _group: group.group_name });
        });
      }
      if (group.cultures?.length > 0) {
        group.cultures.forEach((item) => {
          items.push({ ...item, _type: "test_group_culture", _group: group.group_name });
        });
      }
    });
  }

  // Standalone cultures
  if (printRecord.value?.cultures?.length > 0) {
    printRecord.value.cultures.forEach((item) => {
      items.push({ ...item, _type: "culture" });
    });
  }

  // Packages (nested tests and cultures)
  if (printRecord.value?.packages?.length > 0) {
    printRecord.value.packages.forEach((pkg) => {
      items.push({ _type: "group_header", name: pkg.name, is_done: pkg.is_done });
      if (pkg.tests?.length > 0) {
        pkg.tests.forEach((item) => {
          items.push({ ...item, _type: "package_test", _group: pkg.name });
        });
      }
      if (pkg.cultures?.length > 0) {
        pkg.cultures.forEach((item) => {
          items.push({ ...item, _type: "package_culture", _group: pkg.name });
        });
      }
    });
  }

  return items;
});

// Selectable items (excludes group headers)
const selectableItems = computed(() => allItems.value.filter((i) => i._type !== "group_header"));

const generateBarcodeImage = (value) => {
  if (!value) return "";
  const canvas = document.createElement("canvas");
  JsBarcode(canvas, value, {
    format: "CODE128",
    displayValue: true,
    width: 1,
    height: 15,
  });
  return canvas.toDataURL("image/png");
};

const checkAll = () => {
  if (isAllSelected.value) {
    selectedItems.value = [];
  } else {
    selectedItems.value = [...selectableItems.value];
  }
  isAllSelected.value = !isAllSelected.value;
};

const print = () => {
  setTimeout(function () {
    const jobContent = document.getElementById("worksheet").innerHTML;
    const hasBg = props.withBackground && props.backgroundImage;
    const margins = hasBg
      ? (labSettingsStore.settings.print_margins || { top: 20, bottom: 20, left: 15, right: 15 })
      : null;

    const baseCss = `
      @media print { body, .page { margin: 0px !important; box-shadow: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; color: #000; } }
      body {font-family: Arial, sans-serif;font-size: 14px;margin: 0;padding: 0; color: #000;}
      @page { size: portrait; margin: 0 !important; }
      .report-container {
        border: 1px solid black;
        padding: 20px;
        width: 80%;
        margin: auto;
      }
      .commit td{
        border:none !important;
      }
      .border{border: 1px solid black; min-height: 25px; margin: 0px 7px; padding: 5px;width: 150px;text-align: center;}
      .header-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid black;
        padding-bottom: 10px;
        margin-bottom: 10px;
      }
      .barcode {
        text-align: center;
      }
      .barcode img {
        height: 50px;
      }
      .details {
        text-align: left;
        font-size: 14px;
      }
      .details div {
        margin-bottom: 8px;
      }
      .test-section {
        margin-top: 20px;
        border-top: 1px solid black;
        padding-top: 10px;
      }
      .test-section h3 {
        background-color: #f0f0f0;
        padding: 5px;
        margin-bottom: 0;
        text-align: center;
      }
      .table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
      }
      .table, .table th, .table td {
        border: 1px solid black;
      }
      th, td {
        text-align: center;
        padding: 8px;
      }
      .comment {
        font-weight: bold;
        padding-left: 10px;
      }`;

    const bgCss = hasBg
      ? `
      .bg-layer { position: fixed; top: 0; left: 0; width: 210mm; height: 297mm; background-image: url('${props.backgroundImage}'); background-size: 100% 100%; background-repeat: no-repeat; z-index: -1; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .margin-table { width: 100%; border-collapse: collapse; }
      .margin-table > thead > tr > td,
      .margin-table > tfoot > tr > td { border: none !important; padding: 0 !important; }
      .margin-table > tbody > tr > td { border: none !important; padding: 0 ${margins.right}mm 0 ${margins.left}mm !important; }
      .margin-top-spacer { height: ${margins.top}mm; }
      .margin-bottom-spacer { height: ${margins.bottom}mm; }
      `
      : "";

    let bodyContent;
    if (hasBg) {
      bodyContent = `<div class="bg-layer"></div>
        <table class="margin-table">
          <thead><tr><td><div class="margin-top-spacer"></div></td></tr></thead>
          <tfoot><tr><td><div class="margin-bottom-spacer"></div></td></tr></tfoot>
          <tbody><tr><td>${jobContent}</td></tr></tbody>
        </table>`;
    } else {
      bodyContent = jobContent;
    }

    const printFrame = document.createElement("iframe");
    printFrame.style.position = "absolute";
    printFrame.style.width = "0px";
    printFrame.style.height = "0px";
    printFrame.style.border = "none";
    document.body.appendChild(printFrame);

    const frameDoc = printFrame.contentWindow.document;
    frameDoc.open();
    frameDoc.write(`
      <html>
        <head>
          <title>Print Job</title>
          <style>${baseCss}${bgCss}</style>
        </head>
        <body>${bodyContent}</body>
      </html>
    `);
    frameDoc.close();

    printFrame.contentWindow.focus();
    printFrame.contentWindow.print();
    printFrame.contentWindow.onafterprint = () => {
      document.body.removeChild(printFrame);
    };
  }, 50);
};

const close = () => {
  print_work_sheetDialog.value = false;
  selectedItems.value = [];
  isAllSelected.value = false;
};

watch(selectedItems, () => {
  isAllSelected.value = selectedItems.value.length === selectableItems.value.length && selectableItems.value.length > 0;
});

// Auto-select all items when modal opens
watch(print_work_sheetDialog, (val) => {
  if (val && selectableItems.value.length > 0) {
    selectedItems.value = [...selectableItems.value];
    isAllSelected.value = true;
  }
});

onMounted(() => {
  if (!resultStatus.value?.length) {
    resultStatusStore.GetresultStatus();
  }
});
</script>

<template>
  <!-- Dialog Modal -->
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="print_work_sheetDialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="close"></div>
        <div
          class="relative bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-hidden"
          :dir="lang === 'ar' ? 'rtl' : 'ltr'"
        >
          <!-- Header -->
          <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">{{ t("print_work_sheet") }}</h2>
            <button @click="close" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Content -->
          <div class="p-6 overflow-y-auto max-h-[calc(90vh-150px)]">
            <table class="w-full border border-gray-300">
              <thead>
                <tr class="bg-[#004e54] text-white">
                  <th class="px-4 py-3 text-start text-sm font-semibold" style="width: 87%">{{ t("the_tests") }}</th>
                  <th class="px-4 py-3 text-center text-sm font-semibold">{{ t("done") }}</th>
                  <th class="px-4 py-3 text-center text-sm font-semibold">
                    <button
                      @click="checkAll"
                      class="p-1 rounded-full hover:bg-white/20"
                    >
                      <svg v-if="isAllSelected" class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                      </svg>
                      <svg v-else class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <circle cx="12" cy="12" r="10" stroke-width="2" />
                      </svg>
                    </button>
                  </th>
                </tr>
              </thead>
              <tbody>
                <template v-for="(item, index) in allItems" :key="index">
                  <!-- Group Header Row -->
                  <tr v-if="item._type === 'group_header'" class="bg-[#004e54]/10">
                    <td class="px-4 py-2 text-start font-bold text-[#004e54]" colspan="3">
                      {{ item.name }}
                    </td>
                  </tr>
                  <!-- Normal Item Row -->
                  <tr v-else :class="index % 2 === 0 ? 'bg-gray-200' : 'bg-white'">
                    <td
                      class="px-4 py-3 text-start font-medium"
                      :class="item.is_done ? 'text-green-600' : 'text-red-600'"
                      style="width: 87%"
                    >
                      <span v-if="item._group" class="text-xs text-gray-400 me-1">&bull;</span>
                      {{ item.name || item.report_name }}
                    </td>
                    <td class="px-4 py-3 text-center">
                      <svg v-if="item.is_done" class="w-5 h-5 mx-auto text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                      </svg>
                      <svg v-else class="w-5 h-5 mx-auto text-red-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd" />
                      </svg>
                    </td>
                    <td class="px-4 py-3 text-center">
                      <input type="checkbox" :value="item" v-model="selectedItems" class="w-4 h-4" />
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
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
              @click="print"
              :disabled="selectedItems.length === 0"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ t("print") }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- Hidden Print Content -->
  <div class="hidden" id="worksheet">
    <!-- Header Section -->
    <div class="header-section">
      <div class="details">
        <table>
          <tbody>
            <tr>
              <th>
                <span><strong>Barcode</strong></span>
              </th>
              <td>
                <span>
                  <div>
                    <span>
                      <img :src="generateBarcodeImage(printRecord?.barcode)" alt="Barcode" />
                    </span>
                  </div>
                </span>
              </td>
            </tr>
            <tr>
              <th>
                <div><strong>age/ Sex</strong></div>
              </th>
              <td>
                <div class="border">
                  <strong>
                    {{ printRecord?.patient?.age + printRecord?.patient?.age_unit }} /
                    {{ printRecord?.patient?.gender }}
                  </strong>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="details">
        <table>
          <tbody>
            <tr>
              <th>
                <div><strong>patient name</strong></div>
              </th>
              <td>
                <div class="border">
                  <strong>{{ printRecord?.patient?.name }}</strong>
                </div>
              </td>
            </tr>
            <tr>
              <th>
                <div><strong>Request Date</strong></div>
              </th>
              <td>
                <div class="border">
                  <strong>{{ dateTimeFormat(printRecord?.registration_date) }}</strong>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Test Sections -->
    <div class="test-section" v-for="(test, idx) in selectedItems" :key="idx">
      <h3>{{ test.name || test.report_name }}</h3>
      <table class="table">
        <thead>
          <tr>
            <th>name</th>
            <th>Result</th>
            <th>Unit</th>
            <th>tests reference ranges</th>
            <th>the Status</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>{{ test.name || test.report_name }}</td>
            <td>{{ test.result || "---" }}</td>
            <td>{{ test.unit || "---" }}</td>
            <td>
              <template v-if="test.test_reference_ranges?.length">
                <span v-for="(range, ri) in test.test_reference_ranges" :key="ri">
                  <template v-if="range.notes">{{ range.notes }}</template>
                  <template v-else>{{ range.from }} - {{ range.to }}</template>
                  <template v-if="ri < test.test_reference_ranges.length - 1">, </template>
                </span>
              </template>
              <template v-else>---</template>
            </td>
            <td>{{ getStatusName(test) || "---" }}</td>
          </tr>
          <tr v-if="test.comment" class="commit">
            <td>Comment: {{ test.comment }}</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
          </tr>
        </tbody>
      </table>

      <!-- Notes Section -->
      <div v-if="printRecord?.notes" style="margin-top: 15px; padding: 10px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb;">
        <div style="font-weight: 700; font-size: 13px; margin-bottom: 5px; color: #374151;">{{ t("notes") || "Notes" }}:</div>
        <div style="font-size: 12px; color: #4b5563; white-space: pre-line;">{{ printRecord.notes }}</div>
      </div>
    </div>
  </div>
</template>
