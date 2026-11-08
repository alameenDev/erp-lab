// Synthetic reproduction of four ordinary analyses interrupted by a CBC.
// Deliberately omit categories: this must work for existing uncategorized data.
export function withInterleavedCbc(base) {
  const test = (id, name, extra = {}) => ({ id, test_id_fk: id, name, result: '4', unit: 'unit',
    is_done: true, result_status_id_fk: 2, test_reference_ranges: [], ...extra });
  const cell = (tag, text) => `<${tag} style="padding:1px 4px;font-size:11px;line-height:14px">${text}</${tag}>`;
  const html = '<h2>Complete Blood Count (CBC)</h2><table><thead><tr>' + ['Test', 'Result', 'Unit'].map(t => cell('th', t)).join('') + '</tr></thead><tbody>'
    + Array.from({ length: 22 }, (_, i) => '<tr>' + ['CBC field ' + (i + 1), '4', 'unit'].map(t => cell('td', t)).join('') + '</tr>').join('') + '</tbody></table>';
  return { ...base, tests: [test(1, 'RBS'), test(2, 'HbA1c'), test(3, 'Creatinine'),
    test(4, 'CBC', { sub_tests: [{ name: 'Synthetic field', value: '4' }], content: { html } }),
    test(5, 'S.GPT (ALT)')], test_groups: [], packages: [], cultures: [], result_history: {},
    selection_order: [1, 2, 3, 4, 5].map(id => ({ field: 'tests', id })) };
}
