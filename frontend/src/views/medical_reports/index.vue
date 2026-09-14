<script setup>
import { ref, computed, watch, onMounted, nextTick } from "vue";
import { storeToRefs } from "pinia";
import { useRoute, useRouter } from "vue-router";
import { useinvoicesStore } from "@/store/modules/invoices";
import { $http } from "@/plugins/axios";
import { useContractsStore } from "@/store/modules/contract";
import { useAuthStore } from "@/store/modules/auth";
import { useresultStatusStore } from "@/store/modules/result-status";
import { usePrint } from "@/composables/usePrint";
import { useLabSettingsStore } from "@/store/modules/labSettings";
import { t, dateTimeFormat } from "@/utils/helper";
import { useToast } from "@/composables/useToast";
import html2canvas from "html2canvas-pro";
import jsPDF from "jspdf";
import * as XLSX from "xlsx";
import BarcodeComponent from "@/components/BarcodeComponent.vue";
import pationtHistoryModal from "./componentes/pationtHistory_modal.vue";
import patientModal from "./componentes/patient_modal.vue";
import DueModal from "./componentes/due_modal.vue";
import parcodModal from "./componentes/parcodeModal.vue";
import PrintMarginDialog from "./componentes/PrintMarginDialog.vue";
import attachment from "./componentes/attachment.vue";
import job_orderModal from "./componentes/job_orderModal.vue";
import printResult from "./componentes/print_Result.vue";
import workSheetModal from "./componentes/workSheet_modal.vue";
import printSelectModal from "./componentes/printSelectModal.vue";
import TestsListModal from "@/components/TestsListModal.vue";

// Stores
const route = useRoute();
const router = useRouter();
const invoicesStore = useinvoicesStore();
const contractsStore = useContractsStore();
const authStore = useAuthStore();
const resultStatusStore = useresultStatusStore();
const labSettingsStore = useLabSettingsStore();
const { printWithIframe, printWithCustomContent, printStyles } = usePrint();
const toast = useToast();

// Store refs
const {
  invoices, attachments, AttachDialog, record,
  pationtHistoryDialog, patientdialog, patient, Patient_dueDialog,
  printRecord, print_work_sheetDialog, selectedItems, isAllSelected, updateResultRecord,
  pagination, stats: serverStats,
} = storeToRefs(invoicesStore);

// Stats fall back to client-side aggregation when patient_medical_records is loaded (no server stats)
const stats = computed(() => {
  if (!patientId.value) return serverStats.value;
  const list = invoices.value || [];
  const total = list.length;
  const completed_done = list.filter(i => i.is_done).length;
  const signed = list.filter(i => i.is_signed).length;
  const sent = list.filter(i => i.sent_to_patient).length;
  return {
    total,
    completed_done,
    pending_done: total - completed_done,
    completed_sent: sent,
    pending_sent: total - sent,
    signed,
    sent,
    total_amount: 0,
    paid_amount: 0,
    due_amount: 0,
  };
});
const { contracts } = storeToRefs(contractsStore);

// Local state
const isLoading = ref(true);
const patientId = ref(null);
const marginDialogVisible = ref(false);
const activeMenuId = ref(null);
const showFilters = ref(false);
const reportBackground = computed(() => labSettingsStore.settings.report_background || "");
const whatsappMenuId = ref(null);
const printMenuId = ref(null);
const downloadMenuId = ref(null);
const downloadInProgress = ref(false);
const printResultRef = ref(null);
const printSelectVisible = ref(false);
const printSelectMode = ref("normal");
const printSelectItem = ref(null);
const printWithBgMode = ref(false);

// Filters
const filters = ref({
  patient_name: "",
  registration_date: "",
  from_lab: "",
  contract_id_fk: "",
  status: "", // done, pending
  signed_status: "", // signed, unsigned
});

// Computed
const User = computed(() => {
  try { return JSON.parse(localStorage.getItem("user")); }
  catch { return null; }
});

// Pagination (server-side when !patientId, client-side when patientId)
const currentPage = ref(1);
const perPage = ref(25);

const totalPages = computed(() => {
  if (patientId.value) {
    return Math.ceil(invoices.value.length / perPage.value) || 1;
  }
  return pagination.value.last_page || 1;
});

const activeFiltersCount = computed(() => {
  let count = 0;
  if (filters.value.status) count++;
  if (filters.value.signed_status) count++;
  if (filters.value.contract_id_fk) count++;
  if (filters.value.registration_date) count++;
  if (filters.value.from_lab) count++;
  return count;
});

// Build params for server-side fetch
const buildParams = () => {
  const params = { page: currentPage.value, per_page: perPage.value };
  if (filters.value.patient_name) params.patient_name = filters.value.patient_name;
  if (filters.value.from_lab) params.from_lab = filters.value.from_lab;
  if (filters.value.contract_id_fk) params.contract_id_fk = filters.value.contract_id_fk;
  if (filters.value.status) params.is_done = filters.value.status;
  if (filters.value.signed_status) params.signed_status = filters.value.signed_status;
  if (filters.value.registration_date) params.invoice_date = filters.value.registration_date;
  return params;
};

const fetchInvoices = async () => {
  await invoicesStore.Getinvoices(buildParams());
};

// Methods
const isSameDate = (date1, date2) => {
  if (!date1 || !date2) return false;
  return new Date(date1).toISOString().split("T")[0] === new Date(date2).toISOString().split("T")[0];
};

const onPageChange = (page) => {
  if (page < 1 || page > totalPages.value) return;
  currentPage.value = page;
  if (!patientId.value) fetchInvoices();
};

