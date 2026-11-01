/**
 * Existing browser regressions test their own workflows, not the reason modal.
 * Complete the NEW mandatory reason dialog in those fixtures; the dedicated
 * mutation-reason.browser.mjs tests blank/cancel/submit behavior independently.
 */
export async function autoConfirmMutationReasons(page) {
  await page.addInitScript(() => {
    const handled = new WeakSet();
    const reason = 'تصحيح تجريبي موثق أثناء اختبار النظام';
    const flush = () => {
      for (const dialog of document.querySelectorAll('dialog[data-mutation-reason-dialog]')) {
        if (!dialog.open || handled.has(dialog)) continue;
        handled.add(dialog);
        const textarea = dialog.querySelector('[data-mutation-reason-input]');
        const form = textarea?.closest('form');
        if (!textarea || !form) continue;
        textarea.value = reason;
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        form.requestSubmit();
      }
    };
    new MutationObserver(flush).observe(document, { childList:true, subtree:true, attributes:true, attributeFilter:['open'] });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', flush, {once:true});
    else flush();
  });
}
