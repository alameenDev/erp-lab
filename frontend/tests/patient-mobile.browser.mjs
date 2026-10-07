import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
const browser = await chromium.launch({headless:true});
const page = await browser.newPage({viewport:{width:390,height:844}});
const errors = [];
page.on('pageerror', e => errors.push(e.message));
const methods = [];
let failNext = false;
await page.route('**/api/portal/synthetic-patient-token/mobile-link', async route => {
  const request = route.request();
  methods.push(request.method());
  assert.equal(request.headers().authorization, undefined);
  const response = request.method() === 'GET' ? {available:true,has_phone:true,phone_hint:'••••4567'}
    : request.method() === 'DELETE' ? {message:'تم إلغاء ربط أجهزة التطبيق لهذا الرابط.'}
    : {code:'123456789012',expires_at:new Date(Date.now()+600000).toISOString(),phone_hint:'••••4567'};
  if (failNext && request.method() === 'POST') {
    failNext = false;
    await route.fulfill({status:429,contentType:'application/json',body:JSON.stringify({message:'محاولات كثيرة. يرجى المحاولة لاحقاً.'})});
  } else await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify(response)});
});
await page.goto('http://127.0.0.1:5173/tests/patient-mobile-browser.html');
await page.getByRole('button',{name:'إنشاء رمز ربط التطبيق',exact:true}).waitFor();
failNext = true;
await page.getByRole('button',{name:'إنشاء رمز ربط التطبيق',exact:true}).click();
await page.getByRole('alert').waitFor();
await page.getByRole('button',{name:'إنشاء رمز ربط التطبيق',exact:true}).click();
await page.getByLabel('رمز الربط',{exact:true}).waitFor();
assert.equal((await page.getByLabel('رمز الربط',{exact:true}).textContent()).trim(),'1234 5678 9012');
assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth > innerWidth),false);
await fs.mkdir('test-results/patient-mobile',{recursive:true});
await page.screenshot({path:'test-results/patient-mobile/pairing-mobile.png',fullPage:true});
await page.getByRole('button',{name:'إلغاء ربط أجهزة التطبيق لهذا الملف',exact:true}).click();
await page.getByRole('button',{name:'تأكيد إلغاء ربط أجهزة التطبيق',exact:true}).click();
await page.getByRole('status').waitFor();
assert.equal(await page.getByLabel('رمز الربط',{exact:true}).count(),0);
assert.deepEqual(methods,['GET','POST','POST','DELETE']);
assert.deepEqual(errors,[]);
await browser.close();
console.log('Mobile pairing: issue, retry, revocation, credential isolation and 390px layout passed.');
