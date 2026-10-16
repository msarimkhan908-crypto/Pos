<?php

namespace App\Services;

use App\Enums\PurchaseStatus;
use App\Enums\ReturnStatus;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Report queries over real data only. Money totals are summed by the database.
 *
 * - Sales: completed sales, by completed_at.
 * - Returns: completed returns, by return_date (refunds actually paid out).
 * - Purchases: completed purchases, by purchase_date (drafts and cancelled excluded).
 * - Expenses: by expense_date.
 * - Net sales = sales total − completed refunds.
 */
class ReportService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(DateRange $range): array
    {
        $sales = $this->sales($range)->toBase()
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total, COALESCE(SUM(discount), 0) as discount, COALESCE(SUM(tax), 0) as tax')
            ->first();
        $returns = $this->returns($range)->where('status', ReturnStatus::Completed)->toBase()
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(refund_amount), 0) as total')->first();
        $purchases = $this->purchases($range)->toBase()->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')->first();
        $expenses = $this->expenses($range)->toBase()->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as total')->first();

        $salesTotal = bcadd((string) $sales->total, '0', 2);
        $returnsTotal = bcadd((string) $returns->total, '0', 2);

        return [
            'sales_total' => $salesTotal,
            'sales_count' => (int) $sales->count,
            'discount_total' => bcadd((string) $sales->discount, '0', 2),
            'tax_total' => bcadd((string) $sales->tax, '0', 2),
            'returns_total' => $returnsTotal,
            'returns_count' => (int) $returns->count,
            'net_sales' => bcsub($salesTotal, $returnsTotal, 2),
            'purchases_total' => bcadd((string) $purchases->total, '0', 2),
            'purchases_count' => (int) $purchases->count,
            'expenses_total' => bcadd((string) $expenses->total, '0', 2),
            'expenses_count' => (int) $expenses->count,
            'customers_served' => (int) $this->sales($range)->whereNotNull('customer_id')->distinct()->count('customer_id'),
            'customers_total' => Customer::count(),
            'payments' => SalePayment::query()
                ->whereIn('sale_id', $this->sales($range)->select('id'))
                ->groupBy('payment_method')
                ->selectRaw('payment_method, COALESCE(SUM(amount), 0) as total')
                ->pluck('total', 'payment_method')
                ->map(fn ($total) => bcadd((string) $total, '0', 2))
                ->sortBy(fn ($total, string $method) => array_search($method, ['cash', 'card', 'mixed'], true))
                ->all(),
            'expenses_by_category' => $this->expenses($range)
                ->join('expense_categories', 'expense_categories.id', '=', 'expenses.category_id')
                ->groupBy('expense_categories.name')
                ->orderByRaw('SUM(expenses.amount) DESC')
                ->selectRaw('expense_categories.name as name, SUM(expenses.amount) as total')
                ->limit(8)
                ->pluck('total', 'name')
                ->map(fn ($total) => bcadd((string) $total, '0', 2))
                ->all(),
        ];
    }

    /**
     * @return Builder<Sale>
     */
    public function sales(DateRange $range, array $filters = []): Builder
    {
        return Sale::query()
            ->where('sales.status', SaleStatus::Completed)
            ->whereBetween('sales.completed_at', [$range->from, $range->to])
            ->when($filters['customer'] ?? null, fn (Builder $q, $id) => $q->where('sales.customer_id', $id))
            ->when($filters['cashier'] ?? null, fn (Builder $q, $id) => $q->where('sales.user_id', $id))
            ->when($filters['method'] ?? null, fn (Builder $q, $m) => $q->where('sales.payment_method', $m))
            ->when($filters['returns'] ?? null, fn (Builder $q, $s) => $q->where('sales.return_status', $s));
    }

    /**
     * @return Builder<Purchase>
     */
    public function purchases(DateRange $range, array $filters = []): Builder
    {
        $status = $filters['status'] ?? PurchaseStatus::Completed->value;

        return Purchase::query()
            ->whereDate('purchase_date', '>=', $range->from->toDateString())
            ->whereDate('purchase_date', '<=', $range->to->toDateString())
            ->when($status !== 'all', fn (Builder $q) => $q->where('status', $status))
            ->when($filters['supplier'] ?? null, fn (Builder $q, $id) => $q->where('supplier_id', $id));
    }

    /**
     * @return Builder<SaleReturn>
     */
    public function returns(DateRange $range, array $filters = []): Builder
    {
        return SaleReturn::query()
            ->whereDate('return_date', '>=', $range->from->toDateString())
            ->whereDate('return_date', '<=', $range->to->toDateString())
            ->when($filters['status'] ?? null, fn (Builder $q, $s) => $q->where('status', $s))
            ->when($filters['method'] ?? null, fn (Builder $q, $m) => $q->where('refund_method', $m));
    }

    /**
     * @return Builder<Expense>
     */
    public function expenses(DateRange $range, array $filters = []): Builder
    {
        return Expense::query()
            ->whereDate('expenses.expense_date', '>=', $range->from->toDateString())
            ->whereDate('expenses.expense_date', '<=', $range->to->toDateString())
            ->when($filters['category'] ?? null, fn (Builder $q, $id) => $q->where('expenses.category_id', $id))
            ->when($filters['method'] ?? null, fn (Builder $q, $m) => $q->where('expenses.payment_method', $m));
    }

    /**
     * Registered customers who bought in the period: purchases, total spent, last purchase.
     *
     * @return Builder<Customer>
     */
    public function customers(DateRange $range): Builder
    {
        $sales = $this->sales($range)
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) as purchases, SUM(total) as spent, SUM(returned_amount) as refunded, MAX(completed_at) as last_purchase_at');

        return Customer::query()
            ->joinSub($sales, 'stats', 'stats.customer_id', '=', 'customers.id')
            ->select('customers.id', 'customers.name', 'customers.phone', 'stats.purchases', 'stats.spent', 'stats.refunded', 'stats.last_purchase_at')
            ->orderByDesc('stats.spent')
            ->orderBy('customers.name');
    }

    /**
     * Products sold in the period: quantity, returned quantity, number of sales, revenue
     * (after line and order discounts, before tax).
     */
    /**
     * Grand totals (quantity, returned, revenue) for the product report — runs without GROUP BY.
     *
     * @param  array<string, mixed>  $filters
     * @return object{qty: float, returned: float, revenue: float}
     */
    public function productTotals(DateRange $range, array $filters = []): object
    {
        $query = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', SaleStatus::Completed->value)
            ->whereBetween('sales.completed_at', [$range->from, $range->to])
            ->selectRaw('COALESCE(SUM(sale_items.quantity),0) as qty, COALESCE(SUM(sale_items.returned_quantity),0) as returned, COALESCE(SUM(sale_items.total - sale_items.order_discount),0) as revenue');

        if (! empty($filters['category'])) {
            $query->join('products as p_cat', 'p_cat.id', '=', 'sale_items.product_id')
                ->where('p_cat.category_id', (int) $filters['category']);
        }

        return $query->first();
    }

    /**
     * @param  array<string, mixed>  $filters  Optional: category (int)
     */
    public function products(DateRange $range, array $filters = []): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', SaleStatus::Completed->value)
            ->whereBetween('sales.completed_at', [$range->from, $range->to])
            ->groupBy('sale_items.product_id', 'sale_items.variant_id')
            ->selectRaw('sale_items.product_id, sale_items.variant_id, MAX(sale_items.product_name) as product_name, MAX(sale_items.variant_name) as variant_name,
                SUM(sale_items.quantity) as quantity, COALESCE(SUM(sale_items.returned_quantity),0) as returned,
                COUNT(DISTINCT sale_items.sale_id) as sales_count, SUM(sale_items.total - sale_items.order_discount) as revenue')
            ->orderByDesc('quantity')
            ->orderBy('product_name');

        if (! empty($filters['category'])) {
            $query->join('products as p_cat', 'p_cat.id', '=', 'sale_items.product_id')
                ->where('p_cat.category_id', (int) $filters['category']);
        }

        return $query;
    }
}
