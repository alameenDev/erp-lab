import { test } from 'node:test';
import assert from 'node:assert/strict';
import { reportSelectionModel, reportNodeKeys, selectReportItems, searchReportNodes } from '../src/utils/reportItemSelection.js';
import { reportFormulaValue } from '../src/utils/reportFormulas.js';
import { selectionSample } from './report-selection-data.mjs';

test('all ready/pending items default to selected, including group/package members and compound tests', () => {
  const record = selectionSample(), model = reportSelectionModel(record);
  assert.equal(new Set(model.keys).size, 14);
  const all = selectReportItems(record, { selectedKeys: model.keys });
  assert.equal(all.tests.length, 3); assert.equal(all.tests[1].is_done, false);
  assert.equal(all.test_groups[0].tests[1].is_done, false);
  assert.equal(all.packages[0].tests.length, 4, 'flattened result and catalog membership are not duplicated');
  assert.equal(all.packages[0].tests[1].result, '3', 'entered result wins over catalog data');
  assert.equal(all.suppress_report_qr, false);
});
test('selected calculated rows keep full operands without printing hidden input or other results', () => {
  const record = selectionSample(), before = JSON.stringify(record);
  const selected = selectReportItems(record, { selectedKeys: ['test_groups:1/tests:1','packages:0/tests:2'] });
  assert.deepEqual(selected.tests, []); assert.deepEqual(selected.cultures, []);
  assert.deepEqual(selected.test_groups[0].tests.map(t=>t.name), ['Calculated']);
  assert.equal(reportFormulaValue(selected.test_groups[0], selected.test_groups[0].formula[0]), 8);
  assert.deepEqual(selected.packages[0].tests.map(t=>t.name), ['Nested calculated']);
  assert.equal(reportFormulaValue(selected.packages[0], selected.packages[0].test_groups[0].formula[0]), 6);
  assert.equal(selected.suppress_report_qr, false); assert.equal(JSON.stringify(record), before);
});
test('unselected formula targets cannot reappear as formula rows', () => {
  const model = reportSelectionModel(selectionSample());
  const selected = selectReportItems(selectionSample(), { selectedKeys: model.keys.filter(k=> !['test_groups:1/tests:1','packages:0/tests:2'].includes(k)) });
  assert.deepEqual(selected.test_groups[1].formula, []);
  assert.deepEqual(selected.packages[0].test_groups[0].formula, []);
});
test('whole package/group selection uses independent occurrence paths', () => {
  const record = selectionSample(); record.tests.push({ ...record.test_groups[0].tests[0] });
  const model = reportSelectionModel(record), group = model.sections[2].nodes[0];
  const selected = selectReportItems(record, { selectedKeys: model.keys.filter(k=>!reportNodeKeys(group).includes(k) && !k.startsWith('packages:')) });
  assert.equal(selected.test_groups.length, 1); assert.deepEqual(selected.packages, []);
  assert.equal(selected.tests.at(-1).name, 'Group ready', 'same analysis elsewhere remains independently selectable');
});
test('nested-only package data is normalized and searched without losing hidden selections', () => {
  const record = selectionSample(); record.packages[0].tests = [];
  const model = reportSelectionModel(record); const nodes = searchReportNodes(model.sections[3].nodes, 'input');
  assert.equal(nodes.length, 1); assert.equal(nodes[0].visibleChildren[0].name, 'Nested group');
  assert.equal(model.normalized.packages[0].tests.length, 2);
  assert.deepEqual(reportNodeKeys(nodes[0]), reportNodeKeys(model.sections[3].nodes[0]));
});
test('empty selection never falls back to all and legacy top-level callers still work', () => {
  const record = selectionSample(), empty = selectReportItems(record, { selectedKeys: [] });
  for (const field of ['tests','cultures','test_groups','packages']) assert.deepEqual(empty[field], []);
  const legacy = selectReportItems(record, { tests: [1], cultures: [], testGroups: [0], packages: [] });
  assert.equal(legacy.tests[0].name, 'Standalone pending'); assert.equal(legacy.test_groups[0].tests.length, 2);
});
test('formula-only definitions become selectable output rows and empty containers remain toggleable', () => {
  const record = { test_groups: [{ group_name: 'Formula', tests: [{ name: 'A', result: 0, is_done: true }], formula: [{ name: 'B', tokens: ['A','+','1'] }] }, { group_name: 'Empty' }] };
  const model = reportSelectionModel(record); assert.equal(model.keys.length, 3);
  const result = selectReportItems(record, { selectedKeys: ['test_groups:0/tests:1'] });
  assert.equal(result.test_groups[0].tests[0].name, 'B'); assert.equal(reportFormulaValue(result.test_groups[0],result.test_groups[0].formula[0]), 1);
});
