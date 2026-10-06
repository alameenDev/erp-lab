import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width:1280, height:900 } });
const errors = [];
page.on('pageerror', error => errors.push(error.message));
await mkdir('test-results/report-pages', { recursive:true });
try {
  await page.goto('http://127.0.0.1:5173/tests/report-actions-browser.html');
  await page.waitForFunction(() => Boolean(window.fixture));
  const checks = await page.evaluate(async () => {
    const { store, $http, executeReportAction: execute, reportActionStatus: status, nextTick } = window.fixture;
    const passed = [], check = (ok, label) => { if (!ok) throw new Error(label); passed.push(label); };
    const clone = value => JSON.parse(JSON.stringify(value));
    const patient = {id:1,name:'Synthetic patient',phone:'07700000000'};
    let database, calls, events, mode, releaseSave;
    const reset = (failure = '') => {
      mode = failure; calls = []; events = []; releaseSave = null;
      database = { id:14, patient, sent_to_patient:false, public_with_background:true, report_status:{printed:false,saved:false,sent:false} };
      store.invoices = [clone(database),{id:15,sent_to_patient:false}];
      Object.assign(store.printRecord, clone(database));
      store.updateResultRecord = {...clone(database),notes:'Unsaved input'};
      store.stats = {sent:0,completed_sent:0,pending_sent:2};
    };
    const originalAdapter = $http.defaults.adapter, originalClick = HTMLAnchorElement.prototype.click;
    const output = {blob:new Blob(['%PDF-1.4\nsynthetic']),filename:'synthetic.pdf',withBackground:false,
      pages:['data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7']};
    const run = (action, overrides = {}) => {
      const frame = document.createElement('iframe'); document.body.appendChild(frame);
      const printTab = { closed:false,document:frame.contentDocument,focus(){},print(){ if(mode==='print')throw new Error('Print failed');events.push('print'); } };
      const whatsappTab = { closed:false,opener:{},location:{set href(value){ if(mode==='navigation')throw new Error('Navigation failed');events.push('whatsapp'); }} };
      return execute({action,output,record:store.printRecord,settings:{},printTab,whatsappTab,store,...overrides}).finally(()=>frame.remove());
    };
    try {
      HTMLAnchorElement.prototype.click = function(){events.push('download');};
      $http.defaults.adapter = async config => {
        let data;
        const url = config.url.replace(/^\//,'');
        if(url==='portal/generate') data={url:'https://example.test/portal/synthetic-token'};
        else if(url==='invoices/report-action') {
          const payload = JSON.parse(config.data); calls.push(payload); events.push('persist:'+payload.action);
          if(mode==='save')throw new Error('Persistence failed');
          if(mode==='delayed')await new Promise(resolve=>releaseSave=resolve);
          const s=database.report_status, now='2026-10-06T08:00:00Z';
          if(['print','print-download'].includes(payload.action)){s.printed=true;s.printed_at=now;}
          if(['download','print-download','whatsapp-download'].includes(payload.action)){s.saved=true;s.saved_at=now;}
          if(['whatsapp','whatsapp-download'].includes(payload.action)){s.sent=true;s.sent_at=now;database.sent_to_patient=true;database.public_with_background=payload.with_background;}
          data=clone(database);
        } else if(url==='invoices') data={data:[clone(database),{id:15,sent_to_patient:false}]};
        else throw new Error('Unexpected request '+url);
        return {data,status:200,statusText:'OK',headers:{},config};
      };
      for(const [action,label,eventNames] of [
        ['print','تمت الطباعة',['print','persist:print']],
        ['download','تم الحفظ',['download','persist:download']],
        ['print-download','تمت الطباعة والحفظ',['download','print','persist:print-download']],
        ['whatsapp','تم الإرسال',['download','whatsapp','persist:whatsapp']],
        ['whatsapp-download','تم الحفظ والإرسال',['download','persist:download','whatsapp','persist:whatsapp-download']],
      ]){
        reset();check(!(await run(action)).statusError, action+' completes');
        await nextTick();check(document.getElementById('current-status').textContent.trim()===label,action+' shows precise status');
        check(JSON.stringify(events)===JSON.stringify(eventNames),action+' persists only after actual output');
        check(store.updateResultRecord.notes==='Unsaved input' && !store.invoices[1].sent_to_patient,action+' preserves input and other invoices');
      }
      reset();await run('download');await run('whatsapp');await run('print');await run('whatsapp');
      check(status(store.invoices[0]).label==='تمت الطباعة والحفظ والإرسال','sequential actions accumulate');
      check(store.stats.sent===1,'repeat share does not double-count');
      store.invoices=[];await store.Getinvoices();check(status(store.invoices[0]).label==='تمت الطباعة والحفظ والإرسال','reload restores all actions');
      check(store.printRecord.public_with_background===false,'share preserves form choice');
      reset('delayed');const delayed=run('download');
      for(let i=0;i<100&&!releaseSave;i++)await new Promise(r=>setTimeout(r,5));
      check(Boolean(releaseSave) && status(store.invoices[0]).label==='لم يُنفّذ إجراء','pending persistence cannot claim success');
      releaseSave();await delayed;
      reset('save');check(Boolean((await run('print')).statusError) && !store.invoices[0].report_status.printed,'failed persistence keeps prior state');
      reset('print');let failed=false;try{await run('print-download');}catch{failed=true;}
      check(failed && status(store.invoices[0]).label==='تم الحفظ','partial print/save failure keeps completed save only');
      reset('navigation');failed=false;try{await run('whatsapp-download');}catch{failed=true;}
      check(failed && status(store.invoices[0]).label==='تم الحفظ','partial save/share failure keeps completed save only');
      for(const [action,overrides] of [['print',{printTab:null}],['whatsapp',{whatsappTab:null}],['download',{output:{blob:new Blob(['invalid'])}}]]){
        reset();failed=false;try{await run(action,overrides);}catch{failed=true;}
        check(failed && calls.length===0,'blocked/invalid '+action+' never records completion');
      }
      check(status({sent_to_patient:true}).label==='تم الإرسال','legacy sent state remains visible');
      reset();await run('whatsapp-download');
      return passed;
    } finally { $http.defaults.adapter=originalAdapter;HTMLAnchorElement.prototype.click=originalClick; }
  });
  console.log(checks.map(x=>'PASS '+x).join('\n'));
  await page.screenshot({path:'test-results/report-pages/action-status-desktop.png'});
  await page.getByRole('button',{name:'إخراج التقرير',exact:true}).click();
  await page.getByRole('radio',{name:/حفظ \+ إرسال/}).check();
  await page.screenshot({path:'test-results/report-pages/action-status-dialog.png'});
  await page.getByRole('button',{name:'تنفيذ',exact:true}).click();
  assert.equal(await page.evaluate(()=>window.fixture.selections.at(-1).action),'whatsapp-download');
  await page.getByRole('button',{name:'إخراج التقرير',exact:true}).click();
  await page.getByRole('button',{name:'معاينة التقرير',exact:true}).click();
  assert.equal(await page.evaluate(()=>window.fixture.selections.at(-1).action),'preview');
  await page.evaluate(()=>window.fixture.canShare.value=false);
  await page.getByRole('button',{name:'إخراج التقرير',exact:true}).click();
  assert.equal(await page.getByRole('radio',{name:/حفظ \+ إرسال/}).isDisabled(),true);
  await page.getByRole('button',{name:'إغلاق',exact:true}).click();
  await page.setViewportSize({width:390,height:844});
  await page.screenshot({path:'test-results/report-pages/action-status-mobile.png',fullPage:true});
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
  assert.deepEqual(errors,[]);
  console.log('PASS modal combined choice, preview, permissions and mobile layout');
} finally { await browser.close(); }
