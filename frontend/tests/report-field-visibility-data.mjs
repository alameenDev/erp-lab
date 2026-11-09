export function applyFieldVisibilityFixture(sample) {
  sample.result_history = {};
  const configure = (item, unit, range) => {
    item.show_unit = unit; item.show_reference_range = range;
    item.unit = `${unit ? 'VISIBLE' : 'HIDDEN'}_UNIT_${item.id}`;
    item.test_reference_ranges = [{ from: 1, to: 6, notes: `${range ? 'VISIBLE' : 'HIDDEN'}_NORMAL_${item.id}` }];
    sample.result_history[`test_${item.id}`] = [{ invoice_id: 2, date: '2026-09-01', field: item.content?.html ? 'WBC' : item.name,
      value: '3', unit: `${unit ? 'VISIBLE' : 'HIDDEN'}_HISTORY_UNIT_${item.id}`, is_component: !!item.content?.html }];
    if (item.content?.html) item.content.html = `<h2>${item.name}</h2><table><thead><tr><th>Test</th><th>Result</th><th>Unit</th><th>Normal Range</th></tr></thead><tbody><tr><td>WBC</td><td>{{sub_test.WBC.value}}</td><td>HIDDEN_CUSTOM_UNIT</td><td>HIDDEN_CUSTOM_NORMAL</td></tr></tbody></table>`;
  };
  sample.tests.forEach((item, i) => configure(item, i === 1, i === 0));
  sample.test_groups.forEach(group => group.tests.forEach(item => configure(item, false, false)));
  sample.packages.forEach(pkg => pkg.tests.forEach((item, i) => configure(item, i === 1, false)));
  return sample;
}
