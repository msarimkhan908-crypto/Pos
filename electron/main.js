const { app, BrowserWindow, shell, Menu, Tray, dialog, ipcMain } = require('electron');
const { spawn, spawnSync } = require('child_process');
const crypto = require('crypto');
const path   = require('path');
const net    = require('net');
const http   = require('http');
const fs     = require('fs');

// In dev: ../  |  In production: resources/laravel
const IS_PACKAGED  = app.isPackaged;
const LARAVEL_ROOT = IS_PACKAGED
  ? path.join(process.resourcesPath, 'laravel')
  : path.join(__dirname, '..');

// All writable state lives in userData — survives app updates and uninstall.
const USER_DATA   = app.getPath('userData');
const DB_PATH     = path.join(USER_DATA, 'database.sqlite');
const CREDS_PATH  = path.join(USER_DATA, 'first_run_credentials.json');
const KEY_PATH    = path.join(USER_DATA, 'app-key.json');

// Derived paths inside userData/storage (matches Laravel's expected layout)
const STORAGE     = path.join(USER_DATA, 'storage');
const BOOT_CACHE  = path.join(USER_DATA, 'bootstrap-cache');
const UPLOADS_TMP = path.join(USER_DATA, 'uploads-tmp');

let phpProcess = null;
let mainWindow = null;
let tray       = null;
let serverPort = null;

// ── Helpers ──────────────────────────────────────────────────────────────────

const isWin = process.platform === 'win32';

function phpBin() {
  if (IS_PACKAGED) {
    const bundled = path.join(process.resourcesPath, 'php', 'php.exe');
    if (fs.existsSync(bundled)) return bundled;
  }
  return 'php';
}

/**
 * Build the environment for every PHP spawn.
 * We pass all runtime paths as env vars so the install folder is never written to.
 */
function buildPhpEnv() {
  const phpDir  = IS_PACKAGED ? path.join(process.resourcesPath, 'php') : null;
  const appKey  = readAppKey();
  const safeSep = (p) => p.replace(/\\/g, '/');

  const extra = {
    // Laravel core
    APP_KEY:              appKey,
    APP_ENV:              'production',
    APP_DEBUG:            'false',
    DB_CONNECTION:        'sqlite',
    DB_DATABASE:          DB_PATH,

    // Storage: all writable paths go to userData
    APP_STORAGE_PATH:     STORAGE,
    APP_SERVICES_CACHE:   path.join(BOOT_CACHE, 'services.php'),
    APP_PACKAGES_CACHE:   path.join(BOOT_CACHE, 'packages.php'),
    APP_CONFIG_CACHE:     path.join(BOOT_CACHE, 'config.php'),
    APP_ROUTES_CACHE:     path.join(BOOT_CACHE, 'routes-v7.php'),
    APP_EVENTS_CACHE:     path.join(BOOT_CACHE, 'events.php'),
    VIEW_COMPILED_PATH:   path.join(STORAGE, 'framework', 'views'),

    // Logging
    LOG_CHANNEL:          'single',
    LOG_LEVEL:            'error',

    // Session / cache
    SESSION_DRIVER:       'file',
    CACHE_STORE:          'file',

    // Uploads: point PHP's temp dir to our writable folder
    TMP:    UPLOADS_TMP,
    TEMP:   UPLOADS_TMP,
    TMPDIR: UPLOADS_TMP,

    // Point PHP to the bundled ini so extensions are loaded
    ...(phpDir ? { PHPRC: phpDir, PATH: `${phpDir};${process.env.PATH ?? ''}` } : {}),

    // Allow the built-in server to handle concurrent requests
    PHP_CLI_SERVER_WORKERS: '4',
  };

  return { ...process.env, ...extra };
}

function phpSpawnOpts(cwd) {
  return {
    cwd,
    stdio:       ['ignore', 'pipe', 'pipe'],
    windowsHide: true,
    shell:       false,
    env:         buildPhpEnv(),
  };
}

function artisan(args, cwd = LARAVEL_ROOT, timeout = 120_000) {
  return spawnSync(phpBin(), ['artisan', ...args], { ...phpSpawnOpts(cwd), timeout });
}

/**
 * Run `php -v` on the very first launch so antivirus scans the binary.
 * Skipped on subsequent launches to avoid the 2-3 s PHP startup penalty.
 */
function warmupPhp(isNewDb) {
  if (!isNewDb) return;
  spawnSync(phpBin(), ['-v'], { ...phpSpawnOpts(LARAVEL_ROOT), timeout: 30_000 });
}

// ── APP_KEY: stored in userData, never inside the install folder ───────────

function readAppKey() {
  try {
    if (fs.existsSync(KEY_PATH)) {
      const data = JSON.parse(fs.readFileSync(KEY_PATH, 'utf8'));
      if (data.key) return data.key;
    }
  } catch {}
  return '';
}

