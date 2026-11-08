import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1360, height: 1000 } });
const errors = [];
page.on('pageerror', error => errors.push(error.message));
await mkdir('test-results/report-pages', { recursive: true });
const dialog = page.getByRole('dialog');
let executions = 0;
async function run(action = 'preview') {
  if (action === 'preview') await dialog.getByRole('button', { name: 'معاينة التقرير', exact: true }).click();
  else {
    await dialog.locator('input[type="radio"][value="' + action + '"]').check();
    await dialog.getByRole('button', { name: 'تنفيذ', exact: true }).click();
  }
  await page.waitForFunction(n => document.querySelector('#status').textContent === 'PASS ' + n || document.querySelector('#status').textContent.startsWith('FAIL'), ++executions, { timeout: 60000 });
  assert.equal(await page.locator('#status').innerText(), 'PASS ' + executions);
  return page.evaluate(() => {
    const x = selectionFixture.executions.at(-1);
    return { sections: x.sections, text: x.text, pages: x.output.pages.length, size: x.output.blob.size };
  });
}
async function replaceRecord(mode = 'simple') {
  await dialog.getByRole('button', { name: 'إغلاق', exact: true }).click();
  await page.evaluate(mode => {
    const f = selectionFixture;
    const test = (id, name, category, extra = {}) => ({ id, test_id_fk: id, name, category, result: '4', is_done: true, test_reference_ranges: [], ...extra });
    const record = { ...f.source, result_history: {}, tests: [], test_groups: [], packages: [], cultures: [] };
    if (mode === 'simple') {
      record.tests = [test(3, 'Urea', 'Chemistry'), test(2, 'TSH', 'Hormones'), test(4, 'T4', 'Hormones'), test(1, 'Glucose', 'Chemistry', { comment: 'Glucose comment' })];
      record.selection_order = [1, 2, 3, 4].map(id => ({ field: 'tests', id }));
    } else if (mode === 'mixed') {
      record.tests = [test(1, 'Glucose', 'Chemistry'), test(2, 'TSH', 'Hormones')];
      record.cultures = [test(20, 'Culture A', 'Microbiology'), test(21, 'Culture B', 'Microbiology')];
      record.test_groups = [{ id: 50, group_name: 'Chemistry panel', is_print_alone: true,
        tests: [test(5, 'Input', 'Chemistry'), test(6, 'Calculated', 'Chemistry')], formula: [{ name: 'Calculated', tokens: ['Input', '*', '2'] }] }];
      record.packages = [{ id: 60, name: 'Mixed package', tests: [test(7, 'Package chemistry', 'Chemistry'), test(8, 'Package hormone', 'Hormones')], cultures: [], test_groups: [] }];
      record.selection_order = [{ field: 'cultures', id: 21 }, { field: 'tests', id: 1 }, { field: 'tests', id: 2 }, { field: 'test_groups', id: 50 }, { field: 'cultures', id: 20 }, { field: 'packages', id: 60 }];
    } else if (mode === 'custom') {
      record.tests = [test(1, 'Glucose', 'Chemistry'), test(2, 'TSH', 'Hormones'),
        test(3, 'Custom chemistry', 'Chemistry', { sub_tests: [{ name: 'Custom value', value: '4' }], content: { html: '<h2>Custom chemistry</h2><p>Custom value: 4</p>' } })];
      record.selection_order = [1, 2, 3].map(id => ({ field: 'tests', id }));
    } else {
      record.tests = Array.from({ length: 70 }, (_, i) => test(i + 1, 'Analysis ' + (i + 1), i % 2 ? 'Hormones' : 'Chemistry'));
      record.selection_order = record.tests.map(t => ({ field: 'tests', id: t.id }));
    }
    f.invoices.printRecord = record;
    f.settings.settings.show_comments = true;
    window.categoryOriginal = JSON.stringify(record);
  }, mode);
  await page.locator('#open').click();
}
try {
  for (const template of ['classic', 'modern']) for (const merged of [false, true]) {
    await page.goto('http://127.0.0.1:5173/tests/report-selection-browser.html?template=' + template + (merged ? '&merged=1' : ''));
    await dialog.waitFor(); executions = 0;
    await replaceRecord();
    for (const action of ['preview', 'print', 'download', 'whatsapp', 'print-download', 'whatsapp-download']) {
      const output = await run(action);
      assert.deepEqual(output.sections.flatMap(s => s.tests), ['Glucose', 'Urea', 'TSH', 'T4']);
      assert.deepEqual(output.sections.map(s => s.category), ['Chemistry', '', 'Hormones', '']);
      assert.ok(output.text.includes('Glucose comment'));
      assert.ok(output.size > 1000);
    }
    assert.equal(await page.evaluate(() => selectionFixture.executions.every(x => x.output === selectionFixture.executions[0].output)), true, 'all actions share the ordered output');
    assert.equal(await page.evaluate(() => JSON.stringify(selectionFixture.invoices.printRecord) === categoryOriginal), true, 'printing never reorders saved invoice data');
    // Partial output must start with the first remaining selected category.
    await dialog.getByRole('checkbox', { name: 'Glucose', exact: true }).uncheck();
    let output = await run();
    assert.deepEqual(output.sections.flatMap(s => s.tests), ['TSH', 'T4', 'Urea']);
    assert.deepEqual(output.sections.map(s => s.category), ['Hormones', '', 'Chemistry']);
    assert.ok(!output.text.includes('Glucose comment'));
    await dialog.getByRole('checkbox', { name: 'Glucose', exact: true }).check();
    await page.evaluate(() => { selectionFixture.settings.settings.show_categories = false; });
    output = await run();
    assert.deepEqual(output.sections.flatMap(s => s.tests), ['Glucose', 'Urea', 'TSH', 'T4']);
    assert.ok(output.sections.every(s => !s.category));
    await page.evaluate(() => { selectionFixture.settings.settings.show_categories = true; });
    await replaceRecord('mixed');
    output = await run();
    assert.deepEqual(output.sections.flatMap(s => s.tests), ['Culture B', 'Culture A', 'Glucose', 'Input', 'Calculated', 'Package chemistry', 'TSH', 'Package hormone']);
    const isolated = output.sections.find(s => s.tests.includes('Input'));
    assert.equal(isolated.isolated, true);
    assert.equal(isolated.category, 'Chemistry');
    assert.ok(output.text.includes('8'), 'formula survives category movement');
    assert.ok(output.pages >= 3, 'isolated panel remains on its own sheet');
    assert.equal(await page.evaluate(async () => {
      const fast = await selectionFixture.prepare({ pagesOnly: true, withBackground: false });
      const pdf = selectionFixture.executions.at(-1).output;
      return JSON.stringify(fast.pages) === JSON.stringify(pdf.pages);
    }), true);
    await replaceRecord('custom');
    output = await run();
    assert.ok(output.text.indexOf('Glucose') < output.text.indexOf('Custom chemistry'));
    assert.ok(output.text.indexOf('Custom chemistry') < output.text.indexOf('TSH'));
    assert.equal(await page.locator('#Result .template-section [data-report-category]').count(), 0, 'custom template keeps its own layout without an added heading');
    assert.ok(output.pages >= 3);
    console.log('PASS categories: ' + template + (merged ? ' merged' : ' named') + ', all actions, partial output, hidden headings, cultures, packages, formulas and isolation');
  }
  await replaceRecord('long');
  const long = await run();
  assert.ok(long.pages > 1);
  assert.deepEqual(long.sections.flatMap(s => s.tests), [...Array.from({ length: 35 }, (_, i) => 'Analysis ' + (2 * i + 1)), ...Array.from({ length: 35 }, (_, i) => 'Analysis ' + (2 * i + 2))]);
  assert.deepEqual(long.sections.map(s => s.category).filter(Boolean), ['Chemistry', 'Hormones']);
  await dialog.getByRole('button', { name: 'إغلاق', exact: true }).click();
  await page.locator('#pages').screenshot({ path: 'test-results/report-pages/category-order-long.png' });
  assert.deepEqual(errors, []);
  console.log('PASS 70 interleaved analyses: each category is contiguous across continuation pages');
} catch (error) {
  await page.screenshot({ path: 'test-results/report-pages/category-order-failure.png' });
  throw error;
} finally { await browser.close(); }
