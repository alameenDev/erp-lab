<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from "vue";
import { storeToRefs } from "pinia";
import { useRoute } from "vue-router";
import { createMedicalReportPdf, renderMedicalReportPages, printMedicalReportPages } from "@/utils/medicalReportPages";
import JsBarcode from "jsbarcode";
import { reportPatientPortalUrl } from "@/utils/reportPatientPortal";
import { reportFormChoice, reportFormFromRoute, reportUrlWithForm, medicalReportFilename, downloadMedicalReportFile } from "@/utils/medicalReportOutput";
import { useTemplatesStore } from "@/store/modules/template";
import { useinvoicesStore } from "@/store/modules/invoices";
import { useresultStatusStore } from "@/store/modules/result-status";
import result_section from "./result_section.vue";
import ReportResultFlag from "./ReportResultFlag.vue";
import ResultHistoryTable from '@/components/ResultHistoryTable.vue';
import { resultHistoryRows } from '@/utils/resultHistory';
import ReportTableColumns from "./ReportTableColumns.vue";
import { customReportColumns } from "@/utils/medicalReportColumns";
import result_footer_section from "./result_footer_section.vue";
import { sanitizeHtml } from "@/utils/helper";
import { $http } from "@/plugins/axios";
import { usePrint } from "@/composables/usePrint";
import { useLabSettingsStore } from "@/store/modules/labSettings";

const route = useRoute();
const referralReport = computed(() => route.name === "referral-portal-report");
const sharedReferralReport = computed(() => route.name === "referral-shared-report");
const templatesStore = useTemplatesStore();
const invoicesStore = useinvoicesStore();
const resultStatusStore = useresultStatusStore();

const { templates, record, dialog } = storeToRefs(templatesStore);
const { resultStatus } = storeToRefs(resultStatusStore);
const { printRecord } = storeToRefs(invoicesStore);

const { GetTemplates } = templatesStore;
const { GetinvoicesById } = invoicesStore;
const { printStyles } = usePrint();

const today = ref("");
const reportPages = ref([]);
const reportLoaded = ref(false);
const patientId = ref(null);
const template = ref({});
const contentToConvert = ref(null);
const showWithForm = ref(reportFormFromRoute(route));
const showDirectView = ref(false);
const reportNotReady = ref(false);
const pdfGenerating = ref(false);
const renderForCapture = ref(false);
// Parents expose the source only while preparing a report.
defineExpose({
  beginCapture: () => { renderForCapture.value = true; },
  endCapture: () => { renderForCapture.value = false; },
  prepareReport: (options) => prepareReport(options),
});
const _defaultMargins = { top: 20, bottom: 20, left: 15, right: 15 };
const labSettingsStore = useLabSettingsStore();
const publicLabSettings = ref(null);
const reportSettings = computed(() => publicLabSettings.value || labSettingsStore.settings);
const modernReport = computed(() => reportSettings.value.report_template === "modern");
const reportBrand = computed(() => referralReport.value || sharedReferralReport.value ? reportSettings.value : null);
const reportShareUrl = ref("");
const patientPortalUrl = ref("");
const qrRecordKey = computed(() => JSON.stringify([printRecord.value?.id,
  printRecord.value?.patient?.id || printRecord.value?.patient_id_fk,
  printRecord.value?.suppress_report_qr, route.hash]));
watch(qrRecordKey, () => { patientPortalUrl.value = ''; }, { flush: 'sync' });
const reportQrUrl = computed(() => referralReport.value || sharedReferralReport.value
  ? reportShareUrl.value : patientPortalUrl.value);
const shareError = ref("");
const sharing = ref(false);
const imageBaseUrl = import.meta.env.VITE_IMAGE_URL || window.location.origin + "/storage";
const referralLogo = computed(() => {
  const logo = reportBrand.value?.logo;
  return logo ? (logo.startsWith("http") ? logo : `${imageBaseUrl}/${logo}`) : "";
});
const backgroundUrl = computed(() => reportSettings.value.report_background || null);
const serverMargins = computed(() => reportSettings.value.print_margins || _defaultMargins);
const showBackground = computed(() => Boolean(backgroundUrl.value) && reportFormChoice(showWithForm.value));
let previewTimer;
onUnmounted(() => clearTimeout(previewTimer));

// Check if there's any data to print
const hasAnyData = computed(() => {
  return (
    printRecord.value?.tests?.length > 0 ||
    printRecord.value?.cultures?.length > 0 ||
    printRecord.value?.packages?.length > 0 ||
    printRecord.value?.test_groups?.length > 0
  );
});

const hasRealTemplate = (t) =>
  t.sub_tests?.length > 0 && !!(t.content?.html || "").replace(/<[^>]+>/g, "").trim();

// Has special template test (sub_tests)
const hasTemplateTest = computed(() => {
  const inTests = printRecord.value?.tests?.some(hasRealTemplate);
  const inGroups = printRecord.value?.test_groups?.some(g => g.tests?.some(hasRealTemplate));
  const inPackages = printRecord.value?.packages?.some(p => p.tests?.some(hasRealTemplate));
  return inTests || inGroups || inPackages;
});

// All template tests (with sub_tests) — from standalone, test_groups, and packages
const templateTests = computed(() => {
  const results = [];
  printRecord.value?.tests?.filter(hasRealTemplate).forEach(t => results.push(t));
  printRecord.value?.test_groups?.forEach(g => {
    g.tests?.filter(hasRealTemplate).forEach(t => results.push(t));
  });
  printRecord.value?.packages?.forEach(p => {
    p.tests?.filter(hasRealTemplate).forEach(t => results.push(t));
  });
  return results;
});

// Standalone normal tests (no sub_tests), grouped by test_group name then category
const groupedTests = computed(() => {
  // Standalone tests + package tests FLATTENED — a test inside a package
  // prints exactly like a standalone test (own category/name captions + its
  // own table). Template tests are excluded (they print via templateTests);
  // formula-target tests stay behind for the package's formula rows.
  const source = [
    ...(printRecord.value?.tests || []).map(test => ({ test, bundle: '' })),
    // A package's OWN tests print flat like standalone tests. Tests that came
    // from a whole test group attached to the package are excluded here and
    // render as a proper group block instead (see packageGroupSections), so the
    // group keeps its heading and its formula rows.
    ...((printRecord.value?.packages || []).flatMap((pkg, index) =>
      (pkg.tests || []).filter((t) => !isFormulaTarget(pkg, t) && !t.test_group_name)
        .map(test => ({ test, bundle: `package:${index}` }))
    )),
  ];
  if (!source.length) return [];

  const groups = [];
  let currentGroup = null;
  const shownCategories = new Set();

  source
    .filter(({ test }) => (test.name || test.report_name) && !hasRealTemplate(test))
    .forEach(({ test, bundle }) => {
      if (!currentGroup || currentGroup.category !== test.category || currentGroup.name !== test.name || currentGroup.bundle !== bundle) {
        const isFirstCategory = !shownCategories.has(test.category);
        if (test.category) shownCategories.add(test.category);
        currentGroup = {
          category: test.category,
          showCategory: isFirstCategory,
          name: test.name,
          bundle,
          tests: [],
        };
        groups.push(currentGroup);
      }
      currentGroup.tests.push(test);
    });

  return groups;
});

onMounted(() => {
  const todayDate = new Date();
  const year = todayDate.getFullYear();
  const month = String(todayDate.getMonth() + 1).padStart(2, "0");
  const day = String(todayDate.getDate()).padStart(2, "0");
  today.value = `${year}-${month}-${day}`;


  // Only fetch auth-required data for logged-in users
  if (hasToken && !referralReport.value && !sharedReferralReport.value) {
    GetTemplates();
  }
  if (!resultStatus.value?.length) {
    resultStatusStore.GetresultStatus();
  }
  patientId.value = route?.params?.patientId;
  if (patientId.value) {
    getData();
  }

  // Public page: ensure body allows scrolling
  if (!hasToken) {
    document.documentElement.style.overflow = "auto";
    document.body.style.overflow = "auto";
    document.body.style.background = "#e5e7eb";
    document.body.style.padding = "16px 0";
  }

});

const fetchTemplates = () => {
  templates.value.forEach((t) => {
    template.value[t.id] = t.content.html;
  });
};

