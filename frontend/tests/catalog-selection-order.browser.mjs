import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';

await mkdir('test-results/catalog-selection-order', { recursive: true });
const browser = await chromium.launch();
const errors = [];
try {
  const page = await browser.newPage({ viewport: { width: 1360, height: 1000 } });
  page.on('pageerror', error => { errors.push(error.message); console.error(error.stack); });
  await page.goto('http://127.0.0.1:5173/tests/catalog-selection-order-browser.html');
  await page.waitForFunction(() => window.orderFixture);
  const picker = () => page.locator('.ordered-picker:visible');
  const choose = async name => picker().locator('.catalog-option').filter({ hasText: name }).click();
  const assertSelection = async names => {
    assert.deepEqual(await picker().locator('.selected-row > .item-name > bdi').allTextContents(), names);
    assert.deepEqual(await picker().locator('.order-number').allTextContents(), names.map((_, i) => String(i + 1)));
  };
  const save = async field => {
    await page.locator('form:visible').evaluate(form => form.requestSubmit());
    await page.waitForFunction(field => !orderFixture[field].dialog, field);
    await page.locator('form:visible').waitFor({ state: 'hidden' });
    return page.evaluate(() => orderFixture.saved);
  };

  await page.evaluate(() => orderFixture.openGroup());
  await choose('Hemoglobin'); await choose('Glucose'); await choose('Creatinine');
  await assertSelection(['Hemoglobin', 'Glucose', 'Creatinine']);
  assert.equal(await page.evaluate(() => orderFixture.groups.record.original_price), 9000);
  await picker().getByRole('searchbox').fill('CRE');
  await assertSelection(['Hemoglobin', 'Glucose', 'Creatinine']);
  assert.equal(await page.evaluate(() => orderFixture.groups.record.original_price), 9000, 'search cannot change the selected price');
  await picker().getByRole('searchbox').fill('no matches');
  await assertSelection(['Hemoglobin', 'Glucose', 'Creatinine']);
  await picker().getByRole('searchbox').fill('');
  await picker().getByRole('button', { name: 'إزالة · Glucose', exact: true }).click();
  await choose('Glucose');
  await assertSelection(['Hemoglobin', 'Creatinine', 'Glucose']);
  await picker().getByRole('button', { name: 'تقديم · Glucose', exact: true }).click();
  await assertSelection(['Hemoglobin', 'Glucose', 'Creatinine']);
  await picker().screenshot({ path: 'test-results/catalog-selection-order/group-desktop.png' });
  let saved = await save('groups');
  assert.deepEqual(saved.body.test_ids, [13, 11, 12]);
  await page.evaluate(body => orderFixture.openGroup({ ...body, id: 1 }), saved.body);
  await assertSelection(['Hemoglobin', 'Glucose', 'Creatinine']);
  for (const name of ['Hemoglobin', 'Glucose', 'Creatinine']) await picker().getByRole('button', { name: 'إزالة · ' + name, exact: true }).click();
  assert.equal(await page.evaluate(() => orderFixture.groups.record.original_price), 0, 'removing every test resets the total');
  saved = await save('groups');
  assert.deepEqual(saved.body.test_ids, []);

  await page.evaluate(() => orderFixture.openPackage());
  await choose('Creatinine'); await choose('Hemoglobin'); await choose('Glucose');
  await assertSelection(['Creatinine', 'Hemoglobin', 'Glucose']);
  await picker().getByRole('searchbox').fill('HGB');
  await assertSelection(['Creatinine', 'Hemoglobin', 'Glucose']);
  await picker().getByRole('searchbox').fill('');
  await page.getByRole('button', { name: 'كروبات التحاليل (0)', exact: true }).click();
  await choose('Renal profile');
  await assertSelection(['Renal profile']);
  await picker().locator('summary').click();
  assert.deepEqual(await picker().locator('.group-children li bdi').allTextContents(), ['Creatinine', 'Glucose']);
  await page.getByRole('button', { name: 'التحاليل (3)', exact: true }).click();
  await assertSelection(['Creatinine', 'Hemoglobin', 'Glucose']);
  saved = await save('packages');
  assert.deepEqual(saved.body.tests.map(test => test.id), [12, 13, 11]);
  assert.deepEqual(saved.body.test_groups.map(group => group.id), [21]);
  await page.evaluate(body => orderFixture.openPackage({ ...body, id: 1 }), saved.body);
  await assertSelection(['Creatinine', 'Hemoglobin', 'Glucose']);
  await page.setViewportSize({ width: 390, height: 844 });
  await picker().scrollIntoViewIfNeeded();
  const widths = await picker().evaluate(el => ({ width: el.clientWidth, scrollWidth: el.scrollWidth }));
  assert.ok(widths.scrollWidth <= widths.width + 1, 'selection fits a phone viewport');
  await picker().getByRole('button', { name: 'تقديم · Glucose', exact: true }).click();
  await assertSelection(['Creatinine', 'Glucose', 'Hemoglobin']);
  await picker().screenshot({ path: 'test-results/catalog-selection-order/package-mobile.png' });
  saved = await save('packages');
  assert.deepEqual(saved.body.tests.map(test => test.id), [12, 11, 13]);
  assert.deepEqual(errors, []);
  console.log('PASS: group/package selection order, numbering, search, price stability, reorder, removal, nested groups, save/reopen and mobile layout');
} finally {
  await browser.close();
}
