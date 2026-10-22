<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DashboardService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Number;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Date ranges (in days) offered by the Sales Overview.
     */
    public const RANGES = [1, 7, 30];

    /**
     * Show the dashboard. A widget is built only when it is switched on (Settings → General →
     * Dashboard) and the user may see it; a null widget is hidden, an empty collection renders
     * its empty state.
     */
    public function __invoke(Request $request, DashboardService $dashboard): View
    {
        /** @var User $user */
        $user = $request->user();
        $on = fn (string $widget, string $permission) => in_array($widget, (array) settings('dashboard.widgets'), true) && $user->can($permission);

        $range = in_array($request->integer('range'), self::RANGES, true)
            ? $request->integer('range')
            : 7;

        $topRange = in_array($request->integer('top_range'), self::RANGES, true)
            ? $request->integer('top_range')
            : 1;

        // Parse requested date; reject future dates silently by clamping to today.
        $date = null;
        if ($request->filled('date')) {
            try {
                $parsed = CarbonImmutable::parse($request->string('date')->toString());
                $date = $parsed->isAfter(CarbonImmutable::today()) ? CarbonImmutable::today() : $parsed->startOfDay();
            } catch (\Exception) {
                $date = null;
            }
        }
        $date ??= CarbonImmutable::today();

        return view('dashboard', [
            'stats' => $this->stats($dashboard, $on, $date),
            'range' => $range,
            'ranges' => self::RANGES,
            'date' => $date,
            'salesOverview' => $on('sales_chart', 'sales.view') ? $dashboard->salesOverview($range, $date) : null,
            'recentOrders' => $on('recent_sales', 'sales.view') ? $dashboard->recentOrders() : null,
            'recentPurchases' => $on('recent_purchases', 'purchases.view') ? $dashboard->recentPurchases() : null,
            'topRange' => $topRange,
            'topProducts' => $on('top_products', 'products.view') ? $dashboard->topSellingProducts(5, $topRange, $date) : null,
            'dailyReport' => $on('daily_report', 'reports.view') ? $dashboard->dailyReport($date) : null,
        ]);
    }

    /**
     * Statistic cards that are switched on and that the user is permitted to see.
     *
     * @param  callable(string, string): bool  $on
     * @return list<array{label: string, value: string, description: string, icon: string, tone: string}>
     */
    private function stats(DashboardService $dashboard, callable $on, CarbonImmutable $date): array
    {
        $dayLabel = $date->isToday() ? __('Today') : ($date->isYesterday() ? __('Yesterday') : $date->isoFormat('ddd D MMM'));

        $cards = [
            ['widget' => 'sales', 'permission' => 'sales.view', 'label' => __(':day\'s Sales', ['day' => $dayLabel]), 'value' => fn () => Money::format($dashboard->todaySales($date)), 'description' => $date->isoFormat('dddd, D MMMM YYYY'), 'icon' => 'banknote', 'tone' => 'primary'],
            ['widget' => 'sales', 'permission' => 'sales.view', 'label' => __(':day\'s Orders', ['day' => $dayLabel]), 'value' => fn () => Number::format($dashboard->todayOrders($date)), 'description' => $date->isoFormat('dddd, D MMMM YYYY'), 'icon' => 'shopping-cart', 'tone' => 'info'],
            ['widget' => 'purchases', 'permission' => 'purchases.view', 'label' => __(':day\'s Purchases', ['day' => $dayLabel]), 'value' => fn () => Money::format($dashboard->todayPurchases($date)), 'description' => $date->isoFormat('dddd, D MMMM YYYY'), 'icon' => 'purchase-orders', 'tone' => 'warning'],
            ['widget' => 'returns', 'permission' => 'returns.view', 'label' => __(':day\'s Returns', ['day' => $dayLabel]), 'value' => fn () => Money::format($dashboard->todayReturns($date)), 'description' => $date->isoFormat('dddd, D MMMM YYYY'), 'icon' => 'history', 'tone' => 'danger'],
            ['widget' => 'expenses', 'permission' => 'expenses.view', 'label' => __(':day\'s Expenses', ['day' => $dayLabel]), 'value' => fn () => Money::format($dashboard->todayExpenses($date)), 'description' => $date->isoFormat('dddd, D MMMM YYYY'), 'icon' => 'banknote', 'tone' => 'warning'],
            ['widget' => 'customers', 'permission' => 'customers.view', 'label' => __('Total Customers'), 'value' => fn () => Number::format($dashboard->totalCustomers()), 'description' => __('Customers on record'), 'icon' => 'customers', 'tone' => 'success'],
        ];

        return collect($cards)
            ->filter(fn (array $card) => $on($card['widget'], $card['permission']))
            ->map(fn (array $card) => [
                'label' => $card['label'],
                'value' => ($card['value'])(),
                'description' => $card['description'],
                'icon' => $card['icon'],
                'tone' => $card['tone'],
            ])
            ->values()
            ->all();
    }
}