function ensureAppKey() {
  if (readAppKey()) return;
  const key = 'base64:' + crypto.randomBytes(32).toString('base64');
  fs.writeFileSync(KEY_PATH, JSON.stringify({ key }), 'utf8');
}

/**
 * Patch the bundled php.ini to hard-code upload_tmp_dir to our writable
 * userData folder. upload_tmp_dir is PHP_INI_SYSTEM — it can only be set
 * in php.ini, not in .user.ini, .htaccess, or ini_set(). We write the
 * real runtime path here so every PHP worker reads it from disk.
 */
function patchPhpIni() {
  if (!IS_PACKAGED) return;
  const iniPath = path.join(process.resourcesPath, 'php', 'php.ini');
  if (!fs.existsSync(iniPath)) return;

  const tmpDir = UPLOADS_TMP.replace(/\\/g, '/');
  let content = fs.readFileSync(iniPath, 'utf8');

  const line = `upload_tmp_dir = "${tmpDir}"`;
  if (/^upload_tmp_dir\s*=/m.test(content)) {
    content = content.replace(/^upload_tmp_dir\s*=.*/m, line);
  } else {
    content += `\n${line}\n`;
  }
  try {
    fs.writeFileSync(iniPath, content, 'utf8');
  } catch {}
}

function patchLaravelEnv() {
  const envFile = path.join(LARAVEL_ROOT, '.env');
  if (!fs.existsSync(envFile)) return;

  const key = readAppKey();
  const overrides = {
    APP_KEY:            key,
    DB_DATABASE:        DB_PATH.replace(/\\/g, '/'),
    APP_STORAGE_PATH:   STORAGE.replace(/\\/g, '/'),
    APP_SERVICES_CACHE: path.join(BOOT_CACHE, 'services.php').replace(/\\/g, '/'),
    APP_PACKAGES_CACHE: path.join(BOOT_CACHE, 'packages.php').replace(/\\/g, '/'),
    APP_CONFIG_CACHE:   path.join(BOOT_CACHE, 'config.php').replace(/\\/g, '/'),
    APP_ROUTES_CACHE:   path.join(BOOT_CACHE, 'routes-v7.php').replace(/\\/g, '/'),
    APP_EVENTS_CACHE:   path.join(BOOT_CACHE, 'events.php').replace(/\\/g, '/'),
    VIEW_COMPILED_PATH: path.join(STORAGE, 'framework', 'views').replace(/\\/g, '/'),
  };

  let content = fs.readFileSync(envFile, 'utf8');
  for (const [k, v] of Object.entries(overrides)) {
    const line = `${k}="${v}"`;
    if (new RegExp(`^${k}=`, 'm').test(content)) {
      content = content.replace(new RegExp(`^${k}=.*`, 'm'), line);
    } else {
      content += `\n${line}`;
    }
  }
  fs.writeFileSync(envFile, content, 'utf8');
}

// ── Create all writable dirs in userData on first run ───────────────────────

function ensureDataDirs() {
  const dirs = [
    STORAGE,
    path.join(STORAGE, 'app', 'public'),
    path.join(STORAGE, 'framework', 'cache', 'data'),
    path.join(STORAGE, 'framework', 'sessions'),
    path.join(STORAGE, 'framework', 'views'),
    path.join(STORAGE, 'logs'),
    BOOT_CACHE,
    UPLOADS_TMP,
  ];
  for (const dir of dirs) {
    fs.mkdirSync(dir, { recursive: true });
  }
}

// ── One-time migration of images from old install-folder location ────────────

function migrateOldStorage() {
  const oldPublic  = path.join(LARAVEL_ROOT, 'storage', 'app', 'public');
  const newPublic  = path.join(STORAGE, 'app', 'public');
  if (!fs.existsSync(oldPublic)) return;

  try {
    const files = fs.readdirSync(oldPublic, { recursive: true });
    let copied = 0;
    for (const rel of files) {
      const src  = path.join(oldPublic, rel);
      const dest = path.join(newPublic, rel);
      if (fs.statSync(src).isDirectory()) {
        fs.mkdirSync(dest, { recursive: true });
        continue;
      }
      if (!fs.existsSync(dest)) {
        fs.mkdirSync(path.dirname(dest), { recursive: true });
        fs.copyFileSync(src, dest);
        copied++;
      }
    }
    if (copied > 0) printLog(`migrated ${copied} files from old storage to userData`);
  } catch (e) {
    printLog(`warn migrateOldStorage: ${e.message}`);
  }
}

// ── SQLite migrations + seeding ───────────────────────────────────────────────

const CACHE_VERSION_PATH = path.join(USER_DATA, 'cache-version.json');

function readCachedVersion() {
  try {
    if (fs.existsSync(CACHE_VERSION_PATH)) {
      return JSON.parse(fs.readFileSync(CACHE_VERSION_PATH, 'utf8')).version ?? null;
    }
  } catch {}
  return null;
}

function writeCachedVersion() {
  try {
    fs.writeFileSync(CACHE_VERSION_PATH, JSON.stringify({ version: app.getVersion() }), 'utf8');
  } catch {}
}

