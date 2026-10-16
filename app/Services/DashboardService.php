<?php

namespace App\Services;

use App\Enums\PurchaseStatus;
use App\Enums\ReturnStatus;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleReturn;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data for the dashboard, from recorded sales, purchases, returns, expenses and customers.
 * "Today" follows the business timezone (Settings → General).
 */
class DashboardService
{
    /**
     * Gross total of completed sales on the given date (defaults to today).
     */
    public function todaySales(?CarbonImmutable $date = null): float
    {
        $date ??= CarbonImmutable::today();

        return (float) $this->completedSalesBetween($date->startOfDay(), $date->endOfDay())->sum('total');
    }

    /**
     * Number of sales completed on the given date (defaults to today).
     */
    public function todayOrders(?CarbonImmutable $date = null): int
    {
        $date ??= CarbonImmutable::today();

        return $this->completedSalesBetween($date->startOfDay(), $date->endOfDay())->count();
    }

    /**
     * Total of completed purchases dated on the given date (defaults to today).
     */
    public function todayPurchases(?CarbonImmutable $date = null): float
    {
        $date ??= CarbonImmutable::today();

        return (float) Purchase::query()->where('status', PurchaseStatus::Completed)->whereDate('purchase_date', $date)->sum('total');
    }

    /**
     * Refunds paid out on the given date (defaults to today).
     */
    public function todayReturns(?CarbonImmutable $date = null): float
    {
        $date ??= CarbonImmutable::today();

        return (float) SaleReturn::query()->where('status', ReturnStatus::Completed)->whereDate('return_date', $date)->sum('refund_amount');
    }

    /**
     * Expenses on the given date (defaults to today).
     */
    public function todayExpenses(?CarbonImmutable $date = null): float
    {
        $date ??= CarbonImmutable::today();

        return (float) Expense::query()->whereDate('expense_date', $date)->sum('amount');
    }

    /**
     * Number of customers on record.
     */
    public function totalCustomers(): int
    {
        return Customer::count();
    }

    /**
     * Daily sales for the last $days days, oldest first, ending today.
     *
     * @return array{
     *     days: list<array{date: CarbonImmutable, total: float, orders: int}>,
     *     total: float,
     *     orders: int,
     *     average_order: float,
     * }
     */
    public function salesOverview(int $days = 7, ?CarbonImmutable $date = null): array
    {
        $today = $date ?? CarbonImmutable::today();
        $from = $today->subDays($days - 1);

        $rows = $this->completedSalesBetween($from, $today->endOfDay())
            ->toBase()
            ->selectRaw('DATE(completed_at) as day, SUM(total) as total, COUNT(*) as orders')
            ->groupBy(DB::raw('DATE(completed_at)'))
            ->get()
            ->keyBy('day');

        $series = collect(range($days - 1, 0))
            ->map(function (int $daysAgo) use ($today, $rows) {
                $date = $today->subDays($daysAgo);
                $row = $rows->get($date->toDateString());

                return ['date' => $date, 'total' => (float) ($row->total ?? 0), 'orders' => (int) ($row->orders ?? 0)];
            })
            ->all();

        $total = array_sum(array_column($series, 'total'));
        $orders = array_sum(array_column($series, 'orders'));

        return [
            'days' => $series,
            'total' => $total,
            'orders' => $orders,
            'average_order' => $orders > 0 ? $total / $orders : 0.0,
        ];
    }

