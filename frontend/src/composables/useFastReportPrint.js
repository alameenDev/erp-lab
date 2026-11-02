import { onScopeDispose } from 'vue';
import { printFastMedicalReportPages } from '../utils/fastMedicalReportPrint.js';

export function useFastReportPrint() {
  const controller = new AbortController();
  onScopeDispose(() => controller.abort());
  return async ({ output, record, store }) => {
    await printFastMedicalReportPages(output?.pages, { signal: controller.signal });
    // Reuse the existing print status/audit action; never mark a PDF as saved.
    try {
      await store.recordReportAction(record.id, 'print', output.withBackground);
      return { statusError: null };
    } catch (statusError) { return { statusError }; }
  };
}
