import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
const browser = await chromium.launch();
await mkdir('test-results/report-pages', { recursive: true });
let page;
try {
 for (const query of ['?template=classic','?template=modern&merged=1']) {
  page = await browser.newPage({ viewport: { width: 1280, height: 950 } });
  const errors = []; page.on('pageerror', e => errors.push(e.message));
  await page.goto('http://127.0.0.1:5173/tests/report-selection-browser.html'+query);
  const dialog = page.getByRole('dialog'), checkbox = name => dialog.getByRole('checkbox', { name, exact: true });
  await dialog.waitFor();
  assert.equal(await dialog.getByRole('checkbox').count(), 18);
  for (const box of await dialog.getByRole('checkbox').all()) assert.ok(await box.isChecked(), 'every ready and pending item starts selected');
  await checkbox('Group pending').uncheck();
  assert.ok(await checkbox('Group A').evaluate(el => el.indeterminate));
  await checkbox('Group A').check(); assert.ok(await checkbox('Group pending').isChecked());
  await checkbox('Package A').uncheck();
  assert.equal(await dialog.locator('[data-report-selection="packages:0"] input:checked').count(), 0);
  await checkbox('Package A').check(); assert.ok(await checkbox('Nested calculated').isChecked());
  await dialog.getByRole('button', { name: 'إلغاء تحديد الكل', exact: true }).click();
  assert.ok(await dialog.getByRole('button', { name: 'تنفيذ', exact: true }).isDisabled());
  for (const name of ['Standalone pending','Group ready','Calculated','Nested calculated']) await checkbox(name).check();
  await dialog.getByRole('searchbox').fill('Nested calculated');
  await checkbox('Nested calculated').waitFor();
  await dialog.getByRole('searchbox').fill('');
  assert.ok(await checkbox('Standalone pending').isChecked(), 'search preserves hidden choices');
  const run = async (action, number) => {
    if (action === 'preview') await dialog.getByRole('button', { name: 'معاينة التقرير', exact: true }).click();
    else {
      await dialog.locator(`input[type="radio"][value="${action}"]`).check();
      await dialog.getByRole('button', { name: 'تنفيذ', exact: true }).click();
    }
    await page.waitForFunction(n => document.querySelector('#status').textContent === 'PASS '+n || document.querySelector('#status').textContent.startsWith('FAIL'), number, { timeout: 60000 });
    assert.equal(await page.locator('#status').innerText(), 'PASS '+number);
    const data = await page.evaluate(() => { const x=selectionFixture.executions.at(-1);return {text:x.text,record:x.record,selection:x.selection,size:x.output.blob.size}; });
    for (const name of ['Standalone pending','Group ready','Calculated','Nested calculated']) assert.ok(data.text.includes(name), name+' appears');
    for (const name of ['Standalone ready','Compound report','Standalone culture','Group pending','Group culture','Package direct','Package input','Package pending','Package culture','EXCLUDED-HISTORY']) assert.ok(!data.text.includes(name), name+' stays excluded');
    assert.ok(data.text.includes('8') && data.text.includes('6'), 'formula values survive hidden operands');
    assert.ok(data.size > 1000); assert.equal(data.record.suppress_report_qr, true);
    assert.equal(await page.evaluate(() => JSON.stringify(selectionFixture.invoices.printRecord) === JSON.stringify(selectionFixture.source)), true, 'full saved results restored after each action');
    assert.ok(await checkbox('Standalone pending').isChecked()); assert.equal(await checkbox('Group pending').isChecked(), false);
  };
  await run('preview', 1);
  for (const [i, action] of ['print','download','whatsapp','print-download','whatsapp-download'].entries()) await run(action, i+2);
  const evidence = await page.evaluate(() => ({ sameOutput: selectionFixture.executions.every(x => x.output === selectionFixture.executions[0].output), events: selectionFixture.events, requests: selectionFixture.requests }));
  assert.ok(evidence.sameOutput, 'all actions consume the same prepared selected PDF');
  for (const [, url] of evidence.events.filter(e => e[0] === 'whatsapp')) assert.ok(!new URL(url).searchParams.get('text').includes('/portal/'), 'partial message cannot expose full results');
  assert.equal(evidence.requests.filter(x=>x==='/portal/generate').length, 0);
  await dialog.getByRole('button', { name: 'إغلاق', exact: true }).click();
  await page.locator('#pages').screenshot({path:`test-results/report-pages/selection-${query.includes('modern')?'modern':'classic'}-output.png`,animations:'disabled'});
  await page.locator('#open').click();
  for (const box of await dialog.getByRole('checkbox').all()) assert.ok(await box.isChecked(), 'reopening resets to all selected');
  await page.setViewportSize({width:390,height:844});
  const panel = dialog.locator('.relative').first(), box = await panel.boundingBox();
  assert.ok(box.x >= 0 && box.x + box.width <= 391);
  await page.screenshot({path:`test-results/report-pages/selection-${query.includes('modern')?'modern':'classic'}-mobile.png`,animations:'disabled'});
  assert.deepEqual(errors, []); await page.close();
 }
 console.log('PASS hierarchical report selection, ready/pending defaults, parent/child toggles, search, formula dependencies, all output actions, partial sharing, reopen reset and mobile');
} catch (error) { await page?.screenshot({path:'test-results/report-pages/selection-failure.png',animations:'disabled'});throw error; }
finally { await browser.close(); }