function warmCaches() {
  artisan(['route:cache']);
  artisan(['view:cache']);
  artisan(['event:cache']);
  writeCachedVersion();
}

function runMigrationsIfNeeded() {
  const isNewDb = !fs.existsSync(DB_PATH);

  if (isNewDb) {
    console.log('[setup] New SQLite database — running migrations…');
    const result = artisan(['migrate', '--force']);
    if (result.status !== 0 || result.error) {
      const stderr = result.stderr?.toString().trim() ?? '';
      const stdout = result.stdout?.toString().trim() ?? '';
      const spawnErr = result.error?.message ?? '';
      const detail = [spawnErr, stderr, stdout].filter(Boolean).join('\n') || `exit code ${result.status ?? 'null'}`;
      throw new Error(`Migration failed:\n${detail}`);
    }
    console.log('[setup] Migrations complete.');

    console.log('[setup] Initialising roles and admin account…');
    const initResult = artisan(['desktop:init']);
    if (initResult.status === 0) {
      const stdout = initResult.stdout?.toString() ?? '';
      for (const line of stdout.split(/\r?\n/)) {
        const trimmed = line.trim();
        if (trimmed.startsWith('{') && trimmed.includes('"email"')) {
          try {
            const data = JSON.parse(trimmed);
            if (data.email) fs.writeFileSync(CREDS_PATH, JSON.stringify(data), 'utf8');
          } catch {}
          break;
        }
      }
    }

    // Warm caches — skip config:cache so APP_KEY is always read from process env at runtime
    warmCaches();
  } else {
    artisan(['migrate', '--force']);
    // Regenerate caches when the app version changes (new build = new routes/views)
    if (readCachedVersion() !== app.getVersion()) {
      console.log('[setup] New app version detected — refreshing route and view caches…');
      warmCaches();
    }
  }
}

// ── Sync ─────────────────────────────────────────────────────────────────────

function triggerSync() {
  const pull = spawn(phpBin(), ['artisan', 'sync:pull'], phpSpawnOpts(LARAVEL_ROOT));
  pull.on('close', (code) => {
    console.log(`[sync] pull exited ${code}`);
    const push = spawn(phpBin(), ['artisan', 'sync:push'], phpSpawnOpts(LARAVEL_ROOT));
    push.on('close', (c) => {
      console.log(`[sync] push exited ${c}`);
      if (mainWindow) mainWindow.webContents.send('sync-done');
    });
  });
}

// ── Startup self-check ────────────────────────────────────────────────────────

const STARTUP_CHECK_PATH = path.join(USER_DATA, 'startup-check.json');

async function runStartupChecks() {
  const checks = [];
  const add = (name, ok, detail = '') => checks.push({ name, ok, detail });

  // 1. Data dirs writable
  try {
    const probe = path.join(STORAGE, '.write-test');
    fs.writeFileSync(probe, 'ok');
    fs.unlinkSync(probe);
    add('storage_writable', true);
  } catch (e) {
    add('storage_writable', false, e.message);
  }

  // 2. SQLite writable
  try {
    const probe = path.join(USER_DATA, '.db-test');
    fs.writeFileSync(probe, 'ok');
    fs.unlinkSync(probe);
    add('sqlite_writable', true);
  } catch (e) {
    add('sqlite_writable', false, e.message);
  }

  // 3. PHP extensions
  const extResult = spawnSync(phpBin(), ['-r', 'echo json_encode(["gd"=>extension_loaded("gd"),"fileinfo"=>extension_loaded("fileinfo"),"mbstring"=>extension_loaded("mbstring"),"pdo_sqlite"=>extension_loaded("pdo_sqlite"),"openssl"=>extension_loaded("openssl")]);'], {
    ...phpSpawnOpts(LARAVEL_ROOT),
    timeout: 10_000,
  });
  try {
    const exts = JSON.parse(extResult.stdout?.toString() ?? '{}');
    for (const [k, v] of Object.entries(exts)) {
      add(`ext_${k}`, Boolean(v));
    }
  } catch {
    add('php_extensions', false, extResult.stderr?.toString() ?? 'parse error');
  }

  // 4. PHP upload limits
  const iniResult = spawnSync(phpBin(), ['-r', 'echo json_encode(["upload_max"=>ini_get("upload_max_filesize"),"post_max"=>ini_get("post_max_size"),"file_uploads"=>ini_get("file_uploads")]);'], {
    ...phpSpawnOpts(LARAVEL_ROOT),
    timeout: 10_000,
  });
  try {
    const ini = JSON.parse(iniResult.stdout?.toString() ?? '{}');
    add('php_upload_limits', ini.file_uploads === '1' || ini.file_uploads === 'On', JSON.stringify(ini));
  } catch {
    add('php_upload_limits', false);
  }

  // 5. SumatraPDF present
  const sumaPath = sumatraPath();
  if (sumaPath) {
    add('sumatra_present', fs.existsSync(sumaPath), sumaPath);
  } else {
    add('sumatra_present', true, 'dev-mode');
  }

  // 6. At least one printer
  try {
    const src = mainWindow ?? BrowserWindow.getAllWindows()[0];
    if (src) {
      const list = await src.webContents.getPrintersAsync();
      add('printers_found', list.length > 0, `${list.length} printers`);
    } else {
      add('printers_found', null, 'window not ready');
    }
  } catch {
    add('printers_found', null, 'could not query printers');
  }

  // 7. Print spooler (Windows only)
  if (isWin) {
    const svc = spawnSync('sc', ['query', 'spooler'], { timeout: 5_000, shell: true, windowsHide: true });
    const out = svc.stdout?.toString() ?? '';
    add('spooler_running', out.includes('RUNNING'), out.trim().split('\n')[0] ?? '');
  }

  const result = {
    timestamp: new Date().toISOString(),
    version:   app.getVersion(),
    checks,
    critical_failures: checks.filter(c => c.ok === false).map(c => c.name),
  };

  try {
    fs.writeFileSync(STARTUP_CHECK_PATH, JSON.stringify(result, null, 2), 'utf8');
  } catch {}

  return result;
}