const toggleFilters = () => { showFilters.value = !showFilters.value; };
const toggleMenu = (id) => { activeMenuId.value = activeMenuId.value === id ? null : id; };
const closeMenu = () => { activeMenuId.value = null; whatsappMenuId.value = null; printMenuId.value = null; downloadMenuId.value = null; };

const clearFilters = () => {
  filters.value = { patient_name: "", registration_date: "", from_lab: "", contract_id_fk: "", status: "", signed_status: "" };
};

const exportToExcel = () => {
  const data = invoices.value.map(r => ({
    barcode: r.barcode,
    patient: r.patient?.name,
    registration_date: r.registration_date,
    lab: r.lab,
    created_by: r.created_by?.name,
    signed_by: r.signed_by?.name,
    status: r.is_done ? "Done" : "Pending",
  }));
  const ws = XLSX.utils.json_to_sheet(data);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Medical Reports");
  XLSX.writeFile(wb, "medical_reports.xlsx");
};

const showPatient = (p) => { patient.value = p; patientdialog.value = true; };
const showPatient_due = (data) => { record.value = data; Patient_dueDialog.value = true; };

// Tests list modal
const testsListOpen = ref(false);
const testsListLoading = ref(false);
const testsListInvoice = ref(null);
const showTestsList = async (invoice) => {
  testsListOpen.value = true;
  testsListLoading.value = true;
  testsListInvoice.value = { patient: invoice.patient };
  try {
    await invoicesStore.GetinvoicesById(invoice.id);
    testsListInvoice.value = printRecord.value;
  } finally {
    testsListLoading.value = false;
  }
};
const showAttach = (list) => { attachments.value = list; AttachDialog.value = true; };

const updateResult = (data) => {
  updateResultRecord.value = data;
  updateResultRecord.value.package_comment = data.package_comments || "";
  updateResultRecord.value.tests_comment = Array.isArray(data.tests_comment) ? data.tests_comment.join(", ") : (data.tests_comment || "");
  updateResultRecord.value.cultures_comment = Array.isArray(data.cultures_comment) ? data.cultures_comment.join(", ") : (data.cultures_comment || "");
  router.push(`/medical_reports/update-result/${data.id}`);
};

const PationtHistory = async (rec) => {
  await invoicesStore.patientHistory(rec.patient.id);
  pationtHistoryDialog.value = true;
};

const signInvoice = async (id) => {
  await $http.post(`/invoices/sign`, { id });
  if (patientId.value) {
    await invoicesStore.patient_medical_records(patientId.value);
  } else {
    await fetchInvoices();
  }
};

const print_work_sheet = async (data) => {
  await invoicesStore.GetinvoicesById(data.id);
  print_work_sheetDialog.value = true;
};

// Print functions
const openJobTemplateAsPDF = (data) => {
  printWithIframe("job", printStyles.medicalJobOrder, "Print Job Order", 100, () => invoicesStore.GetinvoicesById(data.id));
};

const openprintResultTemplate = async (data) => {
  invoicesStore.changeInvoiceStatus(data.id, false);
  await invoicesStore.GetinvoicesById(data.id);
  printSelectItem.value = data;
  printSelectMode.value = "normal";
  printSelectVisible.value = true;
};

const printParcode = (data) => {
  printWithIframe("parcode", printStyles.getBarcodeCss(labSettingsStore.settings.barcode_config), "Print Barcode", 100, () => invoicesStore.GetinvoicesById(data.id));
};

const openprintResultWithBackground = async (data) => {
  invoicesStore.changeInvoiceStatus(data.id, true);
  await invoicesStore.GetinvoicesById(data.id);
  printSelectItem.value = data;
  printSelectMode.value = "background";
  printSelectVisible.value = true;
};

// Download menu — same item-selection flow as Print, but emits "download" /
// "download-bg" so handlePrintSelection routes to html2pdf instead of window.print.
const openDownloadResult = async (data) => {
  await invoicesStore.GetinvoicesById(data.id);
  printSelectItem.value = data;
  printSelectMode.value = "download";
  printSelectVisible.value = true;
};
const openDownloadResultWithBackground = async (data) => {
  await invoicesStore.GetinvoicesById(data.id);
  printSelectItem.value = data;
  printSelectMode.value = "download-bg";
  printSelectVisible.value = true;
};
const toggleDownloadMenu = (id) => {
  downloadMenuId.value = downloadMenuId.value === id ? null : id;
  printMenuId.value = null;
  whatsappMenuId.value = null;
};

