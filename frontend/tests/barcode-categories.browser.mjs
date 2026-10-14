import { chromium } from 'playwright';
import { createServer } from 'vite';
import { mkdir } from 'node:fs/promises';
import assert from 'node:assert/strict';
const out='barcode-qa';await mkdir(out,{recursive:true});
const server=await createServer({server:{host:'127.0.0.1',port:5184,strictPort:true}});
let browser,page;
try {
 await server.listen();browser=await chromium.launch({headless:true});
 page=await browser.newPage({viewport:{width:1360,height:1000}});const errors=[];
 page.on('pageerror',error=>errors.push(error.message));
 await page.addInitScript(()=>{
  window.print=function(){window.top.__prints??=[];window.top.__prints.push(document.documentElement.outerHTML);window.dispatchEvent(new Event('afterprint'));};
  window.alert=message=>{window.__alerts??=[];window.__alerts.push(message);};
 });
 await page.goto('http://127.0.0.1:5184/tests/fixtures/barcode-categories.html');
 const dialog=page.locator('dialog[open]');
 const copies=name=>dialog.getByRole('spinbutton',{name:'عدد نسخ '+name,exact:true});
 const print=()=>dialog.getByRole('button',{name:'طباعة الباركود',exact:true});
 await page.locator('#open-a').click();await copies('EDTA').waitFor();
 assert.equal(await dialog.locator('[data-barcode-sample]').count(),3);
 assert.equal(await dialog.getByText('صورة الدم الكاملة',{exact:true}).count(),1);
 assert.equal(await dialog.getByText('سكر الدم',{exact:true}).count(),1);
 assert.equal(await copies('EDTA').inputValue(),'1');
 assert.equal(await page.evaluate(()=>window.__prints?.length||0),0);
 await dialog.getByRole('button',{name:'زيادة نسخ EDTA',exact:true}).click();
 await dialog.getByRole('button',{name:'زيادة نسخ EDTA',exact:true}).click();
 await copies('Serum').fill('2');
 await dialog.getByRole('button',{name:'تقليل نسخ Urine',exact:true}).click();
 assert.match(await dialog.locator('[aria-live]').innerText(),/5/);
 await page.screenshot({path:`${out}/categories-desktop.png`,fullPage:true});
 await print().click();await page.waitForFunction(()=>window.__prints?.length===1);
 const result=await page.evaluate(()=>{
  const doc=new DOMParser().parseFromString(window.__prints[0],'text/html');
  return [...doc.querySelectorAll('.barcode-label')].map(label=>({barcode:label.querySelector('svg').getAttribute('aria-label'),sample:label.querySelector('[data-label-field=sample]').textContent.trim(),tests:label.querySelector('[data-label-field=tests]').textContent.trim()}));
 });
 assert.equal(result.length,5);assert.ok(result.every(x=>x.barcode==='0001234'));
 assert.equal(result.filter(x=>x.tests==='CBC, HbA1c').length,3);assert.equal(result.filter(x=>x.tests==='GLU').length,2);
 assert.ok(!result.some(x=>x.sample==='Urine'));
 for(const name of ['EDTA','Serum','Urine'])await copies(name).fill('0');
 assert.equal(await print().isDisabled(),true);
 await dialog.getByRole('button',{name:'إعادة العدد الافتراضي',exact:true}).click();
 assert.equal(await copies('EDTA').inputValue(),'1');
 await copies('EDTA').fill('99');await copies('Serum').focus();assert.equal(await copies('EDTA').inputValue(),'20');
 await copies('EDTA').fill('-3');await copies('Serum').focus();assert.equal(await copies('EDTA').inputValue(),'0');
 await dialog.getByRole('button',{name:'إعادة العدد الافتراضي',exact:true}).click();
 await page.setViewportSize({width:390,height:844});
 assert.ok(await dialog.evaluate(node=>node.scrollWidth<=node.clientWidth+1));
 await page.screenshot({path:`${out}/categories-mobile.png`,fullPage:true});
 await page.keyboard.press('Escape');await dialog.waitFor({state:'hidden'});
 assert.equal(await page.locator('#open-a').evaluate(el=>el===document.activeElement),true);
 await page.locator('#open-b').click();await copies('Serum').waitFor();
 assert.equal(await copies('Serum').inputValue(),'1');assert.equal(await dialog.locator('[data-barcode-sample]').count(),1);
 assert.match(await dialog.innerText(),/0005678/);assert.ok(!(await dialog.innerText()).includes('مريض تجريبي'));
 await dialog.getByRole('button',{name:'إلغاء',exact:true}).click();
 // Settings toggle changes the existing print path without altering the label design.
 await page.getByText('إعدادات الباركود',{exact:true}).click();
 const option=page.getByRole('checkbox',{name:/اختيار نسخ الباركود حسب نوع العينة قبل الطباعة/});
 await option.uncheck();
 await page.locator('#open-b').click();await page.waitForFunction(()=>window.__prints?.length===2);
 assert.equal(await dialog.count(),0);
 await option.check();await page.getByRole('button',{name:'50 × 30 mm',exact:true}).click();assert.equal(await option.isChecked(),true);
 await page.getByRole('button',{name:'76.2 × 38.1 mm',exact:true}).click();
 // The saved-invoice action uses the same picker even with another printer mounted.
 await page.locator('#open-saved').click();await page.getByRole('button',{name:'طباعة ملصق الباركود',exact:true}).click();
 await copies('EDTA').waitFor();await copies('Urine').fill('0');await copies('Serum').fill('0');
 await print().click();await page.waitForFunction(()=>window.__prints?.length===3);
 assert.equal(await page.evaluate(()=>new DOMParser().parseFromString(window.__prints[2],'text/html').querySelectorAll('.barcode-label').length),1);
 await dialog.getByRole('button',{name:'إلغاء',exact:true}).click();
 await page.evaluate(()=>window.qa.invoices.printInvoiceDialog=false);
 // Cancelling a slow invoice prevents stale rows from populating the next invoice.
 await page.evaluate(()=>window.qa.delay=600);await page.locator('#open-a').click();await dialog.waitFor();
 await dialog.getByRole('button',{name:'إلغاء',exact:true}).click();
 await page.evaluate(()=>window.qa.delay=0);await page.locator('#open-b').click();await copies('Serum').waitFor();
 await page.waitForTimeout(700);assert.equal(await dialog.locator('[data-barcode-sample]').count(),1);assert.match(await dialog.innerText(),/0005678/);
 await dialog.getByRole('button',{name:'إلغاء',exact:true}).click();
 await page.evaluate(()=>window.qa.fail=true);await page.locator('#open-a').click();await dialog.getByRole('alert').waitFor();
 assert.equal(await print().isDisabled(),true);assert.equal(await dialog.locator('[data-barcode-sample]').count(),0);
 assert.equal(await page.evaluate(()=>window.__prints.length),3);
 assert.deepEqual(await page.evaluate(()=>window.__alerts||[]),[]);assert.deepEqual(errors,[]);
 console.log('PASS: sample-type copies, nested groups, sample separation, zero exclusion, exact printed count/barcode, setting toggle, saved invoice, reset, cancellation races, load errors, mobile width and keyboard close.');
} catch(error) {if(page){console.log(await page.locator('body').innerText());await page.screenshot({path:`${out}/categories-failure.png`,fullPage:true});}throw error;}
finally {await browser?.close();await server.close();}
