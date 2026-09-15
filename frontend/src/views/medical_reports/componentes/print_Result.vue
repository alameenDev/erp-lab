<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from "vue";
import { storeToRefs } from "pinia";
import { useRoute } from "vue-router";
import html2pdf from "html2pdf.js";
import JsBarcode from "jsbarcode";
import { useTemplatesStore } from "@/store/modules/template";
import { useinvoicesStore } from "@/store/modules/invoices";
import { useresultStatusStore } from "@/store/modules/result-status";
import result_section from "./result_section.vue";
import result_footer_section from "./result_footer_section.vue";
import { sanitizeHtml } from "@/utils/helper";
import { $http } from "@/plugins/axios";
import { usePrint } from "@/composables/usePrint";
import { useLabSettingsStore } from "@/store/modules/labSettings";

const route = useRoute();
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
const pdfUrl = ref(null);
const documentHeight = ref(0);
const patientId = ref(null);
const template = ref({});
const contentToConvert = ref(null);
const showWithForm = ref(false);
const backgroundUrl = ref(null);
const showDirectView = ref(false);
const reportNotReady = ref(false);
const pdfGenerating = ref(false);
const pdfDownloaded = ref(false);
const renderForCapture = ref(false);
const pdfBlobUrl = ref(null);
// Exposed so the parent (e.g. /medical_reports list) can flip the
// #Result element on for an html2pdf capture and off after — the
// element's display is bound to (showDirectView || renderForCapture)
// in the template, so imperative style writes get overridden otherwise.
defineExpose({
  beginCapture: () => { renderForCapture.value = true; },
  endCapture: () => { renderForCapture.value = false; },
});
const _defaultMargins = { top: 20, bottom: 20, left: 15, right: 15 };
const labSettingsStore = useLabSettingsStore();
const publicLabSettings = ref(null);
const reportSettings = computed(() => publicLabSettings.value || labSettingsStore.settings);
const serverMargins = ref(
  localStorage.getItem("token")
    ? (labSettingsStore.settings.print_margins || { ..._defaultMargins })
    : { ..._defaultMargins }
);
const bgPageHeight = ref(null); // computed page height in px from background image ratio

// Background: controlled by ?form=1 query param for both public and staff
const showBackground = computed(() => {
  return showWithForm.value;
});

// Lay the form-mode (?form=1) report out as discrete A4 pages: insert margin
// spacers at each page boundary and publish --page-height for the page-break
// overlay. onePageHeight is the on-screen px height of one A4 page — derived
// from the letterhead image ratio when a background exists, otherwise from the
// plain A4 ratio (297/210). This is what makes page start/end visible.
const applyPageLayout = (onePageHeight) => {
  nextTick(() => {
    const el = document.getElementById("Result");
    if (!el || !onePageHeight) return;
    bgPageHeight.value = onePageHeight;

    const containerWidth = el.offsetWidth;
    // Convert mm margins to px (containerWidth = 210mm equivalent)
    const pxPerMm = containerWidth / 210;
    const topMarginPx = serverMargins.value.top * pxPerMm;
    const bottomMarginPx = serverMargins.value.bottom * pxPerMm;
    // Space to insert at each page break = footer margin + header margin of next page
    const breakSpacerHeight = bottomMarginPx + topMarginPx;

    // Find all section-level elements inside the content cell
    const contentCell = el.querySelector(".print-wrapper tbody .pw-cell");
    if (!contentCell) {
      el.style.minHeight = `${onePageHeight}px`;
      el.style.setProperty("--page-height", `${onePageHeight}px`);
      return;
    }

    // Usable content area per page (between header margin and footer margin)
    const thead = el.querySelector(".print-wrapper thead");
    const theadHeight = thead ? thead.offsetHeight : 0;
    const firstPageStart = topMarginPx + theadHeight;
    const usableFirstPage = onePageHeight - firstPageStart - bottomMarginPx;
    const usableNextPage = onePageHeight - topMarginPx - bottomMarginPx;

    // Remove previously inserted spacers before recalculating
    contentCell.querySelectorAll(".page-margin-spacer").forEach((s) => s.remove());

    // Walk through sections and insert spacers at page boundaries
    const sections = Array.from(contentCell.querySelectorAll(":scope > section, :scope > table, :scope > div:not(.print-bg):not(.page-margin-spacer)"));
    let accHeight = 0;
    let currentPageRemaining = usableFirstPage;
    let pageIndex = 1;

    for (const section of sections) {
      const sectionHeight = section.offsetHeight;

      if (accHeight + sectionHeight > currentPageRemaining && accHeight > 0) {
        const spacer = document.createElement("div");
        spacer.className = "page-margin-spacer";
        const remainingOnPage = currentPageRemaining - accHeight;
        const spacerH = remainingOnPage + breakSpacerHeight;
        spacer.style.height = `${spacerH}px`;
        section.parentNode.insertBefore(spacer, section);

        accHeight = 0;
        currentPageRemaining = usableNextPage;
        pageIndex++;
      }

      accHeight += sectionHeight;
    }

    // Size the container to a WHOLE number of pages so the last page footer band
    // aligns to a page bottom and the page-break overlay tiles evenly.
    el.style.minHeight = `${pageIndex * onePageHeight}px`;
    el.style.setProperty("--page-height", `${onePageHeight}px`);
  });
};

