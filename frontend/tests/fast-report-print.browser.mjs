import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { jsPDF } from 'jspdf';
const browser=await chromium.launch();
const context=await browser.newContext({viewport:{width:1360,height:960}});
// Exercise actual browser documents/rendering; replace only the native dialog.
await context.addInitScript(()=>{
 window.print=()=>{
  const result={pages:[...document.querySelectorAll('.report-sheet img')].map(i=>i.src),css:document.querySelector('style')?.textContent};
  if(window!==window.top){
   window.top.printed=result;
   if(window.top.failPrint)throw new Error('Synthetic printer failure');
   if(!window.top.keepDialog)window.dispatchEvent(new Event('afterprint'));
  }else window.printed=result;
 };
});
const page=await context.newPage(),errors=[],timings=[];
page.on('pageerror',e=>errors.push(e.message));
await mkdir('test-results/fast-report-print',{recursive:true});
const fast=()=>page.getByRole('button',{name:'طباعة سريعة — تجريبية',exact:true});
const open=async()=>{await page.locator('#open-report').click();await page.locator('[data-patient-debt-warning]').waitFor();};
const done=async n=>page.waitForFunction(n=>fixture.outputs.length>=n&&!fixture.state().reportActionBusy,n,{timeout:120000});
try{
 for(const query of ['entry=list','entry=editor&long&form','entry=list&long&mono&form&template=classic','entry=list&categories','entry=editor&categories&mono&form','entry=list&standard-first&template=classic','entry=editor&standard-first&mono&form','entry=editor&form&attachments']){
  await page.goto('http://127.0.0.1:5173/tests/fast-report-print-browser.html?'+query);
  await page.waitForFunction(()=>window.fixture?.state?.()?.isLoading===false);
  if(query.includes('attachments')){
   const pdf=new jsPDF();pdf.text('External PDF page one',20,40);pdf.addPage();pdf.text('External PDF page two',20,40);
   await page.evaluate(async bytes=>{
    fixture.files.set('/synthetic/test.pdf',new Blob([new Uint8Array(bytes)],{type:'application/pdf'}));
    const c=document.createElement('canvas');c.width=500;c.height=300;const ctx=c.getContext('2d');ctx.fillStyle='#037c83';ctx.fillRect(0,0,500,300);
    fixture.files.set('/synthetic/test.png',await new Promise(r=>c.toBlob(r)));
    fixture.sample.attachments=[{file:location.origin+'/synthetic/test.pdf',name:'تقرير خارجي'},{file:location.origin+'/synthetic/test.png',name:'صورة الفحص'}];
   },Array.from(new Uint8Array(pdf.output('arraybuffer'))));
  }
  await open();
  assert.equal(await fast().isEnabled(),true);
  const before=context.pages().length;
  await fast().click();await done(1);
  assert.equal(context.pages().length,before,'fast printing opens no popup');
  assert.equal(await page.locator('iframe[data-fast-report-print]').count(),0,'afterprint cleans iframe');
  assert.equal(await page.evaluate(()=>fixture.outputs[0].output.blob===undefined),true,'no PDF created');
  assert.deepEqual(await page.evaluate(()=>fixture.actions),[{id:14,action:'print',form:query.includes('form')}]);
  assert.equal(await page.evaluate(()=>JSON.stringify(printed.pages)===JSON.stringify(fixture.outputs[0].output.pages)),true);
  if(query.includes('categories')){
   const sections=await page.evaluate(()=>fixture.outputs[0].sections);
   assert.deepEqual(sections.flatMap(s=>s.tests),['Glucose','Urea','TSH']);
   assert.deepEqual(sections.map(s=>s.category),['Chemistry','','Hormones']);
  }
  if(query.includes('standard-first')){
   const sections=await page.evaluate(()=>fixture.outputs[0].sections);
   assert.deepEqual(sections.flatMap(s=>s.tests),['RBS','HbA1c','Creatinine','S.GPT (ALT)']);
   assert.deepEqual(sections.at(-1).tests,[],'custom CBC follows ordinary rows');
   assert.equal(await page.evaluate(()=>printed.pages.length),2,'ALT no longer creates a third sheet after CBC');
  }
  if(query.includes('long'))assert.ok(await page.evaluate(()=>printed.pages.length)>1);
  if(query.includes('attachments'))assert.equal(await page.evaluate(()=>printed.pages.length),4);
  if(query.includes('mono')){
   const letterhead=await page.evaluate(async()=>{
    const samples=[];
    for(const src of printed.pages){
     const img=new Image();img.src=src;await img.decode();
     const c=document.createElement('canvas');c.width=img.width;c.height=img.height;
     const ctx=c.getContext('2d');ctx.drawImage(img,0,0);
     samples.push(Array.from(ctx.getImageData(100,20,1,1).data));
    }return samples;
   });
   for(const sample of letterhead)assert.deepEqual(sample,[0,124,131,255],'native fast print retains the colored form in B&W mode');
  }
  // Legacy entry stays a popup with a valid PDF, using identical raster sheets.
  await open();
  const [popup]=await Promise.all([context.waitForEvent('page'),page.getByRole('button',{name:'تنفيذ',exact:true}).click()]);
  await done(2);await popup.waitForFunction(()=>!!window.printed);
  assert.equal(await page.evaluate(()=>fixture.outputs[1].output.blob.slice(0,5).text()),'%PDF-');
  assert.equal(await page.evaluate(()=>JSON.stringify(fixture.outputs[0].output.pages)===JSON.stringify(fixture.outputs[1].output.pages)),true,'every page pixel is unchanged');
  assert.deepEqual(await popup.evaluate(()=>printed),await page.evaluate(()=>printed));
  await popup.close();
  timings.push(await page.evaluate(query=>({query,pages:fixture.outputs[0].output.pages.length,fastMs:Math.round(fixture.outputs[0].ms),legacyMs:Math.round(fixture.outputs[1].ms)}),query));
  console.log('PASS actual '+query+': identical pages, PDF bypass, popup isolation, status, debt warning');
 }
 // Changing result data cannot reuse a stale fast-print cache.
 assert.equal(await page.evaluate(async()=>{
  const first=await fixture.prepare({pagesOnly:true,withBackground:true});
  fixture.store.printRecord.test_groups[0].tests[0].result='CHANGED';
  const second=await fixture.prepare({pagesOnly:true,withBackground:true});
  const legacy=await fixture.prepare({withBackground:true});
  return first.pages[0]!==second.pages[0]&&JSON.stringify(second.pages)===JSON.stringify(legacy.pages)&&await legacy.blob.slice(0,5).text()==='%PDF-';
 }),true);
 // Guard debt-loading, no selection, busy double clicks and narrow layout.
 await page.evaluate(()=>{fixture.holdBalance=true;});
 await page.locator('#open-report').click();await page.locator('[data-patient-debt-loading]').waitFor();
 assert.equal(await fast().isDisabled(),true);
 await page.evaluate(()=>{fixture.holdBalance=false;fixture.releaseBalance();});await page.locator('[data-patient-debt-warning]').waitFor();
 await page.getByRole('button',{name:'إلغاء تحديد الكل',exact:true}).click();assert.equal(await fast().isDisabled(),true);
 await page.getByRole('button',{name:'تحديد الكل',exact:true}).click();
 await page.setViewportSize({width:390,height:844});
 assert.equal(await page.getByRole('dialog').evaluate(dialog=>{
  const bounds=dialog.getBoundingClientRect();
  return [...dialog.querySelectorAll('button')].every(b=>{const r=b.getBoundingClientRect();return r.left>=bounds.left&&r.right<=bounds.right;});
 }),true,'new button fits inside mobile dialog');
 await page.getByRole('dialog').screenshot({path:'test-results/fast-report-print/mobile-dialog.png'});
 await page.setViewportSize({width:1360,height:960});
 // Remove attachments to keep subsequent lifecycle checks focused and fast.
 await page.getByRole('button',{name:'إغلاق',exact:true}).click();
 await page.evaluate(()=>{fixture.sample.attachments=[];});
 await open();await page.screenshot({path:'test-results/fast-report-print/desktop-dialog.png'});
 // Selected subset uses the same filtering and restores the full invoice.
 await page.getByRole('checkbox',{name:'Analysis 2',exact:true}).uncheck();
 const startCount=await page.evaluate(()=>fixture.outputs.length);
 await fast().dblclick();await done(startCount+1);
 assert.equal(await page.evaluate(()=>fixture.outputs.length),startCount+1,'busy guard prevents duplicate output');
 const partial=await page.evaluate(()=>fixture.outputs.at(-1).output.pages);
 assert.equal(await page.evaluate(()=>fixture.store.printRecord.test_groups[0].tests.length),3,'full record restored');
 await open();await page.getByRole('checkbox',{name:'Analysis 2',exact:true}).uncheck();
 const [partialPopup]=await Promise.all([context.waitForEvent('page'),page.getByRole('button',{name:'تنفيذ',exact:true}).click()]);
 await done(startCount+2);await partialPopup.waitForFunction(()=>!!window.printed);
 assert.deepEqual(await partialPopup.evaluate(()=>printed.pages),partial,'partial fast and legacy reports agree');await partialPopup.close();
 await open();
 const actionsBeforeFailure=await page.evaluate(()=>fixture.actions.length);
 const n=await page.evaluate(()=>fixture.outputs.length);
 await page.evaluate(()=>{window.failPrint=true;});await fast().click();await done(n+1);
 assert.equal(await page.locator('iframe[data-fast-report-print]').count(),0);
 assert.equal(await page.evaluate(()=>fixture.actions.length),actionsBeforeFailure,'failed print records no success');
 await page.evaluate(()=>{window.failPrint=false;fixture.failStatus=true;});await fast().click();await done(n+2);
 assert.equal(await page.evaluate(()=>fixture.actions.length),actionsBeforeFailure,'status failure is not falsely persisted');
 await open();await page.evaluate(()=>{fixture.failStatus=false;window.keepDialog=true;});
 await fast().click();await done(n+3);
 assert.equal(await page.locator('iframe[data-fast-report-print]').count(),1,'retain frame while dialog remains open');
 await page.evaluate(()=>fixture.unmount());assert.equal(await page.locator('iframe[data-fast-report-print]').count(),0,'navigation cleans orphan frame');
 // A corrupt raster must fail rather than print a blank page.
 assert.equal(await page.evaluate(async()=>{
  const {printFastMedicalReportPages}=await import('/src/utils/fastMedicalReportPrint.js');
  try{await printFastMedicalReportPages(['data:image/png;base64,broken']);return false;}catch{return document.querySelectorAll('iframe[data-fast-report-print]').length===0;}
 }),true);
 // Local helper uses the same report route and never duplicates an uncertain job.
 const helperChecks=await page.evaluate(async()=>{
  const {printFastMedicalReportPages}=await import('/src/utils/fastMedicalReportPrint.js');
  const pages=fixture.outputs[0].output.pages, savedFetch=window.fetch;
  const token='a'.repeat(64);localStorage.setItem('digital-lab-print-v1',JSON.stringify({enabled:true,token,routes:{report:{printer:'Synthetic A4',copies:2}}}));
  let mode='success',jobs=[],fallbacks=0;const oldConfirm=window.confirm;
  window.confirm=()=>{fallbacks++;return true;};
  window.fetch=async(input,options)=>{
   if(String(input).endsWith('/printers'))return Response.json({printers:[{name:'Synthetic A4'}]});
   if(String(input).endsWith('/jobs')){jobs.push(JSON.parse(options.body));if(mode==='uncertain')throw new Error('Lost response');return Response.json({status:'submitted'});}
   if(String(input).includes('/jobs/'))throw new Error('Lost status');
   return savedFetch(input,options);
  };
  try{
   window.printed=null;await printFastMedicalReportPages(pages);
   const correct=jobs.length===1&&jobs[0].kind==='report'&&jobs[0].copies===2&&jobs[0].html.includes(pages[0])&&!window.printed&&!document.querySelector('iframe[data-fast-report-print]');
   mode='uncertain';let rejected=false;try{await printFastMedicalReportPages(pages);}catch{rejected=true;}
   return correct&&rejected&&jobs.length===2&&fallbacks===0&&!window.printed&&!document.querySelector('iframe[data-fast-report-print]');
  }finally{window.fetch=savedFetch;window.confirm=oldConfirm;localStorage.removeItem('digital-lab-print-v1');}
 });assert.equal(helperChecks,true);
 // Cancellation during image decoding must not open the dialog later.
 assert.equal(await page.evaluate(async()=>{
  const {printFastMedicalReportPages}=await import('/src/utils/fastMedicalReportPrint.js');
  const original=HTMLImageElement.prototype.decode,controller=new AbortController();
  HTMLImageElement.prototype.decode=()=>new Promise(()=>{});
  try{const pending=printFastMedicalReportPages(fixture.outputs[0].output.pages,{signal:controller.signal});controller.abort();try{await pending;return false;}catch(e){return e.name==='AbortError'&&!document.querySelector('iframe[data-fast-report-print]');}}
  finally{HTMLImageElement.prototype.decode=original;}
 }),true);
 assert.deepEqual(errors,[]);
 await writeFile('test-results/fast-report-print/timings.json',JSON.stringify(timings,null,2));
 console.log('PASS cache invalidation, guards, status failures, print errors, cleanup and mobile layout');
 console.table(timings);
}catch(error){
 await page.screenshot({path:'test-results/fast-report-print/failure.png',fullPage:true});
 console.log(await page.evaluate(()=>[...document.querySelectorAll('body *')].filter(e=>e.getBoundingClientRect().right>innerWidth+1&&getComputedStyle(e).position!=='fixed').slice(0,12).map(e=>({tag:e.tagName,cls:e.className,right:e.getBoundingClientRect().right}))));
 throw error;
}finally{await browser.close();}
