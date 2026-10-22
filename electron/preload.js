const { contextBridge, ipcRenderer } = require('electron');

// License / trial windows (register.html, expired.html)
contextBridge.exposeInMainWorld('electronAPI', {
  saveLicense:   (data) => ipcRenderer.invoke('license-save', data),
  checkLicense:  ()     => ipcRenderer.invoke('license-check-online'),
  quitApp:       ()     => ipcRenderer.invoke('license-quit'),
  openWhatsApp:  ()     => ipcRenderer.invoke('license-open-whatsapp'),
  getLicenseInfo: ()    => ipcRenderer.invoke('license-get-info'),
});


contextBridge.exposeInMainWorld('desktop', {
    isDesktop: true,

    platform: process.platform,

    /** Returns [{name, isDefault, status}] of all installed printers. */
    getPrinters: () => ipcRenderer.invoke('get-printers'),

    /**
     * Silently prints the given URL to the named printer.
     * @param {{ url: string, printerName: string|null }} opts
     */
    printSilent: (opts) => ipcRenderer.invoke('print-silent', opts),

    /** Prints a short test slip. */
    printTest: (printerName) => ipcRenderer.invoke('print-test', printerName),

    /**
     * Prints a calibration slip.
     * @param {{ printerName?: string, width?: '58mm'|'80mm' }} opts
     */
    printCalibration: (opts) => ipcRenderer.invoke('print-calibration', opts),

    /** Opens the most recently rendered print PDF. */
    openLastPrint: () => ipcRenderer.invoke('open-last-print'),

    /** Returns startup check results from userData/startup-check.json. */
    getStartupCheck: () => ipcRenderer.invoke('get-startup-check'),

    /** Returns last 20 print jobs from userData/print-history.json. */
    getPrintHistory: () => ipcRenderer.invoke('get-print-history'),

    /** Exports diagnostics ZIP to the user's desktop. */
    exportDiagnostics: () => ipcRenderer.invoke('export-diagnostics'),

    /** Trigger a catalog pull + sales push in the background. */
    syncNow: () => ipcRenderer.invoke('sync-now'),

    /** Returns { status, synced_at } from last_sync.json. */
    lastSync: () => ipcRenderer.invoke('last-sync'),

    /** Called when a background sync completes. */
    onSyncDone: (cb) => ipcRenderer.on('sync-done', cb),
});