// Capture the rendered #Result element to a PDF. Uses the SAME approach as
// printDirectWithBackground: build a fresh document in an off-screen iframe
// (same HTML, same CSS) and run html2pdf against THAT — avoiding all the
// Vue reactivity / hidden-element / id-collision issues you'd hit if you
// tried to capture the live in-page element.
const downloadAsPdf = async (withBg) => {
  if (downloadInProgress.value) return;
  downloadInProgress.value = true;

  // Flip on capture mode so the source element exists in the live DOM, then
  // we'll clone its innerHTML into the iframe.
  printResultRef.value?.beginCapture?.();
  await nextTick();
  await new Promise((r) => setTimeout(r, 300));

  let iframe = null;
  try {
    const sourceEl = document.getElementById("Result");
    if (!sourceEl) {
      console.error("[download] #Result element not found");
      toast.error(t("download_failed") || "Download failed");
      return;
    }

    const content = sourceEl.innerHTML;
    if (!content || !content.trim()) {
      console.error("[download] #Result is empty");
      toast.error(t("download_failed") || "Download failed");
      return;
    }

    // Read margins from lab_settings (jsonb). Stored as strings (e.g. "20")
    // by the settings UI — Number() handles both number and string inputs.
    const rawMargins = labSettingsStore.settings.print_margins || {};
    const margins = {
      top: Number(rawMargins.top) || 20,
      bottom: Number(rawMargins.bottom) || 20,
      left: Number(rawMargins.left) || 15,
      right: Number(rawMargins.right) || 15,
    };
    const bgImage = withBg ? reportBackground.value : null;

    // Body padding is NO LONGER applied here — margins are baked into the
    // jsPDF image placement below. Otherwise we'd double-apply (once via
    // body padding visible in the captured canvas, then again in jsPDF).
    let css = printStyles.getResultCss() + printStyles.getPatientHeaderCss(labSettingsStore.settings.patient_header_config) + printStyles.getPrintTableCss(labSettingsStore.settings.print_table_config);
    if (labSettingsStore.settings.print_black_white) css += printStyles.getBlackWhiteCss();
    css += `
      body {
        padding: 0 !important;
        margin: 0 !important;
        background: #fff;
      }
      .pw-cell { padding-left: 0 !important; padding-right: 0 !important; }
      .pw-cell.pw-top { padding-top: 0 !important; }
      .pw-cell.pw-bottom { padding-bottom: 0 !important; }
      .print-bg {
        position: absolute;
        top: 0;
        left: 0;
        width: 210mm;
        height: 297mm;
        z-index: -1;
      }
      .print-bg img { width: 100%; height: 100%; display: block; }
    `;

    // Build the off-screen iframe (visible to html2canvas, hidden to user)
    iframe = document.createElement("iframe");
    iframe.style.cssText = "position: fixed; top: 0; left: -10000px; width: 210mm; height: auto; border: 0; visibility: hidden;";
    document.body.appendChild(iframe);

    const doc = iframe.contentDocument;
    doc.open();
    doc.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>Report</title><style>${css}</style></head><body>${bgImage ? `<div class="print-bg"><img src="${bgImage}" /></div>` : ""}${content}</body></html>`);
    doc.close();

    // Wait for fonts + images to load inside the iframe
    await new Promise((r) => setTimeout(r, 800));
    if (bgImage) {
      // Wait for the bg image specifically
      const bgImg = doc.querySelector(".print-bg img");
      if (bgImg && !bgImg.complete) {
        await new Promise((r) => { bgImg.onload = bgImg.onerror = r; });
      }
    }

    // Inline any remaining external images via fetch → data URL (avoids CORS taint)
    const images = doc.querySelectorAll("img");
    for (const img of images) {
      if (img.src && !img.src.startsWith("data:")) {
        try {
          const response = await fetch(img.src);
          const blob = await response.blob();
          img.src = await new Promise((res) => {
            const reader = new FileReader();
            reader.onloadend = () => res(reader.result);
            reader.readAsDataURL(blob);
          });
        } catch {
          img.remove();
        }
      }
    }

    const code = printRecord.value?.code || printRecord.value?.barcode || Date.now();
    const patientName = printRecord.value?.patient?.name || "";
    const safeName = patientName.replace(/[^\w؀-ۿ\s-]/g, "").trim();
    const filename = safeName ? `${safeName}-${code}.pdf` : `lab-report-${code}.pdf`;

    try {
      // ──────────────────────────────────────────────────────────────────
      // Per-section capture so each <table class="print-wrapper"> becomes
      // its own A4 page in the PDF (matches the print pipeline, where
      // each section gets its own page via page-break-before).
      //
      // If we captured the whole iframe body in one shot, html2canvas would
      // produce a single tall image that jsPDF slices at fixed pixel
      // intervals — sections would bleed across page boundaries and the
      // patient header would repeat inline mid-page.
      // ──────────────────────────────────────────────────────────────────
      const sections = Array.from(doc.querySelectorAll(".print-wrapper"));
      const pdf = new jsPDF({ unit: "mm", format: "a4", orientation: "portrait" });
      const pageW = pdf.internal.pageSize.getWidth();   // 210mm
      const pageH = pdf.internal.pageSize.getHeight();  // 297mm

      // Apply lab-settings margins via jsPDF placement so each captured
      // section sits inside the configured margin box on every A4 page.
      // With-background mode skips margins because the bg image fills the
      // full page and the letterhead has its own designed margins.
      const m = withBg
        ? { top: 0, bottom: 0, left: 0, right: 0 }
        : margins;
      const contentW = pageW - m.left - m.right;
      const contentH = pageH - m.top - m.bottom;

      const renderTo = sections.length > 0 ? sections : [doc.body];

      for (let i = 0; i < renderTo.length; i++) {
        const el = renderTo[i];
        const canvas = await html2canvas(el, {
          scale: 2,
          useCORS: true,
          allowTaint: false,
          logging: false,
          backgroundColor: "#ffffff",
          windowWidth: 794, // 210mm at 96dpi
        });
        const imgData = canvas.toDataURL("image/jpeg", 0.98);
        const imgW = contentW;
        const imgH = (canvas.height * imgW) / canvas.width;

        if (i > 0) pdf.addPage();

        if (imgH <= contentH) {
          pdf.addImage(imgData, "JPEG", m.left, m.top, imgW, imgH, undefined, "FAST");
        } else {
          // Section taller than one page — slice across pages, keeping
          // top/bottom margins on every page.
          let heightLeft = imgH;
          let position = m.top;
          pdf.addImage(imgData, "JPEG", m.left, position, imgW, imgH, undefined, "FAST");
          heightLeft -= contentH;
          while (heightLeft > 0) {
            position = m.top + (heightLeft - imgH);
            pdf.addPage();
            pdf.addImage(imgData, "JPEG", m.left, position, imgW, imgH, undefined, "FAST");
            heightLeft -= contentH;
          }
        }
      }
      pdf.save(filename);
      toast.success(t("download_completed") || "Download completed");
    } catch (e) {
      console.error("[download] PDF generation failed:", e);
      toast.error((t("download_failed") || "Download failed") + ": " + (e?.message || "unknown"), { duration: 6000 });
    }
  } catch (outer) {
    console.error("[download] outer error:", outer);
    toast.error(t("download_failed") || "Download failed");
  } finally {
    if (iframe?.parentNode) iframe.parentNode.removeChild(iframe);
    printResultRef.value?.endCapture?.();
    downloadInProgress.value = false;
  }
};

const printDirectWithBackground = async () => {
  await nextTick();
  await new Promise((r) => setTimeout(r, 150));

  const content = document.getElementById("Result")?.innerHTML;
  if (!content) return;

  const printWindow = window.open("", "_blank");
  if (!printWindow) return;

  const margins = labSettingsStore.settings.print_margins || { top: 20, bottom: 20, left: 15, right: 15 };
  const bgImage = reportBackground.value;

  // Strategy (from Chrome print CSS best practices):
  // 1. @page margin:0 — so position:fixed background covers full physical page
  // 2. body padding + box-decoration-break:clone — padding applies on EVERY page fragment
  // 3. position:fixed background — repeats on every page at full page size
  // box-decoration-break:clone is the KEY — it makes body padding repeat on each printed page.
  let css = printStyles.getResultCss() + printStyles.getPatientHeaderCss(labSettingsStore.settings.patient_header_config) + printStyles.getPrintTableCss(labSettingsStore.settings.print_table_config);
    if (labSettingsStore.settings.print_black_white) css += printStyles.getBlackWhiteCss();
  css += `
    /* Body padding creates content margins; box-decoration-break:clone repeats on EVERY page */
    body {
      padding: ${margins.top}mm ${margins.right}mm ${margins.bottom}mm ${margins.left}mm !important;
      -webkit-box-decoration-break: clone;
      box-decoration-break: clone;
      margin: 0 !important;
    }
    /* Zero pw-cell padding — body padding handles all margins now */
    .pw-cell { padding-left: 0 !important; padding-right: 0 !important; }
    .pw-cell.pw-top { padding-top: 0 !important; }
    .pw-cell.pw-bottom { padding-bottom: 0 !important; }

    /* Background: full page on every page via position:fixed (unaffected by body padding) */
    .print-bg {
      position: fixed;
      top: 0;
      left: 0;
      width: 210mm;
      height: 297mm;
      z-index: -1;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .print-bg img {
      width: 100%;
      height: 100%;
      display: block;
    }
  `;

  printWindow.document.write(`<!DOCTYPE html>
<html>
<head><title>Print Result</title><style>${css}</style></head>
<body>
  ${bgImage ? `<div class="print-bg"><img src="${bgImage}" /></div>` : ""}
  ${content}
</body>
</html>`);
  printWindow.document.close();

  // Wait for background image to load, then print
  const img = new Image();
  img.onload = img.onerror = () => {
    setTimeout(() => {
      printWindow.focus();
      printWindow.print();
    }, 200);
  };
  if (bgImage) {
    img.src = bgImage;
  } else {
    img.onload();
  }
};


// Handle print with selected items
const handlePrintSelection = async (selection) => {
  // Save original data (deep copy arrays)
  const original = {
    tests: [...(printRecord.value.tests || [])],
    cultures: [...(printRecord.value.cultures || [])],
    packages: [...(printRecord.value.packages || [])],
    test_groups: [...(printRecord.value.test_groups || [])],
  };

  // Filter to only selected items
  printRecord.value.tests = original.tests.filter((_, i) => selection.tests.includes(i));
  printRecord.value.cultures = original.cultures.filter((_, i) => selection.cultures.includes(i));
  printRecord.value.packages = original.packages.filter((_, i) => selection.packages.includes(i));
  printRecord.value.test_groups = original.test_groups.filter((_, i) => selection.testGroups.includes(i));

  // Wait for Vue to re-render the print template with filtered data
  await nextTick();
  await new Promise((r) => setTimeout(r, 200));

  if (selection.mode === "whatsapp" || selection.mode === "whatsapp-bg") {
    sendWhatsApp(printSelectItem.value, selection.mode === "whatsapp-bg");
  } else if (selection.mode === "download" || selection.mode === "download-bg") {
    await downloadAsPdf(selection.mode === "download-bg");
  } else if (selection.mode === "normal") {
    const margins = labSettingsStore.settings.print_margins || { top: 20, bottom: 20, left: 15, right: 15 };
    let css = printStyles.getResultCss() + printStyles.getPatientHeaderCss(labSettingsStore.settings.patient_header_config) + printStyles.getPrintTableCss(labSettingsStore.settings.print_table_config);
    if (labSettingsStore.settings.print_black_white) css += printStyles.getBlackWhiteCss();
    css += `
      body {
        padding: ${margins.top}mm ${margins.right}mm ${margins.bottom}mm ${margins.left}mm !important;
        -webkit-box-decoration-break: clone;
        box-decoration-break: clone;
        margin: 0 !important;
      }
      .pw-cell { padding-left: 0 !important; padding-right: 0 !important; }
      .pw-cell.pw-top { padding-top: 0 !important; }
      .pw-cell.pw-bottom { padding-bottom: 0 !important; }
    `;
    await printWithIframe("Result", css, "Print Result", 100);
  } else {
    await printDirectWithBackground();
  }

  // Restore original data after print completes
  printRecord.value.tests = original.tests;
  printRecord.value.cultures = original.cultures;
  printRecord.value.packages = original.packages;
  printRecord.value.test_groups = original.test_groups;
};

const onBackgroundChange = () => {
  // Background is now managed via lab-settings store, no-op
};

const togglePrintMenu = (id) => {
  printMenuId.value = printMenuId.value === id ? null : id;
  whatsappMenuId.value = null;
};

const toggleWhatsappMenu = (id) => {
  whatsappMenuId.value = whatsappMenuId.value === id ? null : id;
  printMenuId.value = null;
};

const openWhatsAppSelection = async (data, withBg) => {
  invoicesStore.changeInvoiceStatus(data.id, withBg);
  await invoicesStore.GetinvoicesById(data.id);
  printSelectItem.value = data;
  printSelectMode.value = withBg ? "whatsapp-bg" : "whatsapp";
  printSelectVisible.value = true;
};

const sendWhatsApp = (rec, withBg = false) => {
  const labName = User.value?.name || "المختبر";
  const patientName = rec?.patient?.name || "المريض";
  const appUrl = import.meta.env.VITE_APP_URL || window.location.origin;
  const resultLink = `${appUrl}/result/${rec.id}${withBg ? "?form=1" : ""}`;
  const message = `اهلا بكم في مختبر ${labName}\nعزيزي ${patientName}\nإليك نتائج الفحوصات الطبية:\n${resultLink}`;
  let phone = rec?.patient?.phone?.replace(/\s+/g, "");
  if (phone?.startsWith("+")) phone = phone.substring(1);
  if (phone?.startsWith("00")) phone = phone.substring(2);
  if (phone?.startsWith("0")) phone = "964" + phone.substring(1);
  if (!phone?.startsWith("964")) phone = "964" + phone;
  window.open(`https://wa.me/${phone}?text=${encodeURIComponent(message)}`, "_blank");
};

// Reset background mode when worksheet dialog closes
watch(print_work_sheetDialog, (val) => {
  if (!val) printWithBgMode.value = false;
});

// Watchers - debounced server-side filter fetch
let filterTimer = null;
watch(filters, () => {
  currentPage.value = 1;
  if (!patientId.value) {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
      fetchInvoices();
    }, 300);
  }
}, { deep: true });

