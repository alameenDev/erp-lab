const MAX_PAGES = 60;
const MAX_BYTES = 10 * 1024 * 1024;

// Dimensions are attached to the result, not the browser or export action.
// Missing metadata on older reports uses a larger, legible default.
export const attachmentPrintLayout = (item = {}) => {
  const dimension = (value, min, max, fallback) => {
    const n = Number(value);
    return Number.isInteger(n) && n >= min && n <= max ? n : fallback;
  };
  return {
    print_width_percent: dimension(item.print_width_percent, 40, 100, 100),
    print_max_height_mm: dimension(item.print_max_height_mm, 100, 240, 210),
    print_auto_crop: item.print_auto_crop !== false && item.print_auto_crop !== 0 && item.print_auto_crop !== '0',
  };
};

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

// Find the actual coloured/printed area of a PDF scan. Light paper texture
// and antialiasing are ignored; the result is padded to protect small marks.
// The 2.5x limit prevents a nearly empty page from becoming absurdly enlarged.
export function attachmentContentBounds(data, width, height) {
  if (!data || width <= 0 || height <= 0) return null;
  const step = Math.max(1, Math.ceil(Math.max(width, height) / 1300));
  let left = width, right = -1, top = height, bottom = -1, found = 0;
  for (let y = 0; y < height; y += step) {
    for (let x = 0; x < width; x += step) {
      const i = (y * width + x) * 4;
      const r = data[i], g = data[i + 1], b = data[i + 2];
      // Do not include near-white scanned paper in the content box.
      if (Math.min(r, g, b) >= 215 && (r + g + b) >= 675) continue;
      left = Math.min(left, x);
      right = Math.max(right, x);
      top = Math.min(top, y);
      bottom = Math.max(bottom, y);
      found++;
    }
  }
  if (found < 12) return null;
  const padding = Math.ceil(Math.min(width, height) * 0.025);
  left = Math.max(0, left - padding);
  right = Math.min(width, right + step + padding);
  top = Math.max(0, top - padding);
  bottom = Math.min(height, bottom + step + padding);
  // Limit enlargement while keeping every detected mark within the crop.
  const expand = (start, end, max, minimum) => {
    const size = Math.max(end - start, Math.ceil(max / 2.5), minimum);
    const origin = Math.max(0, Math.min(max - size, Math.round((start + end - size) / 2)));
    return [origin, Math.min(max, origin + size)];
  };
  [left, right] = expand(left, right, width, 1);
  [top, bottom] = expand(top, bottom, height, 1);
  if (right - left > width * .97 && bottom - top > height * .97) return null;
  return { x: left, y: top, width: right - left, height: bottom - top };
}

function trimPdfWhitespace(canvas) {
  const ctx = canvas.getContext('2d', {willReadFrequently: true});
  const rgba = ctx.getImageData(0, 0, canvas.width, canvas.height);
  const bounds = attachmentContentBounds(rgba.data, canvas.width, canvas.height);
  if (!bounds) return canvas;
  const trimmed = document.createElement('canvas');
  trimmed.width = bounds.width;
  trimmed.height = bounds.height;
  trimmed.getContext('2d').drawImage(canvas,
    bounds.x, bounds.y, bounds.width, bounds.height,
    0, 0, bounds.width, bounds.height);
  return trimmed;
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
    const layout = attachmentPrintLayout(item);
    if (!url) throw new Error(`رابط المرفق غير صالح: ${name}`);
    try {
      const response = await fetch(url, {credentials: 'omit', signal: AbortSignal.timeout(30000)});
      if (!response.ok) throw new ReportAttachmentError(`تعذر تحميل ${label} من الخادم. حاول مجدداً.`);
      if (Number(response.headers.get('Content-Length')) > MAX_BYTES) throw new ReportAttachmentError('حجم المرفق يتجاوز 10 ميغابايت.');
      const blob = await response.blob();
      if (blob.size > MAX_BYTES) throw new ReportAttachmentError('حجم المرفق يتجاوز 10 ميغابايت.');
      const mime = await validateReportAttachment(blob, name);
      if (mime !== 'application/pdf') pages.push({name, url, src: await imageData(new Blob([blob], {type: mime})), kind: 'image', ...layout});
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
            // Render detailed medical text before trimming whitespace; never
            // enlarge a low-resolution preview after a tiny scan was encoded.
            const viewport = page.getViewport({scale: Math.min(3, 3000 / Math.max(base.width, base.height))});
            const canvas = window.document.createElement('canvas');
            canvas.width = Math.ceil(viewport.width); canvas.height = Math.ceil(viewport.height);
            await page.render({canvasContext: canvas.getContext('2d'), viewport, background: '#fff'}).promise;
            const content = layout.print_auto_crop ? trimPdfWhitespace(canvas) : canvas;
            pages.push({name, url, src: content.toDataURL('image/jpeg', 0.96), kind: 'pdf', page: i, total: document.numPages, ...layout, cropped: content !== canvas});
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
