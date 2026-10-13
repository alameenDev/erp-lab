import { test } from 'node:test';
import assert from 'node:assert/strict';
import { orderedInvoiceItems, restoreSelectionPositions, selectedInvoiceItems, selectionOrder, nextSelectionPosition } from '../src/utils/invoiceSelectionOrder.js';
import { selectReportItems } from '../src/utils/reportItemSelection.js';
test('mixed choices survive serialization, removed choices, and appended/re-added items', () => {
  const record = { tests: [{ id: 8, selection_position: 0 }, { id: 2, selection_position: 3 }], packages: [{ id: 8, selection_position: 1 }], test_groups: [{ id: 2, selection_position: 2 }] };
  record.selection_order = selectionOrder(record);
  const saved = JSON.parse(JSON.stringify(record));
  saved.tests.reverse(); // API/catalog array order is not authoritative.
  restoreSelectionPositions(saved);
  assert.deepEqual(selectedInvoiceItems(saved).map(x => x.field+':'+x.item.id), ['tests:8','packages:8','test_groups:2','tests:2']);
  saved.tests = saved.tests.filter(t => t.id !== 8);
  saved.tests.push({ id: 8, selection_position: nextSelectionPosition(saved) });
  saved.selection_order = selectionOrder(saved);
  assert.deepEqual(orderedInvoiceItems(saved).map(x => x.field+':'+x.item.id), ['packages:8','test_groups:2','tests:2','tests:8']);
});
test('partial output keeps saved order and old invoices keep deterministic fallback', () => {
  const record = { tests: [{id:4},{id:3}], packages: [{id:1,tests:[{id:9}],cultures:[]}], selection_order: [{field:'packages',id:1},{field:'tests',id:3},{field:'tests',id:4}] };
  const filtered = {...record, ...selectReportItems(record, {selectedKeys:['tests:1','packages:0/tests:0']})};
  assert.deepEqual(orderedInvoiceItems(filtered).map(x=>x.item.id), [1,3]);
  assert.deepEqual(orderedInvoiceItems({tests:[{id:4},{id:3}]}).map(x=>x.item.id), [4,3]);
  assert.deepEqual(orderedInvoiceItems(record).map(x=>x.item.id), [1,3,4]);
});
