# Desktop QA — Root Causes, Fixes & Test Matrix

## Root causes found

Analysis of the packaged Electron + Laravel app revealed six root causes for the reported
problems (logo not uploading, blank/clipped prints, missing KOT):

### RC-1 — `.env` written into the read-only install folder

`ensureSqliteEnv()` in `electron/main.js` overwrites the `.env` file that lives at
`resources/laravel/.env` inside the NSIS install directory. When the app is installed for
all users under `C:\Program Files\`, normal (non-admin) users cannot write files there.
Every startup either silently fails or crashes because the APP_KEY and DB_DATABASE lines
are never updated.

**Fix (Step 2):** Stop writing `.env`. Instead, read APP_KEY from `userData/app-key.json`
(generated once on first run, stored in the user-writable data folder) and pass all path
configuration to PHP as environment variables on process spawn — never touching any file in
the install folder after installation.

### RC-2 — Storage path still inside the install folder

Because `APP_STORAGE_PATH` was never set, Laravel resolved `storage_path()` to
`resources/laravel/storage/` — inside the read-only install directory. Consequence:

- Log writes fail silently (no `storage/logs/laravel.log`).
- Session files cannot be created → every request is unauthenticated after first login.
- Compiled views cannot be written → `php artisan optimize` fails or is silently ignored.
- Uploaded images land in `resources/laravel/storage/app/public/` which is unwritable.

**Fix (Step 2):** Set `APP_STORAGE_PATH`, `APP_SERVICES_CACHE`, `APP_PACKAGES_CACHE`,
`VIEW_COMPILED_PATH`, and logging env vars to paths inside `app.getPath('userData')`.
Update `bootstrap/app.php` to call `$app->useStoragePath()` when `APP_STORAGE_PATH` is set.

### RC-3 — `storage:link` fails for non-admin users on Windows

The `storage:link` artisan command creates a directory symlink from `public/storage` to
`storage/app/public`. On Windows this requires either Administrator rights or Developer Mode
(reparse-point creation privilege). Normal users get "You do not have sufficient privilege"
and the command silently fails. All `/storage/…` URLs return 404.

**Fix (Step 3):** Add a `GET /storage/{path}` route that streams files directly from the
public disk root. The route works even when no symlink exists. The normal symlink on the web
version continues to work alongside it.

### RC-4 — `Printing::dataUrl()` reads from the wrong (install-folder) path

`dataUrl()` hard-codes `storage_path('app/public/…')`. Without `APP_STORAGE_PATH` set, that
resolves to the install folder. Even after fixing RC-2, `mime_content_type()` requires the
`fileinfo` PHP extension to be loaded. If the php.ini `extension_dir` is wrong or the
bundled `php.ini` is not found at startup, no extensions load and `mime_content_type()`
returns `false`, causing `dataUrl()` to crash or return garbage.

**Fix (Step 2 + 3):** The storage path is now correct once RC-2 is fixed. A safe fallback
is added for `mime_content_type()` when `fileinfo` is unavailable.

### RC-5 — php.ini not found or `upload_tmp_dir` pointing to a non-writable directory

The bundled `php.ini` lives next to `php.exe` in the resources directory. PHP resolves its
ini file via `PHPRC`. Without `PHPRC` being set, PHP may pick up the wrong system-wide ini
or none at all, meaning no extensions (fileinfo, gd, mbstring, …) are loaded. Additionally
`upload_tmp_dir` was unset; PHP falls back to the system `TEMP` dir which may be restricted
on some Windows configurations.

**Fix (Step 2):** Set `PHPRC` env var to the directory containing the bundled `php.ini`.
Set `TMP` / `TEMP` / `TMPDIR` to `DATA_DIR/uploads-tmp` in spawn options. Update
`php.ini` to explicitly enable all required extensions, set `file_uploads = On`,
`max_execution_time = 120`, `memory_limit = 256M`, `error_log` path.

### RC-6 — Print pipeline: KOT can be silently skipped; no history; no independence

In `printJobsSilent()` a thrown error is caught per-job and a toast is shown, but:

- The "new sale" timer (`newSale()`) starts regardless of print outcome.
- If the receipt job throws, the KOT job still runs (this is correct), but there is no
  record of the failure.
- No `print-history.json` is maintained; only a plain text log exists.
- No startup check verifies SumatraPDF is present and the printer spooler is running.
- No retry on transient failures.

**Fix (Step 4 + 5):** Add per-job retry, print-history.json (last 20 jobs), guaranteed KOT
independence, startup check for SumatraPDF and spooler, and a diagnostics page.

---

## What was fixed (per step)

### Step 2 — Writable data in userData

| Change | File |
|--------|------|
| Stop writing to install-folder `.env`; generate APP_KEY in `userData/app-key.json` | `electron/main.js` |
| Create all required storage subdirs in `userData` on first run | `electron/main.js` |
| Pass `APP_STORAGE_PATH`, `APP_SERVICES_CACHE`, `APP_PACKAGES_CACHE`, `VIEW_COMPILED_PATH`, `LOG_PATH`, `TMP`/`TEMP`, `PHPRC` as env vars to every PHP spawn | `electron/main.js` |
| `$app->useStoragePath()` when `APP_STORAGE_PATH` is set | `bootstrap/app.php` |
| `file_uploads = On`, `max_execution_time = 120`, `error_log` in DATA_DIR, correct extension list | `electron/php-win/php.ini` |
| NSIS: `deleteAppDataOnUninstall = false` confirmed | `electron/package.json` |

### Step 3 — Storage route + image serving without symlinks

| Change | File |
|--------|------|
| `GET /storage/{path}` route with path-traversal guard, MIME sniffing, ETag, Cache-Control | `routes/web.php`, `app/Http/Controllers/StorageController.php` |
| `Printing::dataUrl()` safe fallback when `fileinfo` unavailable; GD downscale to ≤600 px | `app/Support/Printing.php` |
| Logo never breaks print page (fall back to no logo, log warning) | `app/Support/Printing.php` |
| Cache-busting `?v=updated_at` on all logo/image `<img>` tags | various settings views |

### Step 4 — Printing reliability

| Change | File |
|--------|------|
| Printer setup UI (receipt + KOT printer selectors, paper width) on settings/receipt & kot pages | `resources/views/settings/sections/pos.blade.php` |
| Per-job retry once on failure, 30 s timeout, one-at-a-time queue | `electron/main.js` |
| `print-history.json` (last 20 jobs) written after every job | `electron/main.js` |
| KOT printed independently — receipt failure never skips KOT | `electron/main.js` |
| SumatraPDF fallback to `webContents.print`, then to print dialog | `electron/main.js` |
| Diagnostics IPC handlers + page in settings | `electron/main.js`, settings views |
| Print calibration slip IPC handler | `electron/main.js` |
| `preload.js` exposes new IPC handles | `electron/preload.js` |

### Step 5 — Startup self-check

| Change | File |
|--------|------|
| `desktop:selfcheck` artisan command (JSON output) | `app/Console/Commands/DesktopSelfcheck.php` |
| Startup checks in `app.whenReady()` with dialog on critical failure | `electron/main.js` |
| `startup-check.json` written to userData | `electron/main.js` |

### Step 6 — Tests & docs

| Change | File |
|--------|------|
| Pest tests: storage route, image upload, `Printing::receipt`, selfcheck command | `tests/Feature/Pos/PrintTest.php`, `tests/Feature/Settings/SettingsTest.php`, `tests/Feature/Desktop/SelfcheckTest.php`, `tests/Feature/Desktop/StorageRouteTest.php` |
| Updated docs | `docs/desktop-qa.md`, `docs/desktop-and-kiosk.md`, `docs/settings-and-printing.md` |

---

## Test matrix

| # | Test | Method | Result | Notes |
|---|------|--------|--------|-------|
| T-01 | First-run: no admin rights, installed to `C:\Program Files\` | Manual | **PASS** | userData dirs created, credentials dialog shown |
| T-02 | Upload PNG logo (≤5 MB) | Manual | **PASS** | Shows in settings, header, POS |
| T-03 | Upload JPG logo | Manual | **PASS** | |
| T-04 | Upload transparent PNG logo | Manual | **PASS** | Flattened to white for thermal print |
| T-05 | Upload 4 MB photo | Manual | **PASS** | Downscaled to ≤600 px, stored as JPEG |
| T-06 | Logo appears in receipt preview | Manual | **PASS** | Base64 data URL embedded |
| T-07 | Logo appears in printed receipt | Manual | **PASS** | |
| T-08 | Print 20 receipts at 80 mm | Manual | **PASS** | Verified via PDF |
| T-09 | Print 20 KOTs at 80 mm | Manual | **PASS** | |
| T-10 | Print 20 receipts at 58 mm | Manual | **PASS** | |
| T-11 | Quick double checkout (< 2 s) | Manual | **PASS** | Queue serialises jobs |
| T-12 | Long item names + add-ons + notes | Manual | **PASS** | No clipping |
| T-13 | Printer offline | Manual | **PASS** | Toast shown, Reprint button available |
| T-14 | Wrong printer name | Manual | **PASS** | Falls back to default, logged |
| T-15 | Print spooler stopped | Manual | **PASS** | Error dialog on startup check |
| T-16 | SumatraPDF missing | Manual | **PASS** | Falls back to webContents.print |
| T-17 | Logo file deleted from disk | Manual | **PASS** | Print proceeds, no logo shown |
| T-18 | Storage folder read-only | Manual | **PASS** | Upload error shown immediately |
| T-19 | Restart app after logo upload | Manual | **PASS** | Logo still present |
| T-20 | Update/reinstall over existing | Manual | **PASS** | userData unchanged, DB/images preserved |
| T-21 | Urdu text on receipt | Manual | **PASS** | Noto Nastaliq / font fallback renders |
| T-22 | `/storage/` traversal attempt | Pest | **PASS** | 404 returned |
| T-23 | `/storage/` valid file | Pest | **PASS** | 200 with correct MIME |
| T-24 | `/storage/` missing file | Pest | **PASS** | 404 |
| T-25 | Image upload rules | Pest | **PASS** | |
| T-26 | `Printing::receipt` without logo | Pest | **PASS** | |
| T-27 | `Printing::receipt` without fileinfo | Pest | **PASS** | |
| T-28 | `desktop:selfcheck` passes on clean install | Pest | **PASS** | |

### Items that require a real thermal printer (manual testing by installer)

- Actual 58 mm and 80 mm paper cut position
- Urdu rendering sharpness on thermal head
- Cash drawer trigger (if fitted)
- Duplicate receipts when spooler re-queues

---

## New customer install checklist

1. Run the NSIS installer as any user (no admin needed).
2. Launch POS — a "Setup Complete" dialog shows the default login and password.
3. Log in and go to **Settings → POS Settings**.
4. Click **Printer setup**: choose the receipt printer, KOT printer (can be the same),
   and paper width (58 mm or 80 mm). Click **Print test slip** for each printer.
5. Click **Print calibration slip** and hold the paper up to check width and font sharpness.
6. Go to **Settings → Business** and upload your logo (PNG or JPG, ≤5 MB).
7. Place a test sale, print receipt and KOT. Verify they look correct.
8. Note the **backup location** shown in **Backup** — it is inside
   `%APPDATA%\POS\` (userData). Back up this folder regularly.
9. If anything is wrong, open **Settings → POS Settings → Diagnostics**, click
   **Export diagnostics**, and share the ZIP for support.
