// Portal URLs are bearer credentials. Only authenticated staff may create one;
// a public report may reuse a supplied token after checking invoice membership.
export const isPatientPortalToken = value => typeof value === 'string' && /^[A-Za-z0-9]{48}$/.test(value);

export async function reportPatientPortalUrl({ record, route = {}, authenticated, http, appBaseUrl }) {
  if (record?.suppress_report_qr) return '';
  const token = new URLSearchParams((route.hash || '').slice(1)).get('portal');
  if (token) {
    if (!isPatientPortalToken(token)) throw new Error('رابط بوابة المريض غير صالح. افتح التقرير من البوابة مجدداً.');
    const { data } = await http.get(`/portal/${token}`);
    if (data.requires_otp || !data.reports?.some(report => String(report.id) === String(record?.id))) {
      throw new Error('تعذر التحقق من بوابة هذا المريض. افتح التقرير من البوابة مجدداً.');
    }
    return `${appBaseUrl.replace(/\/$/, '')}/portal/${token}`;
  }
  // A guessable, public /result/{invoiceId} must never grant portal access.
  if (!authenticated) return '';
  const patientId = record?.patient?.id || record?.patient_id_fk;
  if (!patientId) throw new Error('لا يوجد مريض مرتبط بالتقرير.');
  const { data } = await http.post('/portal/generate', { patient_id: patientId });
  let url;
  try { url = new URL(data?.url); } catch { /* handled below */ }
  if (!url || !['https:', 'http:'].includes(url.protocol) || url.username || url.password ||
      !/^\/portal\/[A-Za-z0-9]{48}$/.test(url.pathname)) {
    throw new Error('تعذر تجهيز رابط بوابة المريض. أعد المحاولة قبل طباعة التقرير.');
  }
  return url.href;
}