const getTemplateHtml = (data) => {
  let templateHtml = data?.content?.html || "<p>No print template available</p>";

  // Migrate legacy page-break markers.
  // Some templates were saved when TipTap stripped the <div class="page-break">
  // wrapper, leaving only the literal text "Page break" inside a <p>. Convert
  // those — and any whitespace/&nbsp; variants — back to a real page-break div
  // BEFORE sanitize so the print CSS can honor them.
  templateHtml = templateHtml.replace(/<p[^>]*>([\s\S]*?)<\/p>/gi, (match, inner) => {
    const plain = inner
      .replace(/<[^>]+>/g, "")
      .replace(/&nbsp;|&#160;/g, " ")
      .replace(/\s+/g, " ")
      .trim()
      .toLowerCase();
    if (plain === "page break" || plain === "page-break") {
      return '<div class="page-break" data-type="page-break" style="page-break-before: always !important; break-before: page !important; height: 0;"></div>';
    }
    return match;
  });

  templateHtml = templateHtml.replace(/{{description}}/g, data?.category || "");
  templateHtml = templateHtml.replace(/{{result}}/g, data?.result || "");
  templateHtml = templateHtml.replace(/{{test}}/g, data?.name || "");
  templateHtml = templateHtml.replace(/{{name}}/g, data?.name || "");
  templateHtml = templateHtml.replace(/{{category}}/g, data?.category || "");
  templateHtml = templateHtml.replace(/{{unit}}/g, data?.unit || "");

  if (Array.isArray(data.sub_tests)) {
    // Try both raw-name (legacy) and slugified-key (new editor) forms so
    // templates created before/after the smart-editor upgrade both work.
    const slugify = (raw) =>
      String(raw || "").trim().replace(/\s+/g, "_").replace(/[^\w؀-ۿ]/g, "").slice(0, 64);

    data.sub_tests.forEach((sub) => {
      if (!sub?.name) return;
      const variants = new Set([sub.name, slugify(sub.name)]);
      let printValue = sub.value;
      // For selection sub-tests (type 4), drop stale values no longer in the
      // option list (e.g. "Normal" removed from the test definition).
      let opts = sub.sup_test_reference_options;
      if (typeof opts === "string") {
        try { opts = JSON.parse(opts); } catch { opts = []; }
      }
      if (Number(sub.type) === 4 && Array.isArray(opts) && opts.length > 0 && typeof printValue === "string") {
        printValue = printValue.split(", ").filter((v) => opts.includes(v)).join(", ");
      }
      if (!printValue || printValue === "null") printValue = "---";
      // Per-option colour picked in /tests (sub_test.option_colors). Only applied
      // when every selected option agrees, so mixed picks stay neutral.
      const cmap = sub.option_colors;
      if (cmap && printValue !== "---") {
        const picked = [...new Set(String(printValue).split(", ").map((v) => cmap[v]).filter(Boolean))];
        if (picked.length === 1 && !printBlackWhite.value) {
          printValue = `<span style="color:${picked[0]} !important;font-weight:700;">${printValue}</span>`;
        }
      }
      variants.forEach((variant) => {
        const escaped = variant.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
        templateHtml = templateHtml.replace(new RegExp(`\\{\\{sub_test\\.${escaped}\\.value\\}\\}`, "g"), printValue);
        templateHtml = templateHtml.replace(new RegExp(`\\{\\{sub_test\\.${escaped}\\.name\\}\\}`, "g"), sub.name || "---");
        templateHtml = templateHtml.replace(new RegExp(`\\{\\{sub_test\\.${escaped}\\.comment\\}\\}`, "g"), sub.comment || "");
      });
    });
  }

  // SECURITY: Sanitize template HTML to prevent XSS
  const sanitizedHtml = sanitizeHtml(templateHtml);

  return `
    <div class="custom-table-container">
      <style>
        .custom-table-container table {
          width: 100%;
          border-collapse: collapse;
          background: #fff;
        }
        .custom-table-container th,
        .custom-table-container td {
          border: 1px solid #ccc;
          padding: 4px 6px;
          /* Default to start so it beats the global th,td center in getResultCss.
             Editor's own inline align (center/end) still wins via inline style.
             Matches on-screen. */
          text-align: start;
        }
        /* Per-cell padding set in the editor wins over the default */
        .custom-table-container th[data-cell-padding],
        .custom-table-container td[data-cell-padding] {
          padding: attr(data-cell-padding);
        }
        /* Page break marker — force a fresh print page wherever the user
           inserted it. Hide the visible "page break" label in print. */
        .custom-table-container .page-break {
          page-break-before: always;
          break-before: page;
          height: 0;
          font-size: 0;
          line-height: 0;
          color: transparent;
          border: 0;
          margin: 0;
          padding: 0;
          background: transparent;
        }
        @media screen {
          .custom-table-container .page-break {
            display: block;
            text-align: center;
            font-size: 11px;
            color: #0f766e;
            padding: 6px 0;
            margin: 10px 0;
            height: auto;
            line-height: 1.4;
            border-top: 2px dashed #14b8a6;
            border-bottom: 2px dashed #14b8a6;
          }
        }
        .custom-table-container th {
          background: #f8f9fa;
          font-weight: bold;
        }
      </style>
      ${sanitizedHtml}
    </div>
  `;
};

// Split a custom-template's HTML at each page-break marker using the DOM —
// more reliable than regex for nested/varied markup. Each chunk is emitted
// as its own .custom-table-container with the shared <style> block so CSS
// still applies. The caller renders each chunk in its own <tr> with
// page-break-before: always on all but the first.
const templateChunks = (data) => {
  const full = getTemplateHtml(data);
  if (typeof DOMParser === "undefined") return [full];

  const doc = new DOMParser().parseFromString(full, "text/html");
  const container = doc.querySelector(".custom-table-container");
  if (!container) return [full];

  const breaks = container.querySelectorAll(".page-break, [data-type='page-break']");
  if (!breaks.length) return [full];

  // Replace every page-break element with a unique placeholder so we can split
  // the final innerHTML string reliably.
  const placeholder = "__DL_PAGE_BREAK__";
  breaks.forEach((el) => {
    const marker = doc.createTextNode(placeholder);
    el.parentNode.insertBefore(marker, el);
    el.remove();
  });

  const styleEl = container.querySelector("style");
  const styleHtml = styleEl ? styleEl.outerHTML : "";
  if (styleEl) styleEl.remove();

  const rawInner = container.innerHTML;
  const parts = rawInner
    .split(placeholder)
    .map((p) => p.trim())
    .filter(Boolean);

  if (parts.length <= 1) return [full];
  return parts.map((p) => `<div class="custom-table-container">${styleHtml}${p}</div>`);
};

const hasToken = !!localStorage.getItem("token");

// One settings source drives the visible report and every export action.
const showCategories = computed(() => reportSettings.value.show_categories !== false);
const showTestName = computed(() => reportSettings.value.show_test_names !== false);
const printBlackWhite = computed(() => reportSettings.value.print_black_white === true);
const showStatus = computed(() => reportSettings.value.show_status !== false);
const showLastResult = computed(() => reportSettings.value.show_last_result === true);

// Inject dynamic <style> for patient-header sizes from lab_settings.
// Same CSS used in print/PDF/WhatsApp via printStyles.getPatientHeaderCss().
const applyPatientHeaderCss = (config) => {
  let el = document.getElementById("patient-header-dynamic");
  if (!el) {
    el = document.createElement("style");
    el.id = "patient-header-dynamic";
    document.head.appendChild(el);
  }
  el.textContent = printStyles.getPatientHeaderCss(config) + printStyles.getPrintTableCss(reportSettings.value.print_table_config) + printStyles.getReportTemplateCss(reportSettings.value);
};
watch(reportSettings, (s) => {
  applyPatientHeaderCss(s.patient_header_config);
  if (reportLoaded.value) {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(generatePDF, 100);
  }
}, { deep: true, immediate: true });

watch(() => [route.query.form, route.hash], () => {
  showWithForm.value = reportFormFromRoute(route);
  if (reportLoaded.value) generatePDF();
});

// Build a map of last results keyed by test name for quick lookup
const lastResultMap = computed(() => {
  const map = {};
  printRecord.value?.tests_last_results?.forEach(lr => {
    if (lr.name) map[lr.name] = lr.result;
  });
  return map;
});

// Column count for category colspan
const colCount = computed(() => {
  let count = 4; // Test, Result, Unit, Reference Ranges
  if (showStatus.value) count++;
  if (showLastResult.value) count++;
  return count;
});

// Merge ALL tests into one list with category rows when showTestName is off
const allTestsMerged = computed(() => {
  if (showTestName.value) return [];
  const sections = [];
  const shownCategories = new Set();

  let blockIndex = 0;
  const addTests = (category, tests, printAlone = false, bundle = '') => {
    if (!tests?.length) return;
    const blocks = tests.map(() => `merged:${blockIndex}`);
    const bundles = tests.map(() => bundle);
    blockIndex++;
    // Identity of the block being added. Blocks only merge into the open section
    // when they share this key — previously ANY block without a category header
    // was appended to whatever section came last, so e.g. a category-less
    // "Electrolyte Profile" group had its tests printed under the unrelated
    // "Immunology" heading. Same-category blocks still merge (that was the
    // point of merged mode), and consecutive category-less blocks still merge
    // with each other because their key is equally empty.
    const key = category || "";
    const isFirstShowOfCategory = showCategories.value && !!category && !shownCategories.has(category);
    if (isFirstShowOfCategory) shownCategories.add(category);

    const last = sections[sections.length - 1];
    const is_print_alone = printAlone == 1;
    if (last && last.key === key && !isFirstShowOfCategory && !is_print_alone && !last.is_print_alone) {
      last.tests.push(...tests);
      last.blocks.push(...blocks);
      last.bundles.push(...bundles);
      return;
    }

    sections.push({ key, category: isFirstShowOfCategory ? category : null, tests: [...tests], blocks, bundles, is_print_alone });
  };

  // Standalone tests
  groupedTests.value.forEach(g => addTests(g.category, g.tests, false, g.bundle));
  // Test group tests
  printRecord.value?.test_groups?.forEach(g => addTests(g.category, g.tests?.filter(t => !hasRealTemplate(t) && !isFormulaTarget(g, t)), g.is_print_alone));
  // A package's own tests are already flattened into groupedTests above; the
  // group-sourced ones come through here. Pass a null category: show_test_names
  // is OFF in merged mode, so emitting the group NAME as a heading would leak
  // exactly what the setting hides. Formula targets are NOT filtered out —
  // merged mode renders no formula rows, so excluding them would print nothing.
  packageGroupSections.value.forEach((sec) => addTests(null, sec.rows || [], sec.is_print_alone, sec.bundle));
  return sections;
});

// Test groups attached to a package, reassembled for printing.
// The tests are taken from pkg.tests (the flattened array is what carries the
// entered results after a save), grouped back by test_group_name, and the
// group's formula is pulled from pkg.test_groups — which holds the definition
// but no results. Without this a group inside a package printed as loose
// individual tests and its formulas never rendered at all.
const packageGroupSections = computed(() => {
  const sections = [];
  (printRecord.value?.packages || []).forEach((pkg, packageIndex) => {
    const byGroup = new Map();
    (pkg.tests || []).forEach((t) => {
      const g = t.test_group_name;
      if (!g) return;
      // A test the PACKAGE's own formula computes is not a measured row — it
      // prints as the package's formula row, so keep it out or it prints twice.
      if (isFormulaTarget(pkg, t)) return;
      // Key by group id when present: two attached groups may share a name.
      const key = t.test_group_id_fk ?? `name:${g}`;
      if (!byGroup.has(key)) byGroup.set(key, { group_name: g, rows: [] });
      byGroup.get(key).rows.push(t);
    });
    byGroup.forEach((entry, key) => {
      const meta = (pkg.test_groups || []).find(
        (g) => (g.test_group_id_fk ?? `name:${g.group_name || g.name}`) === key
      ) || (pkg.test_groups || []).find((g) => (g.group_name || g.name) === entry.group_name);
      const formula = Array.isArray(meta?.formula) ? meta.formula : [];
      // Nothing printable => no ghost heading + header-only table.
      const printableRows = entry.rows.filter((t) => !hasRealTemplate(t));
      if (!printableRows.length && !formula.length) return;
      sections.push({
        group_name: entry.group_name,
        package_name: pkg.name,
        bundle: `package:${packageIndex}`,
        is_print_alone: meta?.is_print_alone == 1,
        formula,
        // `tests` feeds the formula helpers, so it must span the WHOLE package:
        // a group formula may reference a test that is also a package member and
        // was deduped out of the tagged set — otherwise the row evaluates blank.
        tests: pkg.tests || [],
        rows: printableRows,
      });
    });
  });
  return sections;
});

const isFormulaTarget = (parent, test) => {
  const formulas = Array.isArray(parent?.formula) ? parent.formula : [];
  if (!formulas.length) return false;
  const key = test.shortcut || test.name;
  return formulas.some(f => f?.name === key);
};

const findTestByKey = (parent, key) => {
  return (parent?.tests || []).find(t => (t.shortcut || t.name) === key);
};

const getTestLabel = (parent, key) => {
  const t = findTestByKey(parent, key);
  return t?.report_name || t?.name || key || "";
};

const getTestUnit = (parent, key) => {
  return findTestByKey(parent, key)?.unit || "";
};

const getTestRanges = (parent, key) => {
  const t = findTestByKey(parent, key);
  return getFilteredRanges(t?.test_reference_ranges) || [];
};

const getFormulaStatusId = (parent, f) => {
  const val = parseFloat(evalFormulaValue(parent, f));
  if (isNaN(val)) return null;
  let ranges = getTestRanges(parent, f.name);
  if (!ranges?.length) ranges = findTestByKey(parent, f.name)?.test_reference_ranges || [];
  const range = ranges.find(r => r.from != null && r.to != null && !isNaN(parseFloat(r.from)) && !isNaN(parseFloat(r.to)));
  if (!range) return null;
  const from = parseFloat(range.from);
  const to = parseFloat(range.to);
  if (val < from) return 4;
  if (val > to) return 1;
  return 2;
};

// Recursive evaluator with cycle guard. Tokens resolve against child test
// results first, then against sibling formulas (so D=A-C resolves once A is
// computed). visited set breaks circular deps (A=B+1, B=A+1 → both return "").
const evalFormulaValue = (parent, f, visited) => {
  if (!f?.tokens?.length) return "";
  const tests = parent?.tests || [];
  const formulas = Array.isArray(parent?.formula) ? parent.formula : [];
  const seen = visited || new Set();
  const fKey = f.name || "";
  if (fKey && seen.has(fKey)) return ""; // cycle
  if (fKey) seen.add(fKey);

  let expr = "";
  for (const tok of f.tokens) {
    if (/^[+\-*/()]$/.test(tok)) { expr += tok; continue; }
    if (/^[\d.]+$/.test(tok)) { expr += tok; continue; }
    let val = NaN;
    const t2 = tests.find(t => (t.shortcut || t.name) === tok);
    if (t2) val = parseFloat(t2.result);
    if (isNaN(val)) {
      const sibling = formulas.find(x => x?.name === tok);
      if (sibling) {
        const sub = evalFormulaValue(parent, sibling, seen);
        val = sub === "" ? NaN : parseFloat(sub);
      }
    }
    if (isNaN(val)) return "";
    expr += `(${val})`;
  }
  if (!/^[\d+\-*/().\s]+$/.test(expr)) return "";
  try {
    const r = Function(`"use strict"; return (${expr});`)();
    if (typeof r === "number" && !isNaN(r)) return Math.round(r * 100) / 100;
  } catch (_) { /* ignore */ }
  return "";
};

const computeAllFormulas = () => { /* no-op, computed inline */ };

const getData = async () => {
  try {
    if (referralReport.value) {
      const { data } = await $http.get(`/referral-portal/workspace/invoices/${patientId.value}`, { params: { document: "report" } });
      printRecord.value = data;
    } else if (sharedReferralReport.value) {
      const { data } = await $http.get(`/referral-report/${patientId.value}`, { params: route.query });
      printRecord.value = data.report;
      publicLabSettings.value = data.settings || {};
      reportShareUrl.value = data.report.report_share_url;
    } else if (hasToken) {
      await GetinvoicesById(patientId.value);
    } else {
      // Public access (e.g. WhatsApp link) — use unauthenticated endpoint
      const { data } = await $http.get(`/invoices/public/${patientId.value}`);
      printRecord.value = data;
    }
    computeAllFormulas();
  } catch (err) {
    if ((sharedReferralReport.value && [403, 404, 409].includes(err?.response?.status)) || (!hasToken && err?.response?.status === 403) || (referralReport.value && err?.response?.status === 409)) {
      reportNotReady.value = true;
    }
    console.error("Failed to fetch invoice data:", err);
    return;
  }

  try {
    if (referralReport.value) {
      const { data } = await $http.get('/referral-portal/workspace/lab-settings');
      publicLabSettings.value = data;
    } else if (!sharedReferralReport.value && printRecord.value?.lab_id_fk) {
      // Direct patient/QR links always use the invoice lab's current settings,
      // including when another staff account is logged in in this browser.
      const { data } = await $http.get(`/lab-settings/${printRecord.value.lab_id_fk}`);
      publicLabSettings.value = data;
    }
  } catch (error) {
    console.error("Failed to load report settings", error);
    showDirectView.value = true;
    shareError.value = "تعذر تحميل إعدادات التقرير. أعد تحميل الصفحة قبل الطباعة أو الحفظ.";
    return;
  }

  if (referralReport.value) {
    try {
      const { data } = await $http.post(`/referral-portal/workspace/invoices/${patientId.value}/share-report`);
      reportShareUrl.value = data.url;
    } catch (error) {
      console.error("Failed to prepare referral report QR", error);
    }
  }

  reportLoaded.value = true;
  await generatePDF();
};

const appBaseUrl = import.meta.env.VITE_APP_URL || window.location.origin;

const printReferralReport = () => printFromQR();
const shareReferralReport = async () => {
  if (sharing.value || !reportLoaded.value) return;
  sharing.value = true;
  shareError.value = "";
  const whatsappTab = window.open("about:blank", "_blank");
  try {
    const { data } = await $http.post(`/referral-portal/workspace/invoices/${patientId.value}/share-report`);
    reportShareUrl.value = data.url;
    const name = reportBrand.value?.lab_display_name || "مختبر الإحالة";
    const message = `التقرير الطبي من ${name}\n${reportUrlWithForm(data.url, showBackground.value)}`;
    const url = `https://wa.me/?text=${encodeURIComponent(message)}`;
    if (whatsappTab) { whatsappTab.opener = null; whatsappTab.location.href = url; }
    else { await navigator.clipboard.writeText(message); shareError.value = "تم نسخ الرابط؛ افتح واتساب وألصقه في المحادثة"; }
  } catch (error) {
    whatsappTab?.close();
    shareError.value = error.response?.data?.message || "تعذر إنشاء رابط التقرير";
  } finally {
    sharing.value = false;
  }
};

const buildReportPages = async () => {
  renderForCapture.value = true;
  try {
    await nextTick();
    let css = printStyles.getResultCss() + printStyles.getPatientHeaderCss(reportSettings.value.patient_header_config) + printStyles.getPrintTableCss(reportSettings.value.print_table_config) + printStyles.getReportTemplateCss(reportSettings.value);
    if (printBlackWhite.value) css += printStyles.getBlackWhiteCss();
    return await renderMedicalReportPages({ element: contentToConvert.value, css,
      margins: serverMargins.value, background: showBackground.value ? backgroundUrl.value : null });
  } finally {
    renderForCapture.value = false;
  }
};

let preparedReport = null;
let preparingReport = null;
async function prepareReport(options = {}) {
  if (options.withBackground !== undefined) showWithForm.value = options.withBackground;
  if (!referralReport.value && !sharedReferralReport.value && !patientPortalUrl.value) {
    const recordKey = qrRecordKey.value;
    const url = await reportPatientPortalUrl({ record: printRecord.value, route,
      authenticated: hasToken, http: $http, appBaseUrl });
    if (recordKey !== qrRecordKey.value) throw new Error('تغير التقرير المحدد. أعد المحاولة لتجهيز التقرير الصحيح.');
    patientPortalUrl.value = url;
  }
  await nextTick();
  const key = JSON.stringify({ record: printRecord.value, settings: reportSettings.value,
    withBackground: showBackground.value, shareUrl: reportQrUrl.value });
  if (preparedReport?.key === key) return preparedReport.output;
  if (preparingReport?.key === key) return preparingReport.promise;
  const promise = (async () => {
    const pages = await buildReportPages();
    const pdf = await createMedicalReportPdf({ pages });
    const output = { pages, blob: pdf.output('blob'), filename: medicalReportFilename(printRecord.value), withBackground: showBackground.value };
    preparedReport = { key, output };
    return output;
  })();
  preparingReport = { key, promise };
  try { return await promise; }
  finally { if (preparingReport?.promise === promise) preparingReport = null; }
}

let previewVersion = 0;
const generatePDF = async () => {
  const version = ++previewVersion;
  showDirectView.value = true;
  shareError.value = '';
  pdfGenerating.value = true;
  try {
    const output = await prepareReport();
    if (version === previewVersion) reportPages.value = output.pages;
  } catch (error) {
    if (version === previewVersion) shareError.value = error?.message || 'تعذر تجهيز التقرير';
  } finally {
    if (version === previewVersion) pdfGenerating.value = false;
  }
};

const getFilteredRanges = (referenceRanges) => {
  if (!referenceRanges) return [];
  const patientDetails = printRecord.value?.patient;

  const convertToDays = (age, unit) => {
    switch (unit) {
      case "Days":
        return age;
      case "Months":
        return age * 30;
      case "Years":
        return age * 365;
      default:
        return age;
    }
  };

  const patientAgeInDays = convertToDays(patientDetails?.age, patientDetails?.age_unit);

  return referenceRanges.filter((range) => {
    const rangeGender = range?.gender?.toLowerCase();
    const isGenderMatch = !rangeGender || rangeGender === "both" || rangeGender === patientDetails?.gender?.toLowerCase();
    if (range?.age_from == null && range?.age_to == null) return isGenderMatch;
    const rangeAgeFromInDays = convertToDays(range?.age_from ?? 0, range?.age_unit);
    const rangeAgeToInDays = convertToDays(range?.age_to ?? 999, range?.age_unit);
    const isAgeMatch = patientAgeInDays >= rangeAgeFromInDays && patientAgeInDays <= rangeAgeToInDays;
    return isGenderMatch && isAgeMatch;
  });
};

const getStatusLabel = (statusId) => {
  return resultStatus.value?.find((s) => s.value === statusId)?.label || "";
};

// Color the result value by status: high=red, normal=green, low=yellow.
// !important needed so it beats print_table_config's `td { color !important }`
// injected by usePrint.getPrintTableCss (inline !important wins over stylesheet).
const getResultColorStyle = (statusId) => {
  if (printBlackWhite.value) return "";
  const id = Number(statusId);
  if (id === 1) return "color: #dc2626 !important; font-weight: 700;"; // high → red
  if (id === 2) return "color: #16a34a !important; font-weight: 700;"; // normal → green
  if (id === 4) return "color: #ca8a04 !important; font-weight: 700;"; // low → yellow
  return "";
};

// Both actions consume the same complete sheets as the on-screen report.
const printFromQR = async () => {
  if (pdfGenerating.value || !reportLoaded.value) return;
  const printWindow = window.open('about:blank', '_blank');
  pdfGenerating.value = true;
  shareError.value = '';
  try {
    if (!printWindow) throw new Error('اسمح بالنوافذ المنبثقة للطباعة.');
    const output = await prepareReport();
    reportPages.value = output.pages;
    await printMedicalReportPages(output.pages, printWindow);
  } catch (error) {
    printWindow?.close();
    shareError.value = error?.message || 'تعذر تجهيز التقرير';
  } finally {
    pdfGenerating.value = false;
  }
};

const downloadPDF = async () => {
  if (pdfGenerating.value || !reportLoaded.value) return;
  pdfGenerating.value = true;
  shareError.value = '';
  try {
    const output = await prepareReport();
    reportPages.value = output.pages;
    downloadMedicalReportFile(output);
  } catch (error) {
    shareError.value = error?.message || 'تعذر تجهيز التقرير';
  } finally {
    pdfGenerating.value = false;
  }
};

const generateBarcodeImage = (value) => {
  if (!value) return "";

  const canvas = document.createElement("canvas");
  JsBarcode(canvas, value, {
    format: "CODE128",
    displayValue: true,
    width: 2,
    height: 15,
  });
  return canvas.toDataURL("image/png");
};
</script>

<template>
  <!-- Report Not Ready Message -->
  <div v-if="reportNotReady" class="flex items-center justify-center min-h-screen bg-gray-50" dir="ltr">
    <div class="text-center p-10 bg-white rounded-2xl shadow-lg max-w-md w-full mx-4">
      <div class="text-6xl mb-4">&#128339;</div>
      <h2 class="text-xl font-bold text-gray-800 mb-2">Report Not Ready Yet</h2>
      <p class="text-gray-500 mb-4">التقرير غير جاهز بعد</p>
      <p class="text-sm text-gray-400">Please check back later once the results have been completed.</p>
      <p class="text-sm text-gray-400 mt-1">يرجى المراجعة لاحقاً بعد اكتمال النتائج.</p>
    </div>
  </div>

  <template v-else>
    <!-- ===== AUTHENTICATED VIEW (staff) — PDF iframe ===== -->
    <div v-if="referralReport && printRecord?.id" class="referral-actions" dir="rtl">
      <strong>تقرير {{ reportBrand?.lab_display_name || 'مختبر الإحالة' }}</strong>
      <button type="button" :disabled="pdfGenerating || !reportLoaded" @click="printReferralReport">طباعة التقرير</button>
      <button type="button" :disabled="sharing || !reportLoaded" @click="shareReferralReport">{{ sharing ? 'جاري تجهيز الرابط...' : 'إرسال عبر واتساب' }}</button>
      <span v-if="shareError" role="alert">{{ shareError }}</span>
    </div>
    <div v-if="showDirectView" class="report-page-preview">
      <div class="report-preview-actions" dir="rtl">
        <button type="button" :disabled="pdfGenerating || !reportLoaded" @click="printFromQR">طباعة</button>
        <button type="button" :disabled="pdfGenerating || !reportLoaded" @click="downloadPDF">تحميل PDF</button>
        <span v-if="pdfGenerating" role="status">جاري تجهيز التقرير...</span>
        <span v-if="shareError" role="alert">{{ shareError }}</span>
      </div>
      <div v-for="(page, index) in reportPages" :key="index" class="report-preview-sheet">
        <img :src="page" :alt="`صفحة التقرير ${index + 1}`" />
      </div>
    </div>
    <!-- Source DOM is separate from the A4 sheets and never carries a letterhead. -->
    <div id="Result" ref="contentToConvert" :class="{ 'report-modern': modernReport, 'report-monochrome': printBlackWhite }" class="report-capture-source" role="region" aria-label="نتائج التحاليل"
      :style="{ direction: 'ltr', display: showDirectView || renderForCapture ? 'block' : 'none' }">

    <!-- ======== MAIN RESULT CONTENT ======== -->
    <template v-if="hasAnyData || hasTemplateTest">

      <!-- ===== SPECIAL TEMPLATE TESTS — each chunk is its own complete
           <table class="print-wrapper"> with patient <thead>. page-break-before
           on a top-level <table> element is the most reliable way to force a
           new print page across all browsers. -->
      <template v-if="templateTests.length > 0">
        <template v-for="(tTest, tIdx) in templateTests" :key="'tmpl-' + tIdx">
          <table
            v-for="(chunk, cIdx) in templateChunks(tTest)"
            :key="'tmpl-' + tIdx + '-chunk-' + cIdx"
            class="print-wrapper"
            :style="(tIdx > 0 || cIdx > 0) ? 'page-break-before: always; break-before: page;' : ''"
          >
            <thead><tr><td class="pw-cell pw-top"><result_section :modern="modernReport" :with-background="showBackground" :lab-name="reportBrand?.lab_display_name" :lab-logo="showBackground && backgroundUrl ? '' : referralLogo" :share-url="reportQrUrl" :is-referral="referralReport || sharedReferralReport" /></td></tr></thead>
            <tfoot><tr><td class="pw-cell pw-bottom"></td></tr></tfoot>
            <tbody><tr><td class="pw-cell">
              <section class="template-section"><div v-html="chunk"></div><ResultHistoryTable v-if="cIdx === templateChunks(tTest).length - 1" :rows="resultHistoryRows(printRecord, tTest)" /></section>
            </td></tr></tbody>
          </table>
        </template>
      </template>

      <!-- ===== Non-template content (standalone tests / groups / cultures /
           packages / footer) in its own complete <table class="print-wrapper">.
           Forced to a new page when template tests preceded it. Only rendered
           when there's actual content OR a footer to show. -->
      <table
        v-if="(groupedTests.length > 0 || allTestsMerged.length > 0 || (printRecord?.test_groups?.length > 0) || (printRecord?.cultures?.length > 0) || (printRecord?.packages?.length > 0) || !hasTemplateTest)"
        class="print-wrapper"
        :style="hasTemplateTest ? 'page-break-before: always; break-before: page;' : ''"
      >
        <thead><tr><td class="pw-cell pw-top"><result_section :modern="modernReport" :with-background="showBackground" :lab-name="reportBrand?.lab_display_name" :lab-logo="showBackground && backgroundUrl ? '' : referralLogo" :share-url="reportQrUrl" :is-referral="referralReport || sharedReferralReport" /></td></tr></thead>
        <tfoot><tr><td class="pw-cell pw-bottom"></td></tr></tfoot>
        <tbody><tr><td class="pw-cell">

        <!-- ===== MERGED TABLE (when showTestName is off) ===== -->
        <template v-if="!showTestName && allTestsMerged.length > 0">
          <section v-for="(section, sIdx) in allTestsMerged" :key="'merged-s-' + sIdx" data-report-section :data-report-isolated="section.is_print_alone" :style="{ marginTop: sIdx === 0 ? '16px' : '24px', marginBottom: '16px' }">
            <div v-if="section.category" class="section-header w-full font-bold border border-black p-1 text-center text-black bg-gray-300" style="margin-bottom: 10px;"><i v-if="modernReport" class="pi pi-filter mr-section-icon" aria-hidden="true"></i><span>{{ section.category }}</span><small v-if="modernReport" class="mr-section-caption">TEST RESULTS</small></div>
            <table :class="{ 'report-column-widths': customReportColumns(reportSettings.print_table_config) }" class="report-results-table w-full" style="border-collapse: collapse; table-layout: fixed;">
              <ReportTableColumns :settings="reportSettings" />
              <thead>
                <tr>
                  <th class="border border-black/15 p-0.5 text-center">Test</th>
                  <th class="border border-black/15 p-0.5 text-center">Result</th>
                  <th v-if="showStatus && modernReport" class="report-flag-heading">Flag</th>
                  <th class="border border-black/15 p-0.5 text-center">Unit</th>
                  <th class="border border-black/15 p-0.5 text-center">Reference Ranges</th>
                  <th v-if="showLastResult" class="border border-black/15 p-0.5 text-center">Last Result</th>
                  <th v-if="showStatus && !modernReport" class="border border-black/15 p-0.5 text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="(item, idx) in section.tests" :key="'merged-' + idx"><tr :data-report-block="section.blocks[idx]" :data-report-bundle="section.bundles[idx]">
                  <td class="border border-black/15 p-0.5 text-center">{{ item.report_name || item.name }}</td>
                  <td class="border border-black/15 p-0.5 text-center" :style="getResultColorStyle(item.result_status_id_fk)">{{ item.result ?? "" }}</td>
                  <td v-if="showStatus && modernReport" class="report-flag-cell"><ReportResultFlag :status-id="item.result_status_id_fk" :label="getStatusLabel(item.result_status_id_fk)" /></td>
                  <td class="border border-black/15 p-0.5 text-center">{{ item.unit }}</td>
                  <td class="border border-black/15 p-0.5 text-center">
                    <span v-for="range in getFilteredRanges(item?.test_reference_ranges)" :key="range.test_reference_range_id || range.id">
                      <div v-if="range.notes" style="white-space: pre-line;">{{ range.notes }}</div>
                      <p v-else>{{ range.from }}-{{ range.to }}</p>
                      <p v-for="option in range.test_reference_options" :key="option">{{ option }}</p>
                    </span>
                  </td>
                  <td v-if="showLastResult" class="border border-black/15 p-0.5 text-center">{{ lastResultMap[item.name] || '' }}</td>
                  <td v-if="showStatus && !modernReport" class="border border-black/15 p-0.5 text-center">{{ getStatusLabel(item.result_status_id_fk) }}</td>
                </tr>
                <tr v-if="resultHistoryRows(printRecord, item, 'test').length" :data-report-block="section.blocks[idx]" :data-report-bundle="section.bundles[idx]"><td :colspan="colCount"><ResultHistoryTable :rows="resultHistoryRows(printRecord, item, 'test')" /></td></tr></template>
              </tbody>
            </table>
          </section>
        </template>

        <!-- ===== STANDALONE TESTS (grouped by test_group name / category) ===== -->
        <template v-if="showTestName && groupedTests.length > 0">
          <section v-for="(group, index) in groupedTests" :key="'st-' + index" class="test-group-section" data-report-section :data-report-bundle="group.bundle">
            <div v-if="group.showCategory && showCategories && (!modernReport || group.category)" class="section-header w-full font-bold border border-black p-1 text-center text-black bg-gray-300"><i v-if="modernReport" class="pi pi-filter mr-section-icon" aria-hidden="true"></i><span>{{ group.category }}</span><small v-if="modernReport" class="mr-section-caption">TEST RESULTS</small></div>
            <div v-if="group.name && showTestName" class="section-header w-full font-bold border border-black p-1 text-center text-black bg-gray-300" :style="group.showCategory && showCategories ? 'margin-top: 4px' : ''"><i v-if="modernReport" class="pi pi-filter mr-section-icon" aria-hidden="true"></i><span>{{ group.name }}</span><small v-if="modernReport" class="mr-section-caption">TEST RESULTS</small></div>

            <table v-if="group.tests.length > 0" :class="{ 'report-column-widths': customReportColumns(reportSettings.print_table_config) }" class="report-results-table w-full my-5" style="border-collapse: collapse; table-layout: fixed;">
              <ReportTableColumns :settings="reportSettings" />
              <thead>
                <tr>
                  <th class="border border-black/15 p-0.5 text-center">Test</th>
                  <th class="border border-black/15 p-0.5 text-center">Result</th>
                  <th v-if="showStatus && modernReport" class="report-flag-heading">Flag</th>
                  <th class="border border-black/15 p-0.5 text-center">Unit</th>
                  <th class="border border-black/15 p-0.5 text-center">Reference Ranges</th>
                  <th v-if="showLastResult" class="border border-black/15 p-0.5 text-center">Last Result</th>
                  <th v-if="showStatus && !modernReport" class="border border-black/15 p-0.5 text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="(item, idx) in group.tests" :key="idx"><tr>
                  <td class="border border-black/15 p-0.5 text-center">{{ item.report_name || item.name }}</td>
                  <td class="border border-black/15 p-0.5 text-center" :style="getResultColorStyle(item.result_status_id_fk)">{{ item.result ?? "" }}</td>
                  <td v-if="showStatus && modernReport" class="report-flag-cell"><ReportResultFlag :status-id="item.result_status_id_fk" :label="getStatusLabel(item.result_status_id_fk)" /></td>
                  <td class="border border-black/15 p-0.5 text-center">{{ item.unit }}</td>
                  <td class="border border-black/15 p-0.5 text-center">
                    <span v-for="range in getFilteredRanges(item?.test_reference_ranges)" :key="range.test_reference_range_id">
                      <div v-if="range.notes" style="white-space: pre-line;">{{ range.notes }}</div>
                      <p v-else>{{ range.from }}-{{ range.to }}</p>
                      <p v-for="option in range.test_reference_options" :key="option">{{ option }}</p>
                    </span>
                  </td>
                  <td v-if="showLastResult" class="border border-black/15 p-0.5 text-center">{{ lastResultMap[item.name] || '' }}</td>
                  <td v-if="showStatus && !modernReport" class="border border-black/15 p-0.5 text-center">{{ getStatusLabel(item.result_status_id_fk) }}</td>
                </tr>
                <tr v-if="resultHistoryRows(printRecord, item, 'test').length"><td :colspan="colCount"><ResultHistoryTable :rows="resultHistoryRows(printRecord, item, 'test')" /></td></tr></template>
              </tbody>
            </table>
          </section>
        </template>

        <!-- ===== TEST GROUPS (with nested tests and cultures) ===== -->
        <template v-if="showTestName && printRecord?.test_groups?.length > 0">
          <section v-for="(group, gIndex) in printRecord.test_groups" :key="'tg-' + gIndex" class="test-group-section" data-report-section :data-report-isolated="group.is_print_alone == 1">
            <div class="section-header w-full font-bold border border-black p-1 text-center text-black bg-gray-300"><i v-if="modernReport" class="pi pi-filter mr-section-icon" aria-hidden="true"></i><span>{{ group.group_name }}</span><small v-if="modernReport" class="mr-section-caption">TEST RESULTS</small></div>

            <!-- Test Group Tests -->
            <table v-if="group.tests?.length > 0" :class="{ 'report-column-widths': customReportColumns(reportSettings.print_table_config) }" class="report-results-table w-full my-5" style="border-collapse: collapse; table-layout: fixed;">
              <ReportTableColumns :settings="reportSettings" />
              <thead>
                <tr>
                  <th class="border border-black/15 p-0.5 text-center">Test</th>
                  <th class="border border-black/15 p-0.5 text-center">Result</th>
                  <th v-if="showStatus && modernReport" class="report-flag-heading">Flag</th>
                  <th class="border border-black/15 p-0.5 text-center">Unit</th>
                  <th class="border border-black/15 p-0.5 text-center">Reference Ranges</th>
                  <th v-if="showLastResult" class="border border-black/15 p-0.5 text-center">Last Result</th>
                  <th v-if="showStatus && !modernReport" class="border border-black/15 p-0.5 text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="(item, idx) in group.tests.filter(t => !isFormulaTarget(group, t))" :key="'tgt-' + idx"><tr>
                  <td class="border border-black/15 p-0.5 text-center">{{ item.report_name || item.name }}</td>
                  <td class="border border-black/15 p-0.5 text-center" :style="getResultColorStyle(item.result_status_id_fk)">{{ item.result ?? "" }}</td>
                  <td v-if="showStatus && modernReport" class="report-flag-cell"><ReportResultFlag :status-id="item.result_status_id_fk" :label="getStatusLabel(item.result_status_id_fk)" /></td>
                  <td class="border border-black/15 p-0.5 text-center">{{ item.unit }}</td>
                  <td class="border border-black/15 p-0.5 text-center">
                    <span v-for="range in getFilteredRanges(item?.test_reference_ranges)" :key="range.test_reference_range_id || range.id">
                      <div v-if="range.notes" style="white-space: pre-line;">{{ range.notes }}</div>
                      <p v-else>{{ range.from }}-{{ range.to }}</p>
                      <p v-for="option in range.test_reference_options" :key="option">{{ option }}</p>
                    </span>
                  </td>
                  <td v-if="showLastResult" class="border border-black/15 p-0.5 text-center">{{ lastResultMap[item.name] || '' }}</td>
                  <td v-if="showStatus && !modernReport" class="border border-black/15 p-0.5 text-center">{{ getStatusLabel(item.result_status_id_fk) }}</td>
                </tr>
                <tr v-if="resultHistoryRows(printRecord, item, 'test').length"><td :colspan="colCount"><ResultHistoryTable :rows="resultHistoryRows(printRecord, item, 'test')" /></td></tr></template>
                <tr v-for="(f, fi) in (Array.isArray(group.formula) ? group.formula : [])" :key="'gf-' + fi">
                  <td class="border border-black/15 p-0.5 text-center font-bold">{{ getTestLabel(group, f.name) }}</td>
                  <td class="border border-black/15 p-0.5 text-center font-bold" :style="modernReport ? getResultColorStyle(getFormulaStatusId(group, f)) : ''">{{ evalFormulaValue(group, f) }}</td>
                  <td v-if="showStatus && modernReport" class="report-flag-cell"><ReportResultFlag :status-id="getFormulaStatusId(group, f)" :label="getStatusLabel(getFormulaStatusId(group, f))" /></td>
                  <td class="border border-black/15 p-0.5 text-center">{{ getTestUnit(group, f.name) }}</td>
                  <td class="border border-black/15 p-0.5 text-center">
                    <span v-for="range in getTestRanges(group, f.name)" :key="range.test_reference_range_id || range.id">
                      <div v-if="range.notes" style="white-space: pre-line;">{{ range.notes }}</div>
                      <p v-else>{{ range.from }}-{{ range.to }}</p>
                      <p v-for="option in range.test_reference_options" :key="option">{{ option }}</p>
                    </span>
                  </td>
                  <td v-if="showLastResult" class="border border-black/15 p-0.5 text-center">{{ lastResultMap[getTestLabel(group, f.name)] || '' }}</td>
                  <td v-if="showStatus && !modernReport" class="border border-black/15 p-0.5 text-center">{{ getStatusLabel(getFormulaStatusId(group, f)) }}</td>
                </tr>
              </tbody>
            </table>

            <!-- Test Group Cultures -->
            <template v-if="group.cultures?.length > 0">
              <table :class="{ 'report-column-widths': customReportColumns(reportSettings.print_table_config) }" class="report-results-table w-full my-5" style="border-collapse: collapse; table-layout: fixed;">
                <ReportTableColumns :settings="reportSettings" />
                <thead>
                  <tr>
                    <th class="border border-black/15 p-0.5 text-center">Culture</th>
                    <th class="border border-black/15 p-0.5 text-center">Result</th>
                  <th v-if="showStatus && modernReport" class="report-flag-heading">Flag</th>
                    <th class="border border-black/15 p-0.5 text-center">Unit</th>
                    <th class="border border-black/15 p-0.5 text-center">Reference Ranges</th>
                    <th v-if="showLastResult" class="border border-black/15 p-0.5 text-center">Last Result</th>
                    <th v-if="showStatus && !modernReport" class="border border-black/15 p-0.5 text-center">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <template v-for="(item, idx) in group.cultures" :key="'tgc-' + idx"><tr>
                    <td class="border border-black/15 p-0.5 text-center">{{ item.name }}</td>
                    <td class="border border-black/15 p-0.5 text-center" :style="getResultColorStyle(item.result_status_id_fk)">{{ item.result ?? "" }}</td>
                  <td v-if="showStatus && modernReport" class="report-flag-cell"><ReportResultFlag :status-id="item.result_status_id_fk" :label="getStatusLabel(item.result_status_id_fk)" /></td>
                    <td class="border border-black/15 p-0.5 text-center">{{ item.unit }}</td>
                    <td class="border border-black/15 p-0.5 text-center">
                      <span v-for="range in getFilteredRanges(item?.test_reference_ranges)" :key="range.id">
                        <div v-if="range.notes" style="white-space: pre-line;">{{ range.notes }}</div>
                        <p v-else>{{ range.from }}-{{ range.to }}</p>
                      </span>
                    </td>
                    <td v-if="showLastResult" class="border border-black/15 p-0.5 text-center">{{ lastResultMap[item.name] || '' }}</td>
                    <td v-if="showStatus && !modernReport" class="border border-black/15 p-0.5 text-center">{{ getStatusLabel(item.result_status_id_fk) }}</td>
                  </tr>
                <tr v-if="resultHistoryRows(printRecord, item, 'culture').length"><td :colspan="colCount"><ResultHistoryTable :rows="resultHistoryRows(printRecord, item, 'culture')" /></td></tr></template>
                </tbody>
              </table>
            </template>
          </section>
        </template>

        <!-- ===== TEST GROUPS THAT LIVE INSIDE A PACKAGE =====
             Same presentation as a standalone test group: group heading, its
             tests, then its formula rows. Built from the package's flattened
             tests so entered results are included. -->
        <template v-if="showTestName && packageGroupSections.length > 0">
          <section v-for="(group, gpIndex) in packageGroupSections" :key="'pkg-tg-' + gpIndex" class="test-group-section" data-report-section :data-report-isolated="group.is_print_alone" :data-report-bundle="group.bundle">
            <div class="section-header w-full font-bold border border-black p-1 text-center text-black bg-gray-300"><i v-if="modernReport" class="pi pi-filter mr-section-icon" aria-hidden="true"></i><span>{{ group.group_name }}</span><small v-if="modernReport" class="mr-section-caption">TEST RESULTS</small></div>

            <table v-if="group.rows.filter(t => !isFormulaTarget(group, t)).length || group.formula.length" :class="{ 'report-column-widths': customReportColumns(reportSettings.print_table_config) }" class="report-results-table w-full my-5" style="border-collapse: collapse; table-layout: fixed;">
              <ReportTableColumns :settings="reportSettings" />
              <thead>
                <tr>
                  <th class="border border-black/15 p-0.5 text-center">Test</th>
                  <th class="border border-black/15 p-0.5 text-center">Result</th>
                  <th v-if="showStatus && modernReport" class="report-flag-heading">Flag</th>
                  <th class="border border-black/15 p-0.5 text-center">Unit</th>
                  <th class="border border-black/15 p-0.5 text-center">Reference Ranges</th>
                  <th v-if="showLastResult" class="border border-black/15 p-0.5 text-center">Last Result</th>
                  <th v-if="showStatus && !modernReport" class="border border-black/15 p-0.5 text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="(item, idx) in group.rows.filter(t => !isFormulaTarget(group, t))" :key="'ptgt-' + idx"><tr>
                  <td class="border border-black/15 p-0.5 text-center">{{ item.report_name || item.name }}</td>
                  <td class="border border-black/15 p-0.5 text-center" :style="getResultColorStyle(item.result_status_id_fk)">{{ item.result ?? "" }}</td>
                  <td v-if="showStatus && modernReport" class="report-flag-cell"><ReportResultFlag :status-id="item.result_status_id_fk" :label="getStatusLabel(item.result_status_id_fk)" /></td>
                  <td class="border border-black/15 p-0.5 text-center">{{ item.unit }}</td>
                  <td class="border border-black/15 p-0.5 text-center">
                    <span v-for="range in getFilteredRanges(item?.test_reference_ranges)" :key="range.test_reference_range_id || range.id">
                      <div v-if="range.notes" style="white-space: pre-line;">{{ range.notes }}</div>
                      <p v-else>{{ range.from }}-{{ range.to }}</p>
                      <p v-for="option in range.test_reference_options" :key="option">{{ option }}</p>
                    </span>
                  </td>
                  <td v-if="showLastResult" class="border border-black/15 p-0.5 text-center">{{ lastResultMap[item.name] || '' }}</td>
                  <td v-if="showStatus && !modernReport" class="border border-black/15 p-0.5 text-center">{{ getStatusLabel(item.result_status_id_fk) }}</td>
                </tr>
                <tr v-if="resultHistoryRows(printRecord, item, 'test').length"><td :colspan="colCount"><ResultHistoryTable :rows="resultHistoryRows(printRecord, item, 'test')" /></td></tr></template>
                <tr v-for="(f, fi) in group.formula" :key="'ptgf-' + fi">
                  <td class="border border-black/15 p-0.5 text-center font-bold">{{ getTestLabel(group, f.name) }}</td>
                  <td class="border border-black/15 p-0.5 text-center font-bold" :style="modernReport ? getResultColorStyle(getFormulaStatusId(group, f)) : ''">{{ evalFormulaValue(group, f) }}</td>
                  <td v-if="showStatus && modernReport" class="report-flag-cell"><ReportResultFlag :status-id="getFormulaStatusId(group, f)" :label="getStatusLabel(getFormulaStatusId(group, f))" /></td>
                  <td class="border border-black/15 p-0.5 text-center">{{ getTestUnit(group, f.name) }}</td>
                  <td class="border border-black/15 p-0.5 text-center">
                    <span v-for="range in getTestRanges(group, f.name)" :key="range.test_reference_range_id || range.id">
                      <div v-if="range.notes" style="white-space: pre-line;">{{ range.notes }}</div>
                      <p v-else>{{ range.from }}-{{ range.to }}</p>
                      <p v-for="option in range.test_reference_options" :key="option">{{ option }}</p>
                    </span>
                  </td>
                  <td v-if="showLastResult" class="border border-black/15 p-0.5 text-center">{{ lastResultMap[getTestLabel(group, f.name)] || '' }}</td>
                  <td v-if="showStatus && !modernReport" class="border border-black/15 p-0.5 text-center">{{ getStatusLabel(getFormulaStatusId(group, f)) }}</td>
                </tr>
              </tbody>
            </table>
          </section>
        </template>

        <!-- ===== STANDALONE CULTURES ===== -->
        <template v-if="printRecord?.cultures?.length > 0">
          <section v-for="(culture, cIndex) in printRecord.cultures" :key="'sc-' + cIndex" class="test-group-section" data-report-section>
            <div v-if="culture.category && showCategories && !printRecord.cultures.slice(0, cIndex).some(c => c.category === culture.category)" class="section-header w-full font-bold border border-black p-1 text-center text-black bg-gray-300"><i v-if="modernReport" class="pi pi-filter mr-section-icon" aria-hidden="true"></i><span>{{ culture.category }}</span><small v-if="modernReport" class="mr-section-caption">TEST RESULTS</small></div>
            <table :class="{ 'report-column-widths': customReportColumns(reportSettings.print_table_config) }" class="report-results-table w-full my-5" style="border-collapse: collapse; table-layout: fixed;">
              <ReportTableColumns :settings="reportSettings" />
              <thead>
                <tr>
                  <th class="border border-black/15 p-0.5 text-center">Culture</th>
                  <th class="border border-black/15 p-0.5 text-center">Result</th>
                  <th v-if="showStatus && modernReport" class="report-flag-heading">Flag</th>
                  <th class="border border-black/15 p-0.5 text-center">Unit</th>
                  <th class="border border-black/15 p-0.5 text-center">Reference Ranges</th>
                  <th v-if="showLastResult" class="border border-black/15 p-0.5 text-center">Last Result</th>
                  <th v-if="showStatus && !modernReport" class="border border-black/15 p-0.5 text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="border border-black/15 p-0.5 text-center">{{ culture.name }}</td>
                  <td class="border border-black/15 p-0.5 text-center" :style="getResultColorStyle(culture.result_status_id_fk)">{{ culture.result ?? "" }}</td>
                  <td v-if="showStatus && modernReport" class="report-flag-cell"><ReportResultFlag :status-id="culture.result_status_id_fk" :label="getStatusLabel(culture.result_status_id_fk)" /></td>
                  <td class="border border-black/15 p-0.5 text-center">{{ culture.unit }}</td>
                  <td class="border border-black/15 p-0.5 text-center">
                    <span v-for="range in getFilteredRanges(culture?.test_reference_ranges)" :key="range.id">
                      <div v-if="range.notes" style="white-space: pre-line;">{{ range.notes }}</div>
                      <p v-else>{{ range.from }}-{{ range.to }}</p>
                    </span>
                  </td>
                  <td v-if="showLastResult" class="border border-black/15 p-0.5 text-center">{{ lastResultMap[culture.name] || '' }}</td>
                  <td v-if="showStatus && !modernReport" class="border border-black/15 p-0.5 text-center">{{ getStatusLabel(culture.result_status_id_fk) }}</td>
                </tr><tr v-if="resultHistoryRows(printRecord, culture, 'culture').length"><td :colspan="colCount"><ResultHistoryTable :rows="resultHistoryRows(printRecord, culture, 'culture')" /></td></tr>
              </tbody>
            </table>
          </section>
        </template>

        <!-- ===== PACKAGES (with nested tests and cultures) ===== -->
        <!-- Package tests print as standalone tests (flattened into groupedTests).
             The package section remains only for cultures + formula rows. -->
        <template v-if="showTestName && printRecord?.packages?.length > 0">
          <section v-for="(pkg, pIndex) in printRecord.packages.filter(p => p.cultures?.length > 0 || (Array.isArray(p.formula) && p.formula.length > 0))" :key="'pkg-' + pIndex" class="test-group-section" data-report-section :data-report-bundle="'package:' + printRecord.packages.indexOf(pkg)">
            <div class="section-header w-full font-bold border border-black p-1 text-center text-black bg-gray-300"><i v-if="modernReport" class="pi pi-filter mr-section-icon" aria-hidden="true"></i><span>{{ pkg.name }}</span><small v-if="modernReport" class="mr-section-caption">TEST RESULTS</small></div>

            <table :class="{ 'report-column-widths': customReportColumns(reportSettings.print_table_config) }" class="report-results-table w-full my-5" style="border-collapse: collapse; table-layout: fixed;">
              <ReportTableColumns :settings="reportSettings" />
              <thead>
                <tr>
                  <th class="border border-black/15 p-0.5 text-center">Test</th>
                  <th class="border border-black/15 p-0.5 text-center">Result</th>
                  <th v-if="showStatus && modernReport" class="report-flag-heading">Flag</th>
                  <th class="border border-black/15 p-0.5 text-center">Unit</th>
                  <th class="border border-black/15 p-0.5 text-center">Reference Ranges</th>
                  <th v-if="showLastResult" class="border border-black/15 p-0.5 text-center">Last Result</th>
                  <th v-if="showStatus && !modernReport" class="border border-black/15 p-0.5 text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="(item, idx) in pkg.cultures" :key="'pc-' + idx"><tr>
                  <td class="border border-black/15 p-0.5 text-center">{{ item.name }}</td>
                  <td class="border border-black/15 p-0.5 text-center" :style="getResultColorStyle(item.result_status_id_fk)">{{ item.result ?? "" }}</td>
                  <td v-if="showStatus && modernReport" class="report-flag-cell"><ReportResultFlag :status-id="item.result_status_id_fk" :label="getStatusLabel(item.result_status_id_fk)" /></td>
                  <td class="border border-black/15 p-0.5 text-center">{{ item.unit }}</td>
                  <td class="border border-black/15 p-0.5 text-center">
                    <span v-for="range in item?.test_reference_ranges" :key="range">
                      {{ range.from }}-{{ range.to }}
                    </span>
                  </td>
                  <td v-if="showLastResult" class="border border-black/15 p-0.5 text-center">{{ lastResultMap[item.name] || '' }}</td>
                  <td v-if="showStatus && !modernReport" class="border border-black/15 p-0.5 text-center">{{ getStatusLabel(item.result_status_id_fk) }}</td>
                </tr>
                <tr v-if="resultHistoryRows(printRecord, item, 'culture').length"><td :colspan="colCount"><ResultHistoryTable :rows="resultHistoryRows(printRecord, item, 'culture')" /></td></tr></template>
                <tr v-for="(f, fi) in (Array.isArray(pkg.formula) ? pkg.formula : [])" :key="'pf-' + fi">
                  <td class="border border-black/15 p-0.5 text-center font-bold">{{ getTestLabel(pkg, f.name) }}</td>
                  <td class="border border-black/15 p-0.5 text-center font-bold" :style="modernReport ? getResultColorStyle(getFormulaStatusId(pkg, f)) : ''">{{ evalFormulaValue(pkg, f) }}</td>
                  <td v-if="showStatus && modernReport" class="report-flag-cell"><ReportResultFlag :status-id="getFormulaStatusId(pkg, f)" :label="getStatusLabel(getFormulaStatusId(pkg, f))" /></td>
                  <td class="border border-black/15 p-0.5 text-center">{{ getTestUnit(pkg, f.name) }}</td>
                  <td class="border border-black/15 p-0.5 text-center">
                    <span v-for="range in getTestRanges(pkg, f.name)" :key="range.test_reference_range_id || range.id">
                      <div v-if="range.notes" style="white-space: pre-line;">{{ range.notes }}</div>
                      <p v-else>{{ range.from }}-{{ range.to }}</p>
                      <p v-for="option in range.test_reference_options" :key="option">{{ option }}</p>
                    </span>
                  </td>
                  <td v-if="showLastResult" class="border border-black/15 p-0.5 text-center">{{ lastResultMap[getTestLabel(pkg, f.name)] || '' }}</td>
                  <td v-if="showStatus && !modernReport" class="border border-black/15 p-0.5 text-center">{{ getStatusLabel(getFormulaStatusId(pkg, f)) }}</td>
                </tr>
              </tbody>
            </table>
          </section>
        </template>

        <result_footer_section />
          </td>
        </tr>
        </tbody>
      </table>
    </template>
  </div>
  </template>
</template>

<!-- Non-scoped: match print output font/weight/size, scoped to #Result -->
<style>
.referral-actions { display:flex; flex-wrap:wrap; align-items:center; gap:12px; padding:14px 20px; background:white; border-bottom:1px solid #ddd; }
.referral-actions button { background:#0f766e; color:white; padding:9px 16px; border-radius:8px; cursor:pointer; }
.referral-actions button:disabled { opacity:.55; cursor:wait; }
.referral-actions [role="alert"] { color:#b91c1c; }
@media print { .referral-actions { display:none !important; } }
/* ===== Font & weight — match print output (Arial, bold) ===== */
#Result.direct-view-page {
  font-family: Arial, sans-serif;
  font-size: 14px;
  color: #000;
  text-transform: capitalize;
  font-weight: bold;
  direction: ltr;
}
#Result.direct-view-page * {
  font-family: Arial, sans-serif !important;
  font-weight: bold !important;
}

/* Table cell font size — match print 12px */
#Result th, #Result td {
  font-size: 12px !important;
}

/* Print wrapper structure */
#Result .print-wrapper { width: 100%; border-collapse: collapse; position: relative; z-index: 1; }
#Result .pw-cell { border: none !important; padding: 0 !important; text-align: left !important; vertical-align: top !important; font-size: 16px !important; }
#Result .pw-bottom { display: none; }

/* Section headers — match print caption style */
#Result .section-header {
  border-radius: 5px;
  background: #dddddce0;
}

