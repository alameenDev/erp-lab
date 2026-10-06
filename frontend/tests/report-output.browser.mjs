import { chromium } from 'playwright';
import { mkdir, readFile } from 'node:fs/promises';
import assert from 'node:assert/strict';
const out = 'test-results/report-pages';
await mkdir(out, { recursive: true });
const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1350, height: 1000 }, acceptDownloads: true });
await context.addInitScript(() => { window.print = () => { document.body.dataset.printRequested = 'true'; }; });
const page = await context.newPage();
const errors = [];
page.on('pageerror', error => errors.push(error.message));
page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
const fingerprint = bytes => bytes.reduce((hash, byte) => ((hash * 31) ^ byte) >>> 0, 0);
try {
  for (const template of ['classic', 'modern']) for (const mode of ['staff', 'public', 'staff-link']) for (const form of ['0', '1']) {
    await page.goto(`http://127.0.0.1:5173/tests/report-output-browser.html?template=${template}&mode=${mode}&form=${form}`);
    await page.waitForFunction(() => /^(PASS|FAIL)/.test(document.getElementById('status')?.textContent || ''), null, { timeout: 120000 });
    assert.equal(await page.locator('#status').innerText(), 'PASS', await page.locator('#checks').innerText());
    const [download] = await Promise.all([page.waitForEvent('download'), page.getByRole('button', { name: mode === 'staff' ? 'حفظ الملف التجريبي' : 'تحميل PDF', exact: true }).click()]);
    const bytes = await readFile(await download.path());
    assert.equal(bytes.length + ':' + fingerprint(bytes), await page.locator('#checksum').innerText(), 'downloaded PDF matches the shared/uploaded document bytes');
    const selector = mode === 'staff' ? '#pages img' : '.report-page-preview img';
    const sheets = await page.locator(selector).evaluateAll(images => images.map(img => img.src));
    // Decode the final raster pages used by both PDF and native print, not a
    // mocked URL or an unrendered SVG. Each repeated header must be scannable.
    await page.addScriptTag({ path: 'node_modules/jsqr/dist/jsQR.js' });
    const qrLinks = await page.evaluate(async sheets => {
      const links = [];
      for (const src of sheets) {
        const img = new Image(); img.src = src; await img.decode();
        const canvas = document.createElement('canvas');
        canvas.width = img.width; canvas.height = Math.ceil(img.height * 0.45);
        const ctx = canvas.getContext('2d'); ctx.drawImage(img, 0, 0);
        const data = ctx.getImageData(0, 0, canvas.width, canvas.height);
        links.push(window.jsQR(data.data, data.width, data.height)?.data || null);
      }
      return links;
    }, sheets);
    assert.ok(qrLinks.length > 1);
    for (const link of qrLinks) {
      assert.ok(link, `readable QR on each ${template}/${mode} sheet`);
      const url = new URL(link);
      assert.equal(url.pathname, '/portal/' + 'a'.repeat(48));
      assert.equal(url.searchParams.get('report'), '14');
      assert.equal(url.searchParams.get('form'), form);
    }
    const [popup] = await Promise.all([context.waitForEvent('page'), page.getByRole('button', { name: mode === 'staff' ? 'طباعة الملف التجريبي' : 'طباعة', exact: true }).click()]);
    await popup.waitForFunction(() => document.body.dataset.printRequested === 'true');
    assert.deepEqual(await popup.locator('.report-sheet img').evaluateAll(images => images.map(img => img.src)), sheets, 'native print preserves every preview sheet');
    await popup.close();
    console.log(`PASS ${template}/${mode}/form=${form}: preview, PDF download/upload, print and WhatsApp presentation agree`);
    if (template === 'modern' && mode === 'staff' && form === '1') await page.screenshot({ path: `${out}/unified-output.png`, fullPage: true });
  }
  // The modal must not silently reset the chosen form between operations.
  await page.getByRole('button', { name: 'اختيار العملية', exact: true }).click();
  await page.getByRole('checkbox', { name: 'الطباعة على فورمة المختبر', exact: true }).uncheck();
  await page.getByRole('button', { name: 'تنفيذ', exact: true }).click();
  await page.getByRole('dialog').waitFor({ state: 'hidden' });
  assert.equal(await page.locator('#selected-form').innerText(), 'false');
  await page.getByRole('button', { name: 'اختيار العملية', exact: true }).click();
  assert.equal(await page.getByRole('checkbox', { name: 'الطباعة على فورمة المختبر', exact: true }).isChecked(), false);
  await page.getByRole('radio', { name: /إرسال واتساب/ }).check();
  assert.equal(await page.getByRole('checkbox', { name: 'الطباعة على فورمة المختبر', exact: true }).isChecked(), false);
  // Old public report URLs remain viewable but do not mint a full-history
  // portal credential from an enumerable invoice ID.
  await page.goto('http://127.0.0.1:5173/tests/report-output-browser.html?mode=public&legacy=1');
  await page.waitForFunction(() => /^(PASS|FAIL)/.test(document.getElementById('status')?.textContent || ''), null, { timeout: 120000 });
  assert.equal(await page.locator('#status').innerText(), 'PASS', await page.locator('#checks').innerText());
  assert.equal(await page.locator('#Result .mr-qr, #Result .rs-qr').count(), 0);
  assert.deepEqual(errors, []);
  console.log('PASS switching actions and reopening preserves the form selection');
} catch (error) {
  console.log(await page.locator('body').innerText());
  await page.screenshot({ path: `${out}/output-failure.png`, fullPage: true });
  throw error;
} finally { await browser.close(); }