// ── Find a free TCP port ──────────────────────────────────────────────────────

function findFreePort(start = 8765) {
  return new Promise((resolve, reject) => {
    const srv = net.createServer();
    srv.listen(start, '127.0.0.1', () => {
      const { port } = srv.address();
      srv.close(() => resolve(port));
    });
    srv.on('error', () => findFreePort(start + 1).then(resolve, reject));
  });
}

// ── Poll until Laravel responds ───────────────────────────────────────────────

function waitForServer(url, phpProc, attempts = 120) {
  return new Promise((resolve, reject) => {
    let done = false;
    const finish = (fn) => { if (!done) { done = true; fn(); } };

    // Abort immediately if the PHP process exits before the server is up
    phpProc.once('close', (code) => {
      finish(() => reject(new Error(
        `PHP server exited (code ${code}) before accepting connections.\n${phpProc.__stderr ?? ''}`.trim()
      )));
    });

    const try_ = (n) => {
      if (done) return;
      if (n === 0) return finish(() => reject(new Error('PHP server did not start in time (60 s). Check that no other app is using the port.')));
      http.get(url, () => finish(() => resolve()))
          .on('error', () => setTimeout(() => try_(n - 1), 500));
    };
    try_(attempts);
  });
}

// ── Start php artisan serve ───────────────────────────────────────────────────

function startPhpServer(port) {
  const php = spawn(
    phpBin(), ['artisan', 'serve', '--host=127.0.0.1', `--port=${port}`],
    phpSpawnOpts(LARAVEL_ROOT),
  );

  php.__stderr = '';
  php.__stdout = '';

  php.stderr.on('data', (d) => {
    const msg = d.toString();
    console.log('[php]', msg.trim());
    php.__stderr += msg;
  });

  php.stdout.on('data', (d) => {
    php.__stdout += d.toString();
  });

  php.on('close', (code) => {
    if (code !== 0 && code !== null && mainWindow === null) {
      const detail = (php.__stderr + php.__stdout).trim() || `PHP exited with code ${code}`;
      dialog.showErrorBox('PHP server crashed', detail);
      app.quit();
    }
  });

  php.on('error', (err) => {
    dialog.showErrorBox('PHP not found',
      `Could not start PHP:\n${err.message}\n\nMake sure PHP is installed and in your PATH.`);
    app.quit();
  });

  return php;
}

// ── Loading window ────────────────────────────────────────────────────────────

function createLoadingWindow() {
  const win = new BrowserWindow({
    width: 420, height: 280, frame: false, resizable: false, center: true,
    transparent: false,
    webPreferences: { nodeIntegration: false, contextIsolation: true },
    title: 'POS', show: false, skipTaskbar: true,
  });
  win.loadFile(path.join(__dirname, 'loading.html'));
  win.once('ready-to-show', () => win.show());
  return win;
}

// ── Main window ───────────────────────────────────────────────────────────────

function createWindow(url) {
  mainWindow = new BrowserWindow({
    width: 1366, height: 768, minWidth: 960, minHeight: 600,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      nodeIntegration: false, contextIsolation: true, zoomFactor: 1.0,
    },
    title: 'POS', show: false,
  });

  mainWindow.loadURL(url);
  mainWindow.once('ready-to-show', () => {
    mainWindow.webContents.setZoomFactor(1.0);
    mainWindow.show();
  });
  mainWindow.webContents.on('did-finish-load', () => mainWindow.webContents.setZoomFactor(1.0));

  mainWindow.webContents.setWindowOpenHandler(({ url: href }) => {
    if (!href.startsWith('http://127.0.0.1')) shell.openExternal(href);
    return { action: 'deny' };
  });

  mainWindow.on('closed', () => { mainWindow = null; });

  if (!IS_PACKAGED) {
    const devMenu = Menu.buildFromTemplate([{
      label: 'View', submenu: [
        { role: 'reload' }, { role: 'toggleDevTools' }, { type: 'separator' },
        { role: 'resetZoom' }, { role: 'zoomIn' }, { role: 'zoomOut' },
      ],
    }]);
    Menu.setApplicationMenu(devMenu);
  } else {
    Menu.setApplicationMenu(null);
  }
}

