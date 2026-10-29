const {contextBridge,ipcRenderer}=require('electron');
contextBridge.exposeInMainWorld('activation',Object.freeze({status:()=>ipcRenderer.invoke('activation:status'),request:binding=>ipcRenderer.invoke('activation:request',binding),import:()=>ipcRenderer.invoke('activation:import'),continue:()=>ipcRenderer.invoke('activation:continue')}));
