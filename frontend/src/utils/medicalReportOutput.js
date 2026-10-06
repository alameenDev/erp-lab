// Presentation choices travel with the report, independently of the action.
export function reportFormChoice(value, fallback = true) {
  if (value === false || value === 0 || value === '0') return false;
  if (value === true || value === 1 || value === '1') return true;
  return fallback;
}

export function reportFormFromRoute(route) {
  return route.query?.form ?? new URLSearchParams((route.hash || '').slice(1)).get('form');
}

export function reportUrlWithForm(value, withBackground) {
  const url = new URL(value, typeof window === 'undefined' ? 'https://example.test' : window.location.origin);
  // Signed referral URLs must retain their exact query; presentation is local
  // to the frontend and belongs in the fragment for these links.
  if (url.searchParams.has('signature')) {
    const hash = new URLSearchParams(url.hash.slice(1));
    hash.set('form', withBackground ? '1' : '0');
    url.hash = hash.toString();
  } else url.searchParams.set('form', withBackground ? '1' : '0');
  return url.href;
}

export function portalReportUrl(value, invoiceId, query = {}, token = '') {
  let result = value;
  if (String(query.report) === String(invoiceId) && ['0', '1'].includes(query.form)) {
    result = reportUrlWithForm(value, query.form === '1');
  }
  if (!/^[A-Za-z0-9]{48}$/.test(token)) return result;
  const base = typeof window === 'undefined' ? 'https://example.test' : window.location.origin;
  const url = new URL(result, base);
  // Carry the existing credential only to our own report viewer. Fragments
  // stay out of HTTP requests and Referer headers.
  if (url.origin !== new URL(base).origin || url.pathname !== `/result/${invoiceId}`) return result;
  const hash = new URLSearchParams(url.hash.slice(1));
  hash.set('portal', token);
  url.hash = hash.toString();
  return url.href;
}

export function medicalReportFilename(record) {
  const name = (record?.patient?.name || 'patient').replace(/[^\p{L}\p{N}\s_-]/gu, '');
  return `${name}-${record?.barcode || ''}.pdf`;
}

export async function assertPdfBlob(blob) {
  if (!(blob instanceof Blob) || await blob.slice(0, 5).text() !== '%PDF-') {
    throw new Error('ملف التقرير غير صالح. أعد تجهيز التقرير ثم حاول مجدداً.');
  }
  return blob;
}

export function downloadMedicalReportFile({ blob, filename }) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url; link.download = filename;
  document.body.appendChild(link); link.click(); link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 120000);
}
