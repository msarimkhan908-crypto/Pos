# Dashboard

The dashboard (`/dashboard`, route name `dashboard`) is the landing page after login. **Since Day 6 every figure comes from recorded data** (sales, purchases, returns, expenses, customers). No demo records are created.

Which widgets appear is set in **Settings → General Settings → Dashboard**. Each widget also needs the user's permission. See [reports.md](reports.md) for how figures are counted.

## Access

- Middleware: `auth` and `verified`. Guests are redirected to `/login`.
- `verified` currently has no effect, because `App\Models\User` does not implement `MustVerifyEmail` (Breeze default).

## Widgets

A widget is shown only when it is switched on **and** the user has its permission. A hidden widget's data is not loaded.

| Widget | Setting key | Permission | Figure |
|---|---|---|---|
| Today's Sales, Today's Orders | `sales` | sales.view | Completed sales today (total and count) |
| Today's Purchases | `purchases` | purchases.view | Completed purchases dated today |
| Today's Returns | `returns` | returns.view | Refunds paid today (completed returns) |
| Today's Expenses | `expenses` | expenses.view | Expenses dated today |
| Total Customers | `customers` | customers.view | Customers on record |
| Sales Overview (7/30-day chart) | `sales_chart` | sales.view | Completed sales per day |
| Recent Sales | `recent_sales` | sales.view | Latest 5 completed sales, linked to their receipts; "View all" opens the Sales report |
| Recent Purchases | `recent_purchases` | purchases.view | Latest 5 purchases |
| Top Selling Products | `top_products` | products.view | Last 30 days by quantity, with net sold (quantity minus returns) |
| Daily Report | `daily_report` | reports.view | End-of-day summary for the selected date: order count, gross sales, discounts, tax, delivery, returns, net revenue, and payment breakdown. Links to `/reports/daily` for print/CSV. |

"Today" follows the business timezone (**Settings → General**). Money uses the currency and symbol from the same page.

A user with no role, or with every visible widget switched off, sees a "Nothing to show yet" message.

## How it's built

```
app/Http/Controllers/DashboardController.php  widget switches + permissions, builds view data
app/Services/DashboardService.php             queries (real data)
resources/views/dashboard.blade.php           page layout
resources/views/components/dashboard/         stat-card, sales-chart (HTML/CSS, no chart library)
```

The controller passes `null` for a widget that is off or not permitted, and the view hides it. An empty collection renders the widget's empty state.

| Service method | Returns |
|---|---|
| `todaySales()`, `todayPurchases()`, `todayReturns()`, `todayExpenses()` | `float` |
| `todayOrders()`, `totalCustomers()` | `int` |
| `salesOverview(int $days)` | `['days' => [['date', 'total', 'orders'], …], 'total', 'orders', 'average_order']`, one entry per day, oldest first |
| `recentOrders()` | items with `number`, `customer`, `total`, `status`, `created_at`, `url` |
| `recentPurchases()` | items with `number`, `supplier`, `total`, `status`, `date`, `url` |
| `topSellingProducts()` | items with `name`, `quantity`, `returned`, `net_qty`, `revenue` |
| `dailyReport(CarbonImmutable $date)` | `date`, `sales_count`, `sales_total`, `subtotal`, `tax`, `discount`, `delivery`, `returns_count`, `returns_total`, `net_revenue`, `avg_order`, `payments` (keyed by payment method) |

## Sales Overview
- Range switcher: `?range=7` (default) or `?range=30`. Any other value falls back to 7.
- Bars show a tooltip on hover or keyboard focus, and a screen-reader table carries the same data.
- With no sales in the range, the chart shows "No sales recorded in this period".

## Tests

```powershell
php artisan test --filter=Dashboard
php artisan test --filter="the dashboard shows real figures"
```

`DashboardTest` covers access, permission-based widgets, empty states, range validation and rendering with a mocked service. `ReportTest` checks the real figures. `SettingsTest` checks switching widgets off.
