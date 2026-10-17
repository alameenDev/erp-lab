import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';

await mkdir('test-results/report-pages', {recursive: true});
const browser = await chromium.launch();
const errors = [];
const page = await browser.newPage({viewport: {width: 1360, height: 1000}});
page.on('pageerror', error => { errors.push(error.message); console.error(error.stack); });
const url = 'http://127.0.0.1:5173/tests/report-test-filter-browser.html';
const toggle = () => page.getByRole('button', {name: 'الفلاتر', exact: true});
const field = () => page.locator('[data-report-test-filter]');
const choose = async (shortcut, name) => {
  if (!await page.getByRole('searchbox').isVisible()) await field().getByRole('combobox').click();
  await page.getByRole('searchbox').fill(shortcut);
  await page.getByRole('option', {name, exact: true}).click();
};
const waitRows = async ids => {
  await page.waitForFunction(ids => JSON.stringify(Array.from(document.querySelectorAll('tr[data-audit-invoice-id]'), row => Number(row.dataset.auditInvoiceId))) === JSON.stringify(ids), ids);
};
const lastRequest = () => page.evaluate(() => filterFixture.requests.filter(r => r.url === '/invoices' || r.url.includes('patient-medical-records')).at(-1));
try {
  await page.goto(url);
  await waitRows(Array.from({length: 25}, (_, i) => i + 1));
  assert.equal(await page.evaluate(() => filterFixture.requests.some(r => r.url.includes('test-filter-options'))), false, 'options load only when filters open');
  const nextLabel = await page.evaluate(() => filterFixture.t('next'));
  await page.getByRole('button', {name: nextLabel, exact: true}).click();
  await waitRows([26, 27, 28, 29, 30]);
  await toggle().click();
  const labels = await page.evaluate(() => ['theStatus', 'Signature', 'contract', 'from_lab', 'Registration_date', 'tests'].map(filterFixture.t));
  const grid = field().locator('..');
  assert.deepEqual(await grid.locator(':scope > div > label').allTextContents(), labels, 'existing filters keep their order, tests appended');
  await choose('CBC', 'Complete Blood Count (CBC)');
  await waitRows([1, 2]);
  assert.deepEqual((await lastRequest()).params.test_ids, [11]);
  assert.equal((await lastRequest()).params.page, 1, 'selection resets pagination');
  await choose('GLU', 'Glucose (GLU)');
  await waitRows([1]);
  assert.deepEqual((await lastRequest()).params.test_ids, [11, 12]);
  assert.equal(await page.evaluate(() => filterFixture.store.stats.total), 1);
  await page.keyboard.press('Escape');
  assert.match(await field().textContent(), /2 تحاليل مختارة/);
  assert.match(await toggle().textContent(), /1/, 'multiple tests count as one active filter');
  const height = await field().locator('.report-test-filter').evaluate(el => el.getBoundingClientRect().height);
  const otherHeight = await grid.locator('select').first().evaluate(el => el.getBoundingClientRect().height);
  assert.ok(Math.abs(height - otherHeight) <= 3, 'test control aligns with existing filter controls');
  await grid.screenshot({path: 'test-results/report-pages/test-filter-desktop.png'});

  await grid.locator('select').first().selectOption('done');
  await waitRows([]);
  assert.equal((await lastRequest()).params.is_done, 'done');
  assert.deepEqual((await lastRequest()).params.test_ids, [11, 12]);
  await page.getByRole('button', {name: 'الغاء البحث', exact: true}).click();
  await waitRows(Array.from({length: 25}, (_, i) => i + 1));
  assert.equal((await lastRequest()).params.test_ids, undefined);
  assert.equal((await lastRequest()).params.is_done, undefined);

  await choose('CBC', 'Complete Blood Count (CBC)');
  await waitRows([1, 2]);
  await page.keyboard.press('Escape');
  await field().locator('.p-multiselect-clear-icon').click();
  await waitRows(Array.from({length: 25}, (_, i) => i + 1));
  assert.equal((await lastRequest()).params.test_ids, undefined, 'individual clear safely normalizes null');
  await toggle().click(); await toggle().click();
  assert.equal(await page.evaluate(() => filterFixture.requests.filter(r => r.url.includes('test-filter-options')).length), 1, 'loaded options are reused');

  await page.setViewportSize({width: 390, height: 844});
  await field().scrollIntoViewIfNeeded();
  await field().getByRole('combobox').focus();
  await page.keyboard.press('ArrowDown');
  await page.getByRole('searchbox').waitFor();
  await page.getByRole('searchbox').fill('Glucose');
  await page.getByRole('option', {name: 'Glucose (GLU)', exact: true}).click();
  await waitRows([1, 3]);
  const overlay = await page.locator('.p-multiselect-overlay').boundingBox();
  assert.ok(overlay.x >= -1 && overlay.x + overlay.width <= 391, 'dropdown fits mobile width');
  await page.screenshot({path: 'test-results/report-pages/test-filter-mobile.png'});
  await page.keyboard.press('Escape');
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);

  await page.goto(url + '?patient_id=1');
  await waitRows([1, 2]);
  await toggle().click();
  await choose('GLU', 'Glucose (GLU)');
  await waitRows([1]);
  assert.equal((await lastRequest()).url, '/invoices/patient-medical-records/1');
  assert.deepEqual((await lastRequest()).params.test_ids, [12]);
  await page.keyboard.press('Escape');
  await page.getByRole('button', {name: 'الغاء البحث', exact: true}).click();
  await waitRows([1, 2]);
  assert.deepEqual(errors, []);
  console.log('PASS report filter: lazy options, unchanged order, single/multiple tests, pagination, stats, combined filters, both clear controls, mobile keyboard/search, patient reports');
} catch (error) {
  await page.screenshot({path: 'test-results/report-pages/test-filter-failure.png', fullPage: true}).catch(() => {});
  throw error;
} finally { await browser.close(); }
