# Restaurant & Kitchen (Day 5)

Covers dining tables, order types (Dine In, Take Away, Delivery), kitchen stations, kitchen order tickets (KOTs) and the kitchen display. It extends the Day 4 POS ([pos-and-sales.md](pos-and-sales.md)).

- **Retail still works as before.** Restaurant features only apply when an order type or kitchen station is used. The default order type is **Take Away**, which needs no table.
- **No stock is involved.** There is no inventory module, so neither sales nor KOTs change stock, and stock can't be decreased twice.

Schema: [database-design.md](database-design.md#restaurant--kitchen-day-5). Permissions: [roles-and-permissions.md](roles-and-permissions.md).

## Setup

```powershell
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder   # adds tables.* permissions
php artisan db:seed --class=RestaurantSeeder            # optional: Main Kitchen, Grill, Pizza, Drinks, Dessert + Tables 1–12
npm run build
```

Then assign stations. Either give a **category** a kitchen station (Categories → edit → *Kitchen station*), for example Burgers → Grill, or give a **product** its own (Products → edit → *Kitchen station*), for example Coke → Drinks. A product's own station wins over its category's.

> **Only items with a station go to the kitchen.** Products with no station (on the product or its category), or whose station is inactive, are sold normally but never appear on a KOT. So a retail sale creates no KOT.

## Pages

| Page | URL | Who |
|---|---|---|
| POS | `/pos` | `sales.create` (Cashier and up) |
| Tables (floor view, create, edit) | `/restaurant/tables` | view: `tables.view`; change status: `tables.status`; create/edit/delete: `tables.create` / `.edit` / `.delete` |
| Kitchen display | `/kitchen` | view: `kitchen.view`; change KOT status: `kitchen.manage` |
| Printable KOT | `/kitchen/orders/{id}` (`?print=1` opens the print dialog, `?station=` limits it to one station) | `kitchen.view` |
| Kitchen stations | `/kitchen/stations` | list: `settings.view`; change: `settings.manage` (Admin) |

Sidebar: **Restaurant → Tables, Kitchen Orders**, and **Settings → Kitchen Stations**.

## Tables

- **Fields:** number/name (unique, e.g. "Table 12"), section/location (optional), seats, status, active, display order.
- **Statuses:** **Available**, **Occupied**, **Reserved**, always shown with a text label as well as colour.
- **Tables page:** a card per table with its status and any orders still in the kitchen. Staff with `tables.status` (Cashier and up) change the status with one click. The page also counts tables by status.
- **Deleting:** a table with orders can't be deleted; deactivate it instead. Inactive tables are hidden from the POS.

### Status workflow (set by the backend)

| When | Table becomes |
|---|---|
| A **Dine In** order is completed at the POS | **Occupied** (inside the checkout transaction) |
| Its **last open KOT** is marked **Completed** | **Available** (if still Occupied) |
| A KOT is only **Ready** | stays **Occupied** |
| Staff change it on the Tables page or in the POS table picker | whatever they choose |

- A **Reserved** table can't be picked for a new order. The server rejects it with "Table 7 is reserved. Mark it Available or Occupied first to seat guests." When the party arrives, the cashier taps **Mark available** in the picker.
- An **Occupied** table *can* take another order, such as a second round. It stays occupied until all its KOTs are completed.
- A Dine In order with no kitchen items creates no KOT, so its table stays occupied until someone marks it Available.

## Order types

| Type | Table | Notes |
|---|---|---|
| **Dine In** | **required**; must be active and not Reserved | table becomes Occupied |
| **Take Away** (default) | none; any table sent is ignored | same as the Day 4 sale |
| **Delivery** | none | customer name, phone and address from the Customer & delivery dialog; the **delivery fee** is only allowed here |

Saved on the sale as `sales.order_type` (`dine_in` / `take_away` / `delivery`) and `sales.table_id`.

## POS changes

Under the cart header:

```
[ DINE IN ][ TAKE AWAY ][ DELIVERY ]
[ Table 12 ]  [ Order note ]
```

- **Dine In** opens the **table picker**: every active table with its status, section, seats and orders in the kitchen. Reserved tables can't be picked. **Checkout stays disabled** until a table is chosen, and the server checks again.
- **Take Away / Delivery** hide the table. The delivery fee field in the Customer & delivery dialog only appears for Delivery.
- **Order note** (up to 500 characters, e.g. "Customer is waiting outside.") goes on the KOT for the whole order.
- **Item note** is the *Note* button on each cart line (up to 200 characters, e.g. "No onions"). It has quick picks (No onions, Extra spicy, Less spicy, Extra crispy, No ice) and prints under that item on the KOT and the receipt.
- **Modifiers and add-ons** work as on Day 4 and are shown on the KOT.
- After checkout, the **Sale complete** dialog shows *"Dine In · Table 12 · Sent to kitchen"*, the KOT number and a **Print KOT** button.
- **Held sales** keep the order type, table and notes. Holding sends nothing to the kitchen and doesn't occupy the table; that happens when the sale is completed.

## KOT (kitchen order ticket)

Created by `App\Services\KitchenService::send()` **inside the checkout transaction**, after the sale, items and payments. The order is:

1. Sale
2. Sale items
3. Payments
4. KOT and KOT items
5. Table set to Occupied

If any step fails, everything is rolled back; a test forces a failure while the KOT is being written to check this.

- **One KOT per completed order**, holding all of that order's kitchen items. `kitchen_orders.sale_id` is not unique, so a sale can have several KOTs later (e.g. items added afterwards).
- **Number:** `KOT-1`, `KOT-2`, …, sequential per business day (counter resets at the business-day start configured in Settings → General). Generated on the server from the locked `number_sequences` row (`kot`). A unique index backs it.
- **KOT items** point at the sale line, product and variant. Names, variant and add-ons are read from the sale line (already a snapshot), and each item keeps its own **station**, **note** and **status**.
- `created_by` is the cashier.

### Status workflow

```
PENDING ──[START PREPARING]──▶ PREPARING ──[MARK READY]──▶ READY ──[COMPLETE]──▶ COMPLETED
```

- Status lives on the **items**. The kitchen can only move items forward; skipping ahead is allowed (e.g. Pending → Ready), going back is not.
- **Ticket status is worked out from its items:**
  - Completed when all items are completed.
  - Ready when all items are at least ready.
  - Preparing as soon as any item has started.
  - Otherwise Pending.
- `started_at`, `ready_at` and `completed_at` are stamped as each stage is reached.
- `cancelled` exists for future voids; nothing sets it yet.

## Kitchen display (`/kitchen`)

- Full-screen, desktop and tablet friendly. It refreshes every **10 seconds**.
- **Status filter:** All · Pending · Preparing · Ready · Completed, each with a count. *All* shows everything still open plus tickets completed today.
- **Station filter:** All stations · Main Kitchen · Grill · …. With a station selected:
  - each ticket shows **only that station's items**;
  - its badge shows **that station's progress**;
  - its buttons move **only that station's items**.

  The Grill screen can mark its burgers ready while the ticket stays *Preparing* for the other stations.
- **Cards** show:
  - the KOT number, table and order type;
  - a colour-coded status with a text label;
  - the time sent and its age in minutes (amber after 10, red after 20);
  - the invoice, and the customer for Take Away and Delivery;
  - items with large quantities, variants, add-ons, highlighted notes and station;
  - the order note.
- **Buttons:** one large action per card (*Start preparing* → *Mark ready* → *Complete*) plus **Print KOT**. Users without `kitchen.manage` (e.g. Cashiers) see the board read-only.
- Filters are remembered per device (browser storage).

## Printing on checkout

When the order is placed, the POS prints the **customer bill and the kitchen bill (KOT)**, as set in **Settings → POS Settings** (either or both, one job or separate jobs). The KOT layout (width, font, what to show) is set in **Settings → Receipt Settings → Kitchen receipt (KOT)**, and the shared template is `prints/partials/kot.blade.php`. See [settings-and-printing.md](settings-and-printing.md).

## Printable KOT

The layout is shaped for an 80 mm thermal printer and prints through the browser print dialog; there's no printer hardware integration yet. It contains:
- KOT number, TABLE, ORDER type, customer (non Dine In), invoice, time and cashier;
- items grouped by station, each as `2 x Chicken Burger` (quantity-first, plain `x`), with add-ons indented under and notes in bold;
- the order NOTE and the current status.

### KOT layout details (template: `prints/partials/kot.blade.php`)

The KOT template uses dedicated CSS classes rather than generic paper utilities:

| Class | Purpose |
|---|---|
| `.kot-header` | Header block; tight bottom margin |
| `.kot-order-no` | KOT number — large and bold |
| `.kot-item` | One item line; base 1 em |
| `.kot-items-lg` | Container modifier — scales `.kot-item` to 1.2 em |
| `.kot-items-xl` | Container modifier — scales `.kot-item` to 1.5 em |
| `.kot-addon` | Add-on / variant line; 0.9 em, indented |
| `.kot-note` | Item note; 0.9 em, bold, indented |
| `.kot-small` | Small metadata lines (time, cashier, etc.) |

**Item text size** setting (`normal` / `large` / `extra_large`) maps to no class / `kot-items-lg` / `kot-items-xl` on the `.paper` wrapper. Default is `large`.

**Header text** default is `KOT (Kitchen)` (was `KITCHEN ORDER TICKET`).

## Permissions

| Permission | Admin | Manager | Cashier | Kitchen Staff |
|---|:-:|:-:|:-:|:-:|
| tables.view | ✓ | ✓ | ✓ | |
| tables.create / tables.edit | ✓ | ✓ | | |
| tables.delete | ✓ | | | |
| tables.status | ✓ | ✓ | ✓ | |
| kitchen.view (see the board, print KOTs) | ✓ | ✓ | ✓ | ✓ |
| kitchen.manage (change KOT status) | ✓ | ✓ | | ✓ |
| settings.manage (kitchen stations) | ✓ | | | |

Super Admin has everything. Kitchen Staff can't open the POS or the Tables page.

## Code map

| Area | Files |
|---|---|
| Migration | `database/migrations/2026_09_27_100000_create_restaurant_tables.php` |
| Enums | `OrderType`, `TableStatus`, `KitchenStatus` |
| Models | `RestaurantTable`, `KitchenStation`, `KitchenOrder`, `KitchenOrderItem` (table `kot_items`); `Sale::table()`, `Sale::kitchenOrders()`, `Category::kitchenStation()`, `Product::kitchenStation()` |
| Services | `KitchenService` (send, advance, refresh, combine), `SaleService` (order type, table, notes, calls `KitchenService`), `CartResolver` (station per line) |
| Controllers | `RestaurantTableController`, `KitchenOrderController`, `KitchenStationController`, `PosController::tables()` |
| Requests | `RestaurantTableRequest`, `KitchenStationRequest`; `CartRequest` (order type, table, notes, reserved check, delivery-fee rule) |
| Views | `restaurant/tables/*`, `kitchen/index`, `kitchen/orders/show`, `kitchen/stations/*`; POS dialogs `pos-tables`, `pos-note` |
| JavaScript | `resources/js/kitchen.js` (Alpine `kitchen`), `resources/js/pos.js` |
| Seeder | `RestaurantSeeder` (optional) |
| Tests | `tests/Feature/Restaurant/KitchenTest.php`, `tests/Feature/Restaurant/RestaurantTest.php` |

## Routes

| Method | URL | Name |
|---|---|---|
| GET | `/pos/tables` | `pos.tables` (JSON for the picker) |
| GET/POST | `/restaurant/tables` | `restaurant.tables.index` / `.store` |
| GET | `/restaurant/tables/create`, `/restaurant/tables/{table}/edit` | `.create` / `.edit` |
| PUT/DELETE | `/restaurant/tables/{table}` | `.update` / `.destroy` |
| PATCH | `/restaurant/tables/{table}/status` | `restaurant.tables.status` (HTML or JSON) |
| GET | `/kitchen` | `kitchen.index` |
| GET | `/kitchen/orders?status=&station=` | `kitchen.orders` (JSON feed) |
| GET | `/kitchen/orders/{kitchenOrder}` | `kitchen.orders.show` (printable) |
| POST | `/kitchen/orders/{kitchenOrder}/status` | `kitchen.orders.status` (`status`, optional `station`) |
| resource | `/kitchen/stations` | `kitchen.stations.*` (no show) |