    /**
     * Most recent completed sales. Each item: number, customer, total, status, created_at, url.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function recentOrders(int $limit = 5): Collection
    {
        return Sale::query()->completed()->with('customer:id,name')->latest('completed_at')->latest('id')->limit($limit)->get()
            ->map(fn (Sale $sale) => [
                'number' => $sale->invoice_number,
                'customer' => $sale->customerLabel(),
                'total' => (float) $sale->total,
                'status' => $sale->return_status->value === 'none' ? 'completed' : $sale->return_status->label(),
                'created_at' => $sale->completed_at,
                'url' => route('sales.receipt', $sale),
            ]);
    }

    /**
     * Most recent purchases. Each item: number, supplier, total, status, date, url.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function recentPurchases(int $limit = 5): Collection
    {
        return Purchase::query()->with('supplier:id,name')->latest('purchase_date')->latest('id')->limit($limit)->get()
            ->map(fn (Purchase $purchase) => [
                'number' => $purchase->purchase_number,
                'supplier' => $purchase->supplier?->name,
                'total' => (float) $purchase->total,
                'status' => $purchase->status,
                'date' => $purchase->purchase_date,
                'url' => route('purchases.show', $purchase),
            ]);
    }

    /**
     * Best selling products over the last $days days ending on $date. Each item: name, quantity, revenue.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function topSellingProducts(int $limit = 5, int $days = 30, ?CarbonImmutable $date = null): Collection
    {
        $end = ($date ?? CarbonImmutable::today())->endOfDay();
        $from = $end->subDays($days - 1)->startOfDay();

        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', SaleStatus::Completed->value)
            ->whereBetween('sales.completed_at', [$from, $end])
            ->groupBy('sale_items.product_id', 'sale_items.variant_id')
            ->selectRaw('MAX(sale_items.product_name) as product_name, MAX(sale_items.variant_name) as variant_name,
                SUM(sale_items.quantity) as quantity,
                COALESCE(SUM(sale_items.returned_quantity),0) as returned,
                SUM(sale_items.total - sale_items.order_discount) as revenue')
            ->orderByDesc('quantity')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->variant_name ? "{$row->product_name} ({$row->variant_name})" : $row->product_name,
                'quantity' => (int) $row->quantity,
                'returned' => (int) $row->returned,
                'net_qty' => max(0, (int) $row->quantity - (int) $row->returned),
                'revenue' => (float) $row->revenue,
            ]);
    }

    /**
     * Full end-of-day summary for a business date, used by the dashboard widget and the daily report.
     *
     * @return array{
     *     date: CarbonImmutable,
     *     sales_count: int,
     *     sales_total: float,
     *     subtotal: float,
     *     tax: float,
     *     discount: float,
     *     delivery: float,
     *     returns_count: int,
     *     returns_total: float,
     *     net_revenue: float,
     *     avg_order: float,
     *     payments: array<string, float>,
     * }
     */
    public function dailyReport(CarbonImmutable $date): array
    {
        $dateStr = $date->toDateString();

        $totals = Sale::query()
            ->where('status', SaleStatus::Completed)
            ->whereDate('business_date', $dateStr)
            ->toBase()
            ->selectRaw('
                COUNT(*)               AS sales_count,
                COALESCE(SUM(total),0)         AS sales_total,
                COALESCE(SUM(subtotal),0)      AS subtotal,
                COALESCE(SUM(tax),0)           AS tax,
                COALESCE(SUM(discount),0)      AS discount,
                COALESCE(SUM(delivery_fee),0)  AS delivery
            ')
            ->first();

        // Payment method breakdown: aggregate via sale_payments.
        $payments = DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.status', SaleStatus::Completed->value)
            ->whereDate('sales.business_date', $dateStr)
            ->selectRaw('sale_payments.payment_method, COALESCE(SUM(sale_payments.amount),0) AS total')
            ->groupBy('sale_payments.payment_method')
            ->pluck('total', 'payment_method')
            ->map(fn ($v) => (float) $v)
            ->all();

        $returnsRow = SaleReturn::query()
            ->where('status', ReturnStatus::Completed)
            ->whereDate('return_date', $dateStr)
            ->toBase()
            ->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(refund_amount),0) AS total')
            ->first();

        $salesTotal = (float) ($totals->sales_total ?? 0);
        $returnsTotal = (float) ($returnsRow->total ?? 0);
        $salesCount = (int) ($totals->sales_count ?? 0);

        return [
            'date' => $date,
            'sales_count' => $salesCount,
            'sales_total' => $salesTotal,
            'subtotal' => (float) ($totals->subtotal ?? 0),
            'tax' => (float) ($totals->tax ?? 0),
            'discount' => (float) ($totals->discount ?? 0),
            'delivery' => (float) ($totals->delivery ?? 0),
            'returns_count' => (int) ($returnsRow->cnt ?? 0),
            'returns_total' => $returnsTotal,
            'net_revenue' => bcsub((string) $salesTotal, (string) $returnsTotal, 2) * 1.0,
            'avg_order' => $salesCount > 0 ? round($salesTotal / $salesCount, 2) : 0.0,
            'payments' => $payments,
        ];
    }

    /**
     * @return Builder<Sale>
     */
    private function completedSalesBetween(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Sale::query()->where('status', SaleStatus::Completed)->whereBetween('completed_at', [$from, $to]);
    }
}