// Barcode scan
const scanInput = ref("");
const scanResult = ref(null);
const scanLoading = ref(false);
const scanNotFound = ref(false);

const scanBarcode = async () => {
  const barcode = scanInput.value.trim();
  if (!barcode) return;

  scanLoading.value = true;
  scanNotFound.value = false;
  scanResult.value = null;

  try {
    const found = invoices.value.find((inv) => inv.barcode === barcode);
    let invoiceId = found?.id;

    // If not found on current page, search by barcode via API
    if (!invoiceId) {
      const { data } = await $http.get("/invoices", { params: { patient_name: barcode, per_page: 1 } });
      const match = data?.data?.find((inv) => inv.barcode === barcode);
      invoiceId = match?.id;
    }

    if (invoiceId) {
      await invoicesStore.GetinvoicesById(invoiceId);
      const data = printRecord.value;
      const allTests = [];
      data?.tests?.forEach((t) => allTests.push({ name: t.report_name || t.name, is_done: t.is_done }));
      data?.cultures?.forEach((c) => allTests.push({ name: c.name, is_done: c.is_done }));
      data?.packages?.forEach((p) => {
        allTests.push({ name: p.name, is_done: p.is_done });
      });
      data?.test_groups?.forEach((g) => {
        allTests.push({ name: g.group_name, is_done: g.is_done });
      });

      const total = allTests.length;
      const done = allTests.filter((t) => t.is_done).length;
      scanResult.value = {
        invoice: data,
        tests: allTests,
        total,
        done,
        percentage: total > 0 ? Math.round((done / total) * 100) : 0,
      };
    } else {
      scanNotFound.value = true;
    }
  } catch {
    scanNotFound.value = true;
  }
  scanLoading.value = false;
};

