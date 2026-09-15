<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useinvoicesStore } from "@/store/modules/invoices";
import { useLabSettingsStore } from "@/store/modules/labSettings";
import { usePrint } from "@/composables/usePrint";
import { documentCss } from "@/utils/labDocuments";
import { t, dateTimeFormat } from "@/utils/helper";
import parcodModal from "./parcodeModal.vue";
import thermalReciptModal from "./thermal_reciptModal.vue";
import BarcodeComponent from "@/components/BarcodeComponent.vue";

const invoicesStore = useinvoicesStore();
const labSettingsStore = useLabSettingsStore();
const { printRecord, printInvoiceDialog } = storeToRefs(invoicesStore);
const { printStyles } = usePrint();

const close = () => {
  printInvoiceDialog.value = false;
};

const due = computed(() => (printRecord.value?.total || 0) - (printRecord.value?.paid || 0));

const printParcode = (data) => {
  printRecord.value = data;
  setTimeout(() => {
    const jobContent = document.getElementById("parcode")?.innerHTML;
      const css = printStyles.getBarcodeCss(labSettingsStore.settings.barcode_config);

      const printFrame = document.createElement("iframe");
      printFrame.style.position = "absolute";
      document.body.appendChild(printFrame);

      const frameDoc = printFrame.contentWindow.document;
      frameDoc.open();
      frameDoc.write(`<html><head><title>Print Job</title><style>${css}</style></head><body>${jobContent}</body></html>`);
      frameDoc.close();

      printFrame.contentWindow.focus();
      printFrame.contentWindow.print();
      printFrame.contentWindow.onafterprint = () => {
        document.body.removeChild(printFrame);
      };
    }, 50);
};

const openprintINvoiceTemplate = (data) => {
  printRecord.value = data;
  setTimeout(() => {
    const jobContent = document.getElementById("printInvoice")?.innerHTML;
    if (!jobContent) return;

    // Merge the base invoice CSS with the lab's saved Document Settings
    // (paper size, orientation, margin, font size/family, colors, barcode/QR
    // visibility) — same as print_invoice.vue, so this print button honours
    // the same settings as the PDF/preview flow instead of always using
    // the hardcoded defaults.
    const css = printStyles.invoice + documentCss(labSettingsStore.settings, "invoice");

    const printFrame = document.createElement("iframe");
    printFrame.style.cssText = "position: absolute; width: 0px; height: 0px; border: none;";
    document.body.appendChild(printFrame);

    const frameDoc = printFrame.contentWindow.document;
    frameDoc.open();
    frameDoc.write(`<html><head><title>Print Invoice</title><style>${css}</style></head><body>${jobContent}</body></html>`);
    frameDoc.close();

    printFrame.contentWindow.focus();
    printFrame.contentWindow.print();
    printFrame.contentWindow.onafterprint = () => {
      document.body.removeChild(printFrame);
    };
  }, 50);
};