// ── System tray ───────────────────────────────────────────────────────────────

function createTray() {
  const iconPath = path.join(__dirname, 'build', 'icon.ico');
  try {
    tray = new Tray(iconPath);
    tray.setToolTip('POS');
    tray.setContextMenu(Menu.buildFromTemplate([
      { label: 'Open POS', click: () => {
        if (mainWindow) mainWindow.show();
        else createWindow(`http://127.0.0.1:${serverPort}`);
      }},
      { label: 'Sync now', click: () => triggerSync() },
      { type: 'separator' },
      { label: 'Quit', click: () => app.quit() },
    ]));
    tray.on('double-click', () => { if (mainWindow) mainWindow.show(); });
  } catch {}
}

// ── IPC ───────────────────────────────────────────────────────────────────────

ipcMain.handle('sync-now', () => triggerSync());

ipcMain.handle('last-sync', () => {
  try {
    const stamp = path.join(LARAVEL_ROOT, 'storage', 'app', 'last_sync.json');
    if (fs.existsSync(stamp)) return JSON.parse(fs.readFileSync(stamp, 'utf8'));
  } catch {}
  return { status: 'never' };
});

ipcMain.handle('get-printers', async () => {
  try {
    const source = mainWindow ?? BrowserWindow.getAllWindows()[0];
    if (!source) return [];
    const list = await source.webContents.getPrintersAsync();
    return list.map((p) => ({ name: p.name, isDefault: p.isDefault, status: p.status ?? 0 }));
  } catch { return []; }
});

ipcMain.handle('get-startup-check', () => {
  try {
    if (fs.existsSync(STARTUP_CHECK_PATH)) {
      return JSON.parse(fs.readFileSync(STARTUP_CHECK_PATH, 'utf8'));
    }
  } catch {}
  return null;
});

ipcMain.handle('get-print-history', () => {
  try {
    if (fs.existsSync(HISTORY_PATH)) {
      return JSON.parse(fs.readFileSync(HISTORY_PATH, 'utf8'));
    }
  } catch {}
  return [];
});

ipcMain.handle('export-diagnostics', async () => {
  try {
    const { filePath } = await dialog.showSaveDialog({
      title: 'Save diagnostics ZIP',
      defaultPath: path.join(app.getPath('desktop'), `pos-diagnostics-${Date.now()}.zip`),
      filters: [{ name: 'ZIP Archive', extensions: ['zip'] }],
    });
    if (!filePath) return { ok: false, reason: 'cancelled' };

    const archiver = require('archiver');
    const output   = fs.createWriteStream(filePath);
    const archive  = archiver('zip');
    archive.pipe(output);

    const addIfExists = (src, name) => {
      if (fs.existsSync(src)) archive.file(src, { name });
    };
    addIfExists(path.join(USER_DATA, 'print-log.txt'), 'print-log.txt');
    addIfExists(HISTORY_PATH, 'print-history.json');
    addIfExists(STARTUP_CHECK_PATH, 'startup-check.json');

    // Laravel log tail (last 500 lines)
    const logFile = path.join(STORAGE, 'logs', 'laravel.log');
    if (fs.existsSync(logFile)) {
      const lines = fs.readFileSync(logFile, 'utf8').split('\n');
      archive.append(lines.slice(-500).join('\n'), { name: 'laravel.log.tail' });
    }

    // PHP extensions
    const extOut = spawnSync(phpBin(), ['-m'], { ...phpSpawnOpts(LARAVEL_ROOT), timeout: 5_000 });
    archive.append(extOut.stdout?.toString() ?? '', { name: 'php-modules.txt' });

    // App meta
    const meta = {
      app_version:     app.getVersion(),
      electron_version: process.versions.electron,
      node_version:    process.versions.node,
      platform:        process.platform,
      arch:            process.arch,
    };
    archive.append(JSON.stringify(meta, null, 2), { name: 'meta.json' });

    await new Promise((res, rej) => {
      output.on('close', res);
      archive.on('error', rej);
      archive.finalize();
    });

    return { ok: true, path: filePath };
  } catch (e) {
    return { ok: false, reason: e.message };
  }
});

// ── Print log ─────────────────────────────────────────────────────────────────

const LOG_PATH  = path.join(USER_DATA, 'print-log.txt');
const LOG_MAX   = 512 * 1024;

