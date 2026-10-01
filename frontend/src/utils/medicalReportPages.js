export function reportMargins(raw = {}) {
  const defaults = { top: 20, right: 15, bottom: 20, left: 15 };
  const m = Object.fromEntries(Object.entries(defaults).map(([key, fallback]) => {
    const value = Number(raw?.[key] ?? fallback);
    return [key, Number.isFinite(value) && value >= 0 ? value : fallback];
  }));
  if (m.left + m.right >= 190 || m.top + m.bottom >= 267) {
    throw new Error('الهوامش كبيرة جداً لحجم A4. قلّل الهوامش ثم أعد المحاولة.');
  }
  return m;
}

// Every output uses complete A4 page images, already composed with margins.
// Browser page margins therefore stay zero; the letterhead never compensates
// for content margins with negative offsets.
export const reportSheetCss = `
  @page { size: A4 portrait; margin: 0 !important; }
  html, body { margin: 0 !important; padding: 0 !important; }
  .report-sheet { display:block; width:210mm; height:297mm; margin:0; padding:0;
    overflow:hidden; break-inside:avoid; page-break-inside:avoid; }
  .report-sheet + .report-sheet { break-before:page; page-break-before:always; }
  .report-sheet img { display:block; width:100%; height:100%; }
`;

export function reportGeometry(raw, pixelsPerMm = 96 / 25.4 * 2) {
  const m = reportMargins(raw);
  const paper = { width: Math.round(210 * pixelsPerMm), height: Math.round(297 * pixelsPerMm) };
  return { paper, content: {
    x: Math.round(m.left * pixelsPerMm), y: Math.round(m.top * pixelsPerMm),
    width: Math.floor((210 - m.left - m.right) * pixelsPerMm),
    height: Math.floor((297 - m.top - m.bottom) * pixelsPerMm),
  } };
}

export async function printMedicalReportPages(pages, printWindow) {
  if (!printWindow || printWindow.closed) throw new Error('اسمح بالنوافذ المنبثقة للطباعة.');
  if (!pages?.length) throw new Error('التقرير غير جاهز للطباعة.');
  const doc = printWindow.document;
  doc.open();
  doc.write(`<!doctype html><html><head><meta charset="utf-8"><title>Medical report</title><style>${reportSheetCss}</style></head><body></body></html>`);
  doc.close();
  for (const source of pages) {
    const sheet = doc.createElement('div');
    sheet.className = 'report-sheet';
    const img = doc.createElement('img');
    img.src = source;
    img.alt = 'Medical report';
    sheet.appendChild(img);
    doc.body.appendChild(sheet);
  }
  await waitForReportAssets(doc);
  printWindow.focus();
  printWindow.print();
}

// Return disjoint pixel ranges: never re-draw an entire tall image at negative
// offsets (that paints results over the margins). Prefer row/paragraph edges.
export function reportSlices(height, capacity, intervals = [], forcedBreaks = []) {
  if (!(height >= 0 && capacity > 0)) throw new Error('Invalid report page dimensions');
  // An explicit page break (including an isolated group inside a package)
  // takes precedence over keeping a surrounding block on one sheet.
  const keep = intervals.map(([top, bottom]) => [top, Math.min(height, bottom)])
    .filter(([top, bottom]) => bottom > top && bottom - top <= capacity &&
    !forcedBreaks.some(y => y > top + 1 && y < bottom - 1));
  const slices = [];
  let start = 0;
  while (start < height) {
    let end = Math.min(height, start + capacity);
    const forced = forcedBreaks.filter(y => y > start + 1 && y < end).sort((a, b) => a - b)[0];
    if (forced !== undefined) end = forced;
    let previous;
    do {
      previous = end;
      // Adjacent rasterized rows can overlap by one rounded pixel. Do not
      // cascade backwards through every row for that shared border.
      for (const [top, bottom] of keep) {
        if (top > start + 1 && top < end && bottom > end + 1) end = top;
      }
    } while (previous !== end);
    end = Math.max(start + 1, Math.floor(end));
    slices.push([start, end]);
    start = end;
  }
  return slices;
}

export async function waitForReportAssets(doc) {
  if (doc.fonts?.ready) await doc.fonts.ready;
  await Promise.all(Array.from(doc.images, img => img.complete ? Promise.resolve() : new Promise(resolve => {
    img.addEventListener('load', resolve, { once: true });
    img.addEventListener('error', resolve, { once: true });
  })));
}