const openthermalRecord = (data) => {
  printRecord.value = data;
  setTimeout(() => {
    const jobContent = document.getElementById("thermalRecord")?.innerHTML;
    if (!jobContent) return;

    const css = printStyles.thermalReceipt;

    const printFrame = document.createElement("iframe");
    printFrame.style.cssText = "position: absolute; width: 0px; height: 0px; border: none;";
    document.body.appendChild(printFrame);

    const frameDoc = printFrame.contentWindow.document;
    frameDoc.open();
    frameDoc.write(`<html><head><title>Print Thermal Receipt</title><style>${css}</style></head><body>${jobContent}</body></html>`);
    frameDoc.close();

    printFrame.contentWindow.focus();
    printFrame.contentWindow.print();
    printFrame.contentWindow.onafterprint = () => {
      document.body.removeChild(printFrame);
    };
  }, 200);
};
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="printInvoiceDialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="close"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-hidden">
          <!-- Header -->
          <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">{{ t("print_invoice") }}</h2>
            <button @click="close" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Content -->
          <div class="p-6 overflow-y-auto max-h-[calc(90vh-150px)]">
            <!-- Top Actions: Barcode + Thermal Receipt -->
            <section class="flex justify-between items-center border border-gray-300 p-4 rounded-lg mb-4">
              <span @click="printParcode(printRecord)" class="cursor-pointer">
                <div class="flex flex-col items-center">
                  <BarcodeComponent :value="printRecord?.barcode" />
                  <span class="text-sm font-bold mt-1">{{ printRecord?.barcode }}</span>
                </div>
              </span>
              <button
                @click="openthermalRecord(printRecord)"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2"
              >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                {{ t('thermal_recipt') }}
              </button>
            </section>

            <!-- Invoice Preview -->
            <div class="border border-gray-200 rounded-lg p-6 bg-gray-50">
              <!-- Patient Info -->
              <div class="grid grid-cols-2 gap-x-6 gap-y-2 mb-4 text-sm">
                <div class="flex gap-2"><span class="font-semibold text-gray-500 w-28 shrink-0">Patient:</span><strong>{{ printRecord?.patient?.name }}</strong></div>
                <div class="flex gap-2"><span class="font-semibold text-gray-500 w-28 shrink-0">Code:</span><span>{{ printRecord?.patient?.code }}</span></div>
                <div class="flex gap-2"><span class="font-semibold text-gray-500 w-28 shrink-0">Age / Sex:</span><span>{{ printRecord?.patient?.age }}{{ printRecord?.patient?.age_unit }} / {{ printRecord?.patient?.gender }}</span></div>
                <div class="flex gap-2"><span class="font-semibold text-gray-500 w-28 shrink-0">Phone:</span><span>{{ printRecord?.patient?.phone || '-' }}</span></div>
                <div class="flex gap-2"><span class="font-semibold text-gray-500 w-28 shrink-0">Reg. Date:</span><span>{{ dateTimeFormat(printRecord?.registration_date) }}</span></div>
                <div class="flex gap-2"><span class="font-semibold text-gray-500 w-28 shrink-0">Result Date:</span><span>{{ dateTimeFormat(printRecord?.result_date) }}</span></div>
                <div v-if="printRecord?.referral?.name" class="flex gap-2"><span class="font-semibold text-gray-500 w-28 shrink-0">Referral:</span><span>{{ printRecord?.referral?.name }}</span></div>
                <div v-if="printRecord?.contract?.name" class="flex gap-2"><span class="font-semibold text-gray-500 w-28 shrink-0">Contract:</span><span>{{ printRecord?.contract?.name }}</span></div>
                <div v-if="printRecord?.sample_collector?.name" class="flex gap-2"><span class="font-semibold text-gray-500 w-28 shrink-0">Collector:</span><span>{{ printRecord?.sample_collector?.name }}</span></div>
                <div v-if="printRecord?.from_lab" class="flex gap-2"><span class="font-semibold text-gray-500 w-28 shrink-0">From Lab:</span><span>{{ printRecord?.from_lab }}</span></div>
              </div>

              <hr class="my-3">

              <!-- Test Groups -->
              <div v-if="printRecord?.test_groups?.length > 0">
                <div v-for="(group, gi) in printRecord.test_groups" :key="'g-' + gi" class="mb-3">
                  <div class="bg-teal-700 text-white font-bold px-3 py-1.5 text-sm rounded-t">{{ group.group_name }}</div>
                  <table class="w-full text-sm border-collapse">
                    <thead class="bg-teal-500 text-white">
                      <tr>
                        <th class="border border-teal-400 px-2 py-1.5 w-10 text-center">#</th>
                        <th class="border border-teal-400 px-2 py-1.5 text-start">Test</th>
                        <th class="border border-teal-400 px-2 py-1.5 w-24 text-center">Sample</th>
                        <th class="border border-teal-400 px-2 py-1.5 w-20 text-center">Price</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(item, idx) in group.tests" :key="'gt-' + idx" class="even:bg-gray-100">
                        <td class="border border-gray-200 px-2 py-1 text-center">{{ idx + 1 }}</td>
                        <td class="border border-gray-200 px-2 py-1">{{ item.report_name || item.name }}</td>
                        <td class="border border-gray-200 px-2 py-1 text-center text-xs">{{ item.sample_name || '-' }}</td>
                        <td class="border border-gray-200 px-2 py-1 text-center font-semibold">{{ item.price }}</td>
                      </tr>
                      <tr v-for="(item, idx) in group.cultures" :key="'gc-' + idx" class="even:bg-gray-100">
                        <td class="border border-gray-200 px-2 py-1 text-center">{{ (group.tests?.length || 0) + idx + 1 }}</td>
                        <td class="border border-gray-200 px-2 py-1">{{ item.name }}</td>
                        <td class="border border-gray-200 px-2 py-1 text-center text-xs">{{ item.sample_name || '-' }}</td>
                        <td class="border border-gray-200 px-2 py-1 text-center font-semibold">{{ item.price }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Packages -->
              <div v-if="printRecord?.packages?.length > 0">
                <div v-for="(pkg, pi) in printRecord.packages" :key="'pkg-' + pi" class="mb-3">
                  <div class="bg-teal-700 text-white font-bold px-3 py-1.5 text-sm rounded-t">{{ pkg.name }} (Package)</div>
                  <table class="w-full text-sm border-collapse">
                    <thead class="bg-teal-500 text-white">
                      <tr>
                        <th class="border border-teal-400 px-2 py-1.5 w-10 text-center">#</th>
                        <th class="border border-teal-400 px-2 py-1.5 text-start">Test</th>
                        <th class="border border-teal-400 px-2 py-1.5 w-24 text-center">Sample</th>
                        <th class="border border-teal-400 px-2 py-1.5 w-20 text-center">Price</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(item, idx) in pkg.tests" :key="'pt-' + idx" class="even:bg-gray-100">
                        <td class="border border-gray-200 px-2 py-1 text-center">{{ idx + 1 }}</td>
                        <td class="border border-gray-200 px-2 py-1">{{ item.report_name || item.name }}</td>
                        <td class="border border-gray-200 px-2 py-1 text-center text-xs">{{ item.sample_name || '-' }}</td>
                        <td class="border border-gray-200 px-2 py-1 text-center font-semibold">{{ idx === 0 ? pkg.price : '' }}</td>
                      </tr>
                      <tr v-for="(item, idx) in pkg.cultures" :key="'pc-' + idx" class="even:bg-gray-100">
                        <td class="border border-gray-200 px-2 py-1 text-center">{{ (pkg.tests?.length || 0) + idx + 1 }}</td>
                        <td class="border border-gray-200 px-2 py-1">{{ item.name }}</td>
                        <td class="border border-gray-200 px-2 py-1 text-center text-xs">{{ item.sample_name || '-' }}</td>
                        <td class="border border-gray-200 px-2 py-1 text-center font-semibold">{{ !pkg.tests?.length && idx === 0 ? pkg.price : '' }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Individual Tests & Cultures -->
              <div v-if="printRecord?.tests?.length > 0 || printRecord?.cultures?.length > 0" class="mb-3">
                <div class="bg-teal-700 text-white font-bold px-3 py-1.5 text-sm rounded-t">Individual Tests</div>
                <table class="w-full text-sm border-collapse">
                  <thead class="bg-teal-500 text-white">
                    <tr>
                      <th class="border border-teal-400 px-2 py-1.5 w-10 text-center">#</th>
                      <th class="border border-teal-400 px-2 py-1.5 text-start">Test</th>
                      <th class="border border-teal-400 px-2 py-1.5 w-24 text-center">Sample</th>
                      <th class="border border-teal-400 px-2 py-1.5 w-20 text-center">Price</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(item, index) in printRecord?.tests" :key="'t-' + index" class="even:bg-gray-100">
                      <td class="border border-gray-200 px-2 py-1 text-center">{{ index + 1 }}</td>
                      <td class="border border-gray-200 px-2 py-1">{{ item.report_name || item.name }}</td>
                      <td class="border border-gray-200 px-2 py-1 text-center text-xs">{{ item.sample_name || '-' }}</td>
                      <td class="border border-gray-200 px-2 py-1 text-center font-semibold">{{ item.price }}</td>
                    </tr>
                    <tr v-for="(item, index) in printRecord?.cultures" :key="'c-' + index" class="even:bg-gray-100">
                      <td class="border border-gray-200 px-2 py-1 text-center">{{ (printRecord?.tests?.length || 0) + index + 1 }}</td>
                      <td class="border border-gray-200 px-2 py-1">{{ item.name }}</td>
                      <td class="border border-gray-200 px-2 py-1 text-center text-xs">{{ item.sample_name || '-' }}</td>
                      <td class="border border-gray-200 px-2 py-1 text-center font-semibold">{{ item.price }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Notes -->
              <div v-if="printRecord?.notes" class="bg-amber-50 border border-amber-300 rounded p-3 text-sm mb-3">
                <strong class="text-amber-800">Notes:</strong> {{ printRecord.notes }}
              </div>

              <!-- Financial Summary -->
              <div class="flex justify-end mt-4">
                <div class="w-72 border border-gray-300 rounded overflow-hidden">
                  <div class="flex justify-between px-4 py-2 bg-gray-50 border-b border-gray-200">
                    <span class="font-semibold text-gray-600">Subtotal</span>
                    <span>IQD {{ printRecord?.sub_total }}</span>
                  </div>
                  <div v-if="printRecord?.discount" class="flex justify-between px-4 py-2 bg-gray-50 border-b border-gray-200">
                    <span class="font-semibold text-gray-600">Discount</span>
                    <span>IQD {{ printRecord?.discount }}</span>
                  </div>
                  <div class="flex justify-between px-4 py-2 bg-teal-500 text-white font-bold border-b border-teal-400">
                    <span>Total</span>
                    <span>IQD {{ printRecord?.total }}</span>
                  </div>
                  <div class="flex justify-between px-4 py-2 bg-green-50 text-green-700 font-semibold border-b border-gray-200">
                    <span>Paid</span>
                    <span>IQD {{ printRecord?.paid }}</span>
                  </div>
                  <div class="flex justify-between px-4 py-2 bg-red-50 text-red-600 font-bold">
                    <span>Due</span>
                    <span>IQD {{ due }}</span>
                  </div>
                </div>
              </div>

              <!-- Payment Details -->
              <div v-if="printRecord?.paidDetails?.length > 1" class="flex justify-end mt-2">
                <table class="w-72 text-xs border-collapse">
                  <thead>
                    <tr class="bg-gray-100">
                      <th class="border border-gray-200 px-2 py-1">#</th>
                      <th class="border border-gray-200 px-2 py-1">Method</th>
                      <th class="border border-gray-200 px-2 py-1">Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(pd, idx) in printRecord.paidDetails" :key="'pd-' + idx">
                      <td class="border border-gray-200 px-2 py-1 text-center">{{ idx + 1 }}</td>
                      <td class="border border-gray-200 px-2 py-1 text-center">{{ pd.payment_method || '-' }}</td>
                      <td class="border border-gray-200 px-2 py-1 text-center">IQD {{ pd.amount }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex justify-center gap-2 p-4 border-t border-gray-200">
            <button
              @click="close"
              class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
            >
              {{ t('close') }}
            </button>
            <button
              @click="openprintINvoiceTemplate(printRecord)"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 flex items-center gap-2"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
              </svg>
              {{ t("printInvoice") }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
  <parcodModal></parcodModal>
  <thermalReciptModal></thermalReciptModal>

  <!-- Hidden printable invoice -->
  <div class="hidden" id="printInvoice">
    <div class="inv">
      <div class="inv-title">INVOICE</div>

      <!-- Patient Info -->
      <div class="info-grid">
        <div class="info-row">
          <div class="info-cell info-lbl">Patient</div>
          <div class="info-cell info-val">{{ printRecord?.patient?.name }}</div>
          <div class="info-cell info-lbl">Code</div>
          <div class="info-cell info-val">{{ printRecord?.patient?.code }}</div>
        </div>
        <div class="info-row">
          <div class="info-cell info-lbl">Age / Sex</div>
          <div class="info-cell info-val">{{ printRecord?.patient?.age }}{{ printRecord?.patient?.age_unit }} / {{ printRecord?.patient?.gender }}</div>
          <div class="info-cell info-lbl">Phone</div>
          <div class="info-cell info-val">{{ printRecord?.patient?.phone || "-" }}</div>
        </div>
        <div class="info-row">
          <div class="info-cell info-lbl">Reg. Date</div>
          <div class="info-cell info-val">{{ dateTimeFormat(printRecord?.registration_date) }}</div>
          <div class="info-cell info-lbl">Result Date</div>
          <div class="info-cell info-val">{{ dateTimeFormat(printRecord?.result_date) }}</div>
        </div>
        <div v-if="printRecord?.referral?.name" class="info-row">
          <div class="info-cell info-lbl">Referral</div>
          <div class="info-cell info-val">{{ printRecord?.referral?.name }}</div>
          <div class="info-cell info-lbl">Contract</div>
          <div class="info-cell info-val">{{ printRecord?.contract?.name || "-" }}</div>
        </div>
      </div>

      <!-- Test Groups -->
      <template v-if="printRecord?.test_groups?.length > 0">
        <div v-for="(group, gi) in printRecord.test_groups" :key="'pg-' + gi">
          <div class="section-title">{{ group.group_name }}</div>
          <table class="tbl">
            <thead>
              <tr>
                <th class="num">#</th>
                <th class="txt-start">Test</th>
                <th class="sample">Sample</th>
                <th class="price">Price</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in group.tests" :key="'pgt-' + idx">
                <td class="num">{{ idx + 1 }}</td>
                <td class="txt-start">{{ item.report_name || item.name }}</td>
                <td>{{ item.sample_name || "-" }}</td>
                <td class="price">{{ item.price }}</td>
              </tr>
              <tr v-for="(item, idx) in group.cultures" :key="'pgc-' + idx">
                <td class="num">{{ (group.tests?.length || 0) + idx + 1 }}</td>
                <td class="txt-start">{{ item.name }}</td>
                <td>{{ item.sample_name || "-" }}</td>
                <td class="price">{{ item.price }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <!-- Packages -->
      <template v-if="printRecord?.packages?.length > 0">
        <div v-for="(pkg, pi) in printRecord.packages" :key="'ppkg-' + pi">
          <div class="section-title">{{ pkg.name }} (Package)</div>
          <table class="tbl">
            <thead>
              <tr>
                <th class="num">#</th>
                <th class="txt-start">Test</th>
                <th class="sample">Sample</th>
                <th class="price">Price</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in pkg.tests" :key="'ppt-' + idx">
                <td class="num">{{ idx + 1 }}</td>
                <td class="txt-start">{{ item.report_name || item.name }}</td>
                <td>{{ item.sample_name || "-" }}</td>
                <td class="price">{{ idx === 0 ? pkg.price : "" }}</td>
              </tr>
              <tr v-for="(item, idx) in pkg.cultures" :key="'ppc-' + idx">
                <td class="num">{{ (pkg.tests?.length || 0) + idx + 1 }}</td>
                <td class="txt-start">{{ item.name }}</td>
                <td>{{ item.sample_name || "-" }}</td>
                <td class="price">{{ !pkg.tests?.length && idx === 0 ? pkg.price : "" }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <!-- Individual Tests & Cultures -->
      <template v-if="printRecord?.tests?.length > 0 || printRecord?.cultures?.length > 0">
        <div class="section-title">Individual Tests</div>
        <table class="tbl">
          <thead>
            <tr>
              <th class="num">#</th>
              <th class="txt-start">Test</th>
              <th class="sample">Sample</th>
              <th class="price">Price</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in printRecord?.tests" :key="'pit-' + index">
              <td class="num">{{ index + 1 }}</td>
              <td class="txt-start">{{ item.report_name || item.name }}</td>
              <td>{{ item.sample_name || "-" }}</td>
              <td class="price">{{ item.price }}</td>
            </tr>
            <tr v-for="(item, index) in printRecord?.cultures" :key="'pic-' + index">
              <td class="num">{{ (printRecord?.tests?.length || 0) + index + 1 }}</td>
              <td class="txt-start">{{ item.name }}</td>
              <td>{{ item.sample_name || "-" }}</td>
              <td class="price">{{ item.price }}</td>
            </tr>
          </tbody>
        </table>
      </template>

      <!-- Notes -->
      <div v-if="printRecord?.notes" class="notes">
        <strong>Notes:</strong> {{ printRecord.notes }}
      </div>

      <!-- Financial Summary -->
      <div class="summ">
        <table class="summ-tbl">
          <tr>
            <td class="lbl">Subtotal</td>
            <td>IQD {{ printRecord?.sub_total }}</td>
          </tr>
          <tr v-if="printRecord?.discount">
            <td class="lbl">Discount</td>
            <td>IQD {{ printRecord?.discount }}</td>
          </tr>
          <tr class="total">
            <td>Total</td>
            <td>IQD {{ printRecord?.total }}</td>
          </tr>
          <tr class="paid-row">
            <td>Paid</td>
            <td>IQD {{ printRecord?.paid }}</td>
          </tr>
          <tr class="due">
            <td>Due</td>
            <td>IQD {{ due }}</td>
          </tr>
        </table>
      </div>

      <!-- Payment Details -->
      <div v-if="printRecord?.paidDetails?.length > 1" class="pay-details">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Method</th>
              <th>Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(pd, idx) in printRecord.paidDetails" :key="'ppd-' + idx">
              <td>{{ idx + 1 }}</td>
              <td>{{ pd.payment_method || "-" }}</td>
              <td>IQD {{ pd.amount }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="footer">
        <p>Thank you for choosing our lab</p>
      </div>
    </div>
  </div>
</template>
