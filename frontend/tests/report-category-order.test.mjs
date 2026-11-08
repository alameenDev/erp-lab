import test from 'node:test';
import assert from 'node:assert/strict';
import { orderReportCategories, reportBlockCategory } from '../src/utils/reportCategoryOrder.js';

test('first selected category leads, with saved order inside it, without mutating inputs', () => {
  const input = [
    { id: 'urea', category: 'Chemistry', rank: 3 },
    { id: 'tsh', category: 'Hormones', rank: 1 },
    { id: 'glucose', category: 'Chemistry', rank: 0 },
    { id: 'panel', category: '', rank: 2 },
    { id: 't4', category: 'Hormones', rank: 4 },
  ];
  const original = structuredClone(input);
  const output = orderReportCategories(input);
  assert.deepEqual(output.map(x => x.id), ['glucose', 'urea', 'tsh', 't4', 'panel']);
  assert.deepEqual(output.map(x => x.categoryHeading), ['Chemistry', '', 'Hormones', '', '']);
  assert.deepEqual(input, original);
  // Removing the first chosen test changes the first category of this output.
  assert.deepEqual(orderReportCategories(input.filter(x => x.id !== 'glucose')).map(x => x.id), ['tsh', 't4', 'panel', 'urea']);
});

test('old invoices and ties retain existing member order; missing categories remain independent', () => {
  const output = orderReportCategories([
    { id: 1 }, { id: 2, category: ' A ' }, { id: 3 }, { id: 4, category: 'A' }, { id: 5, category: 'B' },
  ]);
  assert.deepEqual(output.map(x => x.id), [1, 2, 4, 3, 5]);
  assert.deepEqual(output.map(x => x.categoryHeading), ['', 'A', '', '', 'B']);
});

test('isolated group and template pages repeat their category without splitting the panel', () => {
  const rows = [{ result: '4' }, { result: '8', formula: true }];
  const output = orderReportCategories([
    { id: 1, category: 'A', rank: 0 }, { id: 2, category: 'B', rank: 1 },
    { id: 3, category: 'A', rank: 2, isolated: true, rows }, { id: 4, category: 'A', rank: 3 },
  ]);
  assert.deepEqual(output.map(x => x.id), [1, 3, 4, 2]);
  assert.deepEqual(output.map(x => x.categoryHeading), ['A', 'A', 'A', 'B']);
  assert.equal(output[1].rows, rows);
});

test('panels only inherit a category shared by every member', () => {
  assert.equal(reportBlockCategory(null, [{ category: 'A' }, { category: ' A ' }]), 'A');
  assert.equal(reportBlockCategory(null, [{ category: 'A' }, { category: 'B' }]), '');
  assert.equal(reportBlockCategory(null, [{ category: 'A' }, {}]), '');
  assert.equal(reportBlockCategory(null, []), '');
  assert.equal(reportBlockCategory({ category: 'Panel category' }, [{ category: 'A' }]), 'Panel category');
});
