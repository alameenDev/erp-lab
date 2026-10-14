import {chromium} from 'playwright';import assert from 'node:assert/strict';import {mkdir} from 'node:fs/promises';
const browser=await chromium.launch();const page=await browser.newPage({viewport:{width:1280,height:1000}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
await mkdir('test-results/patient-portal',{recursive:true});
try{
 await page.goto('http://127.0.0.1:5173/tests/patient-portal-results-browser.html');
 const tab=label=>page.getByRole('button',{name:label,exact:true});
 await tab('الكل (6)').waitFor();assert.equal(await page.locator('article').count(),3);assert.equal(await page.locator('article a').count(),3);
 await tab('جاهز (3)').click();assert.equal(await page.locator('article').count(),2);let text=await page.locator('article').allTextContents();
 assert.ok(text.join().includes('Glucose'));assert.ok(text.join().includes('Package approved'));assert.ok(!text.join().includes('Package pending'));
 await tab('قيد الإجراء (3)').click();assert.equal(await page.locator('article').count(),2);text=await page.locator('article').allTextContents();
 assert.ok(text.join().includes('Package pending'));assert.ok(!text.join().includes('Glucose'));assert.ok(!text.join().includes('Completed test'));
 await tab('الكل (6)').click();await page.setViewportSize({width:390,height:844});
 assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
 await page.screenshot({path:'test-results/patient-portal/analysis-status-mobile.png',fullPage:true});
 const link=await page.locator('article a').first().getAttribute('href');assert.ok(link.includes('#portal='));
 await page.evaluate(url=>{const u=new URL(url);return portalFixture.router.push(u.pathname+u.search+u.hash);},link);
 await page.locator('.report-page-preview img').first().waitFor({timeout:60000});
 assert.ok((await page.getByRole('status').allTextContents()).join().includes('2 من 4'));
 assert.ok(!(await page.locator('#Result').innerText()).includes('Package pending'));
 await page.screenshot({path:'test-results/patient-portal/partial-result-mobile.png',fullPage:true});
 await page.evaluate(()=>portalFixture.router.push('/result/15#portal='+portalFixture.token));
 await page.getByRole('heading',{name:'الفحوصات قيد الإجراء'}).waitFor();assert.ok(await page.getByText('Pending culture',{exact:true}).isVisible());
 await page.evaluate(()=>{portalFixture.state.ready=true;});await page.getByRole('button',{name:'تحديث النتائج',exact:true}).click();
 await page.locator('.report-page-preview img').first().waitFor({timeout:60000});assert.ok((await page.locator('#Result').innerText()).includes('Pending culture'));
 await page.evaluate(()=>portalFixture.router.push('/result/14#portal=invalid'));
 await page.getByRole('heading',{name:'تعذر عرض النتائج'}).waitFor();assert.equal(await page.locator('#Result').count(),0);
 assert.equal(await page.evaluate(()=>portalFixture.requests.some(u=>u.startsWith('/invoices/'))),false,'patient credential wins over unrelated staff session, with no public fallback');
 assert.deepEqual(errors,[]);console.log('PASS per-analysis counters and filters, mixed containers, links in all states, partial PDF, pending-to-ready refresh, mobile and invalid access');
}finally{await browser.close();}