// CSS break-inside cannot protect blocks after the report has been rasterized.
// Measure semantic blocks in the capture document before slicing its pixels.
export function reportKeepIntervals(target, rect, scale, headerPixels) {
  const intervals = [];
  const bounds = node => {
    const r = node.getBoundingClientRect();
    return [Math.floor((r.top - rect.top) * scale) - headerPixels,
      Math.floor((r.bottom - rect.top) * scale) - headerPixels];
  };
  const add = (first, last = first) => {
    const [top] = bounds(first), [, bottom] = bounds(last);
    if (top >= 0 && bottom > top) intervals.push([top, bottom]);
  };
  target.querySelectorAll('tr,p,img,li,pre,blockquote,.sign,[data-report-section],.test-group-section,.template-section,[data-report-keep-together]').forEach(node => add(node));

  // A merged table still contains separate tests, groups and packages. Their
  // identity survives as metadata without changing the configured appearance.
  for (const attribute of ['data-report-block', 'data-report-bundle']) {
    const groups = new Map();
    target.querySelectorAll(`[${attribute}]`).forEach(node => {
      const key = node.getAttribute(attribute);
      if (!key) return;
      const [top, bottom] = bounds(node);
      const prior = groups.get(key);
      groups.set(key, prior ? [Math.min(top, prior[0]), Math.max(bottom, prior[1])] : [top, bottom]);
    });
    intervals.push(...[...groups.values()].filter(([top, bottom]) => top >= 0 && bottom > top));
  }

  // Large sections must continue, but their title(s) and table header must
  // travel with the first complete result. Protect every table header too,
  // including a culture table following the tests in the same group.
  const rows = Array.from(target.querySelectorAll('table > tbody > tr'))
    .filter(row => row.closest('table') !== target && row.getBoundingClientRect().height > 0);
  target.querySelectorAll('table').forEach(table => {
    const first = table.querySelector(':scope > tbody > tr');
    if (first) add(table.querySelector(':scope > thead') || first, first);
  });
  const content = [...rows, ...Array.from(target.querySelectorAll('p,img,li,pre,blockquote'))
    .filter(node => !node.closest('thead,.section-header') && !rows.some(row => row.contains(node)))];
  target.querySelectorAll('.section-header,h1,h2,h3,h4,h5,h6,caption').forEach(heading => {
    const r = heading.getBoundingClientRect();
    const next = content.filter(node => node.getBoundingClientRect().height > 0 &&
      node.getBoundingClientRect().top >= r.bottom - 1)
      .sort((a, b) => a.getBoundingClientRect().top - b.getBoundingClientRect().top)[0];
    if (next) add(heading, next);
  });
  return intervals;
}

// A moved block can end exactly at the page limit while its CSS bottom margin
// extends beyond it. Trailing white/transparent pixels must not create another
// sheet containing only the repeated patient header. Inspect the source, before
// adding the letterhead, and retain every actually painted content pixel.
function reportPaintedHeight(canvas, headerPixels) {
  const ctx = canvas.getContext('2d');
  for (let end = canvas.height; end > headerPixels;) {
    const top = Math.max(headerPixels, end - 128);
    const pixels = ctx.getImageData(0, top, canvas.width, end - top).data;
    for (let i = pixels.length - 4; i >= 0; i -= 4) {
      if (pixels[i + 3] && (pixels[i] !== 255 || pixels[i + 1] !== 255 || pixels[i + 2] !== 255)) {
        return top + Math.floor(i / 4 / canvas.width) + 1;
      }
    }
    end = top;
  }
  return headerPixels;
}

