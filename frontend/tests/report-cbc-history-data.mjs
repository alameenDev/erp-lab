// Synthetic fixtures only. The table structure matches BridgeCbcService::table.
const copy = value => JSON.parse(JSON.stringify(value));
export function cbcTest(id, name = 'CBC', fields = ['WBC', 'HGB', 'RBC', 'PLT']) {
  const sub_tests = fields.map((name, i) => ({ name, value: String(5 + i), unit: 'current-unit', reference_range: '4 - 10' }));
  const html = '<h3 style="font-size:18px;font-weight:700;margin-bottom:12px">Complete Blood Count (CBC)</h3><table dir="ltr" style="width:100%;border-collapse:collapse;text-align:left"><thead><tr>'
    + ['Test', 'Result', 'Unit', 'Reference Range'].map(h => `<th style="border:1px solid #cbd5e1;padding:8px">${h}</th>`).join('')
    + '</tr></thead><tbody>' + sub_tests.map(row => '<tr>' + ['name', 'value', 'unit', 'reference_range'].map(key => `<td style="border:1px solid #cbd5e1;padding:8px">${key === 'value' ? `<span style="color:#dc2626">${row[key]}</span>` : row[key]}</td>`).join('') + '</tr>').join('') + '</tbody></table>';
  return { id, test_id_fk: id, name, report_name: name, is_done: true, last_result: false, is_special_test: true, sub_tests, content: { html, bridge_cbc: true, device_result_id: id, review_required: false } };
}
export function cbcHistory(tests) {
  return Object.fromEntries(tests.map(test => [`test_${test.test_id_fk}`, [1, 2].flatMap(visit => [...test.sub_tests].reverse().filter(row => row.name !== 'PLT').map(row => ({
    invoice_id: 8 - visit, item_id: test.id, date: `2026-09-${30 - visit}`, date_source: 'result', field: row.name, value: `OLD-${test.id}-${row.name}-${visit}`, unit: 'previous-unit', is_component: true,
  })))]));
}
export function applyCbcHistoryFixture(sample) {
  const standalone = cbcTest(3), group = cbcTest(10, 'Group CBC'), pkg = cbcTest(11, 'Package CBC');
  sample.tests = [standalone];
  sample.test_groups = [{ test_group_id_fk: 4, group_name: 'Haematology', tests: [group], cultures: [] }];
  sample.packages = [{ package_id_fk: 6, name: 'Package', tests: [pkg], cultures: [], test_groups: [] }];
  sample.cultures = []; sample.tests_comment = '';
  sample.result_history = cbcHistory([standalone, group, pkg]);
  return copy(sample);
}