const computePageLayout = () => {
  void applyPageLayout; // legacy continuous-mode paginator, no longer invoked
  // Card mode (?form=1): every <table class="print-wrapper"> is its own A4
  // card. A card longer than one page (the wrapper holding all non-template
  // tests) spans several letterhead tiles, so per card: insert margin spacers
  // at each in-card page boundary (content steps over the letterhead
  // header/footer bands) and round the card height up to whole pages so the
  // last letterhead tile isn't cut mid-image.
  if (!showBackground.value) return;
  nextTick(() => {
    const el = document.getElementById("Result");
    if (!el) return;
    el.querySelectorAll("table.print-wrapper").forEach((wrap) => {
      if (!wrap.offsetWidth) return;
      // CSS reference px per mm (96dpi) — MUST match the CSS 297mm letterhead
      // tile. Deriving pageH from the card width was wrong: .paged adds 6px
      // side padding, so width < 210mm → pageH < the 297mm tile → ceil()
      // over-counted pages (one empty page per card) and spacers drifted.
      const pxPerMm = 96 / 25.4;
      const pageH = 297 * pxPerMm;
      const topPx = (Number(serverMargins.value.top) || 0) * pxPerMm;
      const botPx = (Number(serverMargins.value.bottom) || 0) * pxPerMm;
      const cell = wrap.querySelector("tbody .pw-cell");
      if (!cell) return;
      // Clear previous spacers before recalculating (resize / re-render)
      cell.querySelectorAll(".page-margin-spacer").forEach((s) => s.remove());
      const thead = wrap.querySelector("thead");
      const theadH = thead ? thead.offsetHeight : 0; // includes top-margin padding
      const usableFirst = pageH - theadH - botPx;
      const usableNext = pageH - topPx - botPx;
      // Reset explicit heights / shaved padding from a previous run.
      wrap.style.height = "";
      wrap.style.minHeight = "";
      cell.style.removeProperty("padding-bottom");
      const sections = Array.from(
        cell.querySelectorAll(":scope > section, :scope > table, :scope > div:not(.page-margin-spacer)")
      );
      let acc = 0;
      let remaining = usableFirst;
      for (const section of sections) {
        // offsetHeight excludes margins (.my-5 etc.) — include them or page
        // boundaries drift further off on every page of a long card.
        const cs = window.getComputedStyle(section);
        const h = section.offsetHeight + (parseFloat(cs.marginTop) || 0) + (parseFloat(cs.marginBottom) || 0);
        if (acc + h > remaining && acc > 0) {
          const spacer = document.createElement("div");
          spacer.className = "page-margin-spacer";
          // leftover on this page + bottom margin + next page's top margin
          spacer.style.height = `${remaining - acc + botPx + topPx}px`;
          section.parentNode.insertBefore(spacer, section);
          acc = 0;
          remaining = usableNext;
        }
        acc += h;
      }
      // If the card spills past the last page edge by no more than the bottom
      // margin, shave this card's bottom padding so it fits — otherwise the
      // whole-page rounding below would add an empty letterhead-only page for
      // a few overflowing pixels. (setProperty important: the CSS padding rule
      // is !important, plain inline style would lose.)
      let fullH = wrap.offsetHeight;
      const pastEdge = fullH % pageH;
      if (pastEdge > 1 && pastEdge <= botPx) {
        cell.style.setProperty("padding-bottom", `${Math.max(0, botPx - pastEdge - 2)}px`, "important");
        fullH = wrap.offsetHeight;
      }
      // Round the card up to WHOLE pages from its real rendered height — a
      // single section taller than one page grows the table past the page
      // edge without a spacer, which left a cut-off letterhead tile peeking
      // at the card bottom. Ceil of the actual height fixes that.
      // Small tolerance so a card that's a few px over (borders/rounding)
      // doesn't get a whole extra empty page.
      const totalPages = Math.max(1, Math.ceil(fullH / pageH - 0.02));
      wrap.style.height = `${totalPages * pageH}px`;
      wrap.style.minHeight = `${totalPages * pageH}px`;
    });
  });
};

// Mobile fit-to-width: the report is a fixed 210mm "paper". On narrow screens
// it overflows and the user must pinch-zoom out to read it. CSS media queries
// are unreliable here (in-app / iOS browsers use a wider layout viewport), so
// scale the whole #Result uniformly with `zoom` based on the REAL viewport
// width. zoom reflows (no leftover scroll space) and scales content + bg +
// page-height math together, so background-mode alignment is preserved.
// Reset to print before printing (handled by @media print + printFromQR window).
let fitTimer = null;
const fitToWidth = () => {
  const el = document.getElementById("Result");
  if (!el || !(showDirectView.value || renderForCapture.value)) return;
  if (renderForCapture.value) { el.style.zoom = ""; return; } // never scale during PDF capture
  el.style.zoom = ""; // reset to measure natural (210mm) width
  // Plain (non-form) short reports: don't keep a full 297mm blank tail.
  // Form mode (?form=1) keeps its paginated min-height from applyPageLayout.
  if (!showBackground.value) el.style.minHeight = "auto";
  const natural = el.offsetWidth;
  if (!natural) return;
  const avail = (window.visualViewport && window.visualViewport.width) || window.innerWidth || natural;
  el.style.zoom = natural > avail ? String(avail / natural) : "";
};

