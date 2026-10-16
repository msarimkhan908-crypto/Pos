# POS Printing Fix: Receipt + KOT (permanent fix)

How to use: open the project in VS Code, start Claude Code in the project root, and paste the prompt below (everything inside the "PROMPT" section).
After it finishes, rebuild the installer (steps at the bottom) and run the test checklist.

---

## What is wrong (diagnosis, already confirmed from the code and your photos)

Printer: thermal, **80 mm** roll. Desktop app (Electron) prints silently.

Symptoms
1. KOT never prints.
2. Receipt sometimes prints as a blank ~1 inch slip.
3. Receipt sometimes prints large and blurry, with the right side cut off (`DESK-001-INV-00`, amounts like `35` instead of `350.00`).
4. Receipt sometimes prints small, blurry, in the left 60% of the paper, with blank space around it.

Root causes
- `electron/main.js` → `print-silent` does NOT print the page. It opens a hidden window, waits a fixed 1.5 s + 0.8 s, takes a screenshot (`capturePage`), converts it to 1-bit black and white, saves a PNG, then sends it through a PowerShell/`System.Drawing` script with a custom paper size "Slip" (315 x N hundredths of an inch).
  - Screenshots of hidden windows are unreliable, so the image is sometimes all white. The code then crops it to about 30 px, which gives the blank 1-inch slip.
  - The image is a fixed 640 px (80 mm at 203 dpi), but the driver's real printable width is different. The driver sometimes prints 1:1 (clipped on the right) and sometimes shrinks to fit (small, left side). A 1-bit image that is scaled looks blurry.
  - The fixed sleeps are race-prone (logo or CSS not loaded yet).
- `resources/js/pos.js`:
  - `autoPrintOrder()` calls `setTimeout(() => this.newSale(), 3000)`, and `newSale()` sets `this.receipt = null`.
  - `printJobsSilent()` prints the receipt first (takes more than 3 s), then reads `this.receipt.printUrl` for the KOT job. By then `this.receipt` is null, `makeUrl()` throws inside the try/catch, and the KOT is silently skipped.
- A KOT is only created for products or categories that have a kitchen station (`KitchenService::send`). If there is no station, there is no KOT.
- `receipt.kot_printer`, if set to a name that doesn't exactly match a Windows printer, sends the KOT to the wrong place.

---

## PROMPT (paste this into Claude Code)

