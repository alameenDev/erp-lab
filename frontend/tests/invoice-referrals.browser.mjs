import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
await mkdir('test-results/invoice-referrals', { recursive: true });
const browser = await chromium.launch();
try {
  const page = await browser.newPage({ viewport: { width: 1360, height: 1000 } });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.goto('http://127.0.0.1:5173/tests/invoice-referrals-browser.html');
  await page.waitForFunction(() => window.invoiceFixture?.referrals.records.length === 94);
  const doctor = page.locator('#invoice-referral-doctor');
  const lab = page.locator('#invoice-referral-lab');
  const search = () => page.getByRole('searchbox');
  const options = () => page.getByRole('option');
  const choose = async (field, term, name) => {
    await field.click(); await search().fill(term);
    await options().filter({ hasText: name }).click();
  };
  await doctor.click();
  assert.match(await options().first().innerText(), /أَحمـد علي/);
  assert.equal(await search().getAttribute('aria-label'), 'ابحث عن اسم الطبيب');
  await search().fill('احمد'); assert.equal(await options().count(), 1);
  await search().press('ArrowDown'); await search().press('Enter');
  assert.equal(await page.evaluate(() => invoiceFixture.store.record.referral_id_fk), 1001);
  assert.equal(await page.evaluate(() => invoiceFixture.form().commission), 1500);
  assert.equal(await page.evaluate(() => invoiceFixture.form().price_list_id), 31);
  assert.equal(await page.evaluate(() => invoiceFixture.form().patientStore.record.phone), '7700000001');
  await doctor.click(); assert.equal(await search().inputValue(), '', 'search resets on reopening');
  assert.equal(await options().count(), 47);
  await search().fill('لايوجداسم'); await page.getByText('لا توجد نتائج مطابقة', { exact: true }).waitFor();
  await search().press('Escape');
  assert.equal(await page.evaluate(() => invoiceFixture.store.record.referral_id_fk), 1001, 'search never changes the selection');

  await page.evaluate(() => { invoiceFixture.form().patientStore.record.phone = '7800000999'; });
  await lab.click(); assert.match(await options().first().innerText(), /مختبر إزمير/);
  const scroller = page.locator('.p-select-list-container');
  assert.ok(await scroller.evaluate(el => el.scrollHeight > el.clientHeight), 'long list scrolls inside the field panel');
  await options().filter({ hasText: 'مختبر تجريبي 44' }).scrollIntoViewIfNeeded();
  assert.ok(await scroller.evaluate(el => el.scrollTop > 0));
  await search().fill('ازمير'); await options().first().click();
  assert.equal(await page.evaluate(() => invoiceFixture.store.record.from_lab_id_fk), 2001);
  assert.equal(await page.evaluate(() => invoiceFixture.form().price_list_id), 41, 'lab pricing keeps precedence');
  assert.equal(await page.evaluate(() => invoiceFixture.form().discountPercentage), 10);
  assert.equal(await page.evaluate(() => invoiceFixture.form().patientStore.record.phone), '7800000999', 'never replaces the patient phone');
  await lab.locator('..').locator('.p-select-clear-icon').click();
  assert.equal(await page.evaluate(() => invoiceFixture.store.record.from_lab_id_fk), null);
  assert.equal(await page.evaluate(() => invoiceFixture.form().price_list_id), 31, 'clearing lab restores doctor pricing');
  await choose(lab, 'الفرع', 'مختبر الفرع');
  assert.equal(await page.evaluate(() => invoiceFixture.store.record.from_lab_id_fk), 2002, 'branch labs remain selectable');

  assert.deepEqual(await page.evaluate(() => invoiceFixture.requests.filter(r => r.method !== 'get' && r.url !== '/tests/questions')), [], 'browsing does not save usage');
  const refreshCount = await page.evaluate(() => invoiceFixture.requests.filter(r => r.url === '/referrals').length);
  await page.evaluate(async () => {
    const f = invoiceFixture.form(); f.responseData = { id: 501, name: 'Synthetic Patient' };
    f.selectedTests = [{ ...invoiceFixture.test }]; invoiceFixture.state.failSave = true;
    await f.create();
  });
  await page.waitForFunction(() => invoiceFixture.state.alerts.includes('Synthetic save failure'));
  assert.equal(await page.evaluate(() => invoiceFixture.requests.filter(r => r.url === '/referrals').length), refreshCount, 'failed save never refreshes usage');
  await page.evaluate(async () => { invoiceFixture.state.failSave = false; await invoiceFixture.form().create(); });
  await page.waitForFunction(() => invoiceFixture.referrals.records.find(r => r.user_id === 1001).invoice_usage_count === 13);
  const payload = await page.evaluate(() => invoiceFixture.state.lastSaved);
  assert.equal(payload.referral_id_fk, 1001); assert.equal(payload.from_lab_id_fk, 2002);
  await page.evaluate(() => { invoiceFixture.store.printInvoiceDialog = false; });
  await doctor.click(); assert.match(await options().first().innerText(), /13/); await search().press('Escape');

  // Editing and reordering must preserve selected user IDs, not relationship IDs.
  await page.evaluate(() => {
    const f = invoiceFixture.form(); f.record.id = 301; f.record.patient_id_fk = 501;
    f.record.referral_id_fk = 1001; f.record.from_lab_id_fk = 2002;
    invoiceFixture.referrals.records.reverse();
  });
  assert.match(await doctor.innerText(), /أَحمـد علي/);
  await choose(doctor, 'مصطفي', 'مصطفى');
  await page.evaluate(async () => { await invoiceFixture.form().update(); });
  await page.waitForFunction(() => invoiceFixture.referrals.records.find(r => r.user_id === 1002).invoice_usage_count === 3);
  assert.equal(await page.evaluate(() => invoiceFixture.state.lastSaved.referral_id_fk), 1002);

  await lab.scrollIntoViewIfNeeded(); await lab.click();
  await page.screenshot({ path: 'test-results/invoice-referrals/desktop.png' }); await search().press('Escape');
  await page.setViewportSize({ width: 390, height: 844 }); await doctor.scrollIntoViewIfNeeded(); await doctor.click();
  const box = await page.locator('.p-select-overlay').boundingBox();
  assert.ok(box.x >= 0 && box.x + box.width <= 391, 'referral panel fits phone viewport');
  await search().fill('احمد'); await options().first().click();
  assert.equal(await page.evaluate(() => invoiceFixture.store.record.referral_id_fk), 1001);
  await doctor.click(); await page.screenshot({ path: 'test-results/invoice-referrals/mobile.png' });
  assert.deepEqual(errors, []);
  console.log('PASS real invoice form: Arabic search, usage order, scroll, keyboard, clear, branch labs, pricing, user IDs, save/failure/edit refresh, desktop/mobile');
} finally { await browser.close(); }
