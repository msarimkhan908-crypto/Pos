const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('license', {
  register: (payload) => ipcRenderer.invoke('license-register', payload),
  registered: (success) => ipcRenderer.send('license-registered', success),
});