const clearScan = () => {
  scanInput.value = "";
  scanResult.value = null;
  scanNotFound.value = false;
};

// Lifecycle
onMounted(async () => {
  isLoading.value = true;
  try {
    patientId.value = route.params.patientId || route.query.patient_id || null;
    if (patientId.value) {
      // Patient mode: client-side filtering (small dataset)
      await invoicesStore.patient_medical_records(patientId.value);
    } else {
      // Main list: server-side pagination
      await Promise.all([fetchInvoices(), contractsStore.GetRecords()]);
    }
  } finally {
    isLoading.value = false;
  }
});
</script>

<template>
  <div class="space-y-6" @click="closeMenu">
    <!-- ==================== HEADER SECTION ==================== -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 shadow-2xl">
      <div class="absolute inset-0">
        <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.03\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')]"></div>
        <div class="absolute -top-24 -end-24 w-96 h-96 bg-primary-500/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-12 -start-12 w-64 h-64 bg-primary-600/15 rounded-full blur-2xl"></div>
      </div>

      <div class="relative p-6 lg:p-8">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
          <div class="flex-1">
            <div class="flex items-center gap-3 mb-3">
              <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center shadow-xl shadow-primary-500/30">
                <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
              <span class="px-3 py-1 bg-primary-500/20 text-primary-300 text-xs font-semibold rounded-full">
                {{ stats.total }} {{ t("medical_reports") }}
              </span>
            </div>
            <h1 class="text-2xl lg:text-3xl font-bold text-white mb-2">{{ t("medical_reports") }}</h1>
            <p class="text-slate-400 text-sm lg:text-base">{{ t("manage_medical_reports") || "Manage patient test results and reports" }}</p>
          </div>

          <div class="flex gap-3 flex-wrap">
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[120px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-green-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-green-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("done") }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ stats.completed_done }}</p>
            </div>
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[120px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-amber-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("pendening") }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ stats.pending_done }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== STATS CARDS ==================== -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("total") }} {{ t("medical_reports") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ stats.total }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-primary-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("done") }}</p>
            <p class="text-2xl font-bold text-green-600">{{ stats.completed_done }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("Signature") }}</p>
            <p class="text-2xl font-bold text-blue-600">{{ stats.signed }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("is_sent_to_patient") }}</p>
            <p class="text-2xl font-bold text-purple-600">{{ stats.sent }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== FILTERS & ACTIONS ==================== -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
      <div class="p-5">
        <div class="flex flex-wrap items-end gap-4">
          <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("search") }}</label>
            <div class="relative">
              <span class="absolute inset-y-0 start-0 flex items-center ps-4 text-slate-400">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </span>
              <input
                v-model="filters.patient_name"
                type="text"
                :placeholder="t('Pationt_name') + ' / ' + t('barcode') + '...'"
                class="w-full ps-12 pe-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
              />
            </div>
          </div>

          <div class="flex items-center gap-3">
            <button
              @click="toggleFilters"
              class="px-4 py-2.5 border border-slate-200 hover:bg-slate-50 text-slate-700 font-medium rounded-xl transition-all flex items-center gap-2 relative"
              :class="{ 'bg-primary-50 border-primary-200 text-primary-700': showFilters || activeFiltersCount > 0 }"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
              </svg>
              <span class="hidden sm:inline">{{ t("filters") }}</span>
              <span v-if="activeFiltersCount > 0" class="absolute -top-2 -end-2 w-5 h-5 bg-primary-600 text-white text-xs font-bold rounded-full flex items-center justify-center">
                {{ activeFiltersCount }}
              </span>
            </button>
            <button v-if="activeFiltersCount > 0" @click="clearFilters" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-xl transition-all flex items-center gap-2">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
              <span class="hidden sm:inline">{{ t("Clear Filters") }}</span>
            </button>
            <button @click="exportToExcel" class="px-4 py-2.5 bg-green-50 hover:bg-green-100 text-green-700 font-medium rounded-xl transition-all flex items-center gap-2">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <span class="hidden sm:inline">{{ t("Export to Excel") }}</span>
            </button>
            <router-link to="/lab-settings" class="px-5 py-2.5 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-medium rounded-xl transition-all flex items-center gap-2 shadow-lg shadow-primary-500/25 no-underline">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              {{ t("lab_settings") }}
            </router-link>
          </div>
        </div>
      </div>

      <!-- Collapsible Filters -->
      <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="max-h-0 opacity-0"
        enter-to-class="max-h-96 opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="max-h-96 opacity-100"
        leave-to-class="max-h-0 opacity-0"
      >
        <div v-if="showFilters" class="overflow-hidden">
          <div class="px-5 pb-5 pt-2 border-t border-slate-100 bg-slate-50/50">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("theStatus") }}</label>
                <select v-model="filters.status" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all">
                  <option value="">{{ t("all") }}</option>
                  <option value="done">{{ t("done") }}</option>
                  <option value="pending">{{ t("pendening") }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("Signature") }}</label>
                <select v-model="filters.signed_status" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all">
                  <option value="">{{ t("all") }}</option>
                  <option value="signed">{{ t("done") }}</option>
                  <option value="unsigned">{{ t("pendening") }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("contract") }}</label>
                <select v-model="filters.contract_id_fk" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all">
                  <option value="">{{ t("all") }}</option>
                  <option v-for="c in contracts" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("from_lab") }}</label>
                <input v-model="filters.from_lab" type="text" :placeholder="t('from_lab') + '...'" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all" />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("Registration_date") }}</label>
                <input v-model="filters.registration_date" type="date" @click="$event.target.showPicker()" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all" />
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </div>

    <!-- ==================== DATA TABLE ==================== -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
      <!-- Loading -->
      <div v-if="isLoading" class="p-12 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary-50 mb-4">
          <svg class="w-8 h-8 text-primary-600 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>
        <p class="text-slate-500">{{ t("loading") }}...</p>
      </div>

      <!-- Empty -->
      <div v-else-if="invoices.length === 0" class="p-12 text-center">
        <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-10 h-10 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ t("noData") }}</h3>
        <p class="text-slate-500">{{ t("no_reports_message") || "No medical reports found." }}</p>
      </div>

      <!-- Table -->
      <div v-else>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200">
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">#</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Pationt_name") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Registration_date") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("lab/bruanch") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Created_By") }}</th>
                <th v-if="contracts?.length" class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("contract") }}</th>
                <th v-if="!patientId" class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Barcode") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("sign_by") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("theStatus") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("is_sent_to_patient") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("actions") }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="item in invoices" :key="item.id" class="hover:bg-slate-50/80 transition-colors group">
                <td class="px-5 py-4">
                  <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 text-slate-600 text-sm font-medium group-hover:bg-primary-50 group-hover:text-primary-600 transition-colors">
                    {{ item.index }}
                  </span>
                </td>
                <td class="px-5 py-4">
                  <button v-if="item.patient?.name" @click.stop="showPatient(item.patient)" class="inline-flex items-center gap-2 px-3 py-1.5 bg-primary-50 text-primary-700 text-sm font-medium rounded-lg hover:bg-primary-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    {{ item.patient?.name }}
                  </button>
                </td>
                <td class="px-5 py-4 text-sm font-medium text-slate-700">{{ dateTimeFormat(item.registration_date) }}</td>
                <td class="px-5 py-4 text-sm text-slate-600">{{ item.lab }}</td>
                <td class="px-5 py-4 text-sm text-slate-600">{{ item.created_by?.name }}</td>
                <td v-if="contracts?.length" class="px-5 py-4">
                  <span v-if="item.contract?.name" class="inline-flex items-center px-2.5 py-1 bg-blue-50 text-blue-700 text-xs font-medium rounded-lg">{{ item.contract?.name }}</span>
                  <span v-else class="text-slate-400">-</span>
                </td>
                <td v-if="!patientId" class="px-5 py-4">
                  <button @click.stop="printParcode(item)" class="text-center hover:opacity-80 transition-opacity">
                    <span class="text-xs text-slate-500 block mb-1">{{ item.barcode }}</span>
                    <BarcodeComponent :value="item.barcode" />
                  </button>
                </td>
                <td class="px-5 py-4 text-sm text-slate-600">{{ item.signed_by?.name || '-' }}</td>
                <td class="px-5 py-4">
                  <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold" :class="item.is_done ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'">
                    <span class="w-2 h-2 rounded-full" :class="item.is_done ? 'bg-green-500' : 'bg-amber-500'"></span>
                    {{ item.is_done ? t('done') : t('pendening') }}
                  </span>
                </td>
                <td class="px-5 py-4">
                  <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold" :class="item.sent_to_patient ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                    <span class="w-2 h-2 rounded-full" :class="item.sent_to_patient ? 'bg-green-500' : 'bg-red-500'"></span>
                    {{ item.sent_to_patient ? t('done') : t('pendening') }}
                  </span>
                </td>
                <td class="px-5 py-4">
                  <div class="flex items-center gap-1">
                    <!-- Edit Patient -->
                    <button
                      v-if="item.patient?.id"
                      @click.stop="router.push({ path: '/patients', query: { edit: item.patient.id } })"
                      class="p-2 text-indigo-500 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition-all"
                      :title="t('edit_patient')"
                    >
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                      </svg>
                    </button>

                    <!-- View Tests -->
                    <button @click.stop="showTestsList(item)" class="p-2 text-slate-500 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-all" :title="t('view_tests') || 'عرض الفحوصات'">
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                      </svg>
                    </button>

                    <!-- Update Result -->
                    <button v-if="authStore.havePermission('invoices edit') && !patientId" @click.stop="updateResult(item)" class="p-2 text-amber-500 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition-all" :title="t('updateResult')">
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </button>

                    <!-- Print Result Menu -->
                    <div class="relative">
                      <button @click.stop="togglePrintMenu(item.id)" class="p-2 text-primary-500 hover:text-primary-700 hover:bg-primary-50 rounded-lg transition-all" :title="t('Result')">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                      </button>
                      <Transition
                        enter-active-class="transition duration-100 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition duration-75 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                      >
                        <div v-if="printMenuId === item.id" class="absolute end-0 z-50 mt-2 w-52 bg-white rounded-xl shadow-xl border border-slate-200 py-2 overflow-hidden">
                          <button @click.stop="openprintResultTemplate(item); closeMenu()" class="w-full px-4 py-2.5 text-start text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors">
                            <div class="w-7 h-7 rounded-lg bg-primary-50 flex items-center justify-center">
                              <svg class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                              </svg>
                            </div>
                            {{ t("print_normal") || "Print Normal" }}
                          </button>
                          <button v-if="reportBackground" @click.stop="openprintResultWithBackground(item); closeMenu()" class="w-full px-4 py-2.5 text-start text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors">
                            <div class="w-7 h-7 rounded-lg bg-indigo-50 flex items-center justify-center">
                              <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                              </svg>
                            </div>
                            {{ t("print_with_background") || "Print with Background" }}
                          </button>
                        </div>
                      </Transition>
                    </div>

                    <!-- Download Result Menu — same selection flow + same DOM as Print, output via html2pdf -->
                    <div class="relative">
                      <button @click.stop="toggleDownloadMenu(item.id)" class="p-2 text-info-500 hover:text-info-700 hover:bg-info-50 rounded-lg transition-all" :title="t('download') || 'Download'">
                        <svg v-if="!downloadInProgress" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        <svg v-else class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                      </button>
                      <Transition
                        enter-active-class="transition duration-100 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition duration-75 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                      >
                        <div v-if="downloadMenuId === item.id" class="absolute end-0 z-50 mt-2 w-52 bg-white rounded-xl shadow-xl border border-slate-200 py-2 overflow-hidden">
                          <button @click.stop="openDownloadResult(item); closeMenu()" class="w-full px-4 py-2.5 text-start text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors">
                            <div class="w-7 h-7 rounded-lg bg-info-50 flex items-center justify-center">
                              <svg class="w-4 h-4 text-info-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                              </svg>
                            </div>
                            {{ t("download_normal") || "Download Normal" }}
                          </button>
                          <button v-if="reportBackground" @click.stop="openDownloadResultWithBackground(item); closeMenu()" class="w-full px-4 py-2.5 text-start text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors">
                            <div class="w-7 h-7 rounded-lg bg-indigo-50 flex items-center justify-center">
                              <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                              </svg>
                            </div>
                            {{ t("download_with_background") || "Download with Background" }}
                          </button>
                        </div>
                      </Transition>
                    </div>

                    <!-- WhatsApp -->
                    <div v-if="!patientId && authStore.havePermission('invoices send whatsapp')" class="relative">
                      <button @click.stop="toggleWhatsappMenu(item.id)" class="p-2 text-green-500 hover:text-green-700 hover:bg-green-50 rounded-lg transition-all" :title="t('send_whatsapp')">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                          <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                      </button>
                      <Transition
                        enter-active-class="transition duration-100 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition duration-75 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                      >
                        <div v-if="whatsappMenuId === item.id" class="absolute end-0 z-50 mt-2 w-52 bg-white rounded-xl shadow-xl border border-slate-200 py-2 overflow-hidden">
                          <button @click.stop="openWhatsAppSelection(item, false); closeMenu()" class="w-full px-4 py-2.5 text-start text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors">
                            <div class="w-7 h-7 rounded-lg bg-green-50 flex items-center justify-center">
                              <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                              </svg>
                            </div>
                            {{ t("without_form") }}
                          </button>
                          <button v-if="reportBackground" @click.stop="openWhatsAppSelection(item, true); closeMenu()" class="w-full px-4 py-2.5 text-start text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors">
                            <div class="w-7 h-7 rounded-lg bg-indigo-50 flex items-center justify-center">
                              <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                              </svg>
                            </div>
                            {{ t("with_form") }}
                          </button>
                        </div>
                      </Transition>
                    </div>

                    <!-- More Menu -->
                    <div class="relative">
                      <button @click.stop="toggleMenu(item.id)" class="p-2 text-blue-500 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition-all">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                        </svg>
                      </button>

                      <Transition
                        enter-active-class="transition duration-100 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition duration-75 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                      >
                        <div v-if="activeMenuId === item.id" class="absolute end-0 z-50 mt-2 w-52 bg-white rounded-xl shadow-xl border border-slate-200 py-2 overflow-hidden">
                          <button @click.stop="showPatient_due(item); closeMenu()" class="w-full px-4 py-2.5 text-start text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors">
                            <div class="w-8 h-8 rounded-lg bg-primary-50 flex items-center justify-center">
                              <svg class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                              </svg>
                            </div>
                            {{ t("the_tests") }}
                          </button>

                          <button v-if="!patientId" @click.stop="openJobTemplateAsPDF(item); closeMenu()" class="w-full px-4 py-2.5 text-start text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors">
                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                              <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                              </svg>
                            </div>
                            {{ t("job_order") }}
                          </button>

                          <button v-if="!patientId" @click.stop="signInvoice(item.id); closeMenu()" class="w-full px-4 py-2.5 text-start text-sm hover:bg-slate-50 flex items-center gap-3 transition-colors" :class="item.signed_by ? 'text-green-600' : 'text-slate-700'">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center" :class="item.signed_by ? 'bg-green-50' : 'bg-slate-50'">
                              <svg class="w-4 h-4" :class="item.signed_by ? 'text-green-600' : 'text-slate-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                              </svg>
                            </div>
                            {{ t("Signature") }}
                          </button>

                          <button @click.stop="PationtHistory(item); closeMenu()" class="w-full px-4 py-2.5 text-start text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors">
                            <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
                              <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                              </svg>
                            </div>
                            {{ t("pationtHistory") }}
                          </button>

                          <button @click.stop="showAttach(item.attachments); closeMenu()" class="w-full px-4 py-2.5 text-start text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors">
                            <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center">
                              <svg class="w-4 h-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                              </svg>
                            </div>
                            {{ t("attachments") }}
                          </button>

                          <button v-if="!patientId" @click.stop="print_work_sheet(item); closeMenu()" class="w-full px-4 py-2.5 text-start text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors">
                            <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                              <svg class="w-4 h-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                              </svg>
                            </div>
                            {{ t("print_work_sheet") }}
                          </button>

                          <hr class="my-2 border-slate-100" />

                          <button v-if="reportBackground" @click.stop="openprintResultWithBackground(item); closeMenu()" class="w-full px-4 py-2.5 text-start text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors">
                            <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
                              <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                              </svg>
                            </div>
                            {{ t("print_with_background") || "Print with Background" }}
                          </button>

                        </div>
                      </Transition>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="px-5 py-4 border-t border-slate-200 bg-gradient-to-r from-slate-50 to-white">
          <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-sm text-slate-600">
              {{ t("showing") }}
              <span class="font-semibold text-slate-800">{{ patientId ? (currentPage - 1) * perPage + 1 : (pagination.from || 0) }}</span>
              -
              <span class="font-semibold text-slate-800">{{ patientId ? Math.min(currentPage * perPage, invoices.length) : (pagination.to || 0) }}</span>
              {{ t("of") }}
              <span class="font-semibold text-slate-800">{{ patientId ? invoices.length : pagination.total }}</span>
            </p>
            <div class="flex items-center gap-2">
              <button @click="onPageChange(currentPage - 1)" :disabled="currentPage === 1" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-300 disabled:opacity-50 disabled:cursor-not-allowed transition-all flex items-center gap-2">
                <svg class="w-4 h-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                {{ t("previous") }}
              </button>
              <div class="flex items-center gap-1">
                <template v-for="page in totalPages" :key="page">
                  <button v-if="page === 1 || page === totalPages || (page >= currentPage - 1 && page <= currentPage + 1)" @click="onPageChange(page)" class="w-10 h-10 rounded-xl text-sm font-medium transition-all" :class="page === currentPage ? 'bg-primary-600 text-white shadow-lg shadow-primary-500/25' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 hover:border-slate-300'">
                    {{ page }}
                  </button>
                  <span v-else-if="page === currentPage - 2 || page === currentPage + 2" class="px-2 text-slate-400">...</span>
                </template>
              </div>
              <button @click="onPageChange(currentPage + 1)" :disabled="currentPage >= totalPages" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-300 disabled:opacity-50 disabled:cursor-not-allowed transition-all flex items-center gap-2">
                {{ t("next") }}
                <svg class="w-4 h-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <pationtHistoryModal />
    <PrintMarginDialog v-model:visible="marginDialogVisible" :background="reportBackground" @background-change="onBackgroundChange" />
    <patientModal />
    <DueModal />
    <job_orderModal />
    <printResult ref="printResultRef" />
    <parcodModal />
    <workSheetModal :with-background="printWithBgMode" :background-image="reportBackground" />
    <attachment />
    <printSelectModal v-model="printSelectVisible" :print-mode="printSelectMode" @print="handlePrintSelection" />
    <TestsListModal v-model="testsListOpen" :invoice="testsListInvoice" :loading="testsListLoading" />

  </div>
</template>
