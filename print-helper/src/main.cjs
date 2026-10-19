const {app, BrowserWindow, Tray, Menu, dialog, ipcMain, protocol} = require('electron');
const path = require('node:path');
const {pathToFileURL} = require('node:url');
const {State} = require('./state.cjs');
const {createServer, PORT} = require('./server.cjs');
const printing = require('./printing.cjs');
protocol.registerSchemesAsPrivileged([{scheme: 'dlprint', privileges: {standard: true, secure: true, supportFetchAPI: true}}]);
const smoke = process.argv.includes('--smoke-test');
if (!smoke && !app.requestSingleInstanceLock()) {app.quit();}
else {
  let window, tray, state, server, quitting = false;
  const show = () => {window?.show(); window?.focus();};
  app.on('second-instance', show);
  app.on('activate', show);
  app.on('window-all-closed', () => {});
  app.on('before-quit', () => {quitting = true; server?.close();});
  app.whenReady().then(async () => {
    if (smoke) {
      try {await require('./smoke.cjs').run(); app.exit(0);} catch (error) {console.error(error); app.exit(1);} return;
    }
    state = new State(path.join(app.getPath('userData'), 'state.json'));
    window = new BrowserWindow({width: 620, height: 640, minWidth: 500, minHeight: 500, show: false,
      title: 'Digital Lab Print', icon: path.join(__dirname, '../assets/icon.png'), autoHideMenuBar: true,
      webPreferences: {preload: path.join(__dirname, 'preload.cjs'), nodeIntegration: false, contextIsolation: true, sandbox: true}});
    const uiUrl = pathToFileURL(path.join(__dirname, 'ui.html')).href;
    window.webContents.setWindowOpenHandler(() => ({action: 'deny'}));
    window.webContents.on('will-navigate', e => e.preventDefault());
    window.on('close', e => {if (!quitting) {e.preventDefault(); window.hide();}});
    const handle = (name, fn) => ipcMain.handle(name, (event, ...args) => {
      if (event.sender !== window.webContents || event.senderFrame !== window.webContents.mainFrame || event.senderFrame.url !== uiUrl) throw new Error('IPC_DENIED');
      return fn(...args);
    });
    handle('status', () => ({version: app.getVersion(), clients: state.data.clients.map(c => ({origin: c.origin})),
      startup: app.getLoginItemSettings().openAtLogin, code: state.code}));
    handle('code', () => state.newCode());
    handle('revoke', origin => {state.revoke(String(origin)); return true;});
    handle('startup', enabled => {app.setLoginItemSettings({openAtLogin: enabled === true, args: ['--background']}); return true;});
    handle('printers', () => printing.listPrinters().then(ps => ps.map(p => ({name: p.name, displayName: p.displayName || p.name}))));
    await window.loadFile(path.join(__dirname, 'ui.html'));
    tray = new Tray(path.join(__dirname, '../assets/icon.png'));
    tray.setToolTip('Digital Lab Print');
    tray.setContextMenu(Menu.buildFromTemplate([{label: 'فتح Digital Lab Print', click: show}, {type: 'separator'}, {label: 'إنهاء', click: () => app.quit()}]));
    tray.on('double-click', show);
    server = createServer({state, listPrinters: printing.listPrinters, print: printing.print, development: !app.isPackaged,
      approvePair: async origin => {
        show();
        const result = await dialog.showMessageBox(window, {type: 'question', title: 'ربط موقع Digital Lab',
          message: 'السماح لهذا الموقع بإرسال المستندات إلى طابعات هذه الحاسبة؟', detail: origin,
          buttons: ['إلغاء', 'السماح والربط'], defaultId: 0, cancelId: 0});
        return result.response === 1;
      }});
    server.on('error', async error => {
      await dialog.showMessageBox(window, {type: 'error', message: error.code === 'EADDRINUSE' ? 'منفذ مساعد الطباعة مشغول. أغلق النسخة الأخرى ثم افتح البرنامج.' : 'تعذر بدء مساعد الطباعة.'});
      app.quit();
    });
    server.listen(PORT, '127.0.0.1');
    if (!process.argv.includes('--background')) show();
  }).catch(error => {dialog.showErrorBox('Digital Lab Print', 'تعذر تشغيل المساعد. أعد تشغيله أو أعد تثبيته.'); app.exit(1);});
}
