const labels = [
  'لم يُنفّذ إجراء', 'تمت الطباعة', 'تم الحفظ', 'تمت الطباعة والحفظ',
  'تم الإرسال', 'تمت الطباعة والإرسال', 'تم الحفظ والإرسال', 'تمت الطباعة والحفظ والإرسال',
];

export function reportActionStatus(record = {}) {
  const status = record.report_status || {};
  const printed = status.printed === true;
  const saved = status.saved === true;
  const sent = status.sent === true || record.sent_to_patient === true || record.sent_to_patient === 1;
  const mask = Number(printed) + 2 * Number(saved) + 4 * Number(sent);
  const time = value => value ? new Date(value).toLocaleString('ar-IQ', { timeZone: 'Asia/Baghdad' }) : 'سجل سابق';
  const details = [];
  if (printed) details.push(`الطباعة: ${time(status.printed_at)}`);
  if (saved) details.push(`حفظ PDF: ${time(status.saved_at)}`);
  if (sent) details.push(`الإرسال: ${time(status.sent_at)}`);
  if (printed) details.push('الطباعة تسجّل فتح نافذة الطباعة؛ المتصفح لا يؤكد خروج الورق.');
  if (saved) details.push('الحفظ يعني بدء تنزيل ملف PDF.');
  if (sent) details.push('الإرسال يسجّل فتح واتساب؛ لا يؤكد وصول الرسالة.');
  return {
    label: labels[mask],
    tone: sent ? 'sent' : printed && saved ? 'combined' : printed ? 'printed' : saved ? 'saved' : 'pending',
    detail: details.join('\n') || 'لم تُسجّل طباعة أو حفظ أو إرسال للتقرير.',
  };
}
