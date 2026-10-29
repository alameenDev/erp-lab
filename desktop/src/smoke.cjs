const assert=require('node:assert/strict');const fs=require('node:fs');const fsp=fs.promises;const path=require('node:path');const crypto=require('node:crypto');
const {BrowserWindow,session,ipcMain}=require('electron');
const {Runtime}=require('./runtime.cjs');const {createGateway}=require('./gateway.cjs');const {ORIGIN,PORT}=require('./constants.cjs');const {writeBackup,prepareRestore}=require('./backup.cjs');
const pause=ms=>new Promise(r=>setTimeout(r,ms));
async function run({app,bundle,p,vault,license}){
  const qa=process.env.DLD_SMOKE_OUTPUT||path.join(app.getPath('userData'),'qa');await fsp.mkdir(qa,{recursive:true});
  let runtime=new Runtime(p,vault,license),gateway,win;
  const secret=crypto.randomBytes(32).toString('hex'),password=crypto.randomBytes(16).toString('hex');let token='';
  const headers=()=>({'X-Digital-Lab-Desktop':secret,Accept:'application/json',...(token?{Authorization:'Bearer '+token}:{})});
  const api=async(route,body)=>{const response=await fetch(ORIGIN+'/api'+route,{method:body?'POST':'GET',headers:{...headers(),...(body?{'Content-Type':'application/json'}:{})},...(body?{body:JSON.stringify(body)}:{})});const text=await response.text();assert.ok(response.ok,route+' HTTP '+response.status+' '+text.slice(0,500));return JSON.parse(text);};
  try{
    const status=await runtime.init();assert.equal(status.installed,false);
    await runtime.install({owner_name:'QA Owner',owner_email:'owner@example.test',owner_password:password,lab_name:'مختبر فحص النسخة',lab_email:'lab@example.test',lab_password:password});
    await assert.rejects(runtime.install({}),/سابق/);
    if(license?.type==='lifetime')assert.equal((await runtime.sql('SELECT end_date FROM digital_lab_desktop.subscriptions LIMIT 1')).trim(),'9999-12-31');
    gateway=createGateway(new Proxy({},{get:(_,key)=>typeof runtime[key]==='function'?runtime[key].bind(runtime):runtime[key]}),secret);await new Promise((r,j)=>{gateway.server.once('error',j);gateway.server.listen(PORT,'127.0.0.1',r);});
    assert.equal((await fetch(ORIGIN+'/api/patients')).status,403);
    assert.equal((await fetch(ORIGIN+'/api/patients',{headers:{...headers(),Origin:'https://original-site.example'}})).status,403);
    assert.equal((await fetch(ORIGIN+'/api/device/bridge/heartbeat',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'})).status,401);
    const login=await api('/user/login',{email:'lab@example.test',password});token=login.token;assert.equal(login.user.role_id,2);
    assert.deepEqual(await api('/patients'),[]);
    const patient=await api('/patients/create',{name:'مريض فحص اصطناعي',title_id_fk:1,gender_id_fk:1,age:30,age_unit_id_fk:1});assert.ok(patient.id);
    const category=await api('/categories/create',{name:'فئة فحص'});
    const createdTest=await api('/tests/create',{name:'CBC QA',category_id_fk:category.id,result_type_id_fk:1,price:25000,unit:'g/dL'});assert.ok(createdTest.test.id);
    const testId=createdTest.test.id;
    const invoice=await api('/invoices/create',{patient_id_fk:patient.id,total:25000,sub_total:25000,paid:10000,work_register_amount:1234,tests:[{test_id_fk:testId,price:25000}]});assert.ok(invoice.id);
    const day=await api('/work-register');assert.equal(day.entries.length,1);assert.equal(day.totals.work_register_amount,1234);
    const barcode=await api(`/invoices/${invoice.id}/barcode-register`);assert.equal(barcode.work_register_number,1);
    await api('/invoices/update-result?silent=1',{id:invoice.id,tests:[{test_id_fk:testId,result:'12.3',price:25000,is_done:true}]});
    assert.equal((await runtime.sql(`SELECT result FROM digital_lab_desktop.invoice_test_rels WHERE invoice_id_fk=${Number(invoice.id)} AND test_id_fk=${Number(testId)}`)).trim(),'12.3');
    await api('/invoices/update-result?silent=1',{id:invoice.id,tests:[{test_id_fk:testId,result:'13.1',price:25000,is_done:true}]});
    assert.equal((await runtime.sql(`SELECT result FROM digital_lab_desktop.invoice_test_rels WHERE invoice_id_fk=${Number(invoice.id)} AND test_id_fk=${Number(testId)}`)).trim(),'13.1');
    const multipart=new FormData();multipart.append('id',invoice.id);multipart.append('existing_attachments','[]');multipart.append('attachments[0][name]','مرفق الفحص');multipart.append('attachments[0][upload_id]',crypto.randomUUID());multipart.append('attachments[0][file]',new Blob(['%PDF-1.4\n% synthetic attachment\n%%EOF'],{type:'application/pdf'}),'qa.pdf');
    const upload=await fetch(ORIGIN+'/api/invoices/update-result?silent=1',{method:'POST',headers:headers(),body:multipart});assert.equal(upload.status,200,await upload.clone().text());const saved=await upload.json();assert.equal(saved.attachments.length,1);
    assert.ok(saved.attachments[0].file.startsWith(ORIGIN+'/'),JSON.stringify(saved.attachments[0]));
    const attachment=await fetch(saved.attachments[0].file,{headers:headers()});assert.equal(attachment.status,200);assert.ok((await attachment.text()).startsWith('%PDF'));
    console.log('PASS: fresh local install, private accounts, login, patient, invoice, register, barcode reference and multipart PDF attachment.');
    const ses=session.fromPartition('desktop-smoke-'+crypto.randomUUID());const blocked=[],apiErrors=[],pending=new Set();let lastApiActivity=Date.now();
    ses.webRequest.onBeforeRequest((details,callback)=>{const allow=details.url.startsWith(ORIGIN+'/')||/^(data:|blob:|about:)/.test(details.url);if(!allow)blocked.push(new URL(details.url).hostname);if(allow&&details.url.startsWith(ORIGIN+'/api/')){pending.add(details.id);lastApiActivity=Date.now();}callback({cancel:!allow});});
    ses.webRequest.onCompleted(details=>{if(pending.delete(details.id)){lastApiActivity=Date.now();if(details.statusCode>=500)apiErrors.push(new URL(details.url).pathname+' '+details.statusCode);}});
    ses.webRequest.onErrorOccurred(details=>{if(pending.delete(details.id))lastApiActivity=Date.now();});
    ses.webRequest.onBeforeSendHeaders((details,callback)=>{if(details.url.startsWith(ORIGIN+'/'))details.requestHeaders['X-Digital-Lab-Desktop']=secret;callback({requestHeaders:details.requestHeaders});});
    win=new BrowserWindow({show:false,width:1440,height:1000,webPreferences:{session:ses,sandbox:true,nodeIntegration:false,contextIsolation:true,preload:path.join(__dirname,'preload.cjs')}});
    const errors=[];win.webContents.on('console-message',(_e,_level,message)=>{if(/Uncaught/.test(message))errors.push(message);});
    ipcMain.handle('desktop:print',async(_e,route)=>route==='/health'?{name:'Digital Lab Print',protocol:1}:{printers:[]});
    await win.loadURL(ORIGIN+'/login');await pause(1500);
    await win.webContents.executeJavaScript(`localStorage.setItem('token',${JSON.stringify(token)});localStorage.setItem('user',${JSON.stringify(JSON.stringify(login.user))});localStorage.setItem('rolePermissions',${JSON.stringify(JSON.stringify(login.user.roles.flatMap(r=>r.permissions).map(p=>p.name)))});`);
    for(const route of ['/patients','/invoices','/medical_reports','/patients/work-register','/devices','/printer-settings']){
      await win.loadURL(ORIGIN+route);await pause(1800);
      const expectsPatient=['/patients','/invoices','/medical_reports','/patients/work-register'].includes(route);
      const until=Date.now()+45000;let text='',settled=false;
      while(Date.now()<until){text=await win.webContents.executeJavaScript('document.body.innerText');if(text.length>80&&!pending.size&&Date.now()-lastApiActivity>500&&(!expectsPatient||text.includes('مريض فحص اصطناعي'))){settled=true;break;}await pause(250);}
      await fsp.writeFile(path.join(qa,route.replaceAll('/','_')+'.png'),(await win.webContents.capturePage()).toPNG());
      assert.ok(settled,route+' did not finish loading: '+text.slice(0,600));assert.equal(new URL(win.webContents.getURL()).pathname,route,route+' redirected unexpectedly');
      if(route==='/printer-settings'){assert.ok(text.includes('مدمج'));assert.ok(!text.includes('رمز الربط'));assert.equal(await win.webContents.executeJavaScript('typeof window.require'),'undefined');}
    }
    assert.equal(errors.length,0,errors.join('\n'));assert.equal(apiErrors.length,0,apiErrors.join('\n'));assert.ok(!blocked.some(h=>h.includes('hostingersite')));win.destroy();win=null;
    console.log('PASS: packaged frontend routes, Arabic UI, sandbox and native printer settings; network restricted to this installation.');
    await gateway.pause();await runtime.stopDatabase();
    const backup=path.join(qa,'roundtrip.dlbackup');await writeBackup({data:p.data,secrets:runtime.secrets,destination:backup,password});
    const stage=p.data+'.roundtrip';await prepareRestore({source:backup,password,stage,vault});
    const previous=p.data+'.old';await fsp.rename(p.data,previous);await fsp.rename(stage,p.data);
    runtime=new Runtime(p,vault,license);const restored=await runtime.init();assert.equal(restored.installed,true);gateway.resume();
    const restoredLogin=await api('/user/login',{email:'lab@example.test',password});token=restoredLogin.token;
    assert.equal((await api('/patients')).length,1);assert.equal((await api('/work-register')).totals.work_register_amount,1234);
    const restoredPdf=await fetch(saved.attachments[0].file,{headers:headers()});assert.equal(restoredPdf.status,200);
    console.log('PASS: encrypted backup round trip preserves accounts, patient, invoice, register and PDF; existing data survives restart.');
    await require(path.join(bundle,'print/smoke.cjs')).run();
    await fsp.writeFile(path.join(qa,'acceptance.json'),JSON.stringify({version:'1.1.0',packaged:app.isPackaged,passed:true,checks:['install','isolation','login','patient','invoice','result-entry','result-edit','register','barcode','attachments','frontend','backup','restore','restart','native-print']}));
  }catch(e){
    // Synthetic test data only, useful when Windows CI cannot display a console.
    await fsp.writeFile(path.join(qa,'failure.txt'),e.stack+'\n'+(e.detail||''));
    for(const file of await fsp.readdir(path.join(p.storage,'logs')).catch(()=>[])){if(/^(database|laravel).*\.log$/.test(file)){try{await fsp.copyFile(path.join(p.storage,'logs',file),path.join(qa,file));}catch{}}}
    throw e;
  }finally{if(win&&!win.isDestroyed())win.destroy();if(gateway)await gateway.pause();await runtime.stopDatabase();if(gateway)await gateway.close();}
}
module.exports={run};
