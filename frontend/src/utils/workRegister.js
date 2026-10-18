export const ageUnits = {Years: 'سنة', Months: 'شهر', Days: 'يوم', Hours: 'ساعة'};
export const registerAmount = value => Number(value || 0).toLocaleString('en-US', {maximumFractionDigits: 0});
export const registerEntryAmount = value => value == null ? 'غير مدخل' : registerAmount(value);
export const registerAge = row => row.age == null ? '—' : `${row.age} ${ageUnits[row.age_unit] || row.age_unit || ''}`;
const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[char]));

export function workRegisterPrint(report) {
  const items = row => [['تحاليل', row.tests], ['كروبات', row.groups], ['باقات', row.packages]]
    .filter(([, names]) => names?.length).map(([label, names]) => `<div><b>${label}:</b> ${names.map(escape).join('، ')}</div>`).join('') || '—';
  const referrals = row => [row.referral_doctor && `طبيب: ${escape(row.referral_doctor)}`, row.referral_lab && `مختبر: ${escape(row.referral_lab)}`].filter(Boolean).join('<br>') || 'مراجعة مباشرة';
  return {
    content: `<main dir="rtl" lang="ar"><h1>سجل العمل اليومي</h1><p class="heading">${escape(report.lab_name)} · ${escape(report.date)} · توقيت بغداد</p><p>عدد الزيارات: ${report.totals.count} · المبالغ بالدينار العراقي</p><table><colgroup><col style="width:5%"><col style="width:22%"><col style="width:8%"><col style="width:15%"><col style="width:35%"><col style="width:15%"></colgroup><thead><tr><th>ت</th><th>اسم المريض الكامل</th><th>العمر</th><th>الإحالة</th><th>التحاليل والكروبات والباقات</th><th>المدفوع في السجل</th></tr></thead><tbody>${report.entries.map(row => `<tr data-register-print-row><td>${row.position}</td><td>${escape(row.patient_name)}</td><td>${escape(registerAge(row))}</td><td>${referrals(row)}</td><td class="items">${items(row)}</td><td class="amount">${registerEntryAmount(row.work_register_amount)}</td></tr>`).join('')}</tbody><tfoot><tr><th colspan="5">المجموع الكلي — ${report.totals.count} زيارة</th><th class="amount">${registerAmount(report.totals.work_register_amount)}</th></tr></tfoot></table><p class="note">المبالغ مدخلة يدوياً لسجل العمل فقط، ومستقلة عن مبالغ الفواتير والتقارير المالية.</p></main>`,
    css: `@page{size:A4 landscape;margin:12mm}*{box-sizing:border-box}body{font:13px Arial,sans-serif;color:#172b40;margin:0}h1{font-size:24px;margin:0 0 6px}.heading{font-size:15px}p{margin:8px 0 14px}table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:12px;line-height:1.7}th,td{border:1px solid #cbd5e1;padding:8px;vertical-align:top;text-align:right;overflow-wrap:anywhere}thead{display:table-header-group}thead th{background:#edf7f6}tfoot{display:table-row-group}tfoot th{background:#f1f5f9}tr{break-inside:avoid}.amount{direction:ltr;text-align:center;white-space:nowrap}.items div+div{margin-top:4px}.note{font-size:11px;color:#526173}body{-webkit-print-color-adjust:exact;print-color-adjust:exact}`,
  };
}
