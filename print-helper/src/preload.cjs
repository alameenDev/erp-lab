const {contextBridge, ipcRenderer} = require('electron');
contextBridge.exposeInMainWorld('digitalLabPrint', {
  status: () => ipcRenderer.invoke('status'), code: () => ipcRenderer.invoke('code'),
  revoke: origin => ipcRenderer.invoke('revoke', origin), startup: enabled => ipcRenderer.invoke('startup', enabled),
  printers: () => ipcRenderer.invoke('printers'),
});
