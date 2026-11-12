import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
const out = 'test-results/report-pages'; await mkdir(out, { recursive: true });
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 1100 } }), errors = [];
page.on('pageerror', error => errors.push(error.message));
page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
const printSettings = () => page.getByRole('button', { name: 'إعدادات الطباعة', exact: true }).click();
const globalToggle = () => page.getByRole('checkbox', { name: 'إظهار النتائج السابقة في التقرير', exact: true });
const capture = async label => {
  const result = await page.evaluate(() => cbcFixture.prepare());
  assert.equal(result.pdf, '%PDF-'); assert.equal(result.sameSheets, true); assert.equal(result.changed, false); assert.equal(result.invalidated, true);
  const png = await page.evaluate(() => cbcFixture.pages()[0]);
  await writeFile(`${out}/cbc-${label}.png`, Buffer.from(png.split(',')[1], 'base64'));
  console.log(`PASS CBC ${label}: ${result.count} sheets, fast/native/PDF output parity and unchanged results`);
  return result;
};
try {
  // Actual result page, including standalone/group/package branches and auto-save.
  await page.goto('http://127.0.0.1:5173/tests/report-comments-browser.html?mode=editor&cbc-history');
  await page.waitForFunction(() => fixture.ready && fixture.state().autoSaveReady);
  for (const section of ['tests', 'test_groups', 'packages']) {
    await page.evaluate(section => { fixture.state().activeSection = section; fixture.state().activeTestTab = 0; }, section);
    const editor = page.locator('[data-cbc-result-editor]:visible'); await editor.waitFor();
    const toggle = editor.getByRole('checkbox');
    assert.equal(await toggle.isChecked(), false); assert.equal(await editor.locator('[data-cbc-last-result]').count(), 0);
    await toggle.check();
    assert.equal(await editor.locator('[data-cbc-last-result]').count(), 4);
    const wbc = editor.locator('tr').filter({ has: page.getByText('WBC', { exact: true }) });
    const id = section === 'tests' ? 3 : section === 'test_groups' ? 10 : 11;
    assert.ok((await wbc.innerText()).includes(`OLD-${id}-WBC-1`));
    assert.ok((await wbc.innerText()).includes('29/09/2026'));
    assert.ok((await editor.innerText()).includes('لا توجد نتيجة سابقة'));
    assert.equal(await page.locator('.result-history:visible').count(), 0, 'no duplicate permanently visible CBC history');
    await page.waitForFunction(({ section, id }) => fixture.requests.some(request => {
      if (!request.url.includes('update-result')) return false;
      const body = typeof request.data === 'string' ? JSON.parse(request.data) : request.data;
      const list = typeof body[section] === 'string' ? JSON.parse(body[section]) : body[section];
      const rows = section === 'tests' ? list : list?.flatMap(group => group.tests || []);
      return rows?.some(row => Number(row.test_id_fk || row.id) === id && row.last_result === true);
    }), { section, id });
    await toggle.uncheck(); assert.equal(await editor.locator('[data-cbc-last-result]').count(), 0);
  }
  console.log('PASS CBC editor visibility, exact component matching and saved selection in tests/groups/packages');
  await page.goto('http://127.0.0.1:5173/tests/report-cbc-history-browser.html');
  await page.waitForFunction(() => cbcFixture.ready); await printSettings();
  assert.equal(await page.locator('#cbcHistoryLayout').inputValue(), 'inline');
  assert.equal(await page.locator('#Result [data-cbc-last-result]').count(), 0);
  await globalToggle().check();
  await page.waitForFunction(() => document.querySelectorAll('#Result th[data-cbc-last-result]').length === 3);
  for (const table of await page.locator('#Result .template-section table').all()) {
    assert.deepEqual(await table.locator('thead th').allTextContents(), ['Test', 'Result', 'Last Result', 'Unit', 'Reference Range']);
    assert.ok((await table.innerText()).includes('previous-unit'));
    assert.equal(await table.locator('tbody tr').count(), 4);
    assert.ok(await table.locator('tbody td:nth-child(2) span[style*="color"]').count(), 'current flags preserved');
  }
  assert.equal(await page.locator('#Result .result-history').count(), 0);
  await capture('inline-classic');
  await page.locator('#cbcHistoryLayout').selectOption('separate_page');
  await page.locator('#previousResultsCount').selectOption('2');
  await page.getByRole('button', { name: 'حفظ', exact: true }).click();
  await page.waitForFunction(() => document.getElementById('save-count').textContent === '1');
  await page.reload(); await page.waitForFunction(() => cbcFixture.ready); await printSettings();
  assert.equal(await page.locator('#cbcHistoryLayout').inputValue(), 'separate_page');
  assert.equal(await page.locator('#previousResultsCount').inputValue(), '2');
  assert.equal(await page.locator('#Result [data-cbc-last-result]').count(), 0);
  assert.equal(await page.locator('#Result .cbc-history-section').count(), 3);
  assert.equal(await page.locator('#Result .cbc-history-section tbody tr').count(), 18);
  const separate = await capture('separate-classic'); assert.equal(separate.count, 6);
  const markers = await page.evaluate(async () => {
    const results = [];
    for (const src of cbcFixture.pages()) {
      const img = new Image(); img.src = src; await img.decode();
      const canvas = document.createElement('canvas'); canvas.width = img.width; canvas.height = img.height;
      const ctx = canvas.getContext('2d'); ctx.drawImage(img, 0, 0);
      const data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
      let current = 0, history = 0;
      for (let i = 0; i < data.length; i += 4) {
        if (data[i] > 240 && data[i + 1] < 10 && data[i + 2] > 240) current++;
        if (data[i] < 10 && data[i + 1] > 240 && data[i + 2] > 240) history++;
      }
      results.push({ current: current > 10, history: history > 10 });
    }
    return results;
  });
  assert.deepEqual(markers, Array.from({ length: 3 }, () => [{ current: true, history: false }, { current: false, history: true }]).flat(), 'actual sheets alternate current CBC / previous CBC, never mixed');
  await page.locator('[data-report-history-settings]').scrollIntoViewIfNeeded(); await page.screenshot({ path: `${out}/cbc-history-settings.png` });
  await page.getByRole('radio', { name: /النموذج الجديد/ }).check();
  await page.evaluate(() => { cbcFixture.store.settings.print_black_white = true; });
  await capture('separate-modern-monochrome');
  await globalToggle().uncheck();
  await page.evaluate(() => cbcFixture.setVisible(true));
  await page.waitForFunction(() => document.querySelectorAll('#Result .cbc-history-section').length === 3);
  await page.evaluate(() => cbcFixture.setVisible(false));
  await page.waitForFunction(() => !document.querySelector('#Result .cbc-history-section'));
  const hidden = await capture('hidden-modern'); assert.equal(hidden.count, 3);
  await page.evaluate(() => { cbcFixture.setVisible(true); cbcFixture.invoices.printRecord.result_history = {}; });
  await page.waitForFunction(() => !document.querySelector('#Result .cbc-history-section'));
  const empty = await capture('no-previous-results'); assert.equal(empty.count, 3, 'no empty history sheets');
  await page.evaluate(() => { cbcFixture.longPanel(); cbcFixture.store.settings.print_black_white = false; });
  await page.locator('#cbcHistoryLayout').selectOption('inline');
  const long = await capture('full-panel-inline'); assert.ok(long.count >= 1);
  assert.equal(await page.locator('#Result .template-section tbody tr').count(), 21);
  await page.evaluate(() => { cbcFixture.items()[0].show_unit = false; });
  await page.waitForFunction(() => !document.querySelector('#Result').textContent.includes('previous-unit'));
  assert.ok(!(await page.locator('#Result').innerText()).includes('current-unit'));
  await page.setViewportSize({ width: 390, height: 844 });
  await page.locator('[data-report-history-settings]').scrollIntoViewIfNeeded();
  assert.equal(await page.locator('[data-report-history-settings]').evaluate(el => el.scrollWidth <= el.clientWidth + 1), true);
  await page.screenshot({ path: `${out}/cbc-history-settings-mobile.png` });
  assert.deepEqual(errors, []);
} catch (error) {
  await writeFile(`${out}/cbc-history-failure.txt`, `${error.stack}\n${JSON.stringify(errors)}`);
  await page.screenshot({ path: `${out}/cbc-history-failure.png`, fullPage: true });
  throw error;
} finally { await browser.close(); }
