const {contextBridge,ipcRenderer} = require('electron');
contextBridge.exposeInMainWorld('desktopControl',Object.freeze({
  status:()=>ipcRenderer.invoke('desktop:status'),
  install:input=>ipcRenderer.invoke('desktop:install',input),
  backup:password=>ipcRenderer.invoke('desktop:backup',password),
  restore:password=>ipcRenderer.invoke('desktop:restore',password),
  open:()=>ipcRenderer.invoke('desktop:open'),
}));
