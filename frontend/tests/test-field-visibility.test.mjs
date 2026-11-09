import test from 'node:test';
import assert from 'node:assert/strict';
import { showsUnit, showsReferenceRange, resultFieldColumns } from '../src/utils/testFieldVisibility.js';
import { reportColumnKeys, reportColumnWidths } from '../src/utils/medicalReportColumns.js';

test('legacy tests show both fields; explicit false survives JSON/string representations', () => {
  for (const value of [undefined, null, true, 1, '1']) {
    assert.ok(showsUnit({ show_unit: value }));
    assert.ok(showsReferenceRange({ show_reference_range: value }));
  }
  for (const value of [false, 0, '0', 'false']) {
    assert.equal(showsUnit({ show_unit: value }), false);
    assert.equal(showsReferenceRange({ show_reference_range: value }), false);
  }
});

test('independent columns collapse; group and package geometry stays stable', () => {
  const hidden = { show_unit: false, show_reference_range: false, unit: 'unit', test_reference_ranges: [{ from: 1, to: 9 }] };
  const before = structuredClone(hidden);
  assert.deepEqual(resultFieldColumns([hidden]), { show_unit: false, show_reference_range: false });
  assert.deepEqual(resultFieldColumns([hidden, { show_unit: false }]), { show_unit: false, show_reference_range: true });
  assert.deepEqual(resultFieldColumns([hidden], true), { show_unit: true, show_reference_range: true });
  assert.deepEqual(hidden, before, 'presentation leaves units/ranges available for calculations');
});

test('all visibility/template/status/history combinations retain column order and total width', () => {
  for (const report_template of ['classic', 'modern']) for (const show_unit of [true, false])
    for (const show_reference_range of [true, false]) for (const show_status of [true, false]) for (const show_last_result of [true, false]) {
      const settings = { report_template, show_unit, show_reference_range, show_status, show_last_result };
      const keys = reportColumnKeys(settings), widths = reportColumnWidths(settings);
      assert.equal(keys.includes('unit'), show_unit);
      assert.equal(keys.includes('reference'), show_reference_range);
      assert.equal(keys.length, 2 + Number(show_unit) + Number(show_reference_range) + Number(show_status) + Number(show_last_result));
      assert.equal(Object.values(widths).reduce((a, b) => a + b, 0), 100);
      assert.deepEqual(keys.slice(0, 2), ['test', 'result']);
    }
});
