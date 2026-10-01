import test from 'node:test';
import assert from 'node:assert/strict';
import { customReportColumns, reportColumnKeys, reportColumnWidths, resizeReportColumn } from '../src/utils/medicalReportColumns.js';

test('column keys follow both templates and optional columns without hiding test names', () => {
  assert.deepEqual(reportColumnKeys({ show_status: false, show_test_names: false }), ['test', 'result', 'unit', 'reference']);
  assert.deepEqual(reportColumnKeys({ report_template: 'modern', show_last_result: true }), ['test', 'result', 'status', 'unit', 'reference', 'last_result']);
  assert.deepEqual(reportColumnKeys({ show_last_result: true }), ['test', 'result', 'unit', 'reference', 'last_result', 'status']);
  assert.equal(customReportColumns({}), false);
  assert.equal(customReportColumns({ custom_column_widths: '0' }), false);
  assert.equal(customReportColumns({ custom_column_widths: '1' }), true);
});

test('visible widths total 100, remain stable on reload, and do not depend on template order', () => {
  for (const show_status of [true, false]) for (const show_last_result of [true, false]) {
    const settings = { show_status, show_last_result };
    const initial = reportColumnWidths(settings);
    assert.equal(Object.values(initial).reduce((a, b) => a + b), 100);
    assert.deepEqual(reportColumnWidths({ ...settings, report_template: 'modern' }), initial);
    assert.deepEqual(reportColumnWidths({ ...settings, print_table_config: { column_widths: initial } }), initial);
  }
});

test('any column can get an exact requested width, including bounds, while others stay readable', () => {
  for (const show_status of [true, false]) for (const show_last_result of [true, false]) {
    const settings = { show_status, show_last_result };
    for (const key of reportColumnKeys(settings)) for (const input of [5, 17, 40, 100, -10]) {
      const widths = resizeReportColumn(settings, key, input);
      assert.equal(widths[key], Math.max(5, Math.min(100 - (Object.keys(widths).length - 1) * 5, input)));
      assert.equal(Object.values(widths).reduce((a, b) => a + b), 100);
      assert.ok(Object.values(widths).every(value => Number.isInteger(value) && value >= 5));
      assert.deepEqual(reportColumnWidths({ ...settings, print_table_config: { column_widths: widths } }), widths);
    }
  }
});

test('invalid historical data and empty number inputs cannot inject invalid widths', () => {
  const settings = { print_table_config: { column_widths: { test: '</style>', unit: -12, result: null, reference: Infinity, status: 1000 } } };
  assert.deepEqual(reportColumnWidths(settings), reportColumnWidths({}));
  assert.deepEqual(resizeReportColumn(settings, 'test', ''), reportColumnWidths(settings));
  assert.deepEqual(resizeReportColumn(settings, 'unknown', 50), reportColumnWidths(settings));
  assert.deepEqual(resizeReportColumn(settings, 'test', NaN), reportColumnWidths(settings));
});
