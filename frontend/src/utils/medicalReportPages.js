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

// Body padding only protects the first/last fragment. @page reserves space
// on EVERY physical sheet, including continuation pages within a long table.
export function reportPageCss(raw) {
  const m = reportMargins(raw);
  return `
    @page { size: A4 portrait; margin: ${m.top}mm ${m.right}mm ${m.bottom}mm ${m.left}mm !important; }
    html, body { margin: 0 !important; padding: 0 !important; }
    #Result, .print-result-container { width: 100% !important; min-height: 0 !important; padding: 0 !important; margin: 0 !important; zoom: 1 !important; background: none !important; }
    .print-wrapper { height: auto !important; min-height: 0 !important; }
    .pw-cell, .pw-cell.pw-top, .pw-cell.pw-bottom { padding: 0 !important; }
    .page-margin-spacer { display: none !important; }
    .print-wrapper > thead { display: table-header-group; }
    .print-wrapper > tfoot { display: table-footer-group; }
    .print-bg { display: block !important; position: fixed !important; top: -${m.top}mm !important; left: -${m.left}mm !important; width: 210mm !important; height: 297mm !important; z-index: -1; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    .print-bg img { display: block; width: 100%; height: 100%; }
  `;
}

// Return disjoint pixel ranges: never re-draw an entire tall image at negative
// offsets (that paints results over the margins). Prefer row/paragraph edges.
export function reportSlices(height, capacity, intervals = [], forcedBreaks = []) {
  if (!(height >= 0 && capacity > 0)) throw new Error('Invalid report page dimensions');
  const slices = [];
  let start = 0;
  while (start < height) {
    let end = Math.min(height, start + capacity);
    const forced = forcedBreaks.filter(y => y > start + 1 && y < end).sort((a, b) => a - b)[0];
    if (forced !== undefined) end = forced;
    let previous;
    do {
      previous = end;
      for (const [top, bottom] of intervals) {
        if (top > start + 1 && top < end && bottom > end && bottom - top <= capacity) end = top;
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

export async function createMedicalReportPdf({ element, css, margins, background }) {
  if (!element) throw new Error('تعذر العثور على محتوى التقرير.');
  const m = reportMargins(margins);
  const width = 210 - m.left - m.right;
  const height = 297 - m.top - m.bottom;
  const frame = document.createElement('iframe');
  frame.setAttribute('aria-hidden', 'true');
  frame.style.cssText = `position:fixed;left:-10000px;top:0;width:${width}mm;height:297mm;border:0;`;
  document.body.appendChild(frame);
  try {
    const doc = frame.contentDocument;
    doc.open();
    doc.write(`<!doctype html><html><head><meta charset="utf-8"><style>${css}
      html,body { margin:0!important; padding:0!important; width:${width}mm!important; background:transparent!important; }
      #Result { display:block!important; width:100%!important; padding:0!important; margin:0!important; min-height:0!important; zoom:1!important; background:none!important; }
      .print-wrapper { width:100%!important; height:auto!important; min-height:0!important; margin:0!important; background:none!important; }
      .pw-cell,.pw-cell.pw-top,.pw-cell.pw-bottom { padding:0!important; }
      .print-bg,.page-margin-spacer { display:none!important; }
    </style></head><body></body></html>`);
    doc.close();
    const clone = doc.importNode(element, true);
    clone.querySelectorAll('.page-margin-spacer,.print-bg').forEach(node => node.remove());
    doc.body.appendChild(clone);
    await waitForReportAssets(doc);
    const [{ default: html2canvas }, { jsPDF }] = await Promise.all([import('html2canvas-pro'), import('jspdf')]);
    const pdf = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });
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
      const canvas = doc.createElement('canvas');
      canvas.width = img.naturalWidth; canvas.height = img.naturalHeight;
      canvas.getContext('2d').drawImage(img, 0, 0);
      letterhead = canvas.toDataURL('image/png');
    }
    const wrappers = Array.from(clone.querySelectorAll('table.print-wrapper'));
    const targets = wrappers.length ? wrappers : [clone];
    let pageNumber = 0;
    for (const target of targets) {
      const rect = target.getBoundingClientRect();
      const canvas = await html2canvas(target, { scale: 2, useCORS: true, allowTaint: false, logging: false, backgroundColor: null });
      if (!rect.width || !canvas.width || !canvas.height) throw new Error('التقرير فارغ أو غير جاهز للطباعة.');
      const scale = canvas.height / rect.height;
      const pixelsPerMm = canvas.width / width;
      const header = target.querySelector(':scope > thead');
      const headerPixels = header ? Math.ceil((header.getBoundingClientRect().bottom - rect.top) * scale) : 0;
      const available = Math.floor(height * pixelsPerMm) - headerPixels;
      if (available <= 0) throw new Error('بيانات رأس التقرير تتجاوز المساحة المتاحة. قلّل الهوامش أو حجم الخط.');
      const intervals = Array.from(target.querySelectorAll('tr,p,img,.sign,.section-header'), node => {
        const r = node.getBoundingClientRect();
        return [Math.floor((r.top - rect.top) * scale) - headerPixels, Math.ceil((r.bottom - rect.top) * scale) - headerPixels];
      }).filter(([top, bottom]) => top >= 0 && bottom > top);
      const breaks = Array.from(target.querySelectorAll('*')).filter(node => {
        const style = doc.defaultView.getComputedStyle(node);
        return node.matches('.page-break,[data-type="page-break"]') || ['page', 'always'].includes(style.breakBefore);
      }).map(node => Math.floor((node.getBoundingClientRect().top - rect.top) * scale) - headerPixels);
      const slices = reportSlices(canvas.height - headerPixels, available, intervals, breaks);
      // A header-only report still produces one page.
      for (const [start, end] of slices.length ? slices : [[0, 0]]) {
        if (pageNumber++) pdf.addPage();
        if (letterhead) pdf.addImage(letterhead, 'PNG', 0, 0, 210, 297, 'letterhead', 'FAST');
        const page = doc.createElement('canvas');
        page.width = canvas.width; page.height = headerPixels + end - start;
        const ctx = page.getContext('2d');
        if (headerPixels) ctx.drawImage(canvas, 0, 0, canvas.width, headerPixels, 0, 0, canvas.width, headerPixels);
        if (end > start) ctx.drawImage(canvas, 0, headerPixels + start, canvas.width, end - start, 0, headerPixels, canvas.width, end - start);
        pdf.addImage(page.toDataURL('image/png'), 'PNG', m.left, m.top, width, page.height / pixelsPerMm, undefined, 'FAST');
      }
    }
    return pdf;
  } finally {
    frame.remove();
  }
}
