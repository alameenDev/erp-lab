// Search common Arabic spelling variants without changing stored referral names.
export function normalizeReferralSearch(value) {
  return String(value ?? '').normalize('NFKD')
    .replace(/\p{M}/gu, '').replace(/ـ/g, '').replace(/[أإآٱ]/g, 'ا')
    .replace(/ى/g, 'ي').replace(/\s+/g, ' ').trim().toLocaleLowerCase();
}

export function referralContains(value, filter) {
  return normalizeReferralSearch(value).includes(normalizeReferralSearch(filter));
}

export function sortInvoiceReferrals(options, locale = 'ar') {
  const collator = new Intl.Collator(locale, { numeric: true, sensitivity: 'base' });
  return [...(options ?? [])].sort((a, b) =>
    (Number(b.invoice_usage_count) || 0) - (Number(a.invoice_usage_count) || 0)
    || collator.compare(a.name ?? '', b.name ?? '')
    || collator.compare(String(a.user_id), String(b.user_id)));
}
