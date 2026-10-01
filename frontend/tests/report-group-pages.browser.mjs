// Exercise actual report components and inspect the resulting page pixels.
import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import assert from 'node:assert/strict';
const out = 'test-results/report-pages';
await mkdir(out, { recursive: true });
const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1350, height: 1000 } });
await context.addInitScript(() => { window.print = () => { document.body.dataset.printRequested = 'true'; }; });
const page = await context.newPage();
const errors = [];
page.on('pageerror', error => errors.push(error.message));
page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
const ready = async () => {
  await page.waitForFunction(() => /^(PASS|FAIL)/.test(document.getElementById('status')?.textContent || ''), null, { timeout: 120000 });
  assert.equal(await page.locator('#status').innerText(), 'PASS', await page.locator('#checks').innerText());
};
try {
  await page.goto('http://127.0.0.1:5173/tests/report-group-pages-browser.html'); await ready();
  for (const template of ['classic', 'modern']) {
    await page.getByLabel('النموذج', { exact: true }).selectOption(template);
    for (const showNames of [true, false]) {
      await page.getByRole('checkbox').setChecked(showNames);
      for (const mode of ['mixed', 'first', 'last', 'adjacent', 'only', 'long', 'off', 'history', ...(showNames ? ['culture'] : [])]) {
        await page.getByLabel('الحالة', { exact: true }).selectOption(mode);
        await page.getByRole('button', { name: 'تحديث المعاينة', exact: true }).click(); await ready();
        console.log(`PASS ${template} / names=${showNames} / ${mode}: ${await page.locator('#pages img').count()} sheets`);
        if (template === 'modern' && showNames && mode === 'mixed') await page.screenshot({ path: `${out}/isolated-groups.png`, fullPage: true });
      }
    }
  }
  const preview = await page.locator('#pages img').evaluateAll(images => images.map(img => img.src));
  const [popup] = await Promise.all([context.waitForEvent('page'), page.getByRole('button', { name: 'طباعة المعاينة', exact: true }).click()]);
  await popup.waitForFunction(() => document.body.dataset.printRequested === 'true');
  assert.deepEqual(await popup.locator('.report-sheet img').evaluateAll(images => images.map(img => img.src)), preview);
  const pdf = await popup.pdf({ path: `${out}/isolated-groups-native.pdf`, preferCSSPageSize: true, printBackground: true });
  assert.equal((pdf.toString('latin1').match(/\/Type \/Page\b/g) || []).length, preview.length);
  assert.deepEqual(errors, []);
  console.log('PASS native print and PDF preserve isolated sheets without extra pages');
} catch (error) {
  console.log(await page.locator('body').innerText());
  await page.screenshot({ path: `${out}/group-pages-failure.png`, fullPage: true });
  throw error;
} finally { await browser.close(); }
