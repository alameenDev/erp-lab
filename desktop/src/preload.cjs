const {contextBridge,ipcRenderer} = require('electron');
if (process.isMainFrame) contextBridge.exposeInMainWorld('digitalLabDesktop',Object.freeze({
  version:'1.0.0',
  printRequest:(path,options)=>ipcRenderer.invoke('desktop:print',path,options),
  openExternal:url=>ipcRenderer.invoke('desktop:external',url),
}));
