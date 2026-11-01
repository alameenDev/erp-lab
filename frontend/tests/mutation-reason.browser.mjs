import { chromium } from 'playwright';
import assert from 'node:assert/strict';

const browser=await chromium.launch();
const page=await browser.newPage({viewport:{width:1200,height:900}});
const problems=[];
page.on('pageerror', e=>problems.push(e.message));
const size=()=>page.evaluate(()=>reasonFixture.requests.length);
const waitSize=(count)=>page.waitForFunction(count=>reasonFixture.requests.length===count,count);
const reason='س'; // Even one non-whitespace character is permitted; no minimum length.
try{
  await page.goto('http://127.0.0.1:5173/tests/mutation-reason-browser.html',{waitUntil:'networkidle'});
  await page.getByRole('button',{name:'تعديل الفاتورة'}).click();
  await page.locator('[data-mutation-reason-dialog]').waitFor();
  assert.equal(await size(),0,'no mutation before reason');
  assert.match(await page.locator('#mutation-reason-title').innerText(),/سبب التعديل/);
  assert.equal(await page.locator('[data-mutation-reason-dialog]').evaluate(node=>node.open),true,'native top-layer dialog is visible');
  await page.locator('[data-mutation-reason-confirm]').click();
  assert.match(await page.locator('[data-mutation-reason-error]').innerText(),/السبب/);
  assert.equal(await size(),0,'empty reason never saves data');
  await page.locator('[data-mutation-reason-input]').fill('   ');
  await page.locator('[data-mutation-reason-confirm]').click();
  assert.equal(await size(),0,'whitespace-only reason is rejected too');
  await page.locator('[data-mutation-reason-input]').fill(reason);
  await page.locator('[data-mutation-reason-confirm]').click();
  await waitSize(1);
  await page.waitForFunction(()=>!document.querySelector('[data-mutation-reason-dialog]'));
  const edited=await page.evaluate(()=>reasonFixture.requests[0]);
  assert.equal(edited.method,'put');
  assert.equal(edited.data.change_reason,reason);

  await page.getByRole('button',{name:'حذف الفاتورة'}).click();
  await page.locator('[data-mutation-reason-dialog]').waitFor();
  assert.equal(await size(),1);
  await page.locator('[data-mutation-reason-cancel]').click();
  await page.waitForFunction(()=>!document.querySelector('[data-mutation-reason-dialog]'));
  assert.equal(await size(),1,'cancelled delete must never reach API');

  await page.getByRole('button',{name:'حذف الفاتورة'}).click();
  await page.locator('[data-mutation-reason-input]').fill(reason);
  await page.locator('[data-mutation-reason-confirm]').click();
  await waitSize(2);
  assert.equal(await page.evaluate(()=>reasonFixture.requests[1].data.change_reason),reason);

  await page.getByRole('button',{name:'حفظ خصم الفاتورة'}).click();
  await page.locator('[data-mutation-reason-dialog]').waitFor();
  assert.match(await page.locator('#mutation-reason-title').innerText(),/سبب الخصم/);
  assert.equal(await size(),2);
  await page.locator('[data-mutation-reason-input]').fill(reason);
  await page.locator('[data-mutation-reason-confirm]').click();
  await waitSize(3);
  assert.equal(await page.evaluate(()=>reasonFixture.requests[2].data.change_reason),reason);

  await page.getByRole('button',{name:'حفظ نتائج التحاليل'}).click();
  await page.locator('[data-mutation-reason-dialog]').waitFor();
  assert.equal(await size(),3);
  await page.locator('[data-mutation-reason-input]').fill(reason);
  await page.locator('[data-mutation-reason-confirm]').click();
  await waitSize(4);
  assert.equal(await page.evaluate(()=>reasonFixture.requests[3].data.change_reason),reason);

  await page.getByRole('button',{name:'حفظ تلقائي'}).click();
  await waitSize(5);
  assert.equal(await page.locator('[data-mutation-reason-dialog]').count(),0);
  assert.deepEqual(problems,[]);
  console.log('PASS mandatory edit/delete/discount modal, cancellation prevents API calls, multipart reason, silent autosave exempt');
}finally{await browser.close();}
