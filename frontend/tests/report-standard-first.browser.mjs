import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1300, height: 1000 } });
const errors = [];
page.on('pageerror', e => errors.push(e.message));
const dialog = page.getByRole('dialog');
await mkdir('test-results/report-pages', { recursive: true });
let count = 0;
async function run(action = 'preview') {
  if (action === 'preview') await dialog.getByRole('button', { name: 'معاينة التقرير', exact: true }).click();
  else {
    await dialog.locator('input[type="radio"][value="' + action + '"]').check();
    await dialog.getByRole('button', { name: 'تنفيذ', exact: true }).click();
  }
  await page.waitForFunction(n => document.querySelector('#status').textContent === 'PASS ' + n || document.querySelector('#status').textContent.startsWith('FAIL'), ++count, { timeout: 60000 });
  assert.equal(await page.locator('#status').innerText(), 'PASS ' + count);
  return page.evaluate(async () => {
    const output = selectionFixture.executions.at(-1).output;
    const membership = [];
    for (const src of output.pages) {
      const img = new Image(); img.src = src; await img.decode();
      const canvas = document.createElement('canvas'); canvas.width = img.width; canvas.height = img.height;
      const ctx = canvas.getContext('2d'); ctx.drawImage(img, 0, 0);
      const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
      const colors = [[238, 103, 11], [217, 19, 173], [19, 91, 231]], counts = [0, 0, 0];
      for (let i = 0; i < pixels.length; i += 4) colors.forEach((rgb, c) => { if (rgb.every((v, j) => pixels[i + j] === v)) counts[c]++; });
      membership.push(['ordinary', 'cbc', 'alt'].filter((_, i) => counts[i] > 50));
    }
    return { membership, sections: selectionFixture.executions.at(-1).sections, pdf: await output.blob.slice(0, 5).text() };
  });
}
try {
  for (const template of ['classic', 'modern']) for (const merged of [false, true]) {
    await page.goto('http://127.0.0.1:5173/tests/report-selection-browser.html?template=' + template + (merged ? '&merged=1' : ''));
    await dialog.waitFor(); count = 0;
    await dialog.getByRole('button', { name: 'إغلاق', exact: true }).click();
    await page.evaluate(async () => {
      const { withInterleavedCbc } = await import('/tests/report-standard-first-data.mjs');
      const f = selectionFixture;
      f.invoices.printRecord = withInterleavedCbc(f.source);
      window.originalReport = JSON.stringify(f.invoices.printRecord);
      f.settings.settings.print_table_config = { body_font_size: 12, header_font_size: 12, cell_padding: 2 };
      f.beforeCapture = source => {
        source.querySelectorAll('[data-page-marker]').forEach(n => n.remove());
        source.querySelectorAll('[data-report-section] table > tbody > tr').forEach(row => {
          const cell = row.children[0]; if (!cell) return;
          const name = cell.textContent.trim();
          const rgb = row.closest('.template-section') ? [217, 19, 173] : name === 'S.GPT (ALT)' ? [19, 91, 231] : [238, 103, 11];
          const marker = document.createElement('span'); marker.dataset.pageMarker = '';
          marker.style.cssText = 'display:inline-block;width:16px;height:8px;background:rgb(' + rgb + ')!important';
          cell.prepend(marker);
        });
      };
    });
    await page.locator('#open').click();
    for (const action of ['preview', 'print', 'download', 'whatsapp', 'print-download', 'whatsapp-download']) {
      const result = await run(action);
      assert.deepEqual(result.membership, [['ordinary', 'alt'], ['cbc']], 'ALT is on page one; every CBC row is on page two: ' + action);
      assert.equal(result.pdf, '%PDF-');
      assert.deepEqual(result.sections.flatMap(s => s.tests), ['RBS', 'HbA1c', 'Creatinine', 'S.GPT (ALT)']);
      assert.equal(result.sections.at(-1).isolated, true);
    }
    assert.equal(await page.evaluate(() => JSON.stringify(selectionFixture.invoices.printRecord) === originalReport), true);
    assert.equal(await page.evaluate(() => selectionFixture.executions.every(x => x.output === selectionFixture.executions[0].output)), true);
    // Keep a small rendered proof using synthetic data only.
    const png = await page.evaluate(() => selectionFixture.executions[0].output.pages[0]);
    await writeFile('test-results/report-pages/standard-first-' + template + (merged ? '-merged' : '') + '.png', Buffer.from(png.split(',')[1], 'base64'));
    await dialog.getByRole('checkbox', { name: 'CBC', exact: true }).uncheck();
    assert.deepEqual((await run()).membership, [['ordinary', 'alt']], 'omitted CBC creates no blank page');
    await dialog.getByRole('button', { name: 'إلغاء تحديد الكل', exact: true }).click();
    await dialog.getByRole('checkbox', { name: 'CBC', exact: true }).check();
    assert.deepEqual((await run()).membership, [['cbc']], 'CBC alone creates no blank first page');
    // Explicit template page breaks survive moving the template to the end.
    await dialog.getByRole('button', { name: 'إغلاق', exact: true }).click();
    await page.evaluate(() => {
      const cbc = selectionFixture.invoices.printRecord.tests.find(t => t.name === 'CBC');
      cbc.content.html += '<div class="page-break"></div><h2>CBC continuation</h2><table><tbody><tr><td>Extra CBC field</td><td>4</td></tr></tbody></table>';
    });
    await page.locator('#open').click();
    assert.deepEqual((await run()).membership, [['ordinary', 'alt'], ['cbc'], ['cbc']], 'explicit CBC chunks remain dedicated pages after ordinary results');
    console.log('PASS standard-first ' + template + (merged ? ' merged' : ' named') + ': all outputs, actual page pixels, ALT page one, CBC isolation, partial selection and explicit breaks');
  }
  assert.deepEqual(errors, []);
} catch (error) {
  await page.screenshot({ path: 'test-results/report-pages/standard-first-failure.png', fullPage: true });
  throw error;
} finally { await browser.close(); }
