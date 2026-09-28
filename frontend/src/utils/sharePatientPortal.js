import { $http } from '@/plugins/axios';
import { messageTemplate } from '@/utils/labDocuments';

export async function prepareMedicalReportWhatsApp(record, settings, fallbackLabName) {
  const patientId = record?.patient?.id || record?.patient_id_fk;
  if (!patientId) throw new Error('لا يوجد مريض مرتبط بالتقرير.');
  let phone = String(record?.patient?.phone || '').replace(/\D/g, '');
  if (phone.startsWith('00')) phone = phone.slice(2);
  if (phone.startsWith('0')) phone = '964' + phone.slice(1);
  if (!phone.startsWith('964')) phone = '964' + phone;
  if (phone.length < 12 || phone.length > 15) throw new Error('رقم هاتف المريض المسجل غير صالح.');

  const { data } = await $http.post('/portal/generate', { patient_id: patientId });
  if (!data?.url || !new URL(data.url).pathname.startsWith('/portal/')) throw new Error('تعذر إنشاء رابط بوابة المريض.');
  const values = {
    lab_name: settings?.lab_display_name || fallbackLabName || 'المختبر',
    patient_name: record?.patient?.name || 'المريض',
    invoice_number: record?.id || '',
    link: data.url,
    loyalty_points: data.loyalty_points,
    loyalty_tier: data.loyalty_tier,
  };
  const fallback = `عزيزي/عزيزتي ${values.patient_name}،\nتقريرك الطبي من ${values.lab_name} جاهز.\nيمكنك الاطلاع على نتائجك وسجل فحوصاتك عبر بوابة المريض:\n${data.url}\nسنرفق لك نسخة PDF من التقرير في هذه المحادثة.\nنتمنى لك دوام الصحة والعافية.`;
  let message = messageTemplate(settings?.whatsapp_result_message, values, fallback);
  if (!message.includes(data.url)) message += '\n' + data.url;
  return { phone, message, whatsappUrl: `https://wa.me/${phone}?text=${encodeURIComponent(message)}` };
}

export async function sharePatientPortal(record, settings, fallbackLabName, kind = "result") {
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
    const template = kind === "invoice" ? settings?.whatsapp_invoice_message : settings?.whatsapp_result_message;
    const welcome = `أهلاً ${values.patient_name}، نرحب بك في ${values.lab_name}.\nتم تسجيل فاتورتك رقم ${record.id}.\nمن بوابتك الخاصة تقدر تتابع نقاطك وفواتيرك والتحاليل قيد الإجراء والنتائج عند جاهزيتها:\n${data.url}\nنتمنى لك دوام الصحة والعافية.`;
    let message = messageTemplate(template, values, kind === "invoice" ? welcome : `أهلاً ${values.patient_name}\nبوابتك لدى ${values.lab_name} لعرض النتائج والنقاط والمكافآت:\n${data.url}`);
    if (!message.includes(data.url)) message += '\n' + data.url;
    const url = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
    if (tab) tab.location.href = url;
    else if (!window.open(url, '_blank')) throw new Error('اسمح بالنوافذ المنبثقة ثم أعد الإرسال.');
  } catch (error) {
    tab?.close();
    throw new Error(error.response?.data?.message || error.message || 'تعذر إنشاء رابط البوابة؛ لم يتم إرسال رابط بديل.');
  }
}
