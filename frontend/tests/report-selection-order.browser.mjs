import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
const browser = await chromium.launch();
await mkdir('test-results/report-pages', {recursive:true});
let page;
try {
  page = await browser.newPage({viewport:{width:1360,height:1000}});
  const errors=[]; page.on('pageerror',e=>errors.push(e.message));
  // Real invoice add/remove/save methods; type-local catalog IDs may collide.
  await page.goto('http://127.0.0.1:5173/tests/invoice-referrals-browser.html');
  await page.waitForFunction(()=>window.invoiceFixture?.form());
  await page.evaluate(()=>{
    const f=invoiceFixture.form();
    f.addSelection('test',invoiceFixture.test,false);
    f.addSelection('package',invoiceFixture.packages[0],false);
    f.addSelection('test',invoiceFixture.group,false);
    f.addSelection('culture',{id:41,name:'Standalone culture',price:1000},false);
    f.addSelection('test',{id:12,name:'Last test',price:1000},false);
    f.addSelection('test',invoiceFixture.test,false); // duplicate must not move it
  });
  assert.deepEqual(await page.locator('[data-invoice-selection-order] li').allTextContents().then(rows=>rows.map(s=>s.trim().replace(/\s+/g,' '))), ['1. Glucose','2. Mixed package','3. Complete Blood Count','4. Standalone culture','5. Last test']);
  await page.evaluate(async()=>{const f=invoiceFixture.form();f.responseData={id:501,name:'Synthetic patient'};await f.create();});
  await page.waitForFunction(()=>invoiceFixture.state.saved===1);
  assert.deepEqual(await page.evaluate(()=>invoiceFixture.state.lastSaved.selection_order), [
    {field:'tests',id:11},{field:'packages',id:1},{field:'test_groups',id:21},{field:'cultures',id:41},{field:'tests',id:12}
  ]);
  for (const mode of ['group','attached']) {
    await page.goto('http://127.0.0.1:5173/tests/report-formula-editor-browser.html?order=1&mode='+mode);
    await page.locator('[data-result-selection-order] li').first().waitFor();
    const names=await page.locator('[data-result-selection-order] li').allTextContents();
    assert.match(names[0],/First chosen/);assert.match(names[2],/Last chosen/);
    // Default all-results quick-entry rows must interleave the parent contents.
    const rows=await page.locator('[data-test-row]').evaluateAll(nodes=>nodes.map(n=>n.getAttribute('aria-label')));
    assert.ok(rows.length >= 3); assert.match(rows[0],/First chosen/); assert.match(rows.at(-1),/Last chosen/);
    await page.locator('[data-result-selection-order] button').nth(1).click();
    await page.screenshot({path:`test-results/report-pages/order-editor-${mode}.png`,animations:'disabled'});
  }
  for (const query of ['classic','modern','classic&merged=1','modern&merged=1']) {
    await page.goto('http://127.0.0.1:5173/tests/report-selection-browser.html?order=1&template='+query);
    const dialog=page.getByRole('dialog');await dialog.waitFor();
    await dialog.getByRole('button',{name:'معاينة التقرير',exact:true}).click();
    await page.waitForFunction(()=>/^(PASS|FAIL)/.test(document.querySelector('#status').textContent),null,{timeout:60000});
    assert.equal(await page.locator('#status').innerText(),'PASS 1');
    const text=await page.evaluate(()=>selectionFixture.executions[0].text);
    const names=['Standalone pending','Package direct','Standalone culture','Compound report','Group ready','Standalone ready','Input'];
    const positions=names.map(n=>text.indexOf(n));
    assert.ok(positions.every(p=>p>=0),text);
    assert.deepEqual([...positions].sort((a,b)=>a-b),positions,'report follows mixed selection order: '+query);
    assert.ok(text.indexOf('Nested calculated') < text.indexOf('Standalone culture'),'entire package stays in its slot');
    await dialog.getByRole('button',{name:'إغلاق',exact:true}).click();
    await page.locator('#pages').screenshot({path:`test-results/report-pages/order-${query.replace('&merged=1','-merged')}.png`,animations:'disabled'});
  }
  assert.deepEqual(errors,[]);
  console.log('PASS invoice selection order, saved payload, editor navigation and both report templates in normal/merged mode');
} catch(error) {await page?.screenshot({path:'test-results/report-pages/order-failure.png'});throw error;}
finally {await browser.close();}
