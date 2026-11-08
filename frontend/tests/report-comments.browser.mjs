import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir,writeFile } from 'node:fs/promises';
const out='test-results/report-comments';await mkdir(out,{recursive:true});
const browser=await chromium.launch();const page=await browser.newPage({viewport:{width:1400,height:1100}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
const open=async(query='')=>{await page.goto('http://127.0.0.1:5173/tests/report-comments-browser.html?'+query);await page.waitForFunction(()=>window.fixture?.ready);};
try{
 if(!process.env.COMMENTS_UI_ONLY) for(const template of ['classic','modern'])for(const merged of [false,true]){
  await open(`template=${template}&long=1${merged?'&merged=1&noStatus=1':'&history=1'}`);
  const text=await page.locator('#Result').innerText();
  for(const note of ['تعليق عربي','Second line <img src=x> $&','CBC overall','CBC slotted','CBC extra','Grouped template note','Group slot','Group extra','Formula note','Package note','Nested note','Culture note','General tests note'])assert.equal(text.split(note).length-1,1,`${template}/${merged} ${note} exactly once`);
  assert.ok(!text.includes('Ready Glucose'),'unused presets never printed');
  assert.equal(await page.locator('#Result img[src="x"]').count(),0);
  const columns=merged?4:6;
  for(const span of await page.locator('#Result .report-comment-row > td').evaluateAll(nodes=>nodes.map(n=>n.colSpan)))assert.equal(span,columns);
  const fast=await page.evaluate(()=>fixture.prepare());assert.ok(fast.pages>3);
  const layout=await page.evaluate(()=>fixture.layout());assert.deepEqual(layout.badCuts,[],'comments continue between rendered text lines');assert.equal(layout.overflow,0);
  if(template==='modern'&&!merged){
   const full=await page.evaluate(async()=>{const fast=fixture.pages.slice();const result=await fixture.prepare({pagesOnly:false});return {...result,identical:JSON.stringify(fast)===JSON.stringify(fixture.pages)};});
   assert.ok(full.identical,'PDF and fast-print use identical pages');
   const sheets=await page.evaluate(()=>fixture.pages);for(let i=0;i<sheets.length;i++)await writeFile(`${out}/page-${i+1}.png`,Buffer.from(sheets[i].split(',')[1],'base64'));
  }
  await page.evaluate(()=>{fixture.lab.settings.print_table_config.show_comments=false;});
  await page.waitForFunction(()=>!document.querySelector('#Result .report-comment'));
  const hiddenText=await page.locator('#Result').innerText();assert.ok(!hiddenText.includes('CBC slotted'));assert.ok(!hiddenText.includes('تعليق عربي'));
  const hidden=await page.evaluate(()=>fixture.prepare());assert.ok(hidden.pages<fast.pages);
  assert.equal(await page.evaluate(()=>fixture.invoices.printRecord.tests[0].comment),'تعليق عربي\nSecond line <img src=x> $&');
  console.log(`PASS ${template}/${merged?'merged':'named'}: comments, custom slots, long pagination, columns, visibility and cache`);
 }
 await open('mode=editor');await page.waitForFunction(()=>fixture.state().autoSaveReady);
 const quick=page.locator('.result-comment-editor').filter({has:page.locator('select[aria-label="اختر تعليقاً جاهزاً Glucose"]')}).first();
 await quick.locator('summary').click();await quick.locator('select').selectOption('Ready Glucose');await quick.locator('select').selectOption('Ready Glucose');
 assert.equal((await quick.locator('textarea').inputValue()).split('Ready Glucose').length-1,1);
 await quick.locator('textarea').fill('Custom patient note\nتعليق خاص');
 await page.waitForFunction(()=>fixture.requests.some(r=>r.url.includes('update-result')&&JSON.parse(r.data.tests||'[]')[0]?.comment==='Custom patient note\nتعليق خاص'));
 await page.evaluate(()=>{fixture.state().activeSection='packages';});
 const nested=page.locator('.result-comment-editor').filter({has:page.locator('select[aria-label="اختر تعليقاً جاهزاً PackageTest"]')}).last();
 await nested.locator('summary').click();await nested.locator('select').selectOption('Ready PackageTest');
 await page.waitForFunction(()=>fixture.requests.some(r=>r.url.includes('update-result')&&JSON.parse(r.data.packages||'[]')[0]?.tests[0]?.comment?.includes('Ready PackageTest')));
 assert.ok(!(await page.evaluate(()=>fixture.invoices.updateResultRecord.tests[0].comment)).includes('Ready PackageTest'));
 await page.evaluate(()=>{fixture.state().activeSection='test_groups';});
 const formula=page.locator('.result-comment-editor').filter({has:page.locator('select[aria-label="اختر تعليقاً جاهزاً Calculated"]')});
 await formula.locator('summary').click();await formula.locator('select').selectOption('Ready Calculated');
 await page.waitForFunction(()=>fixture.requests.some(r=>r.url.includes('update-result')&&JSON.parse(r.data.test_groups||'[]')[0]?.tests[1]?.comment?.includes('Ready Calculated')));
 await page.screenshot({path:`${out}/editor.png`,fullPage:true});
 console.log('PASS real editor: per-test preset/custom comments, no duplicates, autosave payload and package isolation');
 await open('mode=catalog');await page.getByLabel('التعليق الجاهز 1',{exact:true}).fill('First preset\nسطر ثاني');
 await page.getByRole('button',{name:'إضافة تعليق جاهز',exact:true}).click();await page.getByLabel('التعليق الجاهز 2',{exact:true}).fill('Second preset');
 await page.evaluate(()=>fixture.catalog.Addtests());
 assert.deepEqual(await page.evaluate(()=>JSON.parse(fixture.requests.find(r=>r.url==='/tests/create').data).result_comments),['First preset\nسطر ثاني','Second preset']);
 await page.getByRole('button',{name:'حذف التعليق 2',exact:true}).click();await page.getByRole('button',{name:'حذف التعليق 1',exact:true}).click();
 await page.evaluate(()=>fixture.catalog.Updatetests());assert.deepEqual(await page.evaluate(()=>JSON.parse(fixture.requests.find(r=>r.url==='/tests/update').data).result_comments),[]);
 console.log('PASS real catalog: add multiline presets and explicitly clear all saved choices');
 await open('mode=settings');await page.evaluate(()=>{fixture.state().activeTab='print';});
 assert.equal(await page.locator('.report-template-preview .report-comment').count(),1);
 await page.locator('#showResultComments').uncheck();
 assert.equal(await page.locator('.report-template-preview .report-comment').count(),0);
 await page.evaluate(()=>fixture.state().save());
 assert.equal(await page.evaluate(()=>fixture.requests.find(r=>r.url==='/lab-settings'&&r.data?.['print_table_config[show_comments]'])?.data['print_table_config[show_comments]']),'0');
 assert.equal(await page.locator('#showResultComments').isChecked(),false);
 console.log('PASS real settings: visibility saved in print config and restored from API');
 assert.deepEqual(errors,[]);
}finally{await browser.close();}
