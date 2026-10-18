import QRCode from 'qrcode';

const MAX_PAGES = 60;
const MAX_BYTES = 10 * 1024 * 1024;

export function reportAttachmentUrl(value) {
  try {
    const url = new URL(value, window.location.origin);
    return ['http:', 'https:'].includes(url.protocol) ? url.href : null;
  } catch { return null; }
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
    const name = item.name || item.download_name || 'Attachment';
    if (!url) throw new Error(`رابط المرفق غير صالح: ${name}`);
    const extension = (item.extension || new URL(url).pathname.split('.').pop() || '').toLowerCase();
    const image = /^image\/(jpeg|png|webp|gif)$/.test(item.mime_type || '') || ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(extension);
    const pdf = item.mime_type === 'application/pdf' || extension === 'pdf';
    if (!image && !pdf) {
      pages.push({name, url, qr: await QRCode.toDataURL(url, {width: 180, margin: 1}), kind: 'file'});
      continue;
    }
    try {
      const response = await fetch(url, {credentials: 'omit', signal: AbortSignal.timeout(30000)});
      if (!response.ok || Number(response.headers.get('Content-Length')) > MAX_BYTES) throw new Error('Could not fetch file');
      const blob = await response.blob();
      if (blob.size > MAX_BYTES) throw new Error('File too large');
      if (image) pages.push({name, url, src: await imageData(blob), kind: 'image'});
      else {
        const pdfjs = await import('pdfjs-dist/build/pdf.mjs');
        const {default: workerUrl} = await import('pdfjs-dist/build/pdf.worker.min.mjs?url');
        pdfjs.GlobalWorkerOptions.workerSrc = workerUrl;
        const task = pdfjs.getDocument({data: new Uint8Array(await blob.arrayBuffer()), isEvalSupported: false, useSystemFonts: true, stopAtErrors: true, maxImageSize: 16000000});
        try {
          const document = await task.promise;
          if (pages.length + document.numPages > MAX_PAGES) throw new Error('Too many pages');
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
        } finally { await task.destroy(); }
      }
      if (pages.length > MAX_PAGES) throw new Error('Too many pages');
    } catch (error) {
      throw new Error(`تعذر تجهيز المرفق «${name}» للطباعة. تأكد أن الملف سليم وغير محمي بكلمة مرور، وأن مجموع صفحات المرفقات لا يتجاوز ${MAX_PAGES}.`, {cause: error});
    }
  }
  return pages;
}
