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
try {
  for (const template of ['classic', 'modern']) for (const mode of ['analysis', 'group', 'package', 'merged', 'long', 'template']) {
    await page.goto(`http://127.0.0.1:5173/tests/report-keep-together-browser.html?template=${template}&mode=${mode}`);
    await page.waitForFunction(() => /^(PASS|FAIL)/i.test(document.getElementById('status')?.textContent || ''), null, { timeout: 120000 });
    assert.equal((await page.locator('#status').innerText()).toUpperCase(), 'PASS', await page.locator('#checks').textContent());
    console.log(`PASS ${template}/${mode}: headings, complete results and fitting blocks stay together`);
    if (template === 'classic' && mode === 'group') await page.screenshot({ path: `${out}/virology-kept-together.png`, fullPage: true });
  }
  const images = await page.locator('#pages img').evaluateAll(nodes => nodes.map(node => node.src));
  const [popup] = await Promise.all([context.waitForEvent('page'), page.getByRole('button', { name: 'طباعة المعاينة', exact: true }).click()]);
  await popup.waitForFunction(() => document.body.dataset.printRequested === 'true');
  assert.deepEqual(await popup.locator('.report-sheet img').evaluateAll(nodes => nodes.map(node => node.src)), images);
  assert.deepEqual(errors, []);
  console.log('PASS print consumes the same unsplit pages as the saved PDF');
} catch (error) {
  console.log(await page.locator('#checks').textContent());
  await page.screenshot({ path: `${out}/keep-together-failure.png`, fullPage: true });
  throw error;
} finally { await browser.close(); }
