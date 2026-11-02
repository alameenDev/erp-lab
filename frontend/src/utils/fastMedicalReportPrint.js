import { reportSheetCss } from './medicalReportPages.js';
import { routePrintDocument } from './localPrint.js';

// Experimental destination only: keep the exact A4 rasters produced by the
// existing renderer. No PDF encoding, scaling, pagination or popup here.
export async function printFastMedicalReportPages(pages, { signal } = {}) {
  if (!pages?.length) throw new Error('التقرير غير جاهز للطباعة.');
  signal?.throwIfAborted();
  const frame = document.createElement('iframe');
  frame.dataset.fastReportPrint = '';
  frame.setAttribute('aria-hidden', 'true');
  frame.setAttribute('tabindex', '-1');
  frame.style.cssText = 'position:fixed;left:-10000px;top:0;width:210mm;height:297mm;border:0;pointer-events:none;';
  let rejectAbort;
  const aborted = new Promise((_, reject) => { rejectAbort = reject; });
  const cleanup = () => {
    signal?.removeEventListener('abort', onAbort);
    window.removeEventListener('pagehide', onAbort);
    frame.remove();
  };
  const onAbort = () => {
    cleanup();
    rejectAbort(new DOMException('أُلغيت الطباعة عند مغادرة الصفحة.', 'AbortError'));
  };
  signal?.addEventListener('abort', onAbort, { once: true });
  window.addEventListener('pagehide', onAbort, { once: true });
  document.body.appendChild(frame);
  try {
    const win = frame.contentWindow, doc = win.document;
    doc.open();
    doc.write(`<!doctype html><html><head><meta charset="utf-8"><title>Medical report</title><style>${reportSheetCss}</style></head><body></body></html>`);
    doc.close();
    const ready = pages.map(source => {
      const sheet = doc.createElement('div');
      sheet.className = 'report-sheet';
      const img = doc.createElement('img');
      img.src = source;
      img.alt = 'Medical report';
      sheet.appendChild(img);
      doc.body.appendChild(sheet);
      // Fail closed on a corrupt page instead of printing a blank medical sheet.
      return img.decode();
    });
    await Promise.race([Promise.all([doc.fonts?.ready, ...ready]), aborted]);
    if (!frame.isConnected) throw new DOMException('أُلغيت الطباعة.', 'AbortError');
    if (await routePrintDocument(doc, 'report', { canSend: () => frame.isConnected && !signal?.aborted })) {
      cleanup();
      return;
    }
    signal?.throwIfAborted();
    if (!frame.isConnected) throw new DOMException('أُلغيت الطباعة.', 'AbortError');
    // Do not remove a frame on a timer while its print dialog is still open.
    // afterprint handles printing AND cancellation; navigation also cleans up.
    win.addEventListener('afterprint', cleanup, { once: true });
    win.focus();
    win.print();
  } catch (error) {
    cleanup();
    throw error;
  }
}