function printLog(msg) {
  try {
    const line = `[${new Date().toISOString()}] ${msg}\n`;
    let size = 0;
    try { size = fs.statSync(LOG_PATH).size; } catch {}
    if (size > LOG_MAX) fs.writeFileSync(LOG_PATH, line, 'utf8');
    else fs.appendFileSync(LOG_PATH, line, 'utf8');
  } catch {}
}

// ── Print history (last 20 jobs) ──────────────────────────────────────────────

const HISTORY_PATH    = path.join(USER_DATA, 'print-history.json');
const HISTORY_MAX     = 20;

function addPrintHistory(entry) {
  try {
    let history = [];
    if (fs.existsSync(HISTORY_PATH)) {
      try { history = JSON.parse(fs.readFileSync(HISTORY_PATH, 'utf8')); } catch {}
    }
    history.unshift({ ...entry, ts: new Date().toISOString() });
    if (history.length > HISTORY_MAX) history = history.slice(0, HISTORY_MAX);
    fs.writeFileSync(HISTORY_PATH, JSON.stringify(history, null, 2), 'utf8');
  } catch {}
}

// ── SumatraPDF path ───────────────────────────────────────────────────────────

function sumatraPath() {
  if (IS_PACKAGED) {
    return path.join(
      process.resourcesPath,
      'app.asar.unpacked', 'node_modules', 'pdf-to-printer',
      'dist', 'SumatraPDF-3.4.6-32.exe',
    );
  }
  return undefined;
}

// ── JS injected into the hidden print window ──────────────────────────────────

const ASSET_WAIT_JS = `
new Promise((resolve) => {
  const check = () => {
    const pending = [...document.images].filter(i => !i.complete);
    if (!pending.length) { resolve(); return; }
    let n = pending.length;
    const done = () => { if (--n === 0) resolve(); };
    pending.forEach(img => {
      img.addEventListener('load', done, { once: true });
      img.addEventListener('error', done, { once: true });
    });
  };
  document.fonts.ready.then(check).catch(resolve);
}).catch(() => {})
`;

const MEASURE_JS = `
(() => {
  const paper = document.querySelector('.paper');
  const textLen = document.body.innerText.trim().length;
  if (!paper) return { dataWidth: '80mm', heightPx: document.body.scrollHeight, textLen };
  return {
    dataWidth: paper.getAttribute('data-width') || '80mm',
    heightPx: Math.ceil(paper.scrollHeight),
    textLen,
  };
})()
`;

const RAF2_JS = `new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)))`;

const LAST_PDF_PATH = path.join(USER_DATA, 'last-print.pdf');

// ── Core print function ───────────────────────────────────────────────────────

