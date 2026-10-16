# Settings, Receipts & Printing (Day 6)

## Where
| Sidebar item | Tabs | URLs |
|---|---|---|
| General Settings | Business · General · Appearance · Dashboard | `/settings/business`, `/settings/general`, `/settings/appearance`, `/settings/dashboard` |
| POS Settings | Printing | `/settings/pos` |
| Receipt Settings | Customer receipt · Kitchen receipt (KOT) | `/settings/receipt`, `/settings/kot` |
| Tax Settings | Tax | `/settings/tax` |
| Kitchen Stations | (Day 5) | `/kitchen/stations` |

- **View:** `settings.view` (Super Admin, Admin, Manager). Managers see the pages read-only (every field is disabled and there's no Save button).
- **Change:** `settings.manage` (Super Admin, Admin). This is the project's existing equivalent of `settings.edit`. It is checked in the controller middleware **and** again in `SettingsRequest`.
- Forms use CSRF, server-side validation, and a fixed list of known keys (unknown keys are ignored). Uploads are validated by file content, not extension.

## Storage
One `settings` table (`key`, `value`, `type`, `group`), with keys and defaults defined in `App\Settings\SettingDefinitions`. Values are cached, and the cache is cleared on every save. Receipt, KOT and dashboard settings are stored as JSON; everything else as one value per key. Read them with `settings('key')`, `settings()->receipt()`, `settings()->kot()` or `settings()->business()`.

## Sections

| Section | Settings |
|---|---|
| **Business** | Name, logo (JPG/PNG/WEBP, ≤ 5 MB or PHP's limit, ≤ 4000 px), address, phone, email, website, tax number, registration number. The logo and name appear in the header and on receipts. |
| **General** | Currency code (e.g. PKR), currency symbol (e.g. Rs; replaces the code everywhere), date format, time format, **timezone** (sets "today" for reports and receipt times), number prefixes for invoice/sale, purchase, return, expense and KOT (letters, digits, `- / _`, ≤ 10 characters; new documents only), **Hide .00 on whole amounts** (whole amounts print without decimals; fractional amounts use 2 decimal places) |
| **Appearance** | Theme preset (Indigo, Blue, Emerald, Rose, Amber, Dark sidebar, Custom), **font family** (interface typeface — Figtree default, 25 bundled fonts, no CDN), primary colour, button colour, secondary colour (POS checkout), sidebar colour, sidebar text colour, with a live preview. |
| **Dashboard** | Which widgets show: sales & orders, purchases, returns, expenses, customers, sales chart, recent sales, recent purchases, top products |
| **Tax** | Default tax rate for new products; tax label (e.g. GST) used on receipts and in the POS |
| **POS / Printing** | Auto-print customer receipt after a completed sale; auto-print KOT when it's created; printer layout (**one job** with page breaks, or **separate jobs**). Shows the paper widths and the Chrome kiosk-printing tip. |
| **Customer receipt** | Width (58 mm / 80 mm / A4), **font family** (receipt typeface — Courier Prime default), **heading font** (optional distinct font for the header block), font size, item layout (detailed / compact), header & footer alignment, logo size, header image, header text, footer, notes, and show/hide for logo, address, phone, email, website, tax number, customer, cashier, order type & table, discounts, tax, payment details, item notes, footer, separators, border |
| **Kitchen receipt (KOT)** | Width (58 mm / 80 mm), **font family** (KOT typeface — Courier Prime default), **item text size** (Normal / Large / Extra large — scales `.kot-item` via `kot-items-lg` / `kot-items-xl` container class), font size, header alignment, **header text** (default: `KOT (Kitchen)`), footer text, and show/hide for table, order type, order number, customer, cashier, notes & special instructions, station headings, business logo, separators |

Colours are applied app-wide through CSS variables (`App\Support\Palette` and `<x-theme />` in every layout, mapped in `tailwind.config.js`), so no rebuild is needed and no page hard-codes a chosen colour. Only the sidebar, brand colours and buttons change; custom colour themes are independent of dark mode.

**Fonts** are also applied via CSS variables emitted by `Palette::css()`: `--app-font`, `--receipt-font`, `--receipt-heading-font`, `--kot-font`. The 25 bundled font families live in `App\Support\FontRegistry`; all are loaded offline via `@fontsource/*` npm packages — there are no CDN or Google Fonts links anywhere. The interface `font-family` in `tailwind.config.js` reads `var(--app-font, Figtree)` so new fonts apply automatically without a rebuild.

**Dark mode:** A three-way toggle (Light / Dark / Auto) lives in the navbar. The selected theme is saved in `localStorage` as `pos.theme` (`'light'` | `'dark'` | `'auto'`). Auto follows the OS `prefers-color-scheme`. The `dark` class is applied to `<html>` before first paint (early inline script) to avoid a flash. Tailwind's `class`-based dark mode (`darkMode: 'class'`) powers `dark:*` utility variants used throughout the shell and UI components; a CSS `@layer` in `app.css` also cascades structural overrides (text, backgrounds, borders) so individual page templates don't need `dark:` on every element. Print layouts are explicitly exempt from dark-mode overrides.

## Live previews
The receipt and KOT tabs show a **live preview** next to the form. Every change sends the unsaved form to `POST /settings/preview/{receipt|kot}` (`settings.view`), which renders the **same Blade template used for printing** with **sample data**, clearly labelled "Sample data — preview only".
- The sample sale and KOT are built in memory (`App\Support\ReceiptSample`). **Nothing is saved.**
- A newly chosen header image is shown embedded in the preview; it isn't stored until you save.

## Printing
| Document | URL | Widths |
|---|---|---|
| Customer receipt | `/sales/{id}/receipt` | 58 mm, 80 mm, A4 |
| Kitchen order ticket | `/kitchen/orders/{id}` (`?station=` for one station) | 58 mm, 80 mm |
| Order print job (receipt + KOT) | `/sales/{id}/print` (`?only=receipt` or `?only=kitchen`) | as above, each slip on its own page |

- **Views:** `resources/views/prints/receipt.blade.php`, `prints/kot.blade.php`, `prints/order.blade.php`, with shared bodies in `prints/partials/receipt.blade.php` and `prints/partials/kot.blade.php` (also used by the POS dialog and the previews). The layout is `<x-print-layout>`: no sidebar or navbar; buttons are `.no-print`.
- **Print CSS** (`resources/css/app.css`): `.paper-58mm / -80mm / -a4` widths, font sizes, dashed separators, named `@page` rules (thermal with no margin, A4 with 12 mm margins), and `.print-sheet + .print-sheet { break-before: page }` so each slip gets its own page.
- **After a completed sale** the POS prints according to POS Settings: receipt and/or KOT, as one job or two, from a hidden frame (no popups). The Sale complete dialog has **Print both / Customer bill / Kitchen bill** for reprints.
- **Browser printing** always shows the print dialog. For silent printing in the browser, use Chrome's `--kiosk-printing` (see [desktop-and-kiosk.md](desktop-and-kiosk.md)).
- **Desktop (Electron) printing** renders the print URL to a PDF via `webContents.printToPDF`, then sends it directly to the Windows printer via `pdf-to-printer` / SumatraPDF — no print dialog, no admin rights required. Falls back to `webContents.print` (silent) if SumatraPDF is absent. Per-job retry runs once on failure; failures and successes are logged to `print-history.json` in userData. See [desktop-and-kiosk.md](desktop-and-kiosk.md) for setup and diagnostics.

## Code map
| Area | Files |
|---|---|
| Settings | `app/Settings/{SettingDefinitions,Settings}.php`, `app/helpers.php`, `app/Http/Middleware/ApplySettings.php` (timezone), `SettingsController`, `SettingsRequest`, `resources/views/settings/*`, `resources/js/settings.js` |
| Formatting | `App\Support\Money` (currency and symbol), `App\Support\Format` (date and time), `App\Support\Palette` (colours + font CSS vars), `App\Support\FontRegistry` (25 bundled font families) |
| Printing | `App\Support\Printing`, `App\Support\ReceiptSample`, `resources/views/prints/*`, `resources/views/components/print-layout.blade.php`, `printOrder()` / `printJobs()` in `resources/js/pos.js`; desktop: `electron/main.js` (print queue + retry), `app/Http/Controllers/StorageController.php` (image serving) |
| Fonts | `app/Support/FontRegistry.php`, `resources/css/app.css` (`@import '@fontsource/...'` × 53 files), `tailwind.config.js` (`sans: ['var(--app-font, Figtree)', ...]`) |
| Tests | `tests/Feature/Settings/SettingsTest.php`, `tests/Feature/Pos/PrintTest.php`, `tests/Feature/MoneyFormattingTest.php`, `tests/Feature/StorageRouteTest.php`, `tests/Feature/DesktopSelfcheckTest.php`, `tests/Feature/Desktop/PrintingTest.php` |
