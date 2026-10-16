<?php

namespace App\Http\Controllers;

use App\Enums\MoneyMethod;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Enums\ReturnStatus;
use App\Enums\SaleReturnStatus;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ReportService;
use App\Support\Csv;
use App\Support\DateRange;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports over real data (reports.view). Every report takes a period (?period=today|
 * yesterday|this_week|this_month|last_month|custom&from=&to=) and ?export=csv.
 */
class ReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:reports.view')];
    }

    public function index(Request $request, ReportService $reports): View
    {
        $range = DateRange::fromRequest($request);

        return view('reports.index', ['range' => $range, 'summary' => $reports->summary($range)]);
    }

    public function sales(Request $request, ReportService $reports): View|StreamedResponse
    {
        $range = DateRange::fromRequest($request);
        $filters = $request->validate([
            'customer' => ['nullable', 'integer'],
            'cashier' => ['nullable', 'integer'],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'returns' => ['nullable', Rule::enum(SaleReturnStatus::class)],
        ]);
        $query = $reports->sales($range, $filters)->with(['customer:id,name', 'user:id,name']);

        if ($request->query('export') === 'csv') {
            return Csv::download('sales-'.$range->from->toDateString().'.csv',
                ['Invoice', 'Date', 'Customer', 'Cashier', 'Order type', 'Subtotal', 'Discount', 'Tax', 'Delivery fee', 'Total', 'Refunded', 'Payment', 'Returns'],
                $query->orderBy('completed_at')->lazy()->map(fn (Sale $s) => [
                    $s->invoice_number, Format::dateTime($s->completed_at), $s->customerLabel(), $s->user?->name, $s->order_type->label(),
                    (string) $s->subtotal, (string) $s->discount, (string) $s->tax, (string) $s->delivery_fee, (string) $s->total,
                    (string) $s->returned_amount, $s->payment_method?->label(), $s->return_status->label(),
                ]));
        }

        return view('reports.sales', [
            'range' => $range,
            'filters' => $filters,
            'totals' => (clone $query)->toBase()->selectRaw('COUNT(*) as count, COALESCE(SUM(subtotal),0) as subtotal, COALESCE(SUM(discount),0) as discount, COALESCE(SUM(tax),0) as tax, COALESCE(SUM(total),0) as total, COALESCE(SUM(returned_amount),0) as refunded')->first(),
            'sales' => $query->latest('completed_at')->latest('id')->paginate(25)->withQueryString(),
            'customers' => Customer::query()->whereHas('sales')->orderBy('name')->pluck('name', 'id'),
            'cashiers' => User::query()->whereIn('id', Sale::query()->select('user_id')->whereNotNull('user_id'))->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function purchases(Request $request, ReportService $reports): View|StreamedResponse
    {
        $range = DateRange::fromRequest($request);
        $filters = $request->validate([
            'supplier' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['all', ...array_column(PurchaseStatus::cases(), 'value')])],
        ]);
        $filters['status'] ??= PurchaseStatus::Completed->value;
        $query = $reports->purchases($range, $filters)->with('supplier:id,name,company_name');

        if ($request->query('export') === 'csv') {
            return Csv::download('purchases-'.$range->from->toDateString().'.csv',
                ['Purchase', 'Date', 'Supplier', 'Subtotal', 'Discount', 'Tax', 'Total', 'Status', 'Payment status'],
                $query->orderBy('purchase_date')->lazy()->map(fn (Purchase $p) => [
                    $p->purchase_number, Format::date($p->purchase_date), $p->supplier?->name, (string) $p->subtotal, (string) $p->discount,
                    (string) $p->tax, (string) $p->total, $p->status->label(), $p->payment_status->label(),
                ]));
        }

        return view('reports.purchases', [
            'range' => $range,
            'filters' => $filters,
            'totals' => (clone $query)->toBase()->selectRaw('COUNT(*) as count, COALESCE(SUM(subtotal),0) as subtotal, COALESCE(SUM(discount),0) as discount, COALESCE(SUM(tax),0) as tax, COALESCE(SUM(total),0) as total')->first(),
            'purchases' => $query->latest('purchase_date')->latest('id')->paginate(25)->withQueryString(),
            'suppliers' => Supplier::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function returns(Request $request, ReportService $reports): View|StreamedResponse
    {
        $range = DateRange::fromRequest($request);
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ReturnStatus::class)],
            'method' => ['nullable', Rule::enum(MoneyMethod::class)],
        ]);
        $query = $reports->returns($range, $filters)->with(['sale:id,invoice_number,customer_name,customer_id', 'sale.customer:id,name', 'customer:id,name']);

        if ($request->query('export') === 'csv') {
            return Csv::download('returns-'.$range->from->toDateString().'.csv',
                ['Return', 'Sale', 'Date', 'Customer', 'Refund', 'Refund method', 'Reason', 'Status'],
                $query->orderBy('return_date')->lazy()->map(fn (SaleReturn $r) => [
                    $r->return_number, $r->sale?->invoice_number, Format::date($r->return_date), $r->customerLabel(),
                    (string) $r->refund_amount, $r->refund_method->label(), $r->reason->label(), $r->status->label(),
                ]));
        }

        return view('reports.returns', [
            'range' => $range,
            'filters' => $filters,
            'totals' => (clone $query)->toBase()->selectRaw('COUNT(*) as count, COALESCE(SUM(refund_amount),0) as total')->first(),
            'returns' => $query->latest('return_date')->latest('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function expenses(Request $request, ReportService $reports): View|StreamedResponse
    {
        $range = DateRange::fromRequest($request);
        $filters = $request->validate([
            'category' => ['nullable', 'integer'],
            'method' => ['nullable', Rule::enum(MoneyMethod::class)],
        ]);
        $query = $reports->expenses($range, $filters)->with('category:id,name');

        if ($request->query('export') === 'csv') {
            return Csv::download('expenses-'.$range->from->toDateString().'.csv',
                ['Expense', 'Category', 'Date', 'Amount', 'Payment method', 'Reference', 'Description'],
                $query->orderBy('expense_date')->lazy()->map(fn (Expense $e) => [
                    $e->expense_number, $e->category?->name, Format::date($e->expense_date), (string) $e->amount,
                    $e->payment_method->label(), $e->reference, $e->description,
                ]));
        }

        return view('reports.expenses', [
            'range' => $range,
            'filters' => $filters,
            'totals' => (clone $query)->toBase()->selectRaw('COUNT(*) as count, COALESCE(SUM(amount),0) as total')->first(),
            'expenses' => $query->latest('expense_date')->latest('id')->paginate(25)->withQueryString(),
            'categories' => ExpenseCategory::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function customers(Request $request, ReportService $reports): View|StreamedResponse
    {
        $range = DateRange::fromRequest($request);
        $query = $reports->customers($range);

        if ($request->query('export') === 'csv') {
            return Csv::download('customers-'.$range->from->toDateString().'.csv',
                ['Customer', 'Phone', 'Purchases', 'Total spent', 'Refunded', 'Last purchase'],
                $query->cursor()->map(fn (Customer $c) => [
                    $c->name, $c->phone, (int) $c->purchases, bcadd((string) $c->spent, '0', 2), bcadd((string) $c->refunded, '0', 2),
                    Format::dateTime(CarbonImmutable::parse($c->last_purchase_at)),
                ]));
        }

        return view('reports.customers', [
            'range' => $range,
            'customers' => $query->paginate(25)->withQueryString(),
        ]);
    }

    public function products(Request $request, ReportService $reports): View|StreamedResponse
    {
        $range = DateRange::fromRequest($request);
        $filters = $request->validate([
            'category' => ['nullable', 'integer'],
        ]);
        $query = $reports->products($range, $filters);

        if ($request->query('export') === 'csv') {
            return Csv::download('product-sales-'.$range->from->toDateString().'.csv',
                ['Product', 'Variant', 'Quantity sold', 'Returned', 'Net sold', 'Sales', 'Revenue'],
                $query->cursor()->map(fn ($p) => [
                    $p->product_name, $p->variant_name, (int) $p->quantity, (int) $p->returned,
                    max(0, (int) $p->quantity - (int) $p->returned), (int) $p->sales_count, bcadd((string) $p->revenue, '0', 2),
                ]));
        }

        $totals = $reports->productTotals($range, $filters);

        return view('reports.products', [
            'range' => $range,
            'filters' => $filters,
            'products' => $query->paginate(25)->withQueryString(),
            'totals' => $totals,
            'categories' => \App\Models\Category::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /**
     * End-of-day summary for a single business date.
     * ?date=Y-m-d  (defaults to today)
     * ?export=csv  streams a one-row CSV
     * ?print=1     bare print view (no app chrome)
     */
    public function daily(Request $request, \App\Services\DashboardService $dashboard): View|StreamedResponse
    {
        $date = CarbonImmutable::today();
        if ($request->filled('date')) {
            try {
                $parsed = CarbonImmutable::parse($request->string('date')->toString());
                $date = $parsed->isAfter(CarbonImmutable::today()) ? CarbonImmutable::today() : $parsed->startOfDay();
            } catch (\Exception) {
                $date = CarbonImmutable::today();
            }
        }

        $report = $dashboard->dailyReport($date);

        if ($request->query('export') === 'csv') {
            $rows = collect([
                ['Date', $date->toDateString()],
                ['Orders', $report['sales_count']],
                ['Gross Sales', bcadd((string) $report['sales_total'], '0', 2)],
                ['Subtotal', bcadd((string) $report['subtotal'], '0', 2)],
                ['Discounts', bcadd((string) $report['discount'], '0', 2)],
                ['Tax', bcadd((string) $report['tax'], '0', 2)],
                ['Delivery', bcadd((string) $report['delivery'], '0', 2)],
                ['Returns', bcadd((string) $report['returns_total'], '0', 2)],
                ['Net Revenue', bcadd((string) $report['net_revenue'], '0', 2)],
                ['Avg. Order', bcadd((string) $report['avg_order'], '0', 2)],
            ])->concat(collect($report['payments'])->map(fn ($v, $k) => [ucfirst($k).' (payment)', bcadd((string) $v, '0', 2)])->values());

            return Csv::download('daily-report-'.$date->toDateString().'.csv', ['Metric', 'Value'], $rows);
        }

        $view = $request->boolean('print') ? 'reports.daily-print' : 'reports.daily';

        return view($view, ['report' => $report, 'date' => $date]);
    }
}
