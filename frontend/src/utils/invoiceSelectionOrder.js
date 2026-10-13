// Invoice-level order keeps different item types interleaved without changing
// catalog membership, result values, or the original arrays used by editors.
export const invoiceFields = ['tests', 'cultures', 'test_groups', 'packages'];
const foreignKeys = { tests: 'test_id_fk', cultures: 'culture_id_fk', test_groups: 'test_group_id_fk', packages: 'package_id_fk' };
export const invoiceItemId = (field, item) => String(item?.[foreignKeys[field]] ?? item?.id ?? '');
export function invoiceItemRank(record, field, item) {
  const index = (record?.selection_order || []).findIndex(entry => entry.field === field && String(entry.id) === invoiceItemId(field, item));
  return index < 0 ? Number.MAX_SAFE_INTEGER : index;
}
export function orderedInvoiceItems(record = {}) {
  return invoiceFields.flatMap(field => (record[field] || []).map((item, index) => ({ field, item, index })))
    .sort((a, b) => invoiceItemRank(record, a.field, a.item) - invoiceItemRank(record, b.field, b.item));
}
export function restoreSelectionPositions(record) {
  orderedInvoiceItems(record).forEach(({ item }, index) => { item.selection_position = index; });
}
export function selectedInvoiceItems(record) {
  return invoiceFields.flatMap(field => (record[field] || []).map((item, index) => ({ field, item, index })))
    .sort((a, b) => (a.item.selection_position ?? Number.MAX_SAFE_INTEGER) - (b.item.selection_position ?? Number.MAX_SAFE_INTEGER));
}
export function selectionOrder(record) {
  return selectedInvoiceItems(record).map(({ field, item }) => ({ field, id: Number(invoiceItemId(field, item)) }));
}
export function nextSelectionPosition(record) {
  return Math.max(-1, ...invoiceFields.flatMap(field => (record[field] || []).map(item => item.selection_position ?? -1))) + 1;
}