/* Signature */
#Result .sign { width: 100%; display: flex; justify-content: center; margin: 9px 0; }

/* Patient header — match print sizes */
#Result .patient-header { font-weight: 700 !important; }
#Result .patient-header .info-table td { padding: 1.5px 0 !important; text-align: left !important; font-size: 13px !important; }
#Result .patient-header .info-table .colon { padding: 1.5px 6px !important; }
#Result .patient-header .barcode-box svg { height: 35px !important; width: auto !important; }
#Result .patient-header .qr-col svg { width: 90px !important; height: 90px !important; }

/* When background is active — make inner containers transparent */
#Result.has-bg .bg-white { background-color: transparent !important; }
#Result.has-bg .border-gray-300 { border-color: transparent !important; }

/* Print-only background element — hidden on screen */
#Result .print-bg { display: none; }

</style>

<style scoped>
/* ===== PDF capture overlay ===== */
.pdf-capture-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(135deg, #0f766e 0%, #115e59 50%, #134e4a 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 20px;
}

/* ===== Public download page ===== */
.public-download-page {
  min-height: 100vh;
  background: linear-gradient(135deg, #0f766e 0%, #115e59 50%, #134e4a 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.download-card {
  background: #fff;
  border-radius: 24px;
  padding: 48px 36px;
  max-width: 420px;
  width: 100%;
  text-align: center;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.download-icon-circle {
  width: 88px;
  height: 88px;
  border-radius: 50%;
  background: linear-gradient(135deg, #0d9488, #0f766e);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 24px;
}

.download-icon-circle.success {
  background: linear-gradient(135deg, #16a34a, #15803d);
}

.download-title {
  font-size: 24px;
  font-weight: 800;
  color: #1e293b;
  margin: 0 0 6px;
}

.download-subtitle {
  font-size: 18px;
  font-weight: 600;
  color: #64748b;
  margin: 0 0 20px;
  direction: rtl;
}

.download-patient {
  font-size: 16px;
  font-weight: 700;
  color: #334155;
  margin: 0 0 24px;
  padding: 10px 16px;
  background: #f1f5f9;
  border-radius: 12px;
}

.download-desc {
  font-size: 14px;
  color: #64748b;
  line-height: 1.6;
  margin: 0 0 24px;
}

.download-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  padding: 16px 24px;
  background: linear-gradient(135deg, #0d9488, #0f766e);
  color: #fff;
  border: none;
  border-radius: 14px;
  font-size: 17px;
  font-weight: 700;
  cursor: pointer;
  transition: transform 0.15s, box-shadow 0.15s;
  box-shadow: 0 4px 14px rgba(13, 148, 136, 0.4);
}

.download-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(13, 148, 136, 0.5);
}

.download-btn:active {
  transform: translateY(0);
}

.download-btn:disabled {
  background: #94a3b8;
  box-shadow: none;
  cursor: wait;
  transform: none;
}

.download-btn.secondary {
  background: #f1f5f9;
  color: #334155;
  box-shadow: none;
  border: 2px solid #e2e8f0;
  font-size: 15px;
  padding: 12px 20px;
}

.download-btn.secondary:hover {
  background: #e2e8f0;
  box-shadow: none;
}

.download-lab-name {
  font-size: 13px;
  color: #94a3b8;
  margin: 20px 0 0;
  font-weight: 600;
}

/* ===== PDF viewer page ===== */
.pdf-viewer-page {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: #1e293b;
  z-index: 100;
}

.pdf-iframe {
  width: 100%;
  height: 100%;
  border: none;
}

/* Responsive wrapper for public QR page — scrollable */
.public-page-wrapper {
  min-height: 100vh;
  background: #e5e7eb;
  padding: 16px 0;
  overflow: auto;
  -webkit-overflow-scrolling: touch;
}

.direct-view-page {
  position: relative;
  width: 210mm;
  min-height: 297mm;
  margin: 0 auto;
  background-color: #fff;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}

/* When background is active, make container transparent so repeating bg shows */
.direct-view-page.has-bg {
  background-color: transparent;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}

/* Complete sheets share their pixels with PDF and native print. */
.report-capture-source { position:fixed; left:-10000px; top:0; width:210mm; }
.report-page-preview { background:#e5e7eb; padding:16px 0; }
.report-preview-actions { display:flex; flex-wrap:wrap; align-items:center; gap:12px; max-width:210mm; margin:0 auto 16px; }
.report-preview-actions button { background:#0f766e; color:white; padding:9px 16px; border-radius:8px; }
.report-preview-actions button:disabled { opacity:.5; cursor:wait; }
.report-preview-actions [role="alert"] { color:#b91c1c; }
.report-preview-sheet { width:210mm; max-width:100%; aspect-ratio:210 / 297; margin:0 auto 16px; background:white; box-shadow:0 3px 16px #0003; }
.report-preview-sheet img { display:block; width:100%; height:100%; }

@media print {
  .report-preview-actions, #Result.report-capture-source { display:none !important; }
  .report-page-preview { background:none; padding:0; }
  .report-preview-sheet { width:210mm; height:297mm; max-width:none; margin:0; box-shadow:none; overflow:hidden; break-inside:avoid; }
  .report-preview-sheet + .report-preview-sheet { break-before:page; }

  @page {
    size: A4;
    margin: 0 !important;
  }
  .public-page-wrapper, .public-download-page {
    background: none !important;
    padding: 0 !important;
    overflow: visible;
    min-height: 0;
  }
  .direct-view-page {
    box-shadow: none;
    margin: 0;
    width: 100%;
    zoom: 1 !important; /* undo mobile fit-to-width scaling for print */
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .download-card {
    display: none !important;
  }
  /* User-inserted page-break marker from the template editor */
  :deep(.page-break),
  :deep([data-type="page-break"]) {
    page-break-before: always;
    break-before: page;
    height: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    color: transparent !important;
    font-size: 0 !important;
    line-height: 0 !important;
    background: transparent !important;
  }
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
.animate-spin {
  animation: spin 1s linear infinite;
}
</style>
