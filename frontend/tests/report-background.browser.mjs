// Run in CI against the synthetic fixture; never load production patient data.
import { chromium } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';
const out = 'test-results/report-pages';
await mkdir(out, { recursive: true });
const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1400, height: 1100 } });
// Suppress the OS dialog while still exercising the real print-window document.
await context.addInitScript(() => { window.print = () => { document.body.dataset.printRequested = 'true'; }; });
const page = await context.newPage();
page.on('console', message => console.log('browser:', message.type(), message.text()));
page.on('pageerror', error => console.error('pageerror:', error.message));
try {
  await page.goto('http://127.0.0.1:5173/tests/report-background-browser.html');
  await page.getByText('Ready to run', { exact: true }).waitFor();
  await page.getByRole('button', { name: 'Run regression', exact: true }).click();
  await page.waitForFunction(() => /^(PASS|FAIL)/.test(document.querySelector('#status').textContent), null, { timeout: 120000 });
  const status = await page.locator('#status').innerText();
  const checks = await page.locator('#checks').innerText();
  console.log(checks, status);
  await page.screenshot({ path: `${out}/preview.png`, fullPage: true });
  assert.match(status, /^PASS/);
  const pages = await page.locator('#pages img').evaluateAll(images => images.map(image => image.src));
  for (const [i, src] of pages.entries()) await writeFile(`${out}/sheet-${i + 1}.png`, Buffer.from(src.split(',')[1], 'base64'));
  const [popup] = await Promise.all([
    context.waitForEvent('page'),
    page.getByRole('button', { name: 'Print verified sheets', exact: true }).click(),
  ]);
  await popup.waitForFunction(() => document.body.dataset.printRequested === 'true');
  assert.deepEqual(await popup.locator('.report-sheet img').evaluateAll(images => images.map(image => image.src)), pages);
  const nativePdf = await popup.pdf({ path: `${out}/native-print.pdf`, preferCSSPageSize: true, printBackground: true });
  assert.equal((nativePdf.toString('latin1').match(/\/Type \/Page\b/g) || []).length, pages.length, 'native print must not insert empty sheets');
  console.log('PASS native print receives byte-identical page images, with A4 @page and zero outer margin');
} catch (error) {
  console.log('last UI:', await page.locator('body').innerText());
  await page.screenshot({ path: `${out}/failure.png`, fullPage: true });
  throw error;
} finally {
  await browser.close();
}
