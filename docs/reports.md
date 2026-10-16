# Reports (Day 6)

`/reports` needs `reports.view` (Super Admin, Admin, Manager). Every figure is calculated from recorded data; nothing is estimated or generated.

## Period
Every report has a **Period** picker: **Today** (default), **Yesterday**, **This week** (from Monday), **This month**, **Last month**, **Custom range** (from / to). Days follow the business timezone (**Settings → General**). The period is kept when you switch between reports.

## What counts

| Figure | Source |
|---|---|
| Total sales | Completed sales, by completed time (`sales.total`, including tax and delivery fee) |
| Total returns | **Completed** returns (refunds paid out), by return date |
| Net sales | Total sales − total returns |
| Total purchases | **Completed** purchases, by purchase date (drafts and cancelled excluded) |
| Total expenses | Expenses, by expense date |
| Customers | Registered customers who bought in the period, plus the number on record |

## Reports

| Report | URL | Columns | Filters |
|---|---|---|---|
| Overview | `/reports` | The figures above, sales by payment method, discounts given, tax collected, expenses by category | Period |
| Sales | `/reports/sales` | Invoice, date, customer, cashier, subtotal, discount, tax, total, payment, status (completed / partially / fully returned), **Return** button | Customer, cashier, payment method, return status |
| Purchases | `/reports/purchases` | Purchase, date, supplier, subtotal, discount, tax, total, status | Supplier, status (completed by default, or all / draft / cancelled) |
| Returns | `/reports/returns` | Return, sale, date, customer, refund, method, status | Status, refund method |
| Expenses | `/reports/expenses` | Expense, category, date, amount, payment, description | Category, payment method |
| Customers | `/reports/customers` | Customer, purchases, total spent, refunded, last purchase | Period only; walk-in sales aren't linked to a customer, so they aren't listed |
| Product sales | `/reports/products` | Product (and variant), quantity sold, returned, **net sold** (qty − returned), number of sales, revenue (after discounts, before tax). Totals row at the bottom. | **Category**, period |
| Daily report | `/reports/daily` | Orders, avg order, gross sales, discounts, tax, delivery, returns, net revenue. Payment breakdown by method. | `?date=Y-m-d` (defaults today). `?export=csv` for spreadsheet. |

Tables show a totals row and paginate at 25 rows. With no data they show an empty state.

## Export & print
- **CSV:** the **CSV** button downloads the current report with its filters (`?export=csv`). It streams UTF-8 with a BOM for Excel, and neutralises text cells that start with `= + - @` (formula injection).
- **Print / PDF:** the **Print** button uses the browser print dialog; choose "Save as PDF" for a PDF. The sidebar, header and filters are hidden in print. No PDF package was added.

## Dashboard
The dashboard now uses the same real data:
- Cards: Today's Sales, Orders, Purchases, Returns, Expenses and Total Customers.
- A 7/30-day sales chart.
- Recent sales (linked to receipts) and recent purchases.
- Top products over the last 30 days.

Widgets can be switched off in **Settings → General Settings → Dashboard**; each still needs its permission. See [dashboard.md](dashboard.md).

## Code map
`App\Services\ReportService`, `App\Support\DateRange`, `App\Support\Csv`, `App\Http\Controllers\ReportController`, `resources/views/reports/*`, tests in `tests/Feature/Reports/ReportTest.php`.
