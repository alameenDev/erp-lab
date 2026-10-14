import {chromium} from 'playwright';
import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';
const browser=await chromium.launch();const page=await browser.newPage({viewport:{width:1280,height:950}});
const errors=[];page.on('pageerror',e=>errors.push(e.message));
await mkdir('test-results/report-pages',{recursive:true});
try {
 await page.goto('http://127.0.0.1:5173/tests/report-form-default-browser.html');
 await page.getByRole('button',{name:'إعدادات الطباعة',exact:true}).click();
 const setting=page.locator('#reportFormDefault');await setting.waitFor();assert.ok(await setting.isChecked());await setting.uncheck();
 await page.getByRole('button',{name:/حفظ/}).last().click();
 await page.waitForFunction(()=>formFixture.requests.length===1);assert.equal(await page.evaluate(()=>formFixture.requests[0].report_form_default),'0');
 await page.reload();await page.getByRole('button',{name:'إعدادات الطباعة',exact:true}).click();await setting.waitFor();assert.equal(await setting.isChecked(),false);
 const open=async()=>{await page.locator('#open-report').click();await page.getByRole('dialog').waitFor();};
 const box=()=>page.getByRole('checkbox',{name:'الطباعة على فورمة المختبر',exact:true});
 const close=()=>page.getByRole('button',{name:'إغلاق',exact:true}).click();
 await open();assert.equal(await box().isChecked(),false);await box().check();await close();await open();assert.ok(await box().isChecked(),'manual choice stays on same invoice');await close();
 await page.evaluate(()=>{formFixture.invoices.printRecord={...formFixture.invoices.printRecord,id:15};});await open();assert.equal(await box().isChecked(),false,'new invoice starts with saved default');await close();
 await setting.check();await page.getByRole('button',{name:/حفظ/}).last().click();await page.waitForFunction(()=>formFixture.requests.length===1);await open();assert.ok(await box().isChecked(),'changed lab preference applies');
 await box().uncheck();await page.getByRole('button',{name:'معاينة التقرير',exact:true}).click();
 assert.equal(await page.evaluate(()=>formFixture.events.at(-1).withBackground),false,'manual override is the output choice');
 await page.evaluate(()=>{formFixture.hasBackground.value=false;formFixture.invoices.printRecord.id=16;});await open();assert.equal(await box().count(),0);await page.getByRole('button',{name:'تنفيذ',exact:true}).click();
 assert.equal(await page.evaluate(()=>formFixture.events.at(-1).withBackground),false,'no background cannot be enabled');
 await page.locator('#reportFormDefault').scrollIntoViewIfNeeded();await page.screenshot({path:'test-results/report-pages/form-default-settings.png',animations:'disabled'});
 // Real canonical renderer and existing PDF/download/upload/WhatsApp checks.
 for(const query of ['mode=public&default=0','mode=public&default=0&form=1','mode=public&default=1&form=0','mode=staff&default=0']){
  await page.goto('http://127.0.0.1:5173/tests/report-output-browser.html?'+query);
  await page.waitForFunction(()=>/^(PASS|FAIL)/.test(document.querySelector('#status')?.textContent||''),null,{timeout:60000});
  assert.equal(await page.locator('#status').innerText(),'PASS',await page.locator('#checks').innerText());
 }
 assert.deepEqual(errors,[]);console.log('PASS persisted lab default, per-invoice override, missing background and direct/staff output parity');
}finally{await browser.close();}
