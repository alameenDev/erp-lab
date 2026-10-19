// Real loopback HTTP server + browser HTTPS origin: CORS, pairing, credentials
// and job polling are exercised together without a physical printer.
import {chromium} from 'playwright';
import {createServer as viteServer} from 'vite';
import {createRequire} from 'node:module';
import {mkdtemp,rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import path from 'node:path';
import assert from 'node:assert/strict';
const require=createRequire(path.join(process.cwd(),'package.json'));
const {State}=require('../print-helper/src/state.cjs');
const {createServer}=require('../print-helper/src/server.cjs');
const temp=await mkdtemp(path.join(tmpdir(),'dlprint-browser-'));
const state=new State(path.join(temp,'state.json'));const code=state.newCode().value;
let sent=0,approvedOrigin;
const helper=createServer({state,approvePair:async origin=>{approvedOrigin=origin;return true;},
  listPrinters:async()=>[{name:'HTTPS QA Printer'}],print:async(job,beforeSubmit)=>{assert.match(job.html,/مستند تجريبي/);beforeSubmit();sent++;}});
await new Promise(resolve=>helper.listen(17891,'127.0.0.1',resolve));
const vite=await viteServer({server:{host:'127.0.0.1',port:5187,strictPort:true,hmr:false},logLevel:'error'});await vite.listen();
const browser=await chromium.launch({headless:true});
try {
  const context=await browser.newContext();
  const page=await context.newPage();
  page.on('requestfailed',r=>console.error('Request failed',r.url(),r.failure()?.errorText));
  // Grant the same local-network permission the user grants on first setup;
  // names vary between Chromium versions. No browser security is disabled.
  const cdp=await context.newCDPSession(page);
  const {targetInfo}=await cdp.send('Target.getTargetInfo');
  for(const name of ['loopback-network','local-network','local-network-access']) {
    try {await cdp.send('Browser.setPermission',{permission:{name},setting:'granted',origin:'https://lab.example',browserContextId:targetInfo.browserContextId});} catch (e) {console.log('Permission',name,e.message);}
  }
  await page.route('https://lab.example/**',async route=>{
    const u=new URL(route.request().url());
    await route.fulfill({response:await route.fetch({url:'http://127.0.0.1:5187'+u.pathname+u.search})});
  });
  await page.goto('https://lab.example/tests/local-print.html');await page.waitForFunction(()=>!!window.qa);
  await page.getByPlaceholder('12345678').fill(code);await page.getByRole('button',{name:'ربط الحاسبة',exact:true}).click();
  await page.getByRole('status').filter({hasText:'تم ربط'}).waitFor({timeout:15000}).catch(async e=>{console.error(await page.getByRole('alert').allTextContents());throw e;});assert.equal(approvedOrigin,'https://lab.example');
  await page.getByLabel('طابعة الفواتير',{exact:true}).selectOption('HTTPS QA Printer');
  await page.getByLabel('تفعيل الطباعة المباشرة على هذه الحاسبة').check();
  await page.getByRole('button',{name:'حفظ اختيارات الطابعات'}).click();
  await page.evaluate(()=>window.qa.send('invoice'));assert.equal(sent,1);assert.equal(await page.evaluate(()=>window.nativePrints),0);
  assert.equal(Object.values(state.data.jobs)[0].status,'submitted');
  console.log('PASS: HTTPS website pairs with the real loopback service, discovers printers and submits exactly one authenticated job without the browser print dialog.');
} finally {await browser.close();await vite.close();await new Promise(resolve=>helper.close(resolve));await rm(temp,{recursive:true,force:true});}
