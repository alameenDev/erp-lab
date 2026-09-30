import test from 'node:test';
import assert from 'node:assert/strict';
import { configuredDefaultResult, withDefaultResults, renameResultOption, removeResultOption } from '../src/utils/defaultTestResults.js';

const analysis = (extra = {}) => ({ id: 7, result_type_id_fk: 4, selection_type_options: ['True', 'False'], default_result: 'True', ...extra });

test('a selected default starts a new editable result without completing it or mutating the catalogue', () => {
  const source = analysis();
  const item = withDefaultResults(source);
  assert.equal(item.result, 'True');
  assert.ok(!item.is_done);
  item.result = 'False';
  assert.equal(item.result, 'False');
  assert.equal(source.result, undefined);
});

test('package and group children receive independent defaults; entered values and completed blanks survive', () => {
  const source = { tests: [analysis(), analysis({ result: 'False' }), analysis({ result: '', is_done: true })] };
  const selected = withDefaultResults(source);
  assert.deepEqual(selected.tests.map(t => t.result), ['True', 'False', '']);
  assert.equal(source.tests[0].result, undefined);
  assert.equal(withDefaultResults({ test_group_tests: [analysis()] }).test_group_tests[0].result, 'True');
  assert.equal(withDefaultResults({ package_tests: [analysis()] }).package_tests[0].result, 'True');
});

test('no default, other result types and stale options stay blank, including numeric-looking options', () => {
  for (const value of [analysis({ default_result: null }), analysis({ result_type_id_fk: 1 }), analysis({ default_result: 'Missing' }), analysis({ is_special_test: true })]) {
    assert.equal(configuredDefaultResult(value), null);
    assert.equal(withDefaultResults(value).result, undefined);
  }
  assert.equal(withDefaultResults(analysis({ selection_type_options: ['0', '1'], default_result: '0' })).result, '0');
  const legacy = analysis({ selection_type_options: undefined, selction_type_options: ['True', 'False'] });
  assert.equal(withDefaultResults(legacy).result, 'True');
});

test('renaming follows the selected option; deleting or emptying it clears the default', () => {
  const record = analysis();
  renameResultOption(record, 0, 'Positive');
  assert.equal(record.default_result, 'Positive');
  renameResultOption(record, 1, 'Negative');
  assert.equal(record.default_result, 'Positive');
  removeResultOption(record, 0);
  assert.equal(record.default_result, null);
  assert.deepEqual(record.selection_type_options, ['Negative']);
  record.default_result = 'Negative';
  renameResultOption(record, 0, '');
  assert.equal(record.default_result, null);
});
