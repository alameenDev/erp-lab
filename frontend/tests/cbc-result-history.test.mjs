import test from 'node:test';
import assert from 'node:assert/strict';
import { cbcHistoryEnabled, cbcHistoryLayout, cbcPreviousResult, cbcSeparateHistory, cbcHistoryHtml } from '../src/utils/cbcResultHistory.js';
const item = { id: 7, test_id_fk: 42, name: 'CBC', content: { bridge_cbc: true }, last_result: true };
const row = (field, value, invoice_id = 8, extra = {}) => ({ field, value, invoice_id, date: '2026-10-01', unit: 'old-unit', is_component: true, ...extra });
const record = { id: 10, result_history: { test_42: [row('HGB', '12.30'), row('WBC', '6.10'), row('WBC', 0, 9, { date: '2026-10-02' }), row('WBC', 'CURRENT', 10), row('WBC', '', 9), row('WBC', 'null', 9)], test_7: [row('WBC', 'WRONG ID')] } };
test('CBC visibility inherits only when unset, with explicit persisted true/false taking priority', () => {
  for (const last_result of [true, 1, '1', 'true']) assert.equal(cbcHistoryEnabled({ ...item, last_result }, { show_last_result: false }), true);
  for (const last_result of [false, 0, '0', 'false']) assert.equal(cbcHistoryEnabled({ ...item, last_result }, { show_last_result: true }), false);
  assert.equal(cbcHistoryEnabled({ ...item, last_result: null }, { show_last_result: true }), true);
  assert.equal(cbcHistoryEnabled({ ...item, last_result: null }), false);
  assert.equal(cbcHistoryEnabled({ ...item, content: {} }, { show_last_result: true }), false);
  assert.equal(cbcHistoryLayout({ print_table_config: { cbc_previous_results_layout: 'separate_page' } }), 'separate_page');
  assert.equal(cbcHistoryLayout(), 'inline');
});
test('latest per-component value uses saved identity, exact component names and recorded precision/units', () => {
  const original = JSON.stringify(record);
  assert.equal(cbcPreviousResult(record, item, ' wbc ').value, 0);
  assert.equal(cbcPreviousResult(record, item, 'HGB').value, '12.30');
  assert.equal(cbcPreviousResult(record, item, 'HGB').unit, 'old-unit');
  assert.equal(cbcPreviousResult(record, item, 'Hemoglobin'), null);
  assert.equal(cbcPreviousResult(record, { ...item, last_result: false }, 'WBC'), null);
  assert.equal(cbcPreviousResult(record, { ...item, test_id_fk: 99 }, 'WBC'), null);
  assert.equal(JSON.stringify(record), original);
});
test('ambiguous values from the same prior visit are never silently selected', () => {
  const history = { result_history: { test_42: [row('WBC', '6', 8, { item_id: 1 }), row('WBC', '7', 8, { item_id: 2 })] } };
  assert.deepEqual(cbcPreviousResult(history, item, 'WBC'), { ambiguous: true });
  history.result_history.test_42[1] = { ...history.result_history.test_42[0] };
  assert.equal(cbcPreviousResult(history, item, 'WBC').value, '6');
});
test('separate history counts complete prior component examinations and skips empty/scalar/current rows', () => {
  const settings = { show_last_result: false, previous_results_count: 2 };
  const source = structuredClone(record);
  source.result_history.test_42.push(row('CBC', 'scalar', 99, { date: '2026-10-03', is_component: false }));
  assert.deepEqual(cbcSeparateHistory(source, item, settings).map(r => r.value), [0, '12.30', '6.10']);
  assert.deepEqual(cbcSeparateHistory(source, item, { ...settings, previous_results_count: 1 }).map(r => r.value), [0]);
  assert.deepEqual(cbcSeparateHistory(source, { ...item, last_result: false }, settings), []);
});
test('ordinary templates, hidden CBC and separate-page mode leave current HTML untouched', () => {
  const html = '<table>current results</table>';
  for (const [target, settings] of [[{ ...item, content: {} }, {}], [{ ...item, last_result: false }, {}], [item, { print_table_config: { cbc_previous_results_layout: 'separate_page' } }]]) {
    assert.equal(cbcHistoryHtml(html, record, target, settings), html);
  }
});
