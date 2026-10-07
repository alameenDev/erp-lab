import {chromium} from 'playwright';
import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';
await mkdir('test-results/package-contents',{recursive:true});
const browser=await chromium.launch();const errors=[];
try {
 const page=await browser.newPage({viewport:{width:1360,height:1000}});
 page.on('pageerror',e=>{errors.push(e.message);console.error(e.stack);});
 await page.goto('http://127.0.0.1:5173/tests/package-contents-browser.html');
 await page.locator('[data-package-contents="1"]').waitFor();
 const before=await page.evaluate(()=>JSON.stringify(packageFixture.packagesList));
 const modal=page.getByRole('dialog',{name:'تحاليل وكروبات الباقة'});
 const close=async()=>{await modal.getByRole('button',{name:'غلق',exact:true}).last().click();await modal.waitFor({state:'hidden'});};
 for(const id of [1,2,3,4]) {
  await page.locator(`[data-package-contents="${id}"]`).click();await modal.waitFor();
  assert.equal(await modal.getByText('Glucose',{exact:true}).count(),[1,3].includes(id)?1:0);
  assert.equal(await modal.getByText('Complete Blood Count',{exact:true}).count(),[1,2].includes(id)?1:0);
  assert.equal(await modal.getByText('Hemoglobin',{exact:true}).count(),[1,2].includes(id)?1:0);
  assert.equal(await modal.getByText('Group culture',{exact:true}).count(),[1,2].includes(id)?1:0);
  if(id===1){await modal.getByText('لا توجد تحاليل مضافة داخل هذا الكروب.',{exact:true}).waitFor();await page.screenshot({path:'test-results/package-contents/desktop.png',animations:'disabled'});}
  if(id===4)await modal.getByText('لا توجد تحاليل أو كروبات في هذه الباقة',{exact:true}).waitFor();
  await close();
 }
 assert.equal(await page.evaluate(()=>JSON.stringify(packageFixture.packagesList)),before,'viewing does not edit package membership');
 const row=page.locator('tr').filter({has:page.locator('[data-package-contents="1"]')});
 await row.locator('button[title="تعديل"]').click();
 assert.deepEqual(await page.evaluate(()=>packageFixture.record.test_groups.map(g=>g.id)),[21,22],'editing retains all selected groups');
 await page.evaluate(()=>{packageFixture.dialog=false;});
 await page.setViewportSize({width:390,height:844});await page.locator('[data-package-contents="2"]').click();await modal.waitFor();
 const box=await modal.boundingBox();assert.ok(box.x>=0&&box.x+box.width<=390,'dialog fits a phone viewport');
 await page.screenshot({path:'test-results/package-contents/mobile.png',animations:'disabled'});await close();
 assert.deepEqual(errors,[]);console.log('PASS package button shows direct tests, nested groups/cultures, empty states and membership on desktop/mobile');
} finally {await browser.close();}
