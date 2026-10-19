const {BrowserWindow, session, ipcMain} = require('electron');
const path = require('node:path');
const safeError = message => Object.assign(new Error(message), {safeMessage: message});
const CSP = "default-src 'none'; script-src 'none'; style-src 'unsafe-inline'; img-src data:; font-src data:; base-uri 'none'; form-action 'none'; frame-src 'none'; object-src 'none'";

async function loadDocument(job) {
  const ses = session.fromPartition(`print-${job.id}`);
  ses.setPermissionRequestHandler((_wc, _permission, callback) => callback(false));
  ses.setPermissionCheckHandler(() => false);
  const url = `dlprint://document/${job.id}`;
  ses.protocol.handle('dlprint', request => request.url === url
    ? new Response(job.html, {headers: {'Content-Type': 'text/html; charset=utf-8', 'Content-Security-Policy': CSP}})
    : new Response('', {status: 403}));
  ses.webRequest.onBeforeRequest((details, callback) => callback({cancel: details.url !== url && !details.url.startsWith('data:')}));
  ses.on('will-download', event => event.preventDefault());
  const win = new BrowserWindow({show: false, width: Math.ceil(job.paper.width * 96 / 25.4), height: 1123,
    webPreferences: {session: ses, preload: path.join(__dirname, 'print-preload.cjs'), nodeIntegration: false, contextIsolation: true, sandbox: true, javascript: false,
      webSecurity: true, allowRunningInsecureContent: false, backgroundThrottling: false, spellcheck: false}});
  win.webContents.setWindowOpenHandler(() => ({action: 'deny'}));
  win.webContents.on('will-navigate', event => event.preventDefault());
  win.webContents.on('will-attach-webview', event => event.preventDefault());
  const renderDeadline = setTimeout(() => {if (!win.isDestroyed()) win.destroy();}, 45000);
  const close = () => {clearTimeout(renderDeadline); if (!win.isDestroyed()) win.destroy(); ses.protocol.unhandle('dlprint');};
  try {
    const details = await new Promise((resolve, reject) => {
      const cleanup = () => {ipcMain.removeListener('dlprint:prepared', prepared);win.removeListener('closed', closed);};
      const closed = () => {cleanup();reject(new Error('RENDERER_CLOSED'));};
      const prepared = (event, data) => {
        if (event.sender !== win.webContents || event.senderFrame !== win.webContents.mainFrame || event.senderFrame.url !== url) return;
        cleanup();resolve(data);
      };
      ipcMain.on('dlprint:prepared', prepared);win.once('closed', closed);
      win.loadURL(url).then(() => win.webContents.send('dlprint:prepare', job.paper)).catch(error => {cleanup();reject(error);});
    });
    if (details.error) throw Object.assign(safeError(details.error === 'LABEL_OVERFLOW' ? 'نص الملصق لا يتسع في محرك الطباعة. راجع حجم الخط أو استخدم الطباعة الاعتيادية.' : 'تعذر تحميل محتوى المستند للطباعة.'), {cause: new Error(JSON.stringify(details))});
    const height = details.height;
    if (!Number.isFinite(height) || height < 10 || height > 2000) throw safeError('مقاس الورق غير صالح للطباعة المباشرة.');
    // CSS owns margins, backgrounds and content layout. Set only media size;
    // no scaling or changes to any patient/test content are introduced here.
    await win.webContents.insertCSS(`@page { size: ${job.paper.width}mm ${height}mm; }`);
    clearTimeout(renderDeadline);
    return {win, close, paper: {width: job.paper.width, height}, details};
  } catch (error) {close(); throw error.safeMessage ? error : Object.assign(safeError('تعذر تجهيز المستند أو تحميل صورة/ملصق. استخدم الطباعة الاعتيادية أو راجع إعدادات المستند.'), {cause: error});}
}
async function listPrinters() {
  const win = new BrowserWindow({show: false, webPreferences: {sandbox: true, nodeIntegration: false, contextIsolation: true}});
  try {return await win.webContents.getPrintersAsync();} finally {win.destroy();}
}
async function print(job, beforeSubmit) {
  const printers = await listPrinters();
  if (!printers.some(p => p.name === job.printer)) throw safeError('الطابعة المحفوظة غير موجودة. اخترها من إعدادات الطابعات لهذا الجهاز.');
  const loaded = await loadDocument(job);
  try {
    await new Promise((resolve, reject) => {
      const timer = setTimeout(() => reject(safeError('لم يصل تأكيد الإرسال من Windows. تحقق من قائمة الطباعة قبل إعادة المحاولة.')), 90000);
      try {
        // Persist before invoking the spooler; uncertainty must never trigger
        // an automatic retry or a second browser print.
        beforeSubmit();
        loaded.win.webContents.print({silent: true, deviceName: job.printer, copies: job.copies,
          printBackground: true, scaleFactor: 100, margins: {marginType: 'none'},
          pageSize: {width: Math.round(loaded.paper.width * 1000), height: Math.round(loaded.paper.height * 1000)}},
        success => {clearTimeout(timer); success ? resolve() : reject(safeError('لم يؤكد Windows نجاح إرسال الطباعة. تحقق من قائمة الطباعة والطابعة.'));});
      } catch (error) {clearTimeout(timer); reject(error);}
    });
  } finally {loaded.close();}
}
module.exports = {loadDocument, listPrinters, print};
