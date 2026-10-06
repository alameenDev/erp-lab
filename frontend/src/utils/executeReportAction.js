import { assertPdfBlob, downloadMedicalReportFile } from '@/utils/medicalReportOutput';
import { printMedicalReportPages } from '@/utils/medicalReportPages';
import { sendMedicalReportWhatsApp } from '@/utils/sharePatientPortal';

export const reportActionStatusError = 'تم تنفيذ الإجراء، لكن تعذر حفظ حالته. حدّث الصفحة للتحقق، ثم أعد المحاولة عند الحاجة.';

// Shared by the report list and result editor. A selection/preview is not an action.
export async function executeReportAction({ action, output, record, settings, printTab, whatsappTab, store }) {
  await assertPdfBlob(output?.blob);
  if (['whatsapp', 'whatsapp-download'].includes(action)) {
    return sendMedicalReportWhatsApp({ record, settings, output, tab: whatsappTab,
      markSent: (id, withBackground) => store.recordReportAction(id, action, withBackground),
      // Preserve a completed save even if the subsequent WhatsApp handoff fails.
      afterDownload: action === 'whatsapp-download'
        ? () => store.recordReportAction(record.id, 'download', output.withBackground).catch(() => {})
        : undefined,
    });
  }
  if (!['print', 'download', 'print-download'].includes(action)) throw new Error('إجراء التقرير غير صالح.');
  if (action !== 'download' && (!printTab || printTab.closed)) throw new Error('اسمح بالنوافذ المنبثقة للطباعة.');
  let downloaded = false;
  try {
    if (action !== 'print') { downloadMedicalReportFile(output); downloaded = true; }
    if (action !== 'download') await printMedicalReportPages(output.pages, printTab);
  } catch (error) {
    if (downloaded) {
      try { await store.recordReportAction(record.id, 'download', output.withBackground); }
      catch { error.message += ' تعذر حفظ حالة التنزيل أيضاً.'; }
    }
    throw error;
  }
  try {
    await store.recordReportAction(record.id, action, output.withBackground);
    return { statusError: null };
  } catch (statusError) { return { statusError }; }
}
