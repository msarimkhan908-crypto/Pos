# Suppliers, Purchases & Customers (Day 3)

Supplier management, purchase records with line items, purchase history, and customer management.

**Purchases are records only. They do not change stock**, because there is no inventory module (removed 2026-09-24, see [products.md](products.md#removed-inventory)). Sales, payments, returns and reports are later modules.

Schema: [database-design.md](database-design.md). Permissions: [roles-and-permissions.md](roles-and-permissions.md).

## Pages

| Page | URL | Permission |
|---|---|---|
| Suppliers (list, create, view, edit) | `/suppliers` | `suppliers.view` / `.create` / `.edit` / `.delete` |
| Purchases (history, create, view, edit draft) | `/purchases` | `purchases.view` / `.create` / `.edit` / `.delete` |
| Customers (list, create, view, edit) | `/customers` | `customers.view` / `.create` / `.edit` / `.delete` |

Each controller enforces its permission with Spatie middleware, and each Form Request checks it again in `authorize()`. Typing a URL directly returns **403**.

| Role | Suppliers | Purchases | Customers |
|---|---|---|---|
| Super Admin, Admin | full | full | full |
| Manager | view, create, edit | view, create, edit | view, create, edit |
| Cashier | — | — | view, create, edit |
| Kitchen Staff | — | — | — |

## Suppliers

- **Fields:** name (required), company, phone, email, address, tax number, registration number, notes, status (Active or Inactive).
- **List:** search by name, company, phone or email; filter by status; 15 per page.
- **Detail page:** supplier, contact and tax information, plus the supplier's **purchase history** with the total excluding cancelled purchases. It shows "No purchases found for this supplier." when there are none.
- **Rules:**
  - Inactive suppliers can't be chosen for **new** purchases. Existing drafts keep theirs.
  - A supplier **with purchases cannot be deleted**; deactivate it instead.

## Purchases

### Workflow
Supplier → date → add items (product, variant if the product has variants, quantity, unit cost, optional discount, optional tax) → **Save as draft** or **Save & complete**.

- The product picker searches name, SKU, barcode and variant SKU. Choosing a product or variant pre-fills its cost price.
- A product that has variants **must** be bought per variant. A variant must belong to the chosen product.
- Quantities allow up to 3 decimals (e.g. 2.5 kg).

### Calculations (server-side)
`App\Services\PurchaseCalculator` does exact decimal maths with `bcmath`, never floats:

| Value | Formula |
|---|---|
| Line gross | quantity × unit cost, rounded half up to 2 dp |
| Line total | gross − line discount + line tax |
| Subtotal | Σ line gross |
| Discount | Σ line discounts |
| Tax | Σ line taxes |
| Total | subtotal − discount + tax |

- Discount and tax are **amounts** per line, not percentages. A discount can't exceed the line amount.
- **Totals sent by the browser are ignored.** The form shows a live preview, but the server recalculates everything on save.
- Lines and totals are capped at 999,999,999,999.99, the size of the money columns.

### Purchase number
`PUR-000001`, `PUR-000002`, … It's built from the purchase's database ID inside the save transaction, so it's unique even when two people save at once. A unique index enforces it.

### Saving
`App\Services\PurchaseService` creates the purchase, its number and its items in **one database transaction**. If anything fails, nothing is saved, and a test forces a failure part-way to prove this.

### Status rules

| Status | Edit items | Delete | Can change to |
|---|---|---|---|
| Draft | ✓ | ✓ | Completed, Cancelled |
| Completed | — | — (cancel instead) | Cancelled |
| Cancelled | — | — | nothing (final) |

- **Payment status** (Unpaid / Partially Paid / Paid) can be changed on the purchase page at any time, unless the purchase is cancelled. It's a simple flag; a shared payments ledger can replace it later.
- Archived (soft-deleted) products and variants still show on old purchases, marked "Archived".

### Purchase history
Search by purchase number or supplier. Filter by supplier, status, payment status and date range. 15 per page, newest first.

## Customers

- **Fields:** name (required), phone, email, address, notes, status (Active or Inactive).
- **List:** search by name, phone or email; filter by status; 15 per page.
- **Detail page:** customer information and notes, plus a **Purchase / Sales history** section listing completed POS sales (invoice, date, payment, total, receipt link). It shows "No sales found for this customer." when there are none.
- Phone numbers are indexed for quick lookup at the till but not unique, since family members may share a number.
- **Deleting:** blocked once the customer has any sale (completed or held); deactivate them instead. See [pos-and-sales.md](pos-and-sales.md).
- The dashboard's **Total Customers** card is now live.

## Validation summary

| Entity | Rules |
|---|---|
| Supplier / Customer | name required (≤150); email must be valid if given; phone 5–30 of digits, spaces, `+ - ( )`; status must be active or inactive |
| Purchase | active supplier; valid date; payment status valid; saved as draft or completed; at least 1 item (max 200) |
| Purchase item | product exists (not archived); variant required for variant products and must belong to the product; quantity > 0 (≤ 3 dp); unit cost ≥ 0 (≤ 2 dp); discount ≥ 0 and ≤ line amount; tax ≥ 0 |

## Code map

| Area | Files |
|---|---|
| Models | `app/Models/{Supplier,Purchase,PurchaseItem,Customer}.php` |
| Enums | `app/Enums/{RecordStatus,PurchaseStatus,PaymentStatus}.php` |
| Services | `app/Services/{PurchaseCalculator,PurchaseService}.php`, `app/Exceptions/PurchaseException.php` |
| Controllers | `app/Http/Controllers/{Supplier,Purchase,Customer}Controller.php` |
| Requests | `app/Http/Requests/{Supplier,Purchase,Customer}Request.php` |
| Views | `resources/views/{suppliers,purchases,customers}/` |
| Purchase form JS | `purchaseForm` in `resources/js/app.js` |
| Tests | `tests/Feature/Purchasing/*`, `tests/Feature/Customers/*`, `tests/Unit/PurchaseCalculatorTest.php` |
