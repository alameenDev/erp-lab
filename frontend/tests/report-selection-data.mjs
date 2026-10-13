const test = (id, name, result = '4', extra = {}) => ({ id, test_id_fk: id, name, shortcut: name, result, unit: 'mg/dL', is_done: true, test_reference_ranges: [], ...extra });
export const selectionSample = () => ({ id: 14, patient: { id: 1, name: 'Synthetic patient', phone: '07700000000', code: 'TEST', age: 30, age_unit: 'Years', gender: 'Male' }, registration_date: '2026-10-13',
  tests: [test(1, 'Standalone ready'), test(2, 'Standalone pending', '', { is_done: false }), test(3, 'Compound report', '', { sub_tests: [{ name: 'WBC', value: '7' }], content: { html: '<h3>Compound report</h3><p>WBC: 7</p>' } })],
  cultures: [test(40, 'Standalone culture', 'No growth')],
  test_groups: [{ id: 50, group_name: 'Group A', is_done: false, tests: [test(4, 'Group ready'), test(5, 'Group pending', '', { is_done: false })], cultures: [test(41, 'Group culture', 'No growth')] },
    { id: 51, group_name: 'Formula group', tests: [test(6, 'Input'), test(7, 'Calculated', '999')], formula: [{ name: 'Calculated', tokens: ['Input','*','2'] }] }],
  packages: [{ id: 60, name: 'Package A', tests: [test(8, 'Package direct'), test(9, 'Package input', '3', { test_group_id_fk: 70, test_group_name: 'Nested group' }), test(10, 'Nested calculated', '999', { test_group_id_fk: 70, test_group_name: 'Nested group' }), test(11, 'Package pending', '', { is_done: false })], cultures: [test(42, 'Package culture', 'No growth')],
    test_groups: [{ test_group_id_fk: 70, group_name: 'Nested group', tests: [test(9,'Package input','STALE CATALOG'), test(10,'Nested calculated','STALE CATALOG')], formula: [{ name: 'Nested calculated', tokens: ['Package input','*','2'] }] }] }],
  result_history: { test_1: [{ invoice_id: 1, date: '2026-10-01', value: 'EXCLUDED-HISTORY' }] },
});
