# POS & Sales (Day 4)

The cashier screen at `/pos` (route `pos.index`). It handles products, the cart, discounts, tax, the customer, payment, the saved sale and a basic receipt.

**Sales do not change stock.** There is no inventory module (removed 2026-09-24, see [products.md](products.md#removed-inventory)). On Day 4 the owner chose to keep it that way, so the "decrease stock" step of the original spec is intentionally not built.

Schema: [database-design.md](database-design.md#pos-sales-day-4). Permissions: [roles-and-permissions.md](roles-and-permissions.md).

## Quick start

```powershell
php artisan migrate          # creates number_sequences, sales, sale_items, sale_item_modifiers, sale_payments
npm run build                # or: npm run dev
```

Log in as `cashier@pos.test` / `password` and open **POS** in the sidebar (or `/pos`).

## Restaurant (Day 5)

The cart now starts with an **order type** (Dine In / Take Away / Delivery; Take Away by default), a **table** for Dine In, an **order note**, and a **Note** button on each line. Completing a sale with kitchen items also creates a **KOT** in the same transaction and occupies the Dine In table. A **delivery fee** is only accepted on Delivery orders. See [restaurant-and-kitchen.md](restaurant-and-kitchen.md).

## Access

| Action | Permission | Roles |
|---|---|---|
| POS screen, product search, barcode, pricing, hold / resume / remove held sales, checkout | `sales.create` | Super Admin, Admin, Manager, Cashier |
| Receipt page `/sales/{id}/receipt` | `sales.view` | Super Admin, Admin, Manager, Cashier |

Kitchen Staff and users without a role get **403** on every POS URL. Guests are redirected to login.

## The screen

| Area | What it does |
|---|---|
| Search (F2) | Name, SKU or barcode. Results update as you type (200 ms debounce), with no page reload. Shows up to 60 cards. |
| Barcode (F4) | Focused on load. A scanner types the code and presses Enter. It matches the exact barcode or SKU of a product **or variant** and adds it to the cart. An unknown code shows "Product not found." |
| Categories | **All** plus every active category that has active products. |
| Product cards | Image (or initial), name, price ("from" the lowest variant price). Tap to add. |
| Cart | Name, variant, add-ons, unit price, `[-] qty [+]`, line discount, line total, remove. Adding the same product (same variant and add-ons) again increases the quantity instead of adding a row. Decreasing past 1 removes the line. |
| Totals | Subtotal, Discount (item and order discounts), Tax, Total, all **from the server**. Totals show "—" until the server has priced the current cart. |
| Customer & delivery | **Walk-in Customer** by default. Opens the **Customer & delivery** dialog: type name, phone, address and delivery fee, or search and pick an existing customer (which fills the fields in). See [Customer details & delivery fee](#customer-details--delivery-fee). |
| Buttons | Hold sale, Resume sale (with count), Clear cart (asks for confirmation), **Checkout** (F9). |

**Cart layout:** the panel is kept compact so the item list gets most of the height. The order type, table, order note and customer sit in two short rows at the top; totals are a small two-column block; Hold, Resume and Checkout share one row. Each line shows the name, the quantity stepper, Note, Disc. and remove; "qty × price" appears only when the quantity is above 1 or there are add-ons. The list scrolls to the item just added and highlights it briefly. On phones the cart sits below the products, and a **View cart** bar at the bottom jumps to it. With five items in the cart, all five are fully visible at 1366×768 and four at 1280×720.

Products appear only when they are active and not archived, and their category (if any) is active. A variant product needs at least one active variant.

### Variants and add-ons
If a product has variants or modifier groups, tapping it opens a picker. The cashier chooses one variant (required), then add-ons according to each group's rule (e.g. "Required · choose 1", "Optional · up to 3"). Add-on prices are added to the unit price.

### The cart survives a page reload
The cart (ids, quantities, discounts, customer) is kept in the browser's `localStorage`. It is only a convenience: prices are always re-read from the server.

## Calculations (server-side)

`App\Services\SaleCalculator` does exact decimal maths with `bcmath`, never floats. `App\Services\CartResolver` looks up prices, names and tax rates in the database. **Prices, discount amounts, tax and totals sent by the browser are ignored.**

| Value | Formula |
|---|---|
| Unit price | product (or variant) selling price, after the product's own catalog discount, **+ add-on prices** |
| Line gross | quantity × unit price |
| Line discount | fixed amount **for the whole line**, or % of line gross; can't exceed the line |
| Line total | gross − line discount |
| Order discount | fixed amount, or % of Σ line totals; can't exceed that amount |
| Order discount share | order discount split across lines in proportion to their totals, to the cent (largest remainder) |
| Line tax | (line total − its order discount share) × the product's `tax_rate` %, rounded half up |
| Subtotal | Σ line gross |
| Discount | Σ line discounts + order discount |
| Tax | Σ line tax |
| Delivery fee | as typed; **not taxed and not discounted** |
| **Total** | subtotal − discount + tax + delivery fee |

Tax uses each product's **Tax rate** field (set on the product form). New products start with the default rate from **Settings → Tax Settings**, which also sets the tax label (e.g. GST).

**Reference sale:** Burger 500 × 2, Coke 200, Fries 300, all 18 % tax, order discount 100:
subtotal 1500 − discount 100 = 1400 taxable; tax 252; **total 1652**. Cash 2000 gives change **348**.

### Validation
| Rule | Message |
|---|---|
| Cart empty | "The cart is empty." |
| Quantity whole number 1 – 9 999; max 200 lines | |
| Product inactive, archived or in an inactive category | "This product is no longer available." |
| Variant product without a variant, or wrong/inactive variant | "Choose a variant of …" / "This variant is not available." |
| Add-ons outside the group's min/max, or not on this product | "Extras: Optional · up to 2." |
| % discount > 100, fixed discount > line or order amount, negative discount | |
| Customer not active | "Choose an active customer." |
| Name > 150 or address > 500 characters; phone not 5–30 of digits, spaces, `+ - ( )`; delivery fee negative or more than 2 decimals | |
| "Save as a new customer" without a name, or without `customers.create` | "Enter a name to save this customer." |

## Customer details & delivery fee

Click the **Customer & delivery** box in the cart to open the dialog:

| Field | Notes |
|---|---|
| Search existing customers | Name or phone. Picking one links the sale to that customer and fills in name, phone and address. **Unlink** keeps the typed details but removes the link. While nobody is linked, customers whose phone or name matches what you type are suggested. |
| Customer name, Phone number, Address | Free text, saved **on the sale** (`sales.customer_name`, `customer_phone`, `customer_address`) exactly as typed. Blank fields are filled from the linked customer, if any. |
| Delivery fee | Delivery orders only (Day 5). Added to the total **after tax**; it is not taxed and not discounted. Shown as its own line in the totals and on the receipt. |
| Save as a new customer | Shown when a name is typed and no customer is linked (users with `customers.create`, which Cashiers have). Creates the customer inside the same transaction as the sale and links it. Off by default, to avoid duplicates. |
| Walk-in (clear) | Clears everything, including the delivery fee. |

Held sales keep the details and the fee; the held list shows the name, phone and a **Delivery** tag. Everything resets after checkout or **Clear cart**.

## Payment

| Method | Rules | Saved |
|---|---|---|
| **Cash** | cash received ≥ total | `cash_received`, `change_amount = cash_received − total`; one `sale_payments` row: cash = total |
| **Card** | card amount = total (filled in automatically) | one row: card = total. No card numbers or card details are stored. |
| **Mixed** | cash > 0, card > 0, cash + card = total (card fills in with the rest) | two rows: cash and card; `cash_received` = cash part, change 0 |

The dialog blocks **Complete sale** until the payment is valid, and the server checks it again against its own total. Quick-cash buttons offer the exact total and the next round amounts.

## Checkout transaction

`App\Services\SaleService::checkout()` runs in **one database transaction**:

1. Validate the cart, products, variants, add-ons, quantities and discounts.
2. Calculate prices, discounts, tax and total.
3. Validate the payment.
4. Take the next invoice number.
5. Create the sale, its items (with add-ons) and its payment rows.
6. Commit. Any failure rolls everything back, including the invoice number.

There is no stock step (see the top of this page).

### Invoice numbers
`INV-000001`, `INV-000002`, …, generated on the server from a counter row in `number_sequences`. The row is locked (`SELECT … FOR UPDATE`) until the transaction commits, so two tills never get the same number. A rollback gives the number back, so there are no gaps. `sales.invoice_number` also has a unique index. Held sales get no number.

### Business date
Each completed sale records a `business_date` (the calendar date of the business day, not the wall-clock date). When the business-day start time (Settings → General → Business day start) is set to e.g. `06:00`, a sale at 02:00 belongs to the previous calendar day. This keeps nightly orders grouped correctly for daily reports and KOT numbering.

## Editing a completed sale

A completed sale can be edited by users with `sales.edit` (Super Admin, Admin, Manager). The original totals, items and payment are preserved in the audit log before any change takes effect.

### How it works

1. Open a completed sale and click **Edit sale**.
2. Change items, quantities, discounts or payment method, then click **Save changes**.
3. The server recalculates all totals, increments `sales.edit_version`, writes a `sale_edits` row with a JSON snapshot of the previous state, and saves the updated sale.

Any linked KOT is **not modified** — the kitchen already received and may have acted on the original ticket. A note field lets the editor record the reason for the change (stored in `sale_edits.note`).

### Audit trail

`App\Models\SaleEdit` (table `sale_edits`) keeps a full snapshot per version. To view the history, open a sale and click **Edit history** (Admin / Super Admin only).

### Code map (edit path)

| Area | Files |
|---|---|
| Service | `App\Services\SaleEditService` |
| Controller | `SaleController::edit()`, `SaleController::update()` |
| Request | `EditSaleRequest` |
| Model | `Sale::edits()` (hasMany SaleEdit), `SaleEdit` |
| Views | `resources/views/sales/edit.blade.php`, `_edit-history.blade.php` |
| Tests | `tests/Feature/Pos/SaleEditTest.php` |

## Held sales

- **Hold sale** saves the cart as a `sales` row with status `held`: items, customer and discounts, but no invoice number and no payments. The cart is then cleared for the next customer.
- **Resume sale** lists held sales (number, customer, items, total, time, cashier). **Resume** loads the cart at **today's prices** and removes the sale from the held list. Items that can no longer be sold are dropped and named in a message. Resuming is locked, so two tills can't resume the same sale.
- **Remove** deletes a held sale.
- To resume, the current cart must be empty (hold or clear it first).

## Receipt

### Two bills are printed when the order is placed

As soon as checkout succeeds, the POS prints **one print job with two slips**, each on its own page (a thermal printer cuts between them):

1. **Customer bill**, marked "Customer copy".
2. **Kitchen bill (KOT)**, one per kitchen ticket, only when the order has kitchen items. A retail sale prints just the customer bill.

The job is `/sales/{id}/print`, loaded into a hidden frame on the POS page and printed from there, so popup blockers don't interfere. The **Sale complete** dialog says what's printing and has **Print both**, **Customer bill** and **Kitchen bill** buttons for reprints (`?only=receipt` / `?only=kitchen`). The print page needs `sales.view`.

- **Turn it off or change it:** Settings → **POS Settings** (auto-print receipt, auto-print KOT, one job or separate jobs). `POS_AUTO_PRINT` in `.env` is only the default before those settings are saved. See [settings-and-printing.md](settings-and-printing.md).
- **No print dialog (silent printing):** browsers always show the print dialog to a normal web page. On the till PC, start Chrome with `--kiosk-printing` (e.g. a shortcut to `chrome.exe --kiosk-printing http://localhost:8000/pos`) and make the receipt printer the system default. Both slips then print straight away.

### Receipt page

After checkout, the **Sale complete** dialog shows the receipt and a **New sale** button. The single-receipt page is `/sales/{id}/receipt` (`?print=1` opens the print dialog). It shows the store name, invoice, date, cashier, customer, customer name, phone and address (when entered), items (quantity × price, add-ons, line discounts), subtotal, discount, tax, delivery fee (when set), total, the payment method, and cash received and change (or the cash/card split).

- The store name comes from `POS_STORE_NAME` in `.env` (defaults to `APP_NAME`).
- Receipt customization (header image, colours, kitchen tickets) is a later phase.

## Customers
- `Customer::sales()` (hasMany). The customer page lists completed sales with their receipt links and total spent.
- A customer with any sale (completed or held) **cannot be deleted**; deactivate them instead. The `sales.customer_id` foreign key is `restrict` as a backstop.

## Settings

| Setting | Where | Default |
|---|---|---|
| Currency shown | `POS_CURRENCY` in `.env` | `USD`. Set `POS_CURRENCY=PKR` to show `PKR 1,652.00` |
| Receipt store name | `POS_STORE_NAME` in `.env` | `APP_NAME` |
| Printing after checkout | Settings → POS Settings (default from `POS_AUTO_PRINT`) | print both, one job |
| Tax | each product's **Tax rate** | 0 % |
| Timezone of receipt and dashboard dates | `timezone` in `config/app.php` | `UTC` |

## Code map

| Area | Files |
|---|---|
| Routes | `routes/web.php` (POS / sales group) |
| Controllers | `PosController` (screen, products, lookup, quote), `HeldSaleController`, `SaleController` (checkout, receipt) |
| Requests | `CartRequest`, `CheckoutRequest` |
| Services | `SaleService`, `SaleCalculator`, `CartResolver`, `CartLineException`, `SaleEditService`, `App\Support\NumberSequence` |
| Models | `Sale` (with `edit_version`, `business_date`, `edits()`), `SaleItem`, `SaleItemModifier`, `SalePayment`, `SaleEdit`; `Customer::sales()` |
| Enums | `SaleStatus` (held, completed, cancelled), `PaymentMethod` (cash, card, mixed) |
| Views | `resources/views/pos/index.blade.php`, `resources/views/sales/{_receipt,receipt}.blade.php` |
| JavaScript | `resources/js/pos.js` (Alpine `pos` component) |
| Tests | `tests/Feature/Pos/PosTest.php`, `tests/Unit/SaleCalculatorTest.php` |

## JSON endpoints

| Method | URL | Purpose |
|---|---|---|
| GET | `/pos/products?search=&category=` | product cards |
| GET | `/pos/lookup?code=` | barcode / SKU lookup (404 "Product not found.") |
| POST | `/pos/quote` | server pricing of the current cart |
| GET / POST | `/pos/held` | list / hold |
| POST | `/pos/held/{sale}/resume` | resume (removes it from the held list) |
| DELETE | `/pos/held/{sale}` | remove a held sale |
| POST | `/sales` | checkout; returns the sale, receipt HTML and receipt URL |

All POST and DELETE requests need the CSRF token (`X-CSRF-TOKEN` header, from the page's meta tag). Validation errors come back as 422 with an `errors` object.
