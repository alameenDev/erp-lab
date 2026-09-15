<script setup>
import { ref, computed, onMounted, nextTick } from "vue";
import { useLabSettingsStore } from "@/store/modules/labSettings";
import { documentConfig, documentCss } from "@/utils/labDocuments";
import { $http } from "@/plugins/axios";
const labSettingsStore = useLabSettingsStore();
const publicSettings = ref(null);
const effectiveSettings = computed(() => publicSettings.value || labSettingsStore.settings);
const invoiceConfig = computed(() => documentConfig(effectiveSettings.value,"invoice"));
import { storeToRefs } from "pinia";
import { useRoute } from "vue-router";
import JsBarcode from "jsbarcode";
import QrcodeVue from "qrcode.vue";
import html2pdf from "html2pdf.js";
import { useinvoicesStore } from "@/store/modules/invoices";
import { dateTimeFormat, t } from "@/utils/helper";
import { usePrint } from "@/composables/usePrint";
const { printStyles } = usePrint();

const route = useRoute();
const invoicesStore = useinvoicesStore();
const { printRecord, jobOrder } = storeToRefs(invoicesStore);
const { GetinvoicesById } = invoicesStore;

const isfromRoute = ref(false);
const invoiceId = ref(null);
const documentHeight = ref(0);
const pdfUrl = ref(null);
const contentToConvert = ref(null);

onMounted(() => {
  invoiceId.value = route.params.invoiceId;
  if (invoiceId.value) {
    getData();
  }
  documentHeight.value = document.documentElement.scrollHeight;
  if (isfromRoute.value) {
    openprintINvoiceTemplate([]);
  }
});

const getData = async () => {
  await GetinvoicesById(invoiceId.value);
  const labId = printRecord.value?.lab_id_fk;
  if (labId) {
    const { data } = await $http.get(`/lab-settings/${labId}`);
    publicSettings.value = data;
  }
  await nextTick();
  await generatePDF();
};

const appBaseUrl = import.meta.env.VITE_APP_URL || window.location.origin;

const getPatientReportLink = () => {
  return `${appBaseUrl}/result/${printRecord.value?.id || invoiceId.value}`;
};

