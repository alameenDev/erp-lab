const {app,BrowserWindow,Menu,ipcMain,session,dialog,shell,safeStorage,protocol} = require('electron');
const path = require('node:path');
const fs = require('node:fs');
const fsp = fs.promises;
const crypto = require('node:crypto');
const {pathToFileURL} = require('node:url');
const {Runtime} = require('./runtime.cjs');
const {createGateway} = require('./gateway.cjs');
const {createPrinters} = require('./printers.cjs');
const {writeBackup,prepareRestore} = require('./backup.cjs');
const {paths,PORT,ORIGIN} = require('./constants.cjs');
const {allowedRequest} = require('./network.cjs');
protocol.registerSchemesAsPrivileged([{scheme:'dlprint',privileges:{standard:true,secure:true,supportFetchAPI:true}}]);
const smoke = process.argv.includes('--smoke-test');
if (smoke) app.on('window-all-closed',()=>{});
if (smoke) app.setPath('userData',process.env.DLD_SMOKE_DATA || path.join(app.getPath('temp'),'DigitalLabDesktop-smoke-'+crypto.randomUUID()));
else app.setPath('userData',path.join(app.getPath('appData'),'DigitalLabDesktopV1'));
const bundle = app.isPackaged ? path.join(process.resourcesPath,'bundle') : path.join(__dirname,'../bundle');
const p = paths(bundle,path.join(app.getPath('userData'),'lab-data'));
const controlUrl = pathToFileURL(path.join(__dirname,'../assets/control.html')).href;
let runtime,gateway,printers,main,control,busy=false,quitting=false,ready=false;
const vault = {encrypt:value=>safeStorage.encryptString(value),decrypt:value=>safeStorage.decryptString(value)};
if (!smoke && !app.requestSingleInstanceLock()) app.quit();
app.on('second-instance',()=>{const win=main||control;if(win){win.show();win.focus();}});
const errorBox = e=>dialog.showMessageBox({type:'error',title:'Digital Lab Desktop',message:e.message||'تعذر تنفيذ العملية.'});
function validSender(event,controlOnly=false) {
  const win = controlOnly ? control : main;
  if (!win || win.isDestroyed() || event.sender!==win.webContents || event.senderFrame!==win.webContents.mainFrame || (controlOnly ? event.senderFrame.url!==controlUrl : !event.senderFrame.url.startsWith(ORIGIN+'/'))) throw new Error('Unauthorized desktop request');
}
async function external(url) {
  const parsed = new URL(url);
  if(parsed.protocol!=='https:' || !['wa.me','www.google.com','maps.google.com','www.youtube.com'].includes(parsed.hostname) || parsed.username || parsed.password) throw new Error('هذا الرابط غير متاح في النسخة المستقلة.');
  await shell.openExternal(parsed.href);
}
function showControl() {
  if(control&&!control.isDestroyed()){control.show();control.focus();return;}
  control = new BrowserWindow({width:860,height:900,minWidth:620,minHeight:650,title:'Digital Lab Desktop V1',backgroundColor:'#f8fafc',autoHideMenuBar:true,webPreferences:{preload:path.join(__dirname,'control-preload.cjs'),sandbox:true,nodeIntegration:false,contextIsolation:true}});
  control.webContents.setWindowOpenHandler(()=>({action:'deny'}));
  control.webContents.on('will-navigate',e=>e.preventDefault());
  control.on('close',e=>{if(!quitting && (busy||!ready)){e.preventDefault();errorBox(new Error('انتظر اكتمال تشغيل الخدمات أو العملية الحالية قبل الإغلاق.'));}});
  control.on('closed',()=>{control=null;if(!main&&!busy)app.quit();});
  return control.loadURL(controlUrl);
}
async function openMain() {
  if (!runtime.status?.installed) return showControl();
  if(main&&!main.isDestroyed()){main.show();main.focus();return;}
  main = new BrowserWindow({width:1440,height:940,minWidth:1000,minHeight:700,title:'Digital Lab Desktop V1',backgroundColor:'#f8fafc',webPreferences:{partition:'persist:desktop-v1',preload:path.join(__dirname,'preload.cjs'),sandbox:true,nodeIntegration:false,contextIsolation:true}});
  main.webContents.setWindowOpenHandler(({url})=>{
    if(url==='about:blank'||url.startsWith(ORIGIN+'/')||url.startsWith('blob:'+ORIGIN+'/')) return {action:'allow',overrideBrowserWindowOptions:{webPreferences:{sandbox:true,nodeIntegration:false,contextIsolation:true,preload:undefined},autoHideMenuBar:true}};
    external(url).catch(errorBox);return {action:'deny'};
  });
  main.webContents.on('did-create-window',child=>{
    child.webContents.on('will-navigate',(event,url)=>{if(!url.startsWith(ORIGIN+'/')&&url!=='about:blank'&&!url.startsWith('blob:'+ORIGIN+'/')) {event.preventDefault();external(url).then(()=>child.close()).catch(errorBox);}});
    child.webContents.setWindowOpenHandler(()=>({action:'deny'}));
  });
  main.webContents.on('will-navigate',(event,url)=>{if(!url.startsWith(ORIGIN+'/')) {event.preventDefault();external(url).catch(errorBox);}});
  main.on('page-title-updated',e=>e.preventDefault());
  main.on('close',e=>{if(!quitting && (busy||printers?.pending)){e.preventDefault();errorBox(new Error('انتظر اكتمال العملية أو الطباعة قبل الإغلاق.'));}});
  main.on('closed',()=>{main=null;if(!busy)app.quit();});
  await main.loadURL(ORIGIN+'/login');
  control?.hide();
}
async function maintenance(operation) {
  if(busy) throw new Error('انتظر اكتمال العملية الحالية.');
  if(printers.pending) throw new Error('انتظر اكتمال الطباعة قبل النسخ أو الاستعادة.');
  busy=true;main?.setEnabled(false);
  try {await gateway.pause();await runtime.stopDatabase();return await operation();}
  finally {try {if(!runtime.db) await runtime.startDatabase();} finally {gateway.resume();busy=false;if(main&&!main.isDestroyed())main.setEnabled(true);}}
}
function registerIpc() {
  ipcMain.handle('desktop:status',e=>{validSender(e,true);return {ready,installed:!!runtime?.status?.installed,version:'1.0.0',dataPath:p.data};});
  ipcMain.handle('desktop:install',async(e,input)=>{validSender(e,true);if(busy||!ready)throw new Error('انتظر اكتمال تشغيل الخدمات.');busy=true;try{return await runtime.install(input);}finally{busy=false;}});
  ipcMain.handle('desktop:open',async e=>{validSender(e,true);await openMain();return true;});
  ipcMain.handle('desktop:print',(e,route,options)=>{validSender(e);return printers.request(route,options);});
  ipcMain.handle('desktop:external',(e,url)=>{validSender(e);return external(url);});
  ipcMain.handle('desktop:backup',async(e,password)=>{
    validSender(e,true);if(!runtime.status.installed)throw new Error('أكمل الإعداد أولاً.');
    const choice = await dialog.showSaveDialog(control,{title:'حفظ نسخة احتياطية مشفرة',defaultPath:`Digital-Lab-${new Date().toISOString().slice(0,10)}.dlbackup`,filters:[{name:'Digital Lab backup',extensions:['dlbackup']}]});
    if(choice.canceled)return {canceled:true};
    await maintenance(()=>writeBackup({data:p.data,secrets:runtime.secrets,destination:choice.filePath,password}));return {path:choice.filePath};
  });
  ipcMain.handle('desktop:restore',async(e,password)=>{
    validSender(e,true);
    const choice=await dialog.showOpenDialog(control,{title:'اختيار نسخة احتياطية',properties:['openFile'],filters:[{name:'Digital Lab backup',extensions:['dlbackup']}]});
    if(choice.canceled)return {canceled:true};
    const confirm=await dialog.showMessageBox(control,{type:'warning',title:'استعادة النسخة',message:'الاستعادة تستبدل بيانات هذه الحاسبة ببيانات النسخة المختارة. سيُحفظ المجلد الحالي للاسترجاع. أغلق النماذج غير المحفوظة قبل المتابعة.',buttons:['إلغاء','استعادة'],defaultId:0,cancelId:0});
    if(confirm.response!==1)return {canceled:true};
    if(busy)throw new Error('انتظر اكتمال العملية الحالية.');busy=true;
    const stage=p.data+'.restore-'+crypto.randomUUID();
    try {await prepareRestore({source:choice.filePaths[0],password,stage,vault});} finally {busy=false;}
    const result = await maintenance(async()=>{
      const previous=p.data+'.before-restore-'+Date.now();
      await fsp.rename(p.data,previous);
      try {
        await fsp.rename(stage,p.data);runtime=new Runtime(p,vault);gatewayRuntime.current=runtime;
        await runtime.init();
      } catch(e) {
        try {await runtime.stopDatabase();}catch{}
        await fsp.rename(p.data,p.data+'.failed-restore-'+Date.now());await fsp.rename(previous,p.data);
        runtime=new Runtime(p,vault);gatewayRuntime.current=runtime;await runtime.init();throw new Error('لم تنجح الاستعادة. رجعت البيانات السابقة تلقائياً.');
      }
      await session.fromPartition('persist:desktop-v1').clearStorageData();
      return {restored:true,previous};
    });
    // Reload only after maintenance reopens the local gateway.
    if(main&&!main.isDestroyed())await main.loadURL(ORIGIN+'/login');
    return result;
  });
}
// Gateway accesses the current runtime after a restore, never stale credentials.
const gatewayRuntime={current:null};
const proxyRuntime=new Proxy({}, {get:(_,key)=>{const value=gatewayRuntime.current[key];return typeof value==='function'?value.bind(gatewayRuntime.current):value;}});
function menu() {
  Menu.setApplicationMenu(Menu.buildFromTemplate([
    {label:'البرنامج',submenu:[{label:'إعداد النسخة والنسخ الاحتياطي',click:showControl},{label:'الطابعات لهذا الجهاز',click:()=>main?.loadURL(ORIGIN+'/printer-settings')},{type:'separator'},{label:'خروج',role:'quit'}]},
    {label:'تحرير',submenu:[{role:'undo'},{role:'redo'},{type:'separator'},{role:'cut'},{role:'copy'},{role:'paste'},{role:'selectAll'}]},
    {label:'عرض',submenu:[{role:'reload'},{role:'resetZoom'},{role:'zoomIn'},{role:'zoomOut'},{role:'togglefullscreen'}]},
    {label:'أجهزة التحليل',submenu:[['DxH 500','dxh500'],['NP-21H','np21h'],['Izmir / BM850','bm850']].map(([label,adapter])=>({label,click:()=>{
      const executable=path.join(bundle,'bridges',`DigitalLabDesktopBridge-${adapter}.exe`);
      const child=require('node:child_process').spawn(executable,[],{windowsHide:false,detached:true,stdio:'ignore'});child.on('error',()=>errorBox(new Error('تعذر فتح مستقبل الجهاز.')));child.unref();
    }}))},
    {label:'مساعدة',submenu:[{label:'دليل V1',click:()=>shell.openPath(path.join(bundle,'README_AR.txt'))},{label:'عن البرنامج',click:()=>dialog.showMessageBox({title:'Digital Lab Desktop',message:'Digital Lab Desktop — V1.0.0',detail:'نسخة مستقلة بقاعدة بيانات ومرفقات محلية. لا تتصل بقاعدة بيانات الموقع.'})}]}
  ]));
}
app.whenReady().then(async()=>{
  if(!safeStorage.isEncryptionAvailable())throw new Error('تعذر الوصول إلى حماية المفاتيح في Windows.');
  if(smoke) {await require('./smoke.cjs').run({app,bundle,p,vault});app.exit(0);return;}
  menu();registerIpc();await showControl();
  runtime=new Runtime(p,vault);gatewayRuntime.current=runtime;
  await runtime.init();
  const secret=crypto.randomBytes(32).toString('hex');gateway=createGateway(proxyRuntime,secret);
  await new Promise((resolve,reject)=>{gateway.server.once('error',reject);gateway.server.listen(PORT,'127.0.0.1',resolve);});
  const ses=session.fromPartition('persist:desktop-v1');
  ses.setPermissionRequestHandler((contents,permission,callback)=>callback(permission==='clipboard-sanitized-write' && contents===main?.webContents && contents.getURL().startsWith(ORIGIN+'/')));
  ses.setPermissionCheckHandler((contents,permission,origin)=>permission==='clipboard-sanitized-write' && contents===main?.webContents && origin===ORIGIN);
  ses.webRequest.onBeforeRequest((details,callback)=>callback({cancel:!allowedRequest(details)}));
  ses.webRequest.onBeforeSendHeaders((details,callback)=>{if(details.url.startsWith(ORIGIN+'/'))details.requestHeaders['X-Digital-Lab-Desktop']=secret;callback({requestHeaders:details.requestHeaders});});
  ses.on('will-download',(_e,item)=>{
    const file=dialog.showSaveDialogSync(main||control,{title:'حفظ الملف',defaultPath:item.getFilename()});
    if(!file)item.cancel();else item.setSavePath(file);
  });
  printers=createPrinters(bundle,app.getPath('userData'));ready=true;
  if(runtime.status.installed)await openMain();
}).catch(async e=>{if(smoke){console.error(e.stack);app.exit(1);return;}await errorBox(e);try{await runtime?.stopDatabase();}catch{}app.exit(1);});
app.on('before-quit',e=>{
  if(quitting||smoke)return;e.preventDefault();
  if(busy){errorBox(new Error('انتظر اكتمال النسخ أو الإعداد قبل الإغلاق.'));return;}
  if(printers?.pending){errorBox(new Error('انتظر اكتمال إرسال الطباعة قبل الإغلاق.'));return;}
  quitting=true;
  (async()=>{try{await gateway?.pause();await runtime?.stopDatabase();await gateway?.close();app.quit();}catch(error){quitting=false;gateway?.resume();await errorBox(error);}})();
});
