# Desktop App / Kiosk Mode (planned)

The POS will later run as a **desktop application** or in **kiosk mode** on the till PC. This page records what already works for that and what to keep in mind while building new features.

## Already compatible

| Area | Why it works |
|---|---|
| Full-screen screens | The POS (`/pos`) and the kitchen display (`/kitchen`) are standalone pages with their own navigation. They don't rely on browser back/forward or the address bar. |
| Printing | Everything goes through one URL, `/sales/{id}/print` (customer bill + kitchen bill; `?only=receipt\|kitchen`), and one function, `printOrder()` in `resources/js/pos.js`. There are no popups or new tabs. |
| Local only | Laravel + MySQL on the same machine; no external services are needed. |
| Keyboard / scanner | Barcode scanners act as keyboards (Enter submits). Shortcuts: F2 search, F4 barcode, F9 checkout. |
| State | The cart survives a reload (browser storage). Everything else is on the server. |

## Kiosk mode today (no code changes)

Create a Windows shortcut on the till PC:

```
"C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk --kiosk-printing http://localhost:8000/pos
```

- `--kiosk` opens full screen with no browser UI. Press Alt+F4 to exit.
- `--kiosk-printing` prints silently to the **default Windows printer**, so set the receipt printer as the default. Both bills print straight away when an order is placed.
- For a separate kitchen screen, point another PC or tablet at `http://<server-ip>:8000/kitchen`.

## Desktop app (Electron — implemented)

The app ships as an Electron + bundled PHP installer. Key architecture:

- **All writable state** lives in `app.getPath('userData')` (`%APPDATA%\POS\` on Windows).
  The install directory (`C:\Program Files\POS`) is **read-only** after installation.
- **PHP env vars** are passed directly to the PHP process at spawn time (no `.env` writes).
  `APP_STORAGE_PATH`, `APP_SERVICES_CACHE`, `APP_PACKAGES_CACHE`, `TMP`/`TEMP`, `PHPRC` etc.
- **Printing** goes through a hidden Electron `BrowserWindow` that renders the print URL to PDF
  via `webContents.printToPDF`, then sends to the printer via `pdf-to-printer` / SumatraPDF.
  Falls back to `webContents.print` (silent) then the print dialog if SumatraPDF is missing.
- **Images** are served from the public disk via the `GET /storage/{path}` route — no symlink
  required. The normal `public/storage` symlink on web installs takes precedence.
- **Startup self-check** runs before the window opens and writes `userData/startup-check.json`.
  Critical failures show a dialog; warnings appear in Settings → POS Settings → Diagnostics.

### Data locations

| File/folder | Location |
|---|---|
| SQLite database | `%APPDATA%\POS\database.sqlite` |
| Uploaded images | `%APPDATA%\POS\storage\app\public\` |
| Laravel logs | `%APPDATA%\POS\storage\logs\laravel.log` |
| Print log | `%APPDATA%\POS\print-log.txt` |
| Print history (last 20) | `%APPDATA%\POS\print-history.json` |
| Last rendered PDF | `%APPDATA%\POS\last-print.pdf` |
| Startup check results | `%APPDATA%\POS\startup-check.json` |

### Printer setup

Go to **Settings → POS Settings → Printer setup**. Choose the receipt printer and KOT
printer (can be the same). Use "Print test slip" to verify. Use "Print calibration slip"
to check paper width alignment.

### Diagnostics

**Settings → POS Settings → Diagnostics** shows startup check results, recent print jobs,
and provides "Export diagnostics ZIP" (no customer data) for support.

## Rules for new features

- Don't depend on browser popups, new tabs or browser-only UI (e.g. `window.open` flows, the address bar).
- Keep printing behind server-rendered print URLs plus `printOrder()`, so the output can be swapped for native printing.
- Keep pages usable full-screen with touch and keyboard.
- Never write files into the install directory (everything goes to `userData`).