async function doSilentPrint(url, printerName) {
  const win = new BrowserWindow({
    show: false, width: 450, height: 900,
    webPreferences: { backgroundThrottling: false, nodeIntegration: false, contextIsolation: true },
  });

  const jobId   = `${Date.now()}-${Math.round(Math.random() * 9999)}`;
  const pdfPath = path.join(USER_DATA, `print-${jobId}.pdf`);

  try {
    // Validate the requested printer; fall back to default if not found.
    let device = (printerName || '').trim();
    if (device) {
      try {
        const list = await win.webContents.getPrintersAsync();
        if (!list.find(p => p.name === device)) {
          const def = list.find(p => p.isDefault);
          printLog(`warn printer="${device}" not found, using "${def?.name || 'default'}"`);
          device = '';
        }
      } catch {}
    }

    await new Promise((resolve, reject) => {
      win.webContents.once('did-fail-load', (_, code, desc) =>
        reject(new Error(`Load failed (${code}): ${desc}`))
      );
      win.webContents.once('did-finish-load', resolve);
      win.loadURL(url);
    });

    // Wait for fonts + images; 5 s safety timeout.
    await Promise.race([
      win.webContents.executeJavaScript(ASSET_WAIT_JS),
      new Promise(r => setTimeout(r, 5000)),
    ]);

    // Force light mode so dark-mode class never bleeds into the PDF.
    await win.webContents.executeJavaScript(
      `document.documentElement.classList.remove('dark');
       document.body.classList.remove('dark');`
    );

    // Two animation frames — ensure paint is complete.
    await win.webContents.executeJavaScript(RAF2_JS);

    const { dataWidth, heightPx, textLen } = await win.webContents.executeJavaScript(MEASURE_JS);

    if (textLen === 0 || heightPx < 50) {
      throw new Error(`Print page is empty (textLen=${textLen}, heightPx=${heightPx})`);
    }

    const isA4    = dataWidth === 'a4';
    const widthIn = dataWidth === '58mm' ? 2.2835 : 3.1496;
    const heightIn = isA4 ? undefined : Math.max(widthIn + 0.01, (heightPx / 96) + 0.04);

    const pdfBuf = await win.webContents.printToPDF({
      printBackground: true,
      preferCSSPageSize: false,
      margins: { marginType: 'none' },
      pageSize: isA4 ? 'A4' : { width: widthIn, height: heightIn },
    });

    fs.writeFileSync(pdfPath, pdfBuf);
    fs.writeFileSync(LAST_PDF_PATH, pdfBuf);

    if (pdfBuf.length < 1500) {
      printLog(`warn job=${jobId} pdf bytes=${pdfBuf.length} — may be blank`);
    }

    // Try pdf-to-printer / SumatraPDF first
    let printPath = 'pdf-to-printer';
    try {
      const { print: pdfPrint } = require('pdf-to-printer');
      const printOpts = { scale: 'noscale', silent: true };
      if (device) printOpts.printer = device;
      const sumaPath = sumatraPath();
      if (sumaPath) {
        if (!fs.existsSync(sumaPath)) {
          printLog(`warn SumatraPDF not found at ${sumaPath} — falling back to webContents.print`);
          throw new Error('SumatraPDF missing');
        }
        printOpts.sumatraPdfPath = sumaPath;
      }
      await pdfPrint(pdfPath, printOpts);
    } catch (pdfErr) {
      // Fallback: Chromium's built-in silent print
      printLog(`warn pdf-to-printer failed (${pdfErr.message}), falling back to webContents.print`);
      printPath = 'webContents.print';
      const winP = dataWidth === '58mm'
        ? { width: Math.round(58 * 1000), height: Math.round(heightPx / 96 * 25.4 * 1000) }
        : isA4 ? undefined
        : { width: Math.round(80 * 1000), height: Math.round(heightPx / 96 * 25.4 * 1000) };

      await new Promise((res, rej) => {
        win.webContents.print(
          { silent: true, printBackground: true, deviceName: device || undefined,
            pageSize: isA4 ? 'A4' : winP },
          (success, failureReason) => {
            if (success) res();
            else rej(new Error(failureReason ?? 'webContents.print failed'));
          }
        );
      });
    }

    const safeUrl = url.startsWith('data:') ? 'data:…' :
      url.replace(/([?&])t=\d+/, '').replace(/([?&])autoprint=\d+/, '');
    const logLine = `job=${jobId} url="${safeUrl}" printer="${device || 'default'}" width=${dataWidth} heightPx=${heightPx} textLen=${textLen} pdfBytes=${pdfBuf.length} via=${printPath} result=ok`;
    printLog(logLine);
    addPrintHistory({ jobId, url: safeUrl, printer: device || 'default', width: dataWidth, result: 'ok', via: printPath });
    return { ok: true };

  } catch (err) {
    printLog(`job=${jobId} error: ${err.message}`);
    addPrintHistory({ jobId, url: url.startsWith('data:') ? 'data:…' : url, printer: printerName || 'default', result: 'error', error: err.message });
    throw err;
  } finally {
    try { win.close(); } catch {}
    try { if (fs.existsSync(pdfPath)) fs.unlinkSync(pdfPath); } catch {}
  }
}

// ── Print queue (one job at a time) ──────────────────────────────────────────

let printQueue = Promise.resolve();

function enqueuePrint(fn) {
  let res, rej;
  const p = new Promise((resolve, reject) => { res = resolve; rej = reject; });
  printQueue = printQueue.then(() => fn().then(res, rej), () => fn().then(res, rej));
  return p;
}

/** Retry a print job once on failure. */
async function printWithRetry(url, printerName) {
  try {
    return await doSilentPrint(url, printerName);
  } catch (firstErr) {
    printLog(`retry job url="${url}" after error: ${firstErr.message}`);
    await new Promise(r => setTimeout(r, 1500));
    return doSilentPrint(url, printerName);
  }
}

/**
 * Silent print: loads a URL in a hidden window, renders to PDF, sends to printer.
 * opts: { url: string, printerName: string|null }
 */
ipcMain.handle('print-silent', (event, { url, printerName }) =>
  enqueuePrint(() => printWithRetry(url, printerName))
);

/** Short test slip to verify printer setup. */
ipcMain.handle('print-test', (event, printerName) => {
  const html = `<!doctype html><html><head>
<meta charset="utf-8">
<style>
@page{margin:0}
body{margin:0;font-family:monospace;font-size:14px}
.paper{width:72mm;padding:3mm 4mm;background:#fff;color:#000}
p{text-align:center;margin:1mm 0}
hr{border:none;border-top:1px dashed #000;margin:1.5mm 0}
</style></head><body>
<div class="paper" data-width="80mm">
<p style="font-size:18px;font-weight:bold">TEST SLIP</p>
<hr>
<p>80 mm &bull; ${new Date().toLocaleString()}</p>
<hr>
<p>If you can read this,<br>printing is working!</p>
</div></body></html>`;
  const dataUrl = `data:text/html;charset=utf-8,${encodeURIComponent(html)}`;
  return enqueuePrint(() => doSilentPrint(dataUrl, printerName || ''));
});

