import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
const out = 'test-results/report-pages'; await mkdir(out, { recursive: true });
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1400, height: 1100 } });
const errors = []; page.on('pageerror', e => errors.push(e.message));
const open = async query => {
  await page.goto('http://127.0.0.1:5173/tests/report-comments-browser.html?field-visibility&' + query);
  await page.waitForFunction(() => window.fixture?.ready);
};
const noHidden = async locator => assert.ok(!(await locator.innerText()).includes('HIDDEN_'), 'hidden unit/range is absent from rendered content');
try {
  for (const template of ['classic', 'modern']) for (const merged of [false, true]) {
    await open(`template=${template}${merged ? '&merged=1&mono=1' : ''}&history=1`);
    const source = await page.evaluate(() => JSON.stringify(fixture.invoices.printRecord));
    await noHidden(page.locator('#Result'));
    const text = await page.locator('#Result').innerText();
    assert.ok(text.includes('VISIBLE_NORMAL_1'));
    assert.ok(text.includes('VISIBLE_UNIT_2'));
    assert.ok(text.includes('VISIBLE_UNIT_7'));
    const tables = await page.locator('#Result .report-results-table').evaluateAll(tables => tables.map(table => ({
      heads: [...table.querySelectorAll(':scope > thead > tr > th')].map(th => th.textContent.trim()),
      rows: [...table.querySelectorAll(':scope > tbody > tr:not(.report-comment-row)')].filter(tr => tr.cells.length > 1).map(tr => [...tr.cells].map(td => td.textContent.trim())),
      spans: [...table.querySelectorAll(':scope > tbody > tr.report-comment-row > td')].map(td => td.colSpan),
      widths: [...table.querySelectorAll(':scope > colgroup > col')].map(col => Number.parseFloat(col.style.width)),
    })));
    const glucose = tables.find(t => t.rows.some(r => r[0] === 'Glucose'));
    if (!merged) assert.ok(!glucose.heads.includes('Unit'));
    else assert.equal(glucose.rows.find(r => r[0] === 'Glucose')[glucose.heads.indexOf('Unit')], '', 'shared table keeps the column for other rows');
    assert.ok(glucose.heads.includes('Reference Ranges'));
    const measured = tables.find(t => t.rows.some(r => r[0] === 'Measured'));
    assert.ok(measured.heads.includes('Unit') && measured.heads.includes('Reference Ranges'), 'group retains both columns');
    const row = measured.rows.find(r => r[0] === 'Measured');
    assert.equal(row[measured.heads.indexOf('Unit')], '');
    assert.equal(row[measured.heads.indexOf('Reference Ranges')], '');
    const calculated = measured.rows.find(r => r[0] === 'Calculated');
    assert.equal(calculated[1], '10', 'formula still uses the real operands');
    for (const table of tables) {
      assert.ok(table.rows.every(row => row.length === table.heads.length));
      assert.ok(table.spans.every(span => span === table.heads.length));
      if (table.widths.length) { assert.equal(table.widths.length, table.heads.length); assert.equal(table.widths.reduce((a, b) => a + b, 0), 100); }
    }
    assert.equal(await page.locator('#Result .template-section table').first().locator('tr').last().locator('td').count(), 4, 'custom geometry is retained');
    const fast = await page.evaluate(() => fixture.prepare());
    assert.ok(fast.pages >= 2);
    const parity = await page.evaluate(async () => {
      const fast = [...fixture.pages]; const pdf = await fixture.prepare({ pagesOnly: false });
      return { same: JSON.stringify(fast) === JSON.stringify(fixture.pages), blob: pdf.hasBlob };
    });
    assert.deepEqual(parity, { same: true, blob: true });
    assert.equal(await page.evaluate(() => JSON.stringify(fixture.invoices.printRecord)), source);
    // Re-enabling fields invalidates prepared output without deleting stored values.
    await page.evaluate(() => { fixture.invoices.printRecord.tests[0].show_unit = true; });
    await page.waitForFunction(() => document.querySelector('#Result').textContent.includes('HIDDEN_UNIT_1'));
    assert.ok((await page.locator('#Result').innerText()).includes('HIDDEN_UNIT_1'));
    const png = await page.evaluate(() => fixture.pages[0]);
    await writeFile(`${out}/field-visibility-${template}-${merged ? 'merged' : 'named'}.png`, Buffer.from(png.split(',')[1], 'base64'));
    console.log(`PASS field visibility ${template}/${merged}: standalone columns, group/package alignment, formulas, custom cells and fast/PDF parity`);
  }
  await open('');
  await page.evaluate(() => {
    const rec = fixture.invoices.printRecord;
    rec.tests = [rec.tests[0]]; rec.tests[0].show_reference_range = false;
    rec.test_groups = []; rec.packages = []; rec.cultures = [];
  });
  await page.waitForFunction(() => document.querySelectorAll('#Result .report-results-table').length === 1);
  assert.equal(await page.locator('#Result [data-result-field]').count(), 0, 'both optional columns disappear from standalone table');
  await open('mode=editor'); await page.waitForFunction(() => fixture.state().autoSaveReady);
  await noHidden(page.locator('main'));
  assert.ok((await page.locator('main').innerText()).includes('VISIBLE_UNIT_2'));
  await page.evaluate(() => { fixture.state().activeTestTab = 0; });
  await noHidden(page.locator('main'));
  await page.evaluate(() => { fixture.state().activeTestTab = 2; });
  await page.locator('#custom-table-container .editable-field').first().waitFor();
  await noHidden(page.locator('#custom-table-container'));
  await page.locator('#custom-table-container .editable-field').first().fill('12');
  assert.equal(await page.evaluate(() => fixture.invoices.updateResultRecord.tests[2].sub_tests[0].value), '12');
  for (const section of ['test_groups', 'packages']) {
    await page.evaluate(section => { fixture.state().activeSection = section; }, section);
    await noHidden(page.locator('main'));
  }
  await page.evaluate(() => { fixture.state().activeSection = 'test_groups'; });
  await page.locator('.editable-field-group').first().waitFor();
  await page.locator('.editable-field-group').first().fill('13');
  assert.equal(await page.evaluate(() => fixture.invoices.updateResultRecord.test_groups[0].tests[2].sub_tests[0].value), '13');
  await page.evaluate(() => {
    for (const test of [fixture.invoices.updateResultRecord.tests[2], fixture.invoices.updateResultRecord.test_groups[0].tests[2]]) {
      test.content.bridge_cbc = true;
      test.sub_tests[0].unit = 'HIDDEN_DEVICE_UNIT'; test.sub_tests[0].reference_range = 'HIDDEN_DEVICE_NORMAL';
    }
  });
  await page.getByRole('textbox', { name: 'WBC result', exact: true }).fill('14');
  await noHidden(page.locator('main'));
  assert.equal(await page.evaluate(() => fixture.invoices.updateResultRecord.test_groups[0].tests[2].content.review_required), true);
  await page.evaluate(() => { fixture.state().activeSection = 'tests'; fixture.state().activeTestTab = 2; });
  await page.getByRole('textbox', { name: 'WBC result', exact: true }).fill('15');
  await noHidden(page.locator('main'));
  assert.equal(await page.evaluate(() => fixture.invoices.updateResultRecord.tests[2].sub_tests[0].value), '15');
  await page.evaluate(() => {
    const record = fixture.invoices.updateResultRecord;
    record.tests = [record.tests[0]]; record.tests[0].show_reference_range = false;
    record.test_groups = []; record.packages = [];
    fixture.state().activeSection = 'tests'; fixture.state().activeTestTab = 'all';
  });
  const quickTable = page.locator('table').filter({ has: page.getByText('اسم التحليل', { exact: true }) });
  assert.equal(await quickTable.locator('th[data-result-field]').count(), 0, 'quick editor collapses unused standalone fields');
  console.log('PASS editor: quick entry, individual detail, group/package rows and editable CBC templates');
  await open('mode=catalog');
  const unit = page.getByLabel('إظهار الوحدة (Unit)', { exact: true });
  const normal = page.getByLabel('إظهار المعدل الطبيعي (Normal Range)', { exact: true });
  assert.equal(await unit.isChecked(), true); assert.equal(await normal.isChecked(), true);
  await unit.uncheck(); await normal.uncheck();
  await page.evaluate(() => fixture.catalog.Addtests());
  assert.deepEqual(await page.evaluate(() => { const p = JSON.parse(fixture.requests.find(r => r.url === '/tests/create').data); return [p.show_unit, p.show_reference_range]; }), [false, false]);
  await unit.check(); await page.evaluate(() => fixture.catalog.Updatetests());
  assert.deepEqual(await page.evaluate(() => { const p = JSON.parse(fixture.requests.find(r => r.url === '/tests/update').data); return [p.show_unit, p.show_reference_range]; }), [true, false]);
  console.log('PASS catalog: default visibility, independent switches and explicit false in API payload');
  // Real output-selection actions all use the same filtered renderer.
  await open(''); const sample = await page.evaluate(() => fixture.sample);
  await page.goto('http://127.0.0.1:5173/tests/report-selection-browser.html?template=modern');
  const dialog = page.getByRole('dialog'); await dialog.waitFor();
  await dialog.getByRole('button', { name: 'إغلاق', exact: true }).click();
  await page.evaluate(sample => { sample.patient.phone = selectionFixture.source.patient.phone; selectionFixture.invoices.printRecord = sample; }, sample);
  await page.locator('#open').click(); let count = 0;
  for (const action of ['preview', 'print', 'download', 'whatsapp', 'print-download', 'whatsapp-download']) {
    if (action === 'preview') await dialog.getByRole('button', { name: 'معاينة التقرير', exact: true }).click();
    else { await dialog.locator(`input[value="${action}"]`).check(); await dialog.getByRole('button', { name: 'تنفيذ', exact: true }).click(); }
    await page.waitForFunction(n => document.querySelector('#status').textContent === 'PASS ' + n, ++count, { timeout: 60000 });
    await noHidden(page.locator('#Result'));
    assert.equal(await page.evaluate(async () => selectionFixture.executions.at(-1).output.blob.slice(0, 5).text()), '%PDF-');
  }
  assert.deepEqual(errors, []);
  console.log('PASS every output action: preview/print/PDF/WhatsApp and combined actions');
} catch (error) {
  await writeFile(`${out}/field-visibility-failure.txt`, `${error.stack}\nPage errors: ${JSON.stringify(errors)}`);
  await page.screenshot({ path: `${out}/field-visibility-failure.png`, fullPage: true });
  throw error;
} finally { await browser.close(); }