// Builds the styled invoice PDF (base CSS + the lab's Document Settings)
// and returns it in the requested html2pdf output format. Shared by the
// on-screen preview (datauristring) and by anyone needing the raw file
// (e.g. WhatsApp sending) so both always produce the exact same,
// settings-aware document instead of two different implementations
// drifting out of sync.
const buildInvoicePdf = async (outputType = "datauristring") => {
  const printEl = document.getElementById("printInvoice");
  if (printEl) printEl.style.display = "block";

  await nextTick();
  const element = contentToConvert.value;
  if (!element) return null;
  const pdfStyle = document.createElement("style");
  const rawCss = printStyles.invoice + documentCss(effectiveSettings.value, "invoice");
  // Scope printable rules to this capture; do not restyle the application body.
  pdfStyle.textContent = rawCss.replace(/([^{}]+)\{/g, (match, selectors) => {
    if (selectors.includes("@") || selectors.trim() === "") return match;
    return selectors.split(",").map(selector => "#printInvoice " + selector.trim()).join(",") + "{";
  });
  element.prepend(pdfStyle);
  const opt = {
    margin: Math.max(0, Math.min(30, Number(invoiceConfig.value.margin) || 0)),
    filename: "invoice.pdf",
    image: { type: "jpeg", quality: 0.98 },
    html2canvas: { scale: 4 },
    jsPDF: { unit: "mm", format: invoiceConfig.value.paper === "A5" ? "a5" : "a4", orientation: invoiceConfig.value.orientation === "landscape" ? "landscape" : "portrait" },
  };

  try {
    return await html2pdf().set(opt).from(element).outputPdf(outputType);
  } finally {
    pdfStyle.remove();
    if (printEl) printEl.style.display = "none";
  }
};

const generatePDF = async () => {
  const pdfDataUri = await buildInvoicePdf("datauristring");
  if (pdfDataUri) pdfUrl.value = pdfDataUri;
};

// Exposed for parents (e.g. the WhatsApp-send modal) that need the actual
// PDF bytes — a real, correctly styled document instead of raw page HTML.
defineExpose({
  generatePdfBlob: () => buildInvoicePdf("blob"),
});

const generateBarcodeImage = (value) => {
  if (!value) return "";
  const canvas = document.createElement("canvas");
  JsBarcode(canvas, value, {
    format: "CODE128",
    displayValue: false,
    width: 1.2,
    height: 25,
    margin: 0,
  });
  return canvas.toDataURL("image/png");
};

const due = computed(() => (printRecord.value?.total || 0) - (printRecord.value?.paid || 0));

const openprintINvoiceTemplate = (data) => {
  printRecord.value = data;
  setTimeout(() => {
    const jobContent = document.getElementById("printInvoice")?.innerHTML;
    if (!jobContent) return;

    const css = `
      @page { size: A4; margin: 15mm; }
      @media print {
        body, .page { margin: 0 !important; box-shadow: none; -webkit-print-color-adjust: exact; color: #000; }
      }
      * { margin: 0; padding: 0; box-sizing: border-box; }
      body { font-family: "Segoe UI", "Tajawal", Arial, sans-serif; font-size: 12px; margin: 0; padding: 15mm; color: #1f2937; }
      .inv { width: 100%; max-width: 800px; margin: 0 auto; }
      .inv-title { text-align: center; font-size: 20px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #0f766e; margin-bottom: 12px; border-bottom: 3px solid #14b8a6; padding-bottom: 8px; }
      .hdr { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; gap: 12px; }
      .hdr-bc { display: flex; flex-direction: column; align-items: center; gap: 2px; }
      .hdr-bc img { max-width: 130px; height: 28px; }
      .hdr-bc span { font-size: 10px; font-weight: 600; }
      .hdr-qr { display: flex; flex-direction: column; align-items: center; gap: 2px; }
      .hdr-qr svg { width: 70px !important; height: 70px !important; }
      .hdr-qr span { font-size: 8px; color: #6b7280; }
      .info-grid { display: table; width: 100%; border: 1px solid #d1d5db; border-collapse: collapse; margin-bottom: 16px; }
      .info-row { display: table-row; }
      .info-cell { display: table-cell; padding: 5px 10px; border: 1px solid #e5e7eb; font-size: 11px; vertical-align: middle; }
      .info-lbl { font-weight: 700; color: #374151; background-color: #f9fafb; white-space: nowrap; width: 110px; }
      .info-val { color: #111827; }
      .section-title { background-color: #0f766e; color: white; font-size: 13px; font-weight: 700; padding: 7px 12px; margin-top: 14px; margin-bottom: 0; letter-spacing: 0.5px; }
      .grp-hdr { background-color: #ccfbf1 !important; }
      .grp-hdr td { font-weight: 700; color: #0f766e; border-bottom: 2px solid #14b8a6 !important; }
      .tbl { width: 100%; border-collapse: collapse; margin-bottom: 0; }
      .tbl th { background-color: #14b8a6; color: white; padding: 7px 10px; text-align: center; border: 1px solid #0d9488; font-size: 11px; font-weight: 600; }
      .tbl td { border: 1px solid #e5e7eb; padding: 5px 10px; font-size: 11px; text-align: center; }
      .tbl tbody tr:nth-child(even) { background-color: #f9fafb; }
      .tbl .txt-start { text-align: left; }
      .tbl .num { width: 35px; }
      .tbl .sample { width: 100px; }
      .tbl .price { width: 90px; font-weight: 600; }
      .summ { margin-top: 18px; display: flex; justify-content: flex-end; }
      .summ-tbl { width: 280px; border-collapse: collapse; }
      .summ-tbl td { padding: 6px 14px; border: 1px solid #e5e7eb; font-size: 11px; }
      .summ-tbl .lbl { font-weight: 700; background-color: #f9fafb; color: #374151; }
      .summ-tbl .total { background-color: #14b8a6; color: white; font-weight: 700; font-size: 13px; }
      .summ-tbl .total td { border-color: #0d9488; }
      .summ-tbl .due { background-color: #fef2f2; color: #dc2626; font-weight: 700; }
      .summ-tbl .paid-row { background-color: #f0fdf4; color: #16a34a; font-weight: 600; }
      .notes { margin-top: 14px; padding: 8px 12px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 4px; font-size: 11px; color: #92400e; }
      .notes strong { color: #78350f; }
      .pay-details { margin-top: 10px; }
      .pay-details table { width: 280px; border-collapse: collapse; margin-left: auto; }
      .pay-details th { background-color: #f3f4f6; padding: 4px 10px; font-size: 10px; font-weight: 600; color: #374151; border: 1px solid #e5e7eb; }
      .pay-details td { padding: 4px 10px; font-size: 10px; border: 1px solid #e5e7eb; text-align: center; }
      .footer { margin-top: 20px; text-align: center; font-size: 10px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
      @media print {
        .inv { max-width: 100%; }
        .tbl { page-break-inside: auto; }
        .tbl tr { page-break-inside: avoid; }
        .grp-hdr { page-break-after: avoid; }
      }
    `;

    const newWindow = window.open("", "_blank");
    newWindow.document.write(`
      <html>
        <head>
          <title>Print Invoice</title>
          <style>${css + documentCss(effectiveSettings.value,"invoice")}</style>
        </head>
        <body>${jobContent}</body>
      </html>
    `);
    newWindow.document.close();
    newWindow.onload = () => {
      newWindow.print();
      newWindow.onafterprint = () => {
        newWindow.close();
      };
    };
  }, 50);
};
</script>

<template>
  <div v-if="pdfUrl">
    <iframe :src="pdfUrl" width="100%" :height="documentHeight" style="border: 1px solid #ccc"></iframe>
  </div>
  <div class="hidden" id="printInvoice" ref="contentToConvert">
    <div class="inv">

      <!-- ===== TITLE ===== -->
      <div v-if="effectiveSettings.lab_display_name" class="inv-title">{{ effectiveSettings.lab_display_name }}</div>
      <div class="inv-title">Invoice</div>

      <!-- ===== BARCODES + QR ===== -->
      <div class="hdr">
        <div class="hdr-bc">
          <img :src="generateBarcodeImage(printRecord?.barcode)" alt="" />
          <span>{{ printRecord?.barcode }}</span>
        </div>
        <div class="hdr-bc">
          <img :src="generateBarcodeImage(printRecord?.patient?.code)" alt="" />
          <span>{{ printRecord?.patient?.code }}</span>
        </div>
        <div class="hdr-qr">
          <QrcodeVue
            :value="getPatientReportLink()"
            :size="70"
            level="H"
            render-as="svg"
          />
          <span>Scan for results</span>
        </div>
      </div>

      <!-- ===== PATIENT & INVOICE INFO ===== -->
      <div class="info-grid">
        <div class="info-row">
          <div class="info-cell info-lbl">Patient Name</div>
          <div class="info-cell info-val"><strong>{{ printRecord?.patient?.name }}</strong></div>
          <div class="info-cell info-lbl">Patient Code</div>
          <div class="info-cell info-val">{{ printRecord?.patient?.code }}</div>
        </div>
        <div class="info-row">
          <div class="info-cell info-lbl">Age / Sex</div>
          <div class="info-cell info-val">{{ printRecord?.patient?.age }}{{ printRecord?.patient?.age_unit }} / {{ printRecord?.patient?.gender }}</div>
          <div class="info-cell info-lbl">Phone</div>
          <div class="info-cell info-val">{{ printRecord?.patient?.phone || '-' }}</div>
        </div>
        <div class="info-row">
          <div class="info-cell info-lbl">Reg. Date</div>
          <div class="info-cell info-val">{{ dateTimeFormat(printRecord?.registration_date) }}</div>
          <div class="info-cell info-lbl">Result Date</div>
          <div class="info-cell info-val">{{ dateTimeFormat(printRecord?.result_date) }}</div>
        </div>
        <div class="info-row" v-if="printRecord?.referral?.name || printRecord?.contract?.name">
          <div class="info-cell info-lbl">Referral</div>
          <div class="info-cell info-val">{{ printRecord?.referral?.name || '-' }}</div>
          <div class="info-cell info-lbl">Contract</div>
          <div class="info-cell info-val">{{ printRecord?.contract?.name || '-' }}</div>
        </div>
        <div class="info-row" v-if="printRecord?.sample_collector?.name || printRecord?.from_lab">
          <div class="info-cell info-lbl">Sample Collector</div>
          <div class="info-cell info-val">{{ printRecord?.sample_collector?.name || '-' }}</div>
          <div class="info-cell info-lbl">From Lab</div>
          <div class="info-cell info-val">{{ printRecord?.from_lab || '-' }}</div>
        </div>
      </div>

      <!-- ===== TEST GROUPS ===== -->
      <template v-if="printRecord?.test_groups?.length > 0">
        <div v-for="(group, gi) in printRecord.test_groups" :key="'g-' + gi">
          <div class="section-title">{{ group.group_name }}</div>
          <table class="tbl">
            <thead>
              <tr>
                <th class="num">#</th>
                <th>Test Name</th>
                <th class="sample">Sample</th>
                <th class="price">Price</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in group.tests" :key="'gt-' + gi + '-' + idx">
                <td class="num">{{ idx + 1 }}</td>
                <td class="txt-start">{{ item.report_name || item.name }}</td>
                <td>{{ item.sample_name || '-' }}</td>
                <td class="price">{{ item.price }}</td>
              </tr>
              <tr v-for="(item, idx) in group.cultures" :key="'gc-' + gi + '-' + idx">
                <td class="num">{{ (group.tests?.length || 0) + idx + 1 }}</td>
                <td class="txt-start">{{ item.name }}</td>
                <td>{{ item.sample_name || '-' }}</td>
                <td class="price">{{ item.price }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <!-- ===== PACKAGES ===== -->
      <template v-if="printRecord?.packages?.length > 0">
        <div v-for="(pkg, pi) in printRecord.packages" :key="'pkg-' + pi">
          <div class="section-title">{{ pkg.name }} (Package)</div>
          <table class="tbl">
            <thead>
              <tr>
                <th class="num">#</th>
                <th>Test Name</th>
                <th class="sample">Sample</th>
                <th class="price">Price</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in pkg.tests" :key="'pt-' + pi + '-' + idx">
                <td class="num">{{ idx + 1 }}</td>
                <td class="txt-start">{{ item.report_name || item.name }}</td>
                <td>{{ item.sample_name || '-' }}</td>
                <td class="price">{{ idx === 0 ? pkg.price : '' }}</td>
              </tr>
              <tr v-for="(item, idx) in pkg.cultures" :key="'pc-' + pi + '-' + idx">
                <td class="num">{{ (pkg.tests?.length || 0) + idx + 1 }}</td>
                <td class="txt-start">{{ item.name }}</td>
                <td>{{ item.sample_name || '-' }}</td>
                <td class="price">{{ !pkg.tests?.length && idx === 0 ? pkg.price : '' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <!-- ===== INDIVIDUAL TESTS & CULTURES ===== -->
      <template v-if="printRecord?.tests?.length > 0 || printRecord?.cultures?.length > 0">
        <div class="section-title">Individual Tests</div>
        <table class="tbl">
          <thead>
            <tr>
              <th class="num">#</th>
              <th>Test Name</th>
              <th class="sample">Sample</th>
              <th class="price">Price</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in printRecord?.tests" :key="'t-' + index">
              <td class="num">{{ index + 1 }}</td>
              <td class="txt-start">{{ item.report_name || item.name }}</td>
              <td>{{ item.sample_name || '-' }}</td>
              <td class="price">{{ item.price }}</td>
            </tr>
            <tr v-for="(item, index) in printRecord?.cultures" :key="'c-' + index">
              <td class="num">{{ (printRecord?.tests?.length || 0) + index + 1 }}</td>
              <td class="txt-start">{{ item.name }}</td>
              <td>{{ item.sample_name || '-' }}</td>
              <td class="price">{{ item.price }}</td>
            </tr>
          </tbody>
        </table>
      </template>

      <!-- ===== NOTES ===== -->
      <div v-if="printRecord?.notes" class="notes">
        <strong>Notes:</strong> {{ printRecord.notes }}
      </div>

      <!-- ===== FINANCIAL SUMMARY ===== -->
      <div class="summ">
        <table class="summ-tbl">
          <tbody>
            <tr>
              <td class="lbl">Subtotal</td>
              <td>IQD {{ printRecord?.sub_total }}</td>
            </tr>
            <tr v-if="printRecord?.discount">
              <td class="lbl">Discount {{ printRecord?.discount_type ? '(' + printRecord.discount_type + ')' : '' }}</td>
              <td>IQD {{ printRecord?.discount }}</td>
            </tr>
            <tr class="total">
              <td>Total</td>
              <td>IQD {{ printRecord?.total }}</td>
            </tr>
            <tr class="paid-row">
              <td class="lbl">Paid</td>
              <td>IQD {{ printRecord?.paid }}</td>
            </tr>
            <tr class="due">
              <td>Due</td>
              <td>IQD {{ due }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ===== PAYMENT DETAILS ===== -->
      <div v-if="printRecord?.paidDetails?.length > 1" class="pay-details">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Payment Method</th>
              <th>Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(pd, idx) in printRecord.paidDetails" :key="'pd-' + idx">
              <td>{{ idx + 1 }}</td>
              <td>{{ pd.payment_method || '-' }}</td>
              <td>IQD {{ pd.amount }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ===== FOOTER ===== -->
      <div class="footer"><p style="white-space:pre-line">{{ invoiceConfig.footer }}</p>
        <p>Thank you for choosing our lab &mdash; We wish you good health</p>
      </div>

    </div>
  </div>
</template>