```
You are fixing the thermal receipt + KOT printing in this Laravel + Electron POS permanently.
Read these files first: electron/main.js, electron/preload.js, resources/js/pos.js (printOrder, autoPrintOrder, printJobs, printJobsSilent, newSale),
resources/views/components/print-layout.blade.php, resources/views/prints/*.blade.php, resources/views/prints/partials/{receipt,kot}.blade.php,
resources/css/app.css (the "Printed documents" section), app/Http/Controllers/SaleController.php (print()), app/Services/KitchenService.php,
app/Support/Printing.php, tests/Feature/Pos/PrintTest.php.

Do NOT add any Claude/AI attribution, comments, co-author lines or prompt files anywhere in the repo.

CONTEXT
- Printer is an 80 mm thermal roll printer on Windows, printing silently from Electron (desktop app). The web/browser path (hidden iframe + window.print) must keep working.
- Current desktop print path (ipcMain 'print-silent' in electron/main.js) takes a capturePage screenshot of a hidden window, thresholds it to 1-bit and prints it through a PowerShell System.Drawing script. This is the root cause of:
  (a) blank ~1 inch slips (empty screenshot from a hidden/throttled window),
  (b) big blurry slips with the right side clipped (image wider than the printable area),
  (c) small blurry content in the left part of the paper (driver shrinks the image).
- The KOT never prints because autoPrintOrder() calls newSale() after 3 s, which sets this.receipt = null, and printJobsSilent() then reads this.receipt.printUrl for the kitchen job, throws inside the try/catch and silently skips it.

REQUIRED CHANGES

1) electron/main.js: replace the screenshot + PowerShell pipeline with native Chromium printing
   - Delete the capturePage / PNG / .ps1 / .cs / threshold code, the debug-screenshot.png and every temp job file.
   - New 'print-silent' handler, signature { url, printerName } stays the same, returns { ok: true } or rejects with a clear Error.
   - Create a hidden BrowserWindow: show:false, width ~ 400, webPreferences { backgroundThrottling:false, nodeIntegration:false, contextIsolation:true }.
   - Load the url, wait for 'did-finish-load', then run executeJavaScript that awaits document.fonts.ready AND every <img> being complete (decode them; resolve after a 5 s safety timeout). No fixed sleeps.
   - Measure from the page: the .paper element's data-width ('58mm' | '80mm' | 'a4') and its real content height in CSS px (use getBoundingClientRect of the .paper element or the last .print-sheet, plus its borders).
   - Print with win.webContents.print({
        silent: true,
        printBackground: true,
        deviceName: printerName || '',          // empty = default printer
        margins: { marginType: 'none' },
        pageSize: { width: <microns>, height: <microns> },   // see sizes below
        scaleFactor: 100,
        landscape: false
     }, (success, failureReason) => { ... resolve / reject ... })
     Paper sizes (microns): 80mm -> width 80000; 58mm -> width 58000; height = ceil(contentHeightPx * 264.583) + 4000 (4 mm tail), integers only, minimum 30000. For 'a4' use pageSize: 'A4'.
   - The slip must be vector text, not an image. Keep the existing 80 mm layout: .paper-80mm is 72mm wide centered in the 80mm page (check that nothing is clipped; if the printer's printable width is smaller than 72 mm, centre-fit the content, do not scale it).
   - Reject if printerName is given but not in webContents.getPrintersAsync(); log a warning and fall back to the default printer instead of failing silently.
   - Serialize all print jobs with a simple promise queue (mutex) in the main process so two jobs never overlap.
   - Log every job (timestamp, url without query token, printer used, width, height px, result / failureReason) to userData/print-log.txt. Keep the file small (truncate when > 512 KB).
   - Always close the hidden window in a finally block.
   - Add a 'print-test' IPC that prints a short test slip (shop name, date, "TEST 80mm") so I can verify the printer without placing an order. Expose it in electron/preload.js as window.desktop.printTest(printerName) and add a "Print test slip" button on Settings -> POS Settings (only when window.desktop exists).

2) resources/js/pos.js: fix the sequencing and error handling
   - In printJobsSilent(): read this.receipt.printUrl ONCE at the start into a local const and use that local for every job. Never touch this.receipt inside the loop (it can be null).
   - Await jobs strictly one after another: receipt first, then each KOT. If the receipt fails, still try the KOT, and vice versa.
   - Collect failures and show a visible error notification per failed job ("Receipt print failed: <reason>" / "KOT print failed: <reason>"). Never swallow errors with console.error only.
   - Make autoPrintOrder() async: await the print jobs, THEN start the "auto new sale" timer (keep the 3 s delay after printing finishes). newSale() must never run while a print is still in progress. Guard every this.receipt access with optional chaining.
   - Browser (non-desktop) path in printJobs() must behave as before.
   - If the sale has kitchen items but no KOT was created (no kitchen station assigned) show a warning toast once: "KOT not created: assign a kitchen station to these products". The server already returns kitchen_orders; if needed add a flag in SaleController::store (e.g. kitchen_missing: true when a line has no kitchen_station_id) without breaking the existing JSON shape.

3) Printer settings validation (Settings -> Receipt / KOT)
   - Where receipt.desktop_printer and receipt.kot_printer are chosen, make them a dropdown filled from window.desktop.getPrinters() (plus "Default printer"), not free text, so names always match Windows exactly.

4) CSS / views
   - In resources/css/app.css make sure the print rules keep the slip at its real width with no extra margin: @page { margin: 0 }, html/body margin 0, .print-sheet width auto. Do not use the old insertCSS hacks (width:100%!important on .paper).
   - Check the KOT partial renders at 80 mm and each KOT job is its own page/job. Ensure ?only=receipt and ?only=kitchen still work.
   - Receipt text must be bold/dark enough for thermal printing (keep current font settings).

5) Tests and verification
   - Update/add Pest tests: PrintTest should assert /sales/{id}/print?only=receipt contains data-slip="customer" and not data-slip="kitchen"; ?only=kitchen contains only kitchen slips; a sale with kitchen items returns kitchen_orders; kitchen_missing flag when a line has no station.
   - Run `php artisan test` and fix anything that breaks.
   - Run `npm run build` (Vite) so public/build is current.
   - Do not run the Electron installer build yourself unless I ask; just tell me the exact commands.

6) Docs
   - Update docs/settings-and-printing.md and docs/desktop-and-kiosk.md: describe the new native silent printing, the print log location (userData/print-log.txt), the printer dropdowns, the test slip button and troubleshooting (blank slip, KOT missing = no kitchen station, wrong printer name).

When done, give me a short summary of what changed per file and the manual test checklist.
```

---

## After Claude Code finishes: rebuild and test

Run in the project root, in this order:

```powershell
npm run build
cd electron
npm run dist
```

The installer is created in `dist/`. Uninstall the old POS first (your database in AppData stays), then install the new build.

### Test checklist (80 mm printer)
1. Settings → POS Settings → press **Print test slip**. It should print a clean, sharp, full-width slip.
2. Place a Take Away order with one burger. The receipt should print once, sharp, with nothing cut off on the right (check the invoice number and the amounts).
3. Place a Dine In order with a table. Receipt first, then the KOT.
4. Place 5 orders quickly, back to back. No blank slips, and no KOT missing.
5. Set the KOT printer to a wrong name. You should see a warning, and it should fall back to the default printer.
6. Check `%APPDATA%\POS\print-log.txt` (the userData folder). Every job should show `result=ok`.

If a KOT is still missing: open the product → check the **Kitchen station** field (or the category's station). No station means no KOT.