export async function renderMedicalReportPages({ element, css, margins, background }) {
  if (!element) throw new Error('تعذر العثور على محتوى التقرير.');
  const m = reportMargins(margins);
  const width = 210 - m.left - m.right;
  const frame = document.createElement('iframe');
  frame.setAttribute('aria-hidden', 'true');
  frame.style.cssText = `position:fixed;left:-10000px;top:0;width:${width}mm;height:297mm;border:0;`;
  document.body.appendChild(frame);
  try {
    const doc = frame.contentDocument;
    doc.open();
    doc.write(`<!doctype html><html><head><meta charset="utf-8"><style>${css}
      html,body { margin:0!important; padding:0!important; width:${width}mm!important; background:transparent!important; }
      #Result { display:block!important; position:static!important; left:auto!important; top:auto!important; width:100%!important; padding:0!important; margin:0!important; min-height:0!important; zoom:1!important; background:none!important; }
      .print-wrapper { width:100%!important; height:auto!important; min-height:0!important; margin:0!important; background:none!important; }
      .pw-cell,.pw-cell.pw-top,.pw-cell.pw-bottom { padding:0!important; }
      .print-bg,.page-margin-spacer { display:none!important; }
    </style></head><body></body></html>`);
    doc.close();
    const clone = doc.importNode(element, true);
    clone.querySelectorAll('.page-margin-spacer,.print-bg').forEach(node => node.remove());
    doc.body.appendChild(clone);
    await waitForReportAssets(doc);
    const { default: html2canvas } = await import('html2canvas-pro');
    const pages = [];
    let letterhead;
    if (background) {
      const img = doc.createElement('img');
      img.crossOrigin = 'anonymous';
      const loaded = new Promise((resolve, reject) => {
        img.onload = resolve;
        img.onerror = () => reject(new Error('تعذر تحميل خلفية التقرير. جرّب مجدداً أو اطبع بدون خلفية.'));
      });
      img.src = background;
      await loaded;
      letterhead = img;
    }
    const wrappers = Array.from(clone.querySelectorAll('table.print-wrapper'));
    const targets = wrappers.length ? wrappers : [clone];
    for (const target of targets) {
      const rect = target.getBoundingClientRect();
      const canvas = await html2canvas(target, { scale: 2, useCORS: true, allowTaint: false, logging: false, backgroundColor: null });
      if (!rect.width || !canvas.width || !canvas.height) throw new Error('التقرير فارغ أو غير جاهز للطباعة.');
      const scale = canvas.height / rect.height;
      const pixelsPerMm = 96 / 25.4 * 2;
      const header = target.querySelector(':scope > thead');
      const headerPixels = header ? Math.ceil((header.getBoundingClientRect().bottom - rect.top) * scale) : 0;
      const geometry = reportGeometry(m, pixelsPerMm);
      const available = geometry.content.height - headerPixels;
      if (available <= 0) throw new Error('بيانات رأس التقرير تتجاوز المساحة المتاحة. قلّل الهوامش أو حجم الخط.');
      const intervals = reportKeepIntervals(target, rect, scale, headerPixels);
      const breaks = Array.from(target.querySelectorAll('*')).filter(node => {
        const style = doc.defaultView.getComputedStyle(node);
        return node.matches('.page-break,[data-type="page-break"]') || ['page', 'always'].includes(style.breakBefore);
      }).map(node => Math.floor((node.getBoundingClientRect().top - rect.top) * scale) - headerPixels);
      // Isolate both sides of a marked group, including its continuation pages.
      // Break at the NEXT result section, not the group's bottom: a last group
      // may keep its signature, and must not create a blank trailing sheet.
      // The first section already starts a sheet (after the patient header).
      const sections = Array.from(target.querySelectorAll('[data-report-section]'))
        .filter(node => node.getBoundingClientRect().height > 0);
      sections.forEach((section, index) => {
        if (index > 0 && (section.dataset.reportIsolated === 'true' || sections[index - 1].dataset.reportIsolated === 'true')) {
          breaks.push(Math.floor((section.getBoundingClientRect().top - rect.top) * scale) - headerPixels);
        }
      });
      const slices = reportSlices(reportPaintedHeight(canvas, headerPixels) - headerPixels, available, intervals, breaks);
      // A header-only report still produces one page.
      for (const [start, end] of slices.length ? slices : [[0, 0]]) {
        const page = doc.createElement('canvas');
        page.width = geometry.paper.width; page.height = geometry.paper.height;
        const ctx = page.getContext('2d');
        ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, page.width, page.height);
        // Background coordinates depend only on the sheet, NEVER on margins.
        if (letterhead) ctx.drawImage(letterhead, 0, 0, page.width, page.height);
        const { x, y, width: contentWidth, height: contentHeight } = geometry.content;
        ctx.save();
        ctx.beginPath(); ctx.rect(x, y, contentWidth, contentHeight); ctx.clip();
        if (headerPixels) ctx.drawImage(canvas, 0, 0, canvas.width, headerPixels, x, y, canvas.width, headerPixels);
        if (end > start) ctx.drawImage(canvas, 0, headerPixels + start, canvas.width, end - start, x, y + headerPixels, canvas.width, end - start);
        ctx.restore();
        pages.push(page.toDataURL('image/png'));
      }
    }
    return pages;
  } finally {
    frame.remove();
  }
}

// Preview, download, print and WhatsApp consume the same complete sheets.
export async function createMedicalReportPdf(options) {
  const pages = options.pages || await renderMedicalReportPages(options);
  if (!pages.length) throw new Error('التقرير غير جاهز.');
  const { jsPDF } = await import('jspdf');
  const pdf = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });
  pages.forEach((page, index) => {
    if (index) pdf.addPage();
    pdf.addImage(page, 'PNG', 0, 0, 210, 297, undefined, 'FAST');
  });
  return pdf;
}
