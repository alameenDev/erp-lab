import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
await mkdir('test-results/patient-print-balance', { recursive: true });
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 960 } });
const errors = [], calls = [], states = new Map();
page.on('pageerror', error => errors.push(error.message));
await page.route('**/fonts.googleapis.com/**', route => route.abort());
await page.route('**/fonts.gstatic.com/**', route => route.abort());
await page.route('**/api/invoices/*/patient-balance', async route => {
  const id = Number(new URL(route.request().url()).pathname.split('/').at(-2));
  calls.push(id);
  const state = states.get(id);
  if (state?.wait) await state.wait;
  try { await route.fulfill({ status: state?.fail ? 503 : 200, contentType: 'application/json', body: JSON.stringify(state?.data || {}) }); } catch {}
});
const data = (id, current, other) => ({ invoice_id: id, patient_id: id + 100, currency: 'IQD', unpaid_invoice_count: Number(current > 0) + Number(other > 0), outstanding_amount: current + other, current_invoice_amount: current, other_invoices_amount: other });
const open = (kind, id, referral = false) => page.evaluate(args => debtFixture.open(...args), [kind, id, referral]);
const warning = page.locator('[data-patient-debt-warning]');
const execute = () => page.getByRole('button', { name: 'تنفيذ', exact: true });
const amount = async n => { await warning.waitFor(); assert.equal(await page.locator('[data-patient-debt-total]').innerText(), new Intl.NumberFormat('ar-IQ').format(n) + ' د.ع'); };
try {
  await page.goto('http://127.0.0.1:5173/tests/patient-print-balance-browser.html');
  await page.waitForFunction(() => Boolean(window.debtFixture));
  let release;
  states.set(1, { data: data(1, 5000, 10000), wait: new Promise(r => { release = r; }) });
  await open('report', 1);
  await page.locator('[data-patient-debt-loading]').waitFor(); assert.ok(await execute().isDisabled());
  release(); await amount(15000); assert.ok(await execute().isEnabled());
  await page.screenshot({ path: 'test-results/patient-print-balance/report-debt.png' });
  await execute().click(); assert.equal(await page.evaluate(() => debtFixture.actions.at(-1).action), 'print', 'debt warns without preventing printing');
  // Fetch again on every open; a paid current invoice can still have earlier debt.
  states.set(1, { data: data(1, 0, 10000) }); await open('report', 1); await amount(10000);
  assert.equal(calls.filter(id => id === 1).length, 2);
  states.set(1, { data: data(1, 0, 0) }); await open('report', 1);
  await page.locator('[data-patient-debt-loading]').waitFor({ state: 'hidden' }); assert.equal(await warning.count(), 0);
  // Network failure is not a false zero balance and does not trap the print flow.
  states.set(1, { fail: true }); await open('report', 1);
  await page.locator('[data-patient-debt-error]').waitFor(); assert.ok(await execute().isEnabled());
  states.set(1, { data: data(1, 5000, 10000) }); await page.getByRole('button', { name: 'إعادة المحاولة' }).click(); await amount(15000);
  // Ignore late responses after the dialog switches to another patient/invoice.
  let releaseOld;
  states.set(1, { data: data(1, 99999, 0), wait: new Promise(r => { releaseOld = r; }) });
  await open('report', 1); await page.locator('[data-patient-debt-loading]').waitFor();
  states.set(2, { data: data(2, 1000, 0) }); await open('report', 2); await amount(1000); releaseOld();
  await page.setViewportSize({ width: 390, height: 844 });
  await page.screenshot({ path: 'test-results/patient-print-balance/report-debt-mobile.png' });
  assert.ok((await warning.boundingBox()).width <= 390);
  await amount(1000);
  await page.setViewportSize({ width: 1280, height: 960 });
  states.set(1, { data: data(1, 5000, 10000) }); await open('invoice', 1); await amount(15000);
  assert.ok(await page.getByRole('button', { name: 'طباعة الفاتورة الورقية', exact: true }).isEnabled());
  assert.ok(!(await page.locator('#printInvoice').textContent()).includes('المبلغ المطلوب دفعه'), 'warning stays out of the printed document');
  await page.screenshot({ path: 'test-results/patient-print-balance/invoice-debt.png' });
  const before = calls.length; await open('invoice', 1, true);
  assert.equal(await warning.count(), 0); assert.equal(calls.length, before, 'referral workspace does not query the owning lab ledger');
  assert.deepEqual(errors, []);
  console.log('PASS fresh debt warning, prior invoices, settlement, retry, loading, stale response isolation, report/invoice print access and mobile');
} catch (error) { await page.screenshot({ path: 'test-results/patient-print-balance/failure.png' }); throw error; }
finally { await browser.close(); }
