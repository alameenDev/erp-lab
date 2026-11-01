import { chromium } from 'playwright';
import assert from 'node:assert/strict';

const browser=await chromium.launch();
const page=await browser.newPage({viewport:{width:1200,height:900}});
const problems=[];
page.on('pageerror', e=>problems.push(e.message));
const size=()=>page.evaluate(()=>reasonFixture.requests.length);
const waitSize=(count)=>page.waitForFunction(count=>reasonFixture.requests.length===count,count);
const reason='تصحيح خطأ في تسجيل الفاتورة بناء على مراجعة مدير المختبر';
try{
  await page.goto('http://127.0.0.1:5173/tests/mutation-reason-browser.html',{waitUntil:'networkidle'});
  await page.getByRole('button',{name:'تعديل الفاتورة'}).click();
  await page.locator('.swal2-popup').waitFor();
  assert.equal(await size(),0,'no mutation before reason');
  assert.match(await page.locator('.swal2-title').innerText(),/سبب التعديل/);
  assert.ok(await page.locator('.swal2-container').evaluate(node=>Number(node.style.zIndex)>1000000),'modal overlays all app dialogs');
  await page.locator('.swal2-confirm').click();
  assert.match(await page.locator('.swal2-validation-message').innerText(),/10/);
  assert.equal(await size(),0,'empty reason never saves data');
  await page.locator('.swal2-textarea').fill(reason);
  await page.locator('.swal2-confirm').click();
  await waitSize(1);
  await page.waitForFunction(()=>!document.querySelector('.swal2-container'));
  const edited=await page.evaluate(()=>reasonFixture.requests[0]);
  assert.equal(edited.method,'put');
  assert.equal(edited.data.change_reason,reason);

  await page.getByRole('button',{name:'حذف الفاتورة'}).click();
  await page.locator('.swal2-popup').waitFor();
  assert.equal(await size(),1);
  await page.locator('.swal2-cancel').click();
  await page.waitForFunction(()=>reasonFixture.errors.length===1);
  assert.equal(await size(),1,'cancelled delete must never reach API');
  assert.equal(await page.evaluate(()=>reasonFixture.errors[0].cancelled),true);

  await page.getByRole('button',{name:'حذف الفاتورة'}).click();
  await page.locator('.swal2-textarea').fill(reason);
  await page.locator('.swal2-confirm').click();
  await waitSize(2);
  assert.equal(await page.evaluate(()=>reasonFixture.requests[1].data.change_reason),reason);

  await page.getByRole('button',{name:'حفظ خصم الفاتورة'}).click();
  await page.locator('.swal2-popup').waitFor();
  assert.match(await page.locator('.swal2-title').innerText(),/سبب الخصم/);
  assert.equal(await size(),2);
  await page.locator('.swal2-textarea').fill(reason);
  await page.locator('.swal2-confirm').click();
  await waitSize(3);
  assert.equal(await page.evaluate(()=>reasonFixture.requests[2].data.change_reason),reason);

  await page.getByRole('button',{name:'حفظ نتائج التحاليل'}).click();
  await page.locator('.swal2-popup').waitFor();
  assert.equal(await size(),3);
  await page.locator('.swal2-textarea').fill(reason);
  await page.locator('.swal2-confirm').click();
  await waitSize(4);
  assert.equal(await page.evaluate(()=>reasonFixture.requests[3].data.change_reason),reason);

  await page.getByRole('button',{name:'حفظ تلقائي'}).click();
  await waitSize(5);
  assert.equal(await page.locator('.swal2-popup').count(),0);
  assert.deepEqual(problems,[]);
  console.log('PASS mandatory edit/delete/discount modal, cancellation prevents API calls, multipart reason, silent autosave exempt');
}finally{await browser.close();}
