export const eventLabels = { created: 'إنشاء', updated: 'تعديل', deleted: 'حذف', failed: 'فشل', action: 'إجراء', clicked: 'نقرة زر', login: 'تسجيل دخول' };
export const sourceLabels = { server: 'النظام', device: 'جهاز مختبري', interface: 'الواجهة', legacy: 'سجل سابق' };
export const statusLabels = { success: 'تم التنفيذ', failed: 'فشل الطلب', clicked: 'تم الضغط فقط', legacy: 'سجل سابق' };
const fields = {
  invoice: 'الفاتورة', patient: 'المريض', analyses: 'التحاليل', payments: 'التسديدات', id: 'الرقم', name: 'الاسم',
  result: 'النتيجة', value: 'القيمة', unit: 'Unit', normal_range: 'Reference range', reference_range: 'Reference range',
  sub_tests: 'الفحوصات الفرعية', package_tests: 'تحاليل الباقة', package_cultures: 'زروع الباقة', test_group_tests: 'تحاليل الكروب', test_group_cultures: 'زروع الكروب',
  sub_total: 'المجموع قبل الخصم', total: 'الإجمالي', paid: 'المدفوع', amount: 'مبلغ التسديد', price: 'السعر', discount: 'الخصم',
  is_done: 'اكتمال النتائج', is_signed: 'توقيع التقرير', signed_by_id_fk: 'رقم الموقّع', sent_to_patient: 'بدء إرسال النتيجة',
  is_printed: 'حالة الطباعة', public_with_background: 'فورمة المختبر', notes: 'الملاحظات', comment: 'التعليق',
  result_status_id_fk: 'حالة النتيجة', result_status_text: 'وصف حالة النتيجة', registration_date: 'تاريخ التسجيل', result_date: 'تاريخ النتيجة',
  patient_id_fk: 'رقم المريض', lab_id_fk: 'رقم المختبر', referral_id_fk: 'رقم الإحالة', invoice_id_fk: 'رقم الفاتورة', barcode: 'الباركود',
  payment_method_id_fk: 'طريقة الدفع', attachments: 'المرفقات', tests_comment: 'تعليق التحاليل', cultures_comment: 'تعليق الزروع', packages_comment: 'تعليق الباقات',
  phone: 'الهاتف', email: 'البريد', address: 'العنوان', age: 'العمر', password: 'كلمة المرور', api_token: 'مفتاح الربط',
  test_id_fk: 'رقم التحليل', culture_id_fk: 'رقم الزرع', package_id_fk: 'رقم الباقة', test_group_id_fk: 'رقم الكروب',
  deleted_at: 'وقت الحذف', content: 'محتوى النتيجة', questions: 'إجابات الأسئلة', role_id: 'الدور', permissions: 'الصلاحيات',
};
export function fieldLabel(label = '') {
  return label.split(' / ').map(part => {
    const analysis = part.match(/^(test_group|test|culture|package)_\d+_\d+(.*)$/);
    if (analysis) return ({ test_group: 'كروب', test: 'تحليل', culture: 'زرع', package: 'باقة' }[analysis[1]]) + analysis[2];
    const match = part.match(/^([^ (]+)(.*)$/);
    if (!match) return part;
    return (fields[match[1]] || match[1]) + match[2];
  }).join(' / ');
}
export function auditValue(value) {
  if (value === null || value === undefined) return '—';
  if (value === '') return '(فارغ)';
  if (typeof value === 'boolean') return value ? 'نعم' : 'لا';
  if (typeof value === 'object') return JSON.stringify(value, null, 2);
  return String(value);
}