const onViewportResize = () => {
  clearTimeout(fitTimer);
  fitTimer = setTimeout(() => {
    computePageLayout();
    fitToWidth();
  }, 150);
};

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
    ...(printRecord.value?.tests || []),
    // A package's OWN tests print flat like standalone tests. Tests that came
    // from a whole test group attached to the package are excluded here and
    // render as a proper group block instead (see packageGroupSections), so the
    // group keeps its heading and its formula rows.
    ...((printRecord.value?.packages || []).flatMap((pkg) =>
      (pkg.tests || []).filter((t) => !isFormulaTarget(pkg, t) && !t.test_group_name)
    )),
  ];
  if (!source.length) return [];

  const groups = [];
  let currentGroup = null;
  const shownCategories = new Set();

  source
    .filter((t) => (t.name || t.report_name) && !hasRealTemplate(t))
    .forEach((test) => {
      if (!currentGroup || currentGroup.category !== test.category || currentGroup.name !== test.name) {
        const isFirstCategory = !shownCategories.has(test.category);
        if (test.category) shownCategories.add(test.category);
        currentGroup = {
          category: test.category,
          showCategory: isFirstCategory,
          name: test.name,
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

  showWithForm.value = route.query.form === "1";

  // Only fetch auth-required data for logged-in users
  if (hasToken) {
    GetTemplates();
  }
  if (!resultStatus.value?.length) {
    resultStatusStore.GetresultStatus();
  }
  patientId.value = route?.params?.patientId;
  if (patientId.value) {
    documentHeight.value = document.documentElement?.scrollHeight;
    getData();
  }

  // Public page: ensure body allows scrolling
  if (!hasToken) {
    document.documentElement.style.overflow = "auto";
    document.body.style.overflow = "auto";
    document.body.style.background = "#e5e7eb";
    document.body.style.padding = "16px 0";
  }

  // Re-fit on viewport changes (rotation, in-app browser chrome resize).
  window.addEventListener("resize", onViewportResize);
  window.addEventListener("orientationchange", onViewportResize);
  if (window.visualViewport) window.visualViewport.addEventListener("resize", onViewportResize);
  // Reset scaling right before a browser print so the printed page is 1:1.
  window.addEventListener("beforeprint", () => { const el = document.getElementById("Result"); if (el) el.style.zoom = ""; });
  window.addEventListener("afterprint", () => fitToWidth());
});

onUnmounted(() => {
  clearTimeout(fitTimer);
  window.removeEventListener("resize", onViewportResize);
  window.removeEventListener("orientationchange", onViewportResize);
  if (window.visualViewport) window.visualViewport.removeEventListener("resize", onViewportResize);
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

// Print settings — defaults until API/store loads
const showCategories = ref(true);
const showTestName = ref(true);
// lab_settings.print_black_white — when on, every print output drops colour.
const printBlackWhite = ref(false);
const showStatus = ref(true);
const showLastResult = ref(false);

// For staff: read from Pinia store (already fetched on login)
if (hasToken) {
  const s = labSettingsStore.settings;
  showCategories.value = s.show_categories !== false;
  showTestName.value = s.show_test_names !== false;
  printBlackWhite.value = s.print_black_white === true;
  showStatus.value = s.show_status !== false;
  showLastResult.value = s.show_last_result === true;
}

// Inject dynamic <style> for patient-header sizes from lab_settings.
// Same CSS used in print/PDF/WhatsApp via printStyles.getPatientHeaderCss().
const applyPatientHeaderCss = (config) => {
  let el = document.getElementById("patient-header-dynamic");
  if (!el) {
    el = document.createElement("style");
    el.id = "patient-header-dynamic";
    document.head.appendChild(el);
  }
  el.textContent = printStyles.getPatientHeaderCss(config) + printStyles.getPrintTableCss(reportSettings.value.print_table_config);
};
if (hasToken) {
  applyPatientHeaderCss(labSettingsStore.settings.patient_header_config);
}

// Re-read from store when settings change (e.g. user saves settings in another tab)
watch(() => labSettingsStore.settings, (s) => {
  if (!hasToken) return;
  showCategories.value = s.show_categories !== false;
  showTestName.value = s.show_test_names !== false;
  printBlackWhite.value = s.print_black_white === true;
  showStatus.value = s.show_status !== false;
  showLastResult.value = s.show_last_result === true;
  applyPatientHeaderCss(s.patient_header_config);
}, { deep: true });

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

  const addTests = (category, tests) => {
    if (!tests?.length) return;
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
    if (last && last.key === key && !isFirstShowOfCategory) {
      last.tests.push(...tests);
      return;
    }

    sections.push({ key, category: isFirstShowOfCategory ? category : null, tests: [...tests] });
  };

  // Standalone tests
  groupedTests.value.forEach(g => addTests(g.category, g.tests));
  // Test group tests
  printRecord.value?.test_groups?.forEach(g => addTests(g.category, g.tests?.filter(t => !hasRealTemplate(t) && !isFormulaTarget(g, t))));
  // A package's own tests are already flattened into groupedTests above; the
  // group-sourced ones come through here. Pass a null category: show_test_names
  // is OFF in merged mode, so emitting the group NAME as a heading would leak
  // exactly what the setting hides. Formula targets are NOT filtered out —
  // merged mode renders no formula rows, so excluding them would print nothing.
  packageGroupSections.value.forEach((sec) => addTests(null, sec.rows || []));
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
  (printRecord.value?.packages || []).forEach((pkg) => {
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
    if (hasToken) {
      await GetinvoicesById(patientId.value);
    } else {
      // Public access (e.g. WhatsApp link) — use unauthenticated endpoint
      const { data } = await $http.get(`/invoices/public/${patientId.value}`);
      printRecord.value = data;
    }
    computeAllFormulas();
  } catch (err) {
    if (!hasToken && err?.response?.status === 403) {
      reportNotReady.value = true;
    }
    console.error("Failed to fetch invoice data:", err);
    return;
  }

  if (printRecord.value?.lab_id_fk) {
    if (hasToken) {
      // Staff: read from Pinia store (already fetched on login)
      const s = labSettingsStore.settings;
      serverMargins.value = s.print_margins || _defaultMargins;
      // Background only for ?form=1
      if (showWithForm.value && s.report_background) {
        backgroundUrl.value = s.report_background;
      }
    } else {
      // Public: fetch from API
      try {
        const { data } = await $http.get(`/lab-settings/${printRecord.value.lab_id_fk}`);
        publicLabSettings.value = data;
        serverMargins.value = data?.print_margins || _defaultMargins;
        if (data?.report_background) backgroundUrl.value = data.report_background;
        if (data?.show_categories !== undefined) showCategories.value = data.show_categories;
        if (data?.show_test_names !== undefined) showTestName.value = data.show_test_names;
        if (data?.print_black_white !== undefined) printBlackWhite.value = data.print_black_white === true;
        if (data?.show_status !== undefined) showStatus.value = data.show_status;
        if (data?.show_last_result !== undefined) showLastResult.value = data.show_last_result;
        applyPatientHeaderCss(data?.patient_header_config);
      } catch (err) {
        console.error("Failed to fetch lab settings:", err);
      }
    }
  }

  // Public access: show hidden content for PDF capture, then auto-download
  if (!hasToken) {
    showDirectView.value = true;
    nextTick(() => setTimeout(() => { computePageLayout(); fitToWidth(); }, 800));
    return;
  }

  generatePDF();
};

const appBaseUrl = import.meta.env.VITE_APP_URL || window.location.origin;

const getPatientReportLink = () => {
  return `${appBaseUrl}/result/${patientId.value}`;
};

const generatePDF = async () => {
  const m = serverMargins.value;
  if (showWithForm.value) {
    // Show content directly — same as print view
    showDirectView.value = true;
    nextTick(() => setTimeout(() => { computePageLayout(); fitToWidth(); }, 800));
    return;
  }

  // Make the Result element visible so html2pdf can capture it
  const element = contentToConvert.value;
  if (element) element.style.display = "block";

  const opt = {
    margin: [m.top, m.right, m.bottom, m.left],
    filename: "medical-report.pdf",
    image: { type: "jpeg", quality: 0.98 },
    html2canvas: { scale: 2 },
    jsPDF: { unit: "mm", format: "a4", orientation: "portrait" },
  };

  await html2pdf()
    .set(opt)
    .from(element)
    .outputPdf("datauristring")
    .then((pdfDataUri) => {
      pdfUrl.value = pdfDataUri;
      if (element) element.style.display = "none";
    });
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

// Print from QR page — opens new window with same technique as staff "Print with Background"
const printFromQR = () => {
  const printWindow = window.open("", "_blank");
  if (!printWindow) return;

  const content = document.getElementById("Result")?.innerHTML;
  if (!content) {
    printWindow.close();
    return;
  }

  const m = serverMargins.value;
  const bgImage = showBackground.value ? backgroundUrl.value : null;

  // Same CSS as staff print flow: getResultCss() + body padding + fixed background
  let css = printStyles.getResultCss() + printStyles.getPatientHeaderCss(reportSettings.value.patient_header_config) + printStyles.getPrintTableCss(reportSettings.value.print_table_config);
  if (printBlackWhite.value) css += printStyles.getBlackWhiteCss();
  css += `
    body {
      padding: ${m.top}mm ${m.right}mm ${m.bottom}mm ${m.left}mm !important;
      -webkit-box-decoration-break: clone;
      box-decoration-break: clone;
      margin: 0 !important;
    }
    .pw-cell { padding-left: 0 !important; padding-right: 0 !important; }
    .pw-cell.pw-top { padding-top: 0 !important; }
    .pw-cell.pw-bottom { padding-bottom: 0 !important; }
    .print-bg {
      position: fixed;
      top: 0; left: 0;
      width: 210mm; height: 297mm;
      z-index: -1;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .print-bg img { width: 100%; height: 100%; display: block; }
  `;

  printWindow.document.write(`<!DOCTYPE html>
<html><head><title>Print Result</title><style>${css}</style></head>
<body>
  ${bgImage ? `<div class="print-bg"><img src="${bgImage}" /></div>` : ""}
  ${content}
</body></html>`);
  printWindow.document.close();

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

// Download PDF — generates a high-quality A4 PDF matching the print layout
const downloadPDF = async () => {
  if (pdfGenerating.value) return;
  pdfGenerating.value = true;

  // 1. Show the Result div on screen
  renderForCapture.value = true;
  await nextTick();
  window.scrollTo(0, 0);
  await nextTick();

  // 2. Wait for Vue to render child components
  await new Promise((resolve) => setTimeout(resolve, 1000));

  const element = document.getElementById("Result");
  if (!element) {
    pdfGenerating.value = false;
    renderForCapture.value = false;
    return;
  }

  // 3. Remove all cross-origin images that can block html2canvas
  //    Convert external <img> to data URLs, skip ones that fail
  const images = element.querySelectorAll("img");
  for (const img of images) {
    if (img.src && !img.src.startsWith("data:")) {
      try {
        const response = await fetch(img.src);
        const blob = await response.blob();
        const dataUrl = await new Promise((resolve) => {
          const reader = new FileReader();
          reader.onloadend = () => resolve(reader.result);
          reader.readAsDataURL(blob);
        });
        img.src = dataUrl;
      } catch {
        // Remove images that can't be fetched — they'd block html2canvas
        img.remove();
      }
    }
  }

  await nextTick();

  // 4. Hide print-bg overlay
  const printBgEl = element.querySelector(".print-bg");
  if (printBgEl) printBgEl.style.display = "none";

  // 5. Hide the fixed overlay so html2canvas doesn't try to render it
  const overlay = document.querySelector(".pdf-capture-overlay");
  if (overlay) overlay.style.display = "none";

  const code = printRecord.value?.code || printRecord.value?.barcode || "";
  const patientName = printRecord.value?.patient?.name || "";
  const safeName = patientName.replace(/[^\w\u0600-\u06FF\s-]/g, "").trim();
  const filename = safeName ? `${safeName}-${code}.pdf` : `lab-report-${code}.pdf`;

  const opt = {
    margin: 0,
    filename,
    image: { type: "jpeg", quality: 0.98 },
    html2canvas: {
      scale: 2,
      useCORS: true,
      allowTaint: false,
      logging: false,
      removeContainer: true,
      imageTimeout: 5000,
    },
    jsPDF: { unit: "mm", format: "a4", orientation: "portrait" },
  };

  try {
    // Generate PDF as blob and show it in browser
    const pdfBlob = await html2pdf().set(opt).from(element).outputPdf("blob");
    const url = URL.createObjectURL(pdfBlob);
    pdfBlobUrl.value = url;

    if (!hasToken) {
      pdfDownloaded.value = true;
    }
  } catch (err) {
    console.error("PDF generation failed:", err);
  } finally {
    if (printBgEl) printBgEl.style.display = "";
    if (overlay) overlay.style.display = "";
    renderForCapture.value = false;
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
    <div v-if="hasToken && !showDirectView && pdfUrl">
      <iframe :src="pdfUrl" width="100%" :height="documentHeight" style="border: 1px solid #ccc"></iframe>
    </div>
    <!-- Result div: visible for direct view (public + staff ?form=1), hidden for PDF generation -->
    <div
      id="Result"
      ref="contentToConvert"
      :class="[showDirectView || renderForCapture ? 'direct-view-page' : '', { 'has-bg': showDirectView && showBackground && backgroundUrl, 'paged': showDirectView && showBackground }]"
      :style="{
        direction: 'ltr',
        display: showDirectView || renderForCapture ? 'block' : 'none',
        padding: `${serverMargins.top}mm ${serverMargins.right}mm ${serverMargins.bottom}mm ${serverMargins.left}mm`,
        backgroundImage: showBackground && backgroundUrl ? `url(${backgroundUrl})` : 'none',
        backgroundSize: '210mm 297mm',
        backgroundRepeat: showBackground ? 'repeat-y' : 'no-repeat',
        backgroundPosition: 'top center',
        '--lh': showBackground && backgroundUrl ? `url(${backgroundUrl})` : 'none',
        '--mt': `${serverMargins.top}mm`,
        '--mb': `${serverMargins.bottom}mm`,
        '--ml': `${serverMargins.left}mm`,
        '--mr': `${serverMargins.right}mm`,
      }"
    >
    <!-- Print-only background: hidden on screen, appears on every printed page via position:fixed -->
    <div v-if="showBackground && backgroundUrl" class="print-bg">
      <img :src="backgroundUrl" alt="" />
    </div>

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
            <thead><tr><td class="pw-cell pw-top"><result_section /></td></tr></thead>
            <tfoot><tr><td class="pw-cell pw-bottom"></td></tr></tfoot>
            <tbody><tr><td class="pw-cell">
              <section class="template-section"><div v-html="chunk"></div></section>
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
        <thead><tr><td class="pw-cell pw-top"><result_section /></td></tr></thead>
        <tfoot><tr><td class="pw-cell pw-bottom"></td></tr></tfoot>
        <tbody><tr><td class="pw-cell">

        <!-- ===== MERGED TABLE (when showTestName is off) ===== -->
        <template v-if="!showTestName && allTestsMerged.length > 0">
          <section v-for="(section, sIdx) in allTestsMerged" :key="'merged-s-' + sIdx" :style="{ marginTop: sIdx === 0 ? '16px' : '24px', marginBottom: '16px' }">
            <div v-if="section.category" class="section-header w-full font-bold border border-black p-1 text-center text-black bg-gray-300" style="margin-bottom: 10px;">{{ section.category }}</div>
            <table class="w-full" style="border-collapse: collapse; table-layout: fixed;">
              <thead>
                <tr>
                  <th class="border border-black/15 p-0.5 text-center">Test</th>
                  <th class="border border-black/15 p-0.5 text-center">Result</th>
                  <th class="border border-black/15 p-0.5 text-center">Unit</th>
                  <th class="border border-black/15 p-0.5 text-center">Reference Ranges</th>
                  <th v-if="showLastResult" class="border border-black/15 p-0.5 text-center">Last Result</th>
                  <th v-if="showStatus" class="border border-black/15 p-0.5 text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in section.tests" :key="'merged-' + idx">
                  <td class="border border-black/15 p-0.5 text-center">{{ item.report_name || item.name }}</td>
                  <td class="border border-black/15 p-0.5 text-center" :style="getResultColorStyle(item.result_status_id_fk)">{{ item.result ?? "" }}</td>
                  <td class="border border-black/15 p-0.5 text-center">{{ item.unit }}</td>
                  <td class="border border-black/15 p-0.5 text-center">
                    <span v-for="range in getFilteredRanges(item?.test_reference_ranges)" :key="range.test_reference_range_id || range.id">
                      <div v-if="range.notes" style="white-space: pre-line;">{{ range.notes }}</div>
                      <p v-else>{{ range.from }}-{{ range.to }}</p>
                      <p v-for="option in range.test_reference_options" :key="option">{{ option }}</p>
                    </span>
                  </td>
                  <td v-if="showLastResult" class="border border-black/15 p-0.5 text-center">{{ lastResultMap[item.name] || '' }}</td>
                  <td v-if="showStatus" class="border border-black/15 p-0.5 text-center">{{ getStatusLabel(item.result_status_id_fk) }}</td>
                </tr>
              </tbody>
            </table>
          </section>
        </template>

        <!-- ===== STANDALONE TESTS (grouped by test_group name / category) ===== -->
        <template v-if="showTestName && groupedTests.length > 0">
          <section v-for="(group, index) in groupedTests" :key="'st-' + index" class="test-group-section">
            <div v-if="group.showCategory && showCategories" class="section-header w-full font-bold border border-black p-1 text-center text-black bg-gray-300">{{ group.category }}</div>
            <div v-if="group.name && showTestName" class="section-header w-full font-bold border border-black p-1 text-center text-black bg-gray-300" :style="group.showCategory && showCategories ? 'margin-top: 4px' : ''">{{ group.name }}</div>

            <table v-if="group.tests.length > 0" class="w-full my-5" style="border-collapse: collapse; table-layout: fixed;">
              <thead>
                <tr>
                  <th class="border border-black/15 p-0.5 text-center">Test</th>
                  <th class="border border-black/15 p-0.5 text-center">Result</th>
                  <th class="border border-black/15 p-0.5 text-center">Unit</th>
                  <th class="border border-black/15 p-0.5 text-center">Reference Ranges</th>
                  <th v-if="showLastResult" class="border border-black/15 p-0.5 text-center">Last Result</th>
                  <th v-if="showStatus" class="border border-black/15 p-0.5 text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in group.tests" :key="idx">
                  <td class="border border-black/15 p-0.5 text-center">{{ item.report_name || item.name }}</td>
                  <td class="border border-black/15 p-0.5 text-center" :style="getResultColorStyle(item.result_status_id_fk)">{{ item.result ?? "" }}</td>
                  <td class="border border-black/15 p-0.5 text-center">{{ item.unit }}</td>
                  <td class="border border-black/15 p-0.5 text-center">
                    <span v-for="range in getFilteredRanges(item?.test_reference_ranges)" :key="range.test_reference_range_id">
                      <div v-if="range.notes" style="white-space: pre-line;">{{ range.notes }}</div>
                      <p v-else>{{ range.from }}-{{ range.to }}</p>
                      <p v-for="option in range.test_reference_options" :key="option">{{ option }}</p>
                    </span>
                  </td>
                  <td v-if="showLastResult" class="border border-black/15 p-0.5 text-center">{{ lastResultMap[item.name] || '' }}</td>
                  <td v-if="showStatus" class="border border-black/15 p-0.5 text-center">{{ getStatusLabel(item.result_status_id_fk) }}</td>
                </tr>
              </tbody>
            </table>
          </section>
        </template>

        <!-- ===== TEST GROUPS (with nested tests and cultures) ===== -->
        <template v-if="showTestName && printRecord?.test_groups?.length > 0">
          <section v-for="(group, gIndex) in printRecord.test_groups" :key="'tg-' + gIndex" class="test-group-section" :style="(group.is_print_alone == 1 || group.is_print_alone === true) ? 'page-break-before: always; break-before: page;' : ''">
            <div class="section-header w-full font-bold border border-black p-1 text-center text-black bg-gray-300">{{ group.group_name }}</div>

            <!-- Test Group Tests -->
            <table v-if="group.tests?.length > 0" class="w-full my-5" style="border-collapse: collapse; table-layout: fixed;">
              <thead>
                <tr>
                  <th class="border border-black/15 p-0.5 text-center">Test</th>
                  <th class="border border-black/15 p-0.5 text-center">Result</th>
                  <th class="border border-black/15 p-0.5 text-center">Unit</th>
                  <th class="border border-black/15 p-0.5 text-center">Reference Ranges</th>
                  <th v-if="showLastResult" class="border border-black/15 p-0.5 text-center">Last Result</th>
                  <th v-if="showStatus" class="border border-black/15 p-0.5 text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in group.tests.filter(t => !isFormulaTarget(group, t))" :key="'tgt-' + idx">
                  <td class="border border-black/15 p-0.5 text-center">{{ item.report_name || item.name }}</td>
                  <td class="border border-black/15 p-0.5 text-center" :style="getResultColorStyle(item.result_status_id_fk)">{{ item.result ?? "" }}</td>
                  <td class="border border-black/15 p-0.5 text-center">{{ item.unit }}</td>
                  <td class="border border-black/15 p-0.5 text-center">
                    <span v-for="range in getFilteredRanges(item?.test_reference_ranges)" :key="range.test_reference_range_id || range.id">
                      <div v-if="range.notes" style="white-space: pre-line;">{{ range.notes }}</div>
                      <p v-else>{{ range.from }}-{{ range.to }}</p>
                      <p v-for="option in range.test_reference_options" :key="option">{{ option }}</p>
                    </span>
                  </td>
                  <td v-if="showLastResult" class="border border-black/15 p-0.5 text-center">{{ lastResultMap[item.name] || '' }}</td>
                  <td v-if="showStatus" class="border border-black/15 p-0.5 text-center">{{ getStatusLabel(item.result_status_id_fk) }}</td>
                </tr>
                <tr v-for="(f, fi) in (Array.isArray(group.formula) ? group.formula : [])" :key="'gf-' + fi">
                  <td class="border border-black/15 p-0.5 text-center font-bold">{{ getTestLabel(group, f.name) }}</td>
                  <td class="border border-black/15 p-0.5 text-center font-bold">{{ evalFormulaValue(group, f) }}</td>
                  <td class="border border-black/15 p-0.5 text-center">{{ getTestUnit(group, f.name) }}</td>
                  <td class="border border-black/15 p-0.5 text-center">
                    <span v-for="range in getTestRanges(group, f.name)" :key="range.test_reference_range_id || range.id">
                      <div v-if="range.notes" style="white-space: pre-line;">{{ range.notes }}</div>
                      <p v-else>{{ range.from }}-{{ range.to }}</p>
                      <p v-for="option in range.test_reference_options" :key="option">{{ option }}</p>
                    </span>
                  </td>
                  <td v-if="showLastResult" class="border border-black/15 p-0.5 text-center">{{ lastResultMap[getTestLabel(group, f.name)] || '' }}</td>
                  <td v-if="showStatus" class="border border-black/15 p-0.5 text-center">{{ getStatusLabel(getFormulaStatusId(group, f)) }}</td>
                </tr>
              </tbody>
            </table>

            <!-- Test Group Cultures -->
            <template v-if="group.cultures?.length > 0">
              <table class="w-full my-5" style="border-collapse: collapse; table-layout: fixed;">
                <thead>
                  <tr>
                    <th class="border border-black/15 p-0.5 text-center">Culture</th>
                    <th class="border border-black/15 p-0.5 text-center">Result</th>
                    <th class="border border-black/15 p-0.5 text-center">Unit</th>
                    <th class="border border-black/15 p-0.5 text-center">Reference Ranges</th>
                    <th v-if="showLastResult" class="border border-black/15 p-0.5 text-center">Last Result</th>
                    <th v-if="showStatus" class="border border-black/15 p-0.5 text-center">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, idx) in group.cultures" :key="'tgc-' + idx">
                    <td class="border border-black/15 p-0.5 text-center">{{ item.name }}</td>
                    <td class="border border-black/15 p-0.5 text-center" :style="getResultColorStyle(item.result_status_id_fk)">{{ item.result ?? "" }}</td>
                    <td class="border border-black/15 p-0.5 text-center">{{ item.unit }}</td>
                    <td class="border border-black/15 p-0.5 text-center">
                      <span v-for="range in getFilteredRanges(item?.test_reference_ranges)" :key="range.id">
                        <div v-if="range.notes" style="white-space: pre-line;">{{ range.notes }}</div>
                        <p v-else>{{ range.from }}-{{ range.to }}</p>
                      </span>
                    </td>
                    <td v-if="showLastResult" class="border border-black/15 p-0.5 text-center">{{ lastResultMap[item.name] || '' }}</td>
                    <td v-if="showStatus" class="border border-black/15 p-0.5 text-center">{{ getStatusLabel(item.result_status_id_fk) }}</td>
                  </tr>
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
          <section v-for="(group, gpIndex) in packageGroupSections" :key="'pkg-tg-' + gpIndex" class="test-group-section">
            <div class="section-header w-full font-bold border border-black p-1 text-center text-black bg-gray-300">{{ group.group_name }}</div>

            <table v-if="group.rows.filter(t => !isFormulaTarget(group, t)).length || group.formula.length" class="w-full my-5" style="border-collapse: collapse; table-layout: fixed;">
              <thead>
                <tr>
                  <th class="border border-black/15 p-0.5 text-center">Test</th>
                  <th class="border border-black/15 p-0.5 text-center">Result</th>
                  <th class="border border-black/15 p-0.5 text-center">Unit</th>
                  <th class="border border-black/15 p-0.5 text-center">Reference Ranges</th>
                  <th v-if="showLastResult" class="border border-black/15 p-0.5 text-center">Last Result</th>
                  <th v-if="showStatus" class="border border-black/15 p-0.5 text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in group.rows.filter(t => !isFormulaTarget(group, t))" :key="'ptgt-' + idx">
                  <td class="border border-black/15 p-0.5 text-center">{{ item.report_name || item.name }}</td>
                  <td class="border border-black/15 p-0.5 text-center" :style="getResultColorStyle(item.result_status_id_fk)">{{ item.result ?? "" }}</td>
                  <td class="border border-black/15 p-0.5 text-center">{{ item.unit }}</td>
                  <td class="border border-black/15 p-0.5 text-center">
                    <span v-for="range in getFilteredRanges(item?.test_reference_ranges)" :key="range.test_reference_range_id || range.id">
                      <div v-if="range.notes" style="white-space: pre-line;">{{ range.notes }}</div>
                      <p v-else>{{ range.from }}-{{ range.to }}</p>
                      <p v-for="option in range.test_reference_options" :key="option">{{ option }}</p>
                    </span>
                  </td>
                  <td v-if="showLastResult" class="border border-black/15 p-0.5 text-center">{{ lastResultMap[item.name] || '' }}</td>
                  <td v-if="showStatus" class="border border-black/15 p-0.5 text-center">{{ getStatusLabel(item.result_status_id_fk) }}</td>
                </tr>
                <tr v-for="(f, fi) in group.formula" :key="'ptgf-' + fi">
                  <td class="border border-black/15 p-0.5 text-center font-bold">{{ getTestLabel(group, f.name) }}</td>
                  <td class="border border-black/15 p-0.5 text-center font-bold">{{ evalFormulaValue(group, f) }}</td>
                  <td class="border border-black/15 p-0.5 text-center">{{ getTestUnit(group, f.name) }}</td>
                  <td class="border border-black/15 p-0.5 text-center">
                    <span v-for="range in getTestRanges(group, f.name)" :key="range.test_reference_range_id || range.id">
                      <div v-if="range.notes" style="white-space: pre-line;">{{ range.notes }}</div>
                      <p v-else>{{ range.from }}-{{ range.to }}</p>
                      <p v-for="option in range.test_reference_options" :key="option">{{ option }}</p>
                    </span>
                  </td>
                  <td v-if="showLastResult" class="border border-black/15 p-0.5 text-center">{{ lastResultMap[getTestLabel(group, f.name)] || '' }}</td>
                  <td v-if="showStatus" class="border border-black/15 p-0.5 text-center">{{ getStatusLabel(getFormulaStatusId(group, f)) }}</td>
                </tr>
              </tbody>
            </table>
          </section>
        </template>

        <!-- ===== STANDALONE CULTURES ===== -->
        <template v-if="printRecord?.cultures?.length > 0">
          <section v-for="(culture, cIndex) in printRecord.cultures" :key="'sc-' + cIndex" class="test-group-section">
            <div v-if="culture.category && showCategories && !printRecord.cultures.slice(0, cIndex).some(c => c.category === culture.category)" class="section-header w-full font-bold border border-black p-1 text-center text-black bg-gray-300">{{ culture.category }}</div>
            <table class="w-full my-5" style="border-collapse: collapse; table-layout: fixed;">
              <thead>
                <tr>
                  <th class="border border-black/15 p-0.5 text-center">Culture</th>
                  <th class="border border-black/15 p-0.5 text-center">Result</th>
                  <th class="border border-black/15 p-0.5 text-center">Unit</th>
                  <th class="border border-black/15 p-0.5 text-center">Reference Ranges</th>
                  <th v-if="showLastResult" class="border border-black/15 p-0.5 text-center">Last Result</th>
                  <th v-if="showStatus" class="border border-black/15 p-0.5 text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="border border-black/15 p-0.5 text-center">{{ culture.name }}</td>
                  <td class="border border-black/15 p-0.5 text-center" :style="getResultColorStyle(culture.result_status_id_fk)">{{ culture.result ?? "" }}</td>
                  <td class="border border-black/15 p-0.5 text-center">{{ culture.unit }}</td>
                  <td class="border border-black/15 p-0.5 text-center">
                    <span v-for="range in getFilteredRanges(culture?.test_reference_ranges)" :key="range.id">
                      <div v-if="range.notes" style="white-space: pre-line;">{{ range.notes }}</div>
                      <p v-else>{{ range.from }}-{{ range.to }}</p>
                    </span>
                  </td>
                  <td v-if="showLastResult" class="border border-black/15 p-0.5 text-center">{{ lastResultMap[culture.name] || '' }}</td>
                  <td v-if="showStatus" class="border border-black/15 p-0.5 text-center">{{ getStatusLabel(culture.result_status_id_fk) }}</td>
                </tr>
              </tbody>
            </table>
          </section>
        </template>

        <!-- ===== PACKAGES (with nested tests and cultures) ===== -->
        <!-- Package tests print as standalone tests (flattened into groupedTests).
             The package section remains only for cultures + formula rows. -->
        <template v-if="showTestName && printRecord?.packages?.length > 0">
          <section v-for="(pkg, pIndex) in printRecord.packages.filter(p => p.cultures?.length > 0 || (Array.isArray(p.formula) && p.formula.length > 0))" :key="'pkg-' + pIndex" class="test-group-section">
            <div class="section-header w-full font-bold border border-black p-1 text-center text-black bg-gray-300">{{ pkg.name }}</div>

            <table class="w-full my-5" style="border-collapse: collapse; table-layout: fixed;">
              <thead>
                <tr>
                  <th class="border border-black/15 p-0.5 text-center">Test</th>
                  <th class="border border-black/15 p-0.5 text-center">Result</th>
                  <th class="border border-black/15 p-0.5 text-center">Unit</th>
                  <th class="border border-black/15 p-0.5 text-center">Reference Ranges</th>
                  <th v-if="showLastResult" class="border border-black/15 p-0.5 text-center">Last Result</th>
                  <th v-if="showStatus" class="border border-black/15 p-0.5 text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in pkg.cultures" :key="'pc-' + idx">
                  <td class="border border-black/15 p-0.5 text-center">{{ item.name }}</td>
                  <td class="border border-black/15 p-0.5 text-center" :style="getResultColorStyle(item.result_status_id_fk)">{{ item.result ?? "" }}</td>
                  <td class="border border-black/15 p-0.5 text-center">{{ item.unit }}</td>
                  <td class="border border-black/15 p-0.5 text-center">
                    <span v-for="range in item?.test_reference_ranges" :key="range">
                      {{ range.from }}-{{ range.to }}
                    </span>
                  </td>
                  <td v-if="showLastResult" class="border border-black/15 p-0.5 text-center">{{ lastResultMap[item.name] || '' }}</td>
                  <td v-if="showStatus" class="border border-black/15 p-0.5 text-center">{{ getStatusLabel(item.result_status_id_fk) }}</td>
                </tr>
                <tr v-for="(f, fi) in (Array.isArray(pkg.formula) ? pkg.formula : [])" :key="'pf-' + fi">
                  <td class="border border-black/15 p-0.5 text-center font-bold">{{ getTestLabel(pkg, f.name) }}</td>
                  <td class="border border-black/15 p-0.5 text-center font-bold">{{ evalFormulaValue(pkg, f) }}</td>
                  <td class="border border-black/15 p-0.5 text-center">{{ getTestUnit(pkg, f.name) }}</td>
                  <td class="border border-black/15 p-0.5 text-center">
                    <span v-for="range in getTestRanges(pkg, f.name)" :key="range.test_reference_range_id || range.id">
                      <div v-if="range.notes" style="white-space: pre-line;">{{ range.notes }}</div>
                      <p v-else>{{ range.from }}-{{ range.to }}</p>
                      <p v-for="option in range.test_reference_options" :key="option">{{ option }}</p>
                    </span>
                  </td>
                  <td v-if="showLastResult" class="border border-black/15 p-0.5 text-center">{{ lastResultMap[getTestLabel(pkg, f.name)] || '' }}</td>
                  <td v-if="showStatus" class="border border-black/15 p-0.5 text-center">{{ getStatusLabel(getFormulaStatusId(pkg, f)) }}</td>
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

/* ===== On-screen "separate page cards" for the ?form=1 view =====
   Special-test reports split into several <table class="print-wrapper">, each
   with its own patient-header thead. On print they land on separate pages; on
   screen they used to stack (duplicate header + one long sheet). Here each
   print-wrapper becomes its own A4 card — gray gutter between, drop shadow, and
   its own letterhead — so every section reads as a distinct page.
   Scoped (data-v) → the print/PDF iframe (which only loads getResultCss) is
   unaffected. */
.direct-view-page.paged {
  background: #e5e7eb !important;       /* page-gray gutter behind the cards */
  background-image: none !important;    /* drop the continuous letterhead */
  padding: 14px 6px !important;
  min-height: 100vh;
}
.direct-view-page.paged .print-wrapper {
  width: 100%;
  max-width: 210mm;
  min-height: 297mm;                    /* full A4 sheet even when short */
  margin: 0 auto 18px auto;
  background-color: #fff;
  /* Letterhead = one tile per A4 page. A card taller than one page (e.g. the
     wrapper holding all non-template tests) repeats the letterhead at each
     page boundary instead of stretching one image over the whole card. */
  background-image: var(--lh, none);
  background-size: 100% 297mm;
  background-repeat: repeat-y;
  background-position: top center;
  box-shadow: 0 3px 16px rgba(0, 0, 0, 0.22);
  border-radius: 2px;
}
.direct-view-page.paged .print-wrapper:last-child {
  margin-bottom: 0;
}
/* Keep content inside the letterhead's safe area (clear the logo top + footer).
   Prefixed with #Result so these beat the id-specificity rule
   `#Result .pw-cell { padding: 0 !important }` — without the id prefix the
   class selector loses the !important tie-break and the configured margins are
   forced to 0 (i.e. lab print-margin settings stop applying). */
#Result.direct-view-page.paged .pw-cell {
  padding-left: var(--ml, 15mm) !important;
  padding-right: var(--mr, 15mm) !important;
}
#Result.direct-view-page.paged thead .pw-cell {
  padding-top: var(--mt, 20mm) !important;
}
#Result.direct-view-page.paged tbody .pw-cell {
  padding-bottom: var(--mb, 20mm) !important;
}

@media print {
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
