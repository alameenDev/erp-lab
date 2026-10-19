import {chromium} from 'playwright';
import {createServer} from 'vite';
import {mkdir, writeFile} from 'node:fs/promises';
import assert from 'node:assert/strict';
const server = await createServer({server:{host:'127.0.0.1',port:5186,strictPort:true,watch:{ignored:['**/print-helper-qa/**']}},logLevel:'error'});
await server.listen();
const browser=await chromium.launch({headless:true});
try {
  const page=await browser.newPage({viewport:{width:1400,height:1000}});const errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  const jobs=[],fixtures=[];let mode='success',lastJob,requestCount=0,confirmCount=0;
  page.on('dialog',dialog=>{confirmCount++;return dialog.accept();});
  await page.route('http://127.0.0.1:17891/**', async route=>{
    const req=route.request(),url=new URL(req.url());requestCount++;
    const headers={'Access-Control-Allow-Origin':'http://127.0.0.1:5186','Access-Control-Allow-Headers':'Content-Type, Authorization','Access-Control-Allow-Methods':'GET, POST, DELETE','Access-Control-Allow-Private-Network':'true'};
    const reply=(body,status=200)=>route.fulfill({status,headers,contentType:'application/json',body:JSON.stringify(body)});
    if(req.method()==='OPTIONS')return reply({},204);
    if(mode==='offline')return route.abort('connectionrefused');
    if(url.pathname.endsWith('/health'))return reply({name:'Digital Lab Print',protocol:1});
    if(url.pathname.endsWith('/pair'))return reply({token:'a'.repeat(64)});
    if(url.pathname.endsWith('/printers'))return reply({printers:[{name:'Label USB',displayName:'Label USB'},{name:'Office A4',displayName:'Office A4'}]});
    if(req.method()==='POST'&&url.pathname.endsWith('/jobs')){
      const job=req.postDataJSON();jobs.push(job);lastJob=job;
      if(mode==='lost-response')return route.abort('connectionreset');
      return reply({id:job.id,status:mode==='unknown'?'unknown':'submitted'},202);
    }
    if(url.pathname.includes('/jobs/'))return reply({id:lastJob.id,status:'submitted'});
    return reply({error:'unexpected'},404);
  });
  await page.goto('http://127.0.0.1:5186/tests/local-print.html');await page.waitForFunction(()=>!!window.qa);
  await page.evaluate(()=>window.qa.send('invoice'));assert.equal(requestCount,0);assert.equal(await page.evaluate(()=>window.nativePrints),1);
  // Settings pairing, per-type routes and explicit activation.
  await page.getByPlaceholder('12345678').fill('12345678');await page.getByRole('button',{name:'ربط الحاسبة',exact:true}).click();
  await page.getByRole('status').filter({hasText:'تم ربط'}).waitFor();
  await page.getByLabel('طابعة الباركود',{exact:true}).selectOption('Label USB');
  await page.getByLabel('طابعة التقارير الطبية',{exact:true}).selectOption('Office A4');
  await page.getByLabel('تفعيل الطباعة المباشرة على هذه الحاسبة').check();
  await page.getByRole('button',{name:'حفظ اختيارات الطابعات'}).click();
  const saved=await page.evaluate(()=>window.qa.readPrintSettings());assert.equal(saved.routes.invoice.printer,'');
  await page.evaluate(()=>window.qa.send('invoice'));assert.equal(jobs.length,0,'unmapped type retains browser');
  await page.evaluate(()=>window.qa.send('barcode'));assert.equal(jobs.at(-1).printer,'Label USB');assert.deepEqual(jobs.at(-1).paper,{width:50,height:30});assert.match(jobs.at(-1).html,/#12/);assert.match(jobs.at(-1).html,/<svg/);fixtures.push(jobs.at(-1));
  await page.evaluate(()=>window.qa.report());assert.equal(jobs.at(-1).kind,'report');assert.equal((jobs.at(-1).html.match(/class="report-sheet"/g)||[]).length,2);fixtures.push(jobs.at(-1));
  await page.evaluate(()=>{const s=window.qa.readPrintSettings();for(const k of Object.keys(s.routes))s.routes[k]={printer:k==='barcode'?'Label USB':'Office A4',copies:2};window.qa.savePrintSettings(s);});
  for(const kind of ['invoice','thermal','job','worksheet','history','register']) {
    const css=kind==='thermal'?'@page{size:58mm auto;margin:3mm} body{width:52mm}':kind==='register'?'@page{size:A4 landscape;margin:12mm}':'@page{size:A5 landscape;margin:8mm}';
    await page.evaluate(([k,c])=>window.qa.send(k,c),[kind,css]);assert.equal(jobs.at(-1).kind,kind);assert.equal(jobs.at(-1).copies,2);assert.match(jobs.at(-1).html,/12.5/);fixtures.push(jobs.at(-1));
  }
  const prior=jobs.length, native=await page.evaluate(()=>window.nativePrints);
  mode='offline';await page.evaluate(()=>window.qa.send('invoice'));assert.equal(jobs.length,prior);assert.equal(await page.evaluate(()=>window.nativePrints),native+1);
  mode='success';await page.evaluate(()=>window.qa.missingAsset());assert.equal(jobs.length,prior,'failed image never submits incomplete document');
  mode='lost-response';await page.evaluate(()=>window.qa.send('invoice'));assert.equal(jobs.length,prior+1,'lost response queries same job without reprinting');
  mode='unknown';const uncertain=await page.evaluate(()=>window.qa.send('invoice').then(()=>'',e=>e.message));assert.match(uncertain,/غير مؤكدة/);assert.equal(jobs.length,prior+2);assert.equal(await page.evaluate(()=>window.nativePrints),native+1,'uncertain submission never auto-falls back');
  mode='success';await page.reload();await page.waitForFunction(()=>!!window.qa);await page.getByRole('button',{name:'العودة للطباعة الاعتيادية'}).click();
  const requests=requestCount;await page.evaluate(()=>window.qa.send('barcode'));assert.equal(requestCount,requests,'disable disconnects all routing');
  await mkdir('print-helper-qa',{recursive:true});await writeFile('print-helper-qa/documents.json',JSON.stringify(fixtures));
  await page.screenshot({path:'print-helper-qa/desktop.png',fullPage:true});
  await page.setViewportSize({width:390,height:844});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true);await page.screenshot({path:'print-helper-qa/mobile.png',fullPage:true});
  assert.deepEqual(errors,[]);console.log('PASS: 8 document routes, real barcode/report sheets, local pairing/settings, browser opt-out, missing assets, offline fallback, uncertain/lost responses and mobile layout.');
} finally {await browser.close();await server.close();}
