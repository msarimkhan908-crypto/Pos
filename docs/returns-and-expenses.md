# Returns & Expenses (Day 6)

Neither module touches stock; there is no inventory module (see [products.md](products.md#removed-inventory)). Schema: [database-design.md](database-design.md#returns-expenses--settings-day-6).

## Returns

A return refunds items of a **completed** sale.

| Page | URL | Permission |
|---|---|---|
| Return history | `/returns` | `returns.view` |
| New return | `/returns/create` (or `?sale={id}`, `?invoice=INV-…`) | `returns.create` |
| Return details | `/returns/{id}` | `returns.view` |
| Complete or cancel a pending return | `PATCH /returns/{id}/status` | `returns.edit` |
| Delete a pending or cancelled return | `DELETE /returns/{id}` | `returns.delete` |

Admins have all four; managers have view, create and edit; cashiers and kitchen staff have none. You can change this in `RolesAndPermissionsSeeder`.

### Workflow
1. **Find the sale** by invoice number. The Sales report also has a **Return** button on each sale.
2. **Choose items:** each line shows sold, already returned and paid amounts. Enter the quantity to return (0 up to what is left) and optionally an item reason. **Return everything left** fills all lines.
3. **Refund:** choose the return reason (Damaged / defective, Wrong item, Quality issue, Expired, Customer changed mind, Order mistake, Other), the refund method (Cash, Card, Bank transfer, Other) and optional notes.
4. **Complete return & refund** (status Completed) or **Save as pending** (refund later). A pending return can be **completed** (choose the refund method) or **cancelled**.

### Refund calculation (server-side, `App\Services\ReturnService`)
Each unit refunds what the customer actually paid for it: the line total after its own discount and its share of the order discount, **including tax**, spread evenly over the units and rounded to the cent. Refunds for a line always add up to exactly what was paid for it:

```
refund(q) = round(paid × (already + q) / sold) − round(paid × already / sold)
```

Example (the Day 4 reference sale): Burger ×2 paid 1,101.33, so returning one refunds **550.67** and the second **550.66**. Returning everything refunds the full 1,652.00. **The delivery fee is not refunded.**

Refund amounts, prices and quantities sent by the browser are never used. The form's "Estimated refund" is only a preview.

### Rules
- Only completed sales; held sales are refused.
- A quantity can't exceed what is left to return. Pending and completed returns both count; cancelled ones don't. Quantities must be whole and not negative.
- A fully returned item shows **Fully returned** and can't be returned again.
- Every item must belong to that sale, and the same line can't appear twice.
- The sale lines are locked during the return (`SELECT … FOR UPDATE`), so two tills can't over-return the same item.
- **Completed returns are final.** They can't be cancelled or deleted, because they are financial records.

### What gets updated
- `sale_items.returned_quantity`
- `sales.return_status`: `none` / `partial` / `full`
- `sales.returned_amount`: completed refunds only

These appear in the Sales report and on the dashboard.

### Return numbers
`RET-000001`, `RET-000002`, …, from the locked `number_sequences` row (`return`), plus a unique index. The prefix is set under **Settings → General**.

## Expenses

| Page | URL | Permission |
|---|---|---|
| Expenses (list, search, filters, total) | `/expenses` | `expenses.view` |
| New / edit / view / delete | `/expenses/create`, `/expenses/{id}/edit`, `/expenses/{id}` | `expenses.create` / `.edit` / `.view` / `.delete` |
| Expense categories | `/expense-categories` | view `expenses.view`; create and edit `expenses.edit`; delete `expenses.delete` |

Admins have all; managers have view, create and edit.

- **Fields:** category (active), amount (> 0, 2 decimals), date (not in the future), payment method (Cash / Card / Bank transfer / Other), description, reference (bill number).
- **Number:** `EXP-000001`… is generated on the server. The number and `created_by` can't be set from the form.
- **List:** search (number, description, reference), category, payment method and date range filters, 15 per page, with the filtered total.
- **Categories:** name (unique), description, status. **No categories are created automatically.** Add your own, e.g. Rent, Electricity, Internet, Salaries, Transportation, Maintenance, Supplies, Other. Inactive categories can't be chosen for new expenses. A category that has expenses can't be deleted; deactivate it instead.

## Code map
| Area | Files |
|---|---|
| Models | `SaleReturn` (table `returns`), `ReturnItem`, `Expense`, `ExpenseCategory`; `Sale::returns()`, `SaleItem::returnItems()`, `SaleItem::paidAmount()` |
| Enums | `ReturnStatus`, `ReturnReason`, `SaleReturnStatus`, `MoneyMethod` |
| Services | `ReturnService` |
| Controllers | `ReturnController`, `ExpenseController`, `ExpenseCategoryController` |
| Requests | `ReturnRequest`, `ExpenseRequest` |
| Views | `returns/*`, `expenses/*`, `expense-categories/*` |
| JavaScript | `resources/js/returns.js` (refund estimate) |
| Tests | `tests/Feature/Returns/ReturnTest.php`, `tests/Feature/Expenses/ExpenseTest.php` |
