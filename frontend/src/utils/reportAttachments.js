const MAX_PAGES = 60;
const MAX_BYTES = 10 * 1024 * 1024;

export function reportAttachmentUrl(value) {
  try {
    const url = new URL(value, window.location.origin);
    return ['http:', 'https:'].includes(url.protocol) ? url.href : null;
  } catch { return null; }
}

class ReportAttachmentError extends Error {}

// Storage URLs may have no extension and servers may send octet-stream.
// Identify the actual file bytes so a PDF/image always renders inline.
export async function validateReportAttachment(blob, name = blob.name || 'Attachment') {
  const bytes = new Uint8Array(await blob.slice(0, 1024).arrayBuffer());
  const ascii = Array.from(bytes, byte => String.fromCharCode(byte)).join('');
  if (ascii.includes('%PDF-')) return 'application/pdf';
  if (bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff) return 'image/jpeg';
  if ([137,80,78,71,13,10,26,10].every((byte, i) => bytes[i] === byte)) return 'image/png';
  if (/^GIF8[79]a/.test(ascii)) return 'image/gif';
  if (ascii.startsWith('RIFF') && ascii.slice(8, 12) === 'WEBP') return 'image/webp';
  if (ascii.startsWith('BM')) return 'image/bmp';
  throw new ReportAttachmentError(`المرفق «${name}» لا يدعم العرض داخل التقرير. حوّل الملف إلى PDF أو صورة ثم أرفقه من جديد.`);
}

async function imageData(blob) {
  const url = URL.createObjectURL(blob);
  try {
    const img = new Image(); img.src = url; await img.decode();
    const scale = Math.min(1, 2400 / Math.max(img.naturalWidth, img.naturalHeight));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(img.naturalWidth * scale));
    canvas.height = Math.max(1, Math.round(img.naturalHeight * scale));
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
    const result = canvas.toDataURL('image/jpeg', 0.94);
    canvas.width = canvas.height = 0;
    return result;
  } finally { URL.revokeObjectURL(url); }
}

export async function prepareReportAttachments(attachments = []) {
  const pages = [];
  for (const item of attachments || []) {
    if (pages.length >= MAX_PAGES) throw new Error(`مجموع صفحات المرفقات لا يتجاوز ${MAX_PAGES}.`);
    const url = reportAttachmentUrl(item.file);
    // Only the user-entered display name belongs in the printed report.
    const name = typeof item.name === 'string' ? item.name.trim() : '';
    const label = name ? `المرفق «${name}»` : 'المرفق';
    if (!url) throw new Error(`رابط المرفق غير صالح: ${name}`);
    try {
      const response = await fetch(url, {credentials: 'omit', signal: AbortSignal.timeout(30000)});
      if (!response.ok) throw new ReportAttachmentError(`تعذر تحميل ${label} من الخادم. حاول مجدداً.`);
      if (Number(response.headers.get('Content-Length')) > MAX_BYTES) throw new ReportAttachmentError('حجم المرفق يتجاوز 10 ميغابايت.');
      const blob = await response.blob();
      if (blob.size > MAX_BYTES) throw new ReportAttachmentError('حجم المرفق يتجاوز 10 ميغابايت.');
      const mime = await validateReportAttachment(blob, name);
      if (mime !== 'application/pdf') pages.push({name, url, src: await imageData(new Blob([blob], {type: mime})), kind: 'image'});
      else {
        const pdfjs = await import('pdfjs-dist/legacy/build/pdf.mjs');
        // Bundle the worker into a normal JS chunk. Shared hosting can reject
        // separate .mjs assets or serve them with a non-JavaScript MIME type.
        const {default: InlinePdfWorker} = await import('pdfjs-dist/legacy/build/pdf.worker.min.mjs?worker&inline');
        const port = new InlinePdfWorker();
        let worker, task;
        try {
          worker = new pdfjs.PDFWorker({port});
          task = pdfjs.getDocument({worker, data: new Uint8Array(await blob.arrayBuffer()), isEvalSupported: false, useSystemFonts: true, stopAtErrors: true, maxImageSize: 16000000});
          const document = await task.promise;
          if (pages.length + document.numPages > MAX_PAGES) throw new ReportAttachmentError(`مجموع صفحات المرفقات لا يتجاوز ${MAX_PAGES}.`);
          for (let i = 1; i <= document.numPages; i++) {
            const page = await document.getPage(i);
            const base = page.getViewport({scale: 1});
            const viewport = page.getViewport({scale: Math.min(2, 2200 / Math.max(base.width, base.height))});
            const canvas = window.document.createElement('canvas');
            canvas.width = Math.ceil(viewport.width); canvas.height = Math.ceil(viewport.height);
            await page.render({canvasContext: canvas.getContext('2d'), viewport, background: '#fff'}).promise;
            pages.push({name, url, src: canvas.toDataURL('image/jpeg', 0.94), kind: 'pdf', page: i, total: document.numPages});
            canvas.width = canvas.height = 0;
            page.cleanup();
          }
        } finally {
          try { await task?.destroy(); }
          finally { worker?.destroy(); port.terminate(); }
        }
      }
    } catch (error) {
      if (error instanceof ReportAttachmentError) throw error;
      if (error.name === 'PasswordException') throw new Error(`ملف ${label} يطلب كلمة مرور لفتحه. أرفق نسخة يمكن فتحها مباشرة.`, {cause: error});
      if (error.name === 'InvalidPDFException') throw new Error(`تعذر قراءة ملف PDF في ${label}. جرّب فتح الملف أو تصديره مجدداً.`, {cause: error});
      throw new Error(`تعذر تجهيز ${label} للطباعة. أعد تحميل الصفحة وحاول مجدداً.`, {cause: error});
    }
  }
  return pages;
}
