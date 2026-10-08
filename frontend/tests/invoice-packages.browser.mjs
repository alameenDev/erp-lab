import {chromium} from 'playwright';
import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';
await mkdir('test-results/invoice-packages',{recursive:true});
const browser=await chromium.launch();const errors=[];
try {
 const page=await browser.newPage({viewport:{width:1360,height:1000}});
 page.on('pageerror',error=>{errors.push(error.message);console.error(error.stack);});
 await page.goto('http://127.0.0.1:5173/tests/invoice-packages-browser.html');
 await page.waitForFunction(()=>window.invoiceFixture?.catalog.packagesList.length===4);
 const select=async(name)=>{
  await page.locator('[data-invoice-picker="package"] input').fill(name);
  await page.getByRole('option',{name,exact:true}).click();
 };
 const card=id=>page.locator(`[data-invoice-package="${id}"]`);
 await select('Mixed package');const mixed=card(1);
 await mixed.getByText('Complete Blood Count',{exact:true}).waitFor();
 for(const text of ['Glucose','GLU','Hemoglobin','HGB','White Blood Cells','WBC','CBC','Group culture','Direct culture','Empty group'])await mixed.getByText(text,{exact:true}).waitFor();
 assert.equal(await mixed.locator('[data-package-group]').count(),2);
 const noShortcut=mixed.locator('[data-analysis-name]').filter({has:page.getByText('Analysis without abbreviation',{exact:true})});
 assert.equal(await noShortcut.locator('[data-analysis-shortcut]').count(),0);
 const label=mixed.locator('[data-analysis-name]').filter({has:page.getByText('Hemoglobin',{exact:true})});
 const nameBox=await label.getByText('Hemoglobin',{exact:true}).boundingBox();const shortcutBox=await label.getByText('HGB',{exact:true}).boundingBox();
 assert.ok(shortcutBox.y>=nameBox.y+nameBox.height,'abbreviation is below the full analysis name');
 const snapshot=await page.evaluate(()=>JSON.stringify(invoiceFixture.store.selectedPackages));
 assert.equal(await page.evaluate(()=>invoiceFixture.form().baseTotal),10000,'nested tests are not billed separately');
 await page.evaluate(()=>invoiceFixture.form().addSelection('testGroup',invoiceFixture.group,false));
 const standalone=page.locator('[data-invoice-group="21"]');const groupToggle=standalone.locator('button[aria-expanded]');
 await groupToggle.click();await mixed.locator('button[aria-expanded]').click();
 assert.equal(await groupToggle.getAttribute('aria-expanded'),'true','package toggle leaves standalone group open');
 await standalone.getByText('HGB',{exact:true}).waitFor();
 await mixed.locator('button[aria-expanded]').click();
 assert.equal(await page.evaluate(()=>JSON.stringify(invoiceFixture.store.selectedPackages)),snapshot,'expansion does not mutate invoice contents');
 await select('Groups only');await card(2).getByText('Hemoglobin',{exact:true}).waitFor();
 await select('Tests only');await card(3).getByText('GLU',{exact:true}).waitFor();
 await select('Empty package');await card(4).getByText('لا توجد تحاليل أو كروبات في هذه الباقة.',{exact:true}).waitFor();
 await page.evaluate(()=>invoiceFixture.form().removeSelection('package',0));
 assert.equal(await card(2).locator('button[aria-expanded]').getAttribute('aria-expanded'),'true','removing an earlier package preserves expansion');
 await page.locator('[data-invoice-picker="analysis"] input').fill('Glucose');
 const analysisOption=page.getByRole('option').filter({has:page.getByText('Glucose',{exact:true})});
 await analysisOption.getByText('GLU',{exact:true}).waitFor();await analysisOption.click();
 await page.waitForFunction(()=>invoiceFixture.store.selectedTests.length===1);
 await page.locator('[data-invoice-analysis="11"]').getByText('GLU',{exact:true}).waitFor();
 await card(3).getByText('GLU',{exact:true}).waitFor();
 // Existing invoices may flatten group children alongside the nested membership.
 await page.evaluate(()=>{const p=structuredClone(invoiceFixture.packages[0]);p.package_id_fk=p.id;delete p.id;p.tests.push(...p.test_groups[0].tests);invoiceFixture.store.selectedPackages=[p];invoiceFixture.form().expandedContents={'package:1':true};});
 assert.equal(await card(1).getByText('Hemoglobin',{exact:true}).count(),1,'flattened edit response does not duplicate group tests');
 await card(1).screenshot({path:'test-results/invoice-packages/desktop.png',animations:'disabled'});
 await page.setViewportSize({width:390,height:844});await card(1).scrollIntoViewIfNeeded();
 const box=await card(1).boundingBox();assert.ok(box.x>=0&&box.x+box.width<=390,'package card fits phone viewport');
 for(const el of await card(1).locator('[data-analysis-name]').all()){const b=await el.boundingBox();assert.ok(b.x>=box.x&&b.x+b.width<=box.x+box.width+1,'analysis names stay inside the card');}
 await card(1).screenshot({path:'test-results/invoice-packages/mobile.png',animations:'disabled'});
 assert.deepEqual(await page.evaluate(()=>invoiceFixture.requests.filter(r=>r.method!=='get'&&r.url!=='/tests/questions')),[]);
 assert.deepEqual(errors,[]);console.log('PASS invoice selection shows package groups/cultures, abbreviations below names, independent expansion, stable price and flattened invoice display on desktop/mobile');
} finally {await browser.close();}