/** Calibration slip: ruler, full-width box, text sizes, logo-placeholder block. */
ipcMain.handle('print-calibration', (event, { printerName = '', width = '80mm' } = {}) => {
  const mmWidth = width === '58mm' ? 48 : 72;
  const html = `<!doctype html><html><head>
<meta charset="utf-8">
<style>
@page{margin:0}
body{margin:0;font-family:monospace;font-size:12px;background:#fff;color:#000}
.paper{width:${mmWidth}mm;padding:2mm 3mm}
.box{border:2px solid #000;padding:1mm;text-align:center;margin:1mm 0}
.ruler{display:flex;justify-content:space-between;font-size:9px;border-top:1px solid #000;padding-top:0.5mm}
</style></head><body>
<div class="paper" data-width="${width}">
<p style="text-align:center;font-size:16px;font-weight:bold">CALIBRATION SLIP</p>
<p style="text-align:center;font-size:10px">${width} paper · ${new Date().toLocaleString()}</p>
<hr style="border:none;border-top:1px dashed #000">
<div class="ruler">${Array.from({length:11},(_,i)=>`<span>${i*10}</span>`).join('')}</div>
<div class="box">← Full-width border box →</div>
<p style="font-size:10px">Small text (10px)</p>
<p style="font-size:14px">Normal text (14px)</p>
<p style="font-size:18px">Large text (18px)</p>
<div style="background:#000;height:8mm;margin:2mm 0"></div>
<p style="text-align:center;font-size:10px">Logo placeholder above</p>
<div style="display:flex;gap:2mm;flex-wrap:wrap;margin:1mm 0">
${Array.from({length:8},()=>'<div style="background:#000;width:3mm;height:3mm"></div>').join('')}
</div>
<p style="font-size:9px;text-align:center">If box edges are clipped, increase paper width setting</p>
</div></body></html>`;
  const dataUrl = `data:text/html;charset=utf-8,${encodeURIComponent(html)}`;
  return enqueuePrint(() => doSilentPrint(dataUrl, printerName));
});

/** Opens the most recently rendered print PDF. */
ipcMain.handle('open-last-print', () => {
  if (!fs.existsSync(LAST_PDF_PATH)) return { ok: false, reason: 'No print PDF yet' };
  shell.openPath(LAST_PDF_PATH);
  return { ok: true };
});

// ── App lifecycle ─────────────────────────────────────────────────────────────

if (!app.requestSingleInstanceLock()) {
  app.quit();
} else {
  app.on('second-instance', () => {
    if (mainWindow) {
      if (mainWindow.isMinimized()) mainWindow.restore();
      mainWindow.focus();
    }
  });
}

app.whenReady().then(async () => {
  const loadingWin = createLoadingWindow();

  try {
    // Step 1: Ensure all writable dirs exist in userData
    fs.mkdirSync(USER_DATA, { recursive: true });
    ensureDataDirs();
    ensureAppKey();
    patchLaravelEnv();
    patchPhpIni();
    migrateOldStorage();

    const isNewDb = !fs.existsSync(DB_PATH);
    warmupPhp(isNewDb);

    // Step 2: Migrations
    runMigrationsIfNeeded();

    // Step 3: Start PHP server
    serverPort = await findFreePort(8765);
    phpProcess = startPhpServer(serverPort);

    const url = `http://127.0.0.1:${serverPort}`;
    await waitForServer(url, phpProcess);

    // Step 4: Create window first (needed to query printers)
    createWindow(url);
    loadingWin.destroy();
    createTray();

    // Step 5: Show first-run credentials
    if (fs.existsSync(CREDS_PATH)) {
      try {
        const creds = JSON.parse(fs.readFileSync(CREDS_PATH, 'utf8'));
        fs.unlinkSync(CREDS_PATH);
        await dialog.showMessageBox({
          type: 'info', title: 'Setup Complete',
          message: 'POS is ready!\n\nYour default login credentials:',
          detail: `Email:    ${creds.email}\nPassword: ${creds.password}\n\nPlease change your password after first login.`,
          buttons: ['OK, got it'],
        });
      } catch {}
    }

    // Step 6: Startup checks (non-blocking — results shown in Diagnostics)
    runStartupChecks().then((result) => {
      const critical = result.critical_failures ?? [];
      const mustFix  = critical.filter(n => ['storage_writable', 'sqlite_writable', 'ext_pdo_sqlite'].includes(n));
      if (mustFix.length > 0) {
        dialog.showErrorBox('POS startup problem',
          `The following critical checks failed:\n\n${mustFix.join(', ')}\n\nThe app may not work correctly.\n\nGo to Settings → POS Settings → Diagnostics for details and export a diagnostic report.`
        );
      }
    }).catch(() => {});

    // Sync in background after window is ready
    triggerSync();

  } catch (err) {
    loadingWin.destroy();
    dialog.showErrorBox('Startup error', err.message);
    app.quit();
  }
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});

app.on('activate', () => {
  if (BrowserWindow.getAllWindows().length === 0 && serverPort) {
    createWindow(`http://127.0.0.1:${serverPort}`);
  }
});

app.on('before-quit', () => {
  if (phpProcess) { phpProcess.kill(); phpProcess = null; }
});
