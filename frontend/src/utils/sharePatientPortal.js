import { $http } from '@/plugins/axios';
import { messageTemplate } from '@/utils/labDocuments';

export async function sharePatientPortal(record, settings, fallbackLabName) {
  const patientId = record?.patient?.id || record?.patient_id_fk;
  if (!patientId) throw new Error('لا يوجد مريض مرتبط بهذه الفاتورة.');
  let phone = String(record?.patient?.phone || '').replace(/[\s()+-]/g, '');
  if (!phone) throw new Error('أضف رقم هاتف المريض أولاً.');
  if (phone.startsWith('00')) phone = phone.slice(2);
  if (phone.startsWith('0')) phone = '964' + phone.slice(1);
  if (!phone.startsWith('964')) phone = '964' + phone;
  const tab = window.open('', '_blank');
  try {
    const { data } = await $http.post('/portal/generate', { patient_id: patientId });
    if (!data?.url || !new URL(data.url).pathname.startsWith('/portal/')) throw new Error('تعذر إنشاء رابط بوابة المريض.');
    const values = { lab_name: settings?.lab_display_name || fallbackLabName || 'المختبر', patient_name: record?.patient?.name || 'المريض', invoice_number: record.id, link: data.url, loyalty_points: data.loyalty_points, loyalty_tier: data.loyalty_tier };
    let message = messageTemplate(settings?.whatsapp_result_message, values, `أهلاً ${values.patient_name}\nبوابتك لدى ${values.lab_name} لعرض النتائج والنقاط والمكافآت:\n${data.url}`);
    if (!message.includes(data.url)) message += '\n' + data.url;
    const url = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
    if (tab) tab.location.href = url;
    else if (!window.open(url, '_blank')) throw new Error('اسمح بالنوافذ المنبثقة ثم أعد الإرسال.');
  } catch (error) {
    tab?.close();
    throw new Error(error.response?.data?.message || error.message || 'تعذر إنشاء رابط البوابة؛ لم يتم إرسال رابط بديل.');
  }
}
