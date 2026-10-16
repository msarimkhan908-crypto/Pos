<?php

use App\Models\User;
use App\Services\DashboardService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function dashboardUser(?string $role = null): User
{
    $user = User::factory()->create(['name' => 'Dana Owner']);

    return $role ? $user->assignRole($role) : $user;
}

test('guests are redirected to login', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
});

test('super admin sees every widget with zero values and empty states', function () {
    $this->actingAs(dashboardUser('Super Admin'))
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Welcome back, Dana Owner')
        ->assertSee(now()->isoFormat('dddd, D MMMM YYYY'))
        ->assertSeeInOrder(["Today's Sales", '$0', "Today's Orders", 'Total Customers'])
        ->assertSee('Sales Overview')
        ->assertSee('No sales recorded in this period')
        ->assertSee('No sales yet')
        ->assertSee('No product sales yet')
        ->assertDontSee('Low Stock');
});

test('widgets are limited by permission', function () {
    $this->actingAs(dashboardUser('Kitchen Staff'))
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Top Selling Products')
        ->assertDontSee("Today's Sales")
        ->assertDontSee('Sales Overview')
        ->assertDontSee('Recent Sales');

    $this->actingAs(dashboardUser('Manager'))
        ->get('/dashboard')
        ->assertSee("Today's Sales")
        ->assertSee('Total Customers')
        ->assertSee('Recent Sales');
});

test('user without a role sees an empty dashboard', function () {
    $this->actingAs(dashboardUser())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('No role has been assigned')
        ->assertDontSee('Sales Overview');
});

test('sales overview range can be switched and is validated', function (mixed $range, int $expected) {
    $this->actingAs(dashboardUser('Manager'))
        ->get('/dashboard?range='.$range)
        ->assertOk()
        ->assertViewHas('range', $expected)
        ->assertViewHas('salesOverview', fn (array $overview) => count($overview['days']) === $expected);
})->with([
    'default' => ['', 7],
    '30 days' => [30, 30],
    'invalid' => [999, 7],
    'garbage' => ['abc', 7],
]);

test('sales overview covers consecutive days ending today', function () {
    $days = app(DashboardService::class)->salesOverview(7)['days'];

    expect($days)->toHaveCount(7)
        ->and($days[6]['date']->isToday())->toBeTrue()
        ->and($days[0]['date']->toDateString())->toBe(today()->subDays(6)->toDateString());
});

test('dashboard renders real data when the service provides it', function () {
    $this->mock(DashboardService::class, function ($mock) {
        $mock->shouldReceive('todaySales')->andReturn(1250.5);
        $mock->shouldReceive('todayOrders')->andReturn(12);
        $mock->shouldReceive('totalCustomers')->andReturn(340);
        $mock->shouldReceive('todayPurchases')->andReturn(0.0);
        $mock->shouldReceive('todayReturns')->andReturn(0.0);
        $mock->shouldReceive('todayExpenses')->andReturn(0.0);
        $mock->shouldReceive('recentPurchases')->andReturn(collect());
        $mock->shouldReceive('salesOverview')->andReturn([
            'days' => [['date' => now()->toImmutable(), 'total' => 1250.5, 'orders' => 12]],
            'total' => 1250.5, 'orders' => 12, 'average_order' => 104.21,
        ]);
        $mock->shouldReceive('recentOrders')->andReturn(collect([
            ['number' => 'A1001', 'customer' => 'Ali Khan', 'total' => 45.0, 'status' => 'completed', 'created_at' => now()],
        ]));
        $mock->shouldReceive('topSellingProducts')->andReturn(collect([
            ['name' => 'Chicken Burger', 'quantity' => 30, 'revenue' => 450.0],
        ]));
        $mock->shouldReceive('dailyReport')->andReturn([
            'date' => now()->toImmutable(),
            'sales_count' => 12,
            'sales_total' => 1250.5,
            'subtotal' => 1250.5,
            'tax' => 0.0,
            'discount' => 0.0,
            'delivery' => 0.0,
            'returns_count' => 0,
            'returns_total' => 0.0,
            'net_revenue' => 1250.5,
            'avg_order' => 104.21,
            'payments' => ['cash' => 1250.5],
        ]);
    });

    $this->actingAs(dashboardUser('Super Admin'))
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('$1,250.50')
        ->assertSee('340')
        ->assertSee('#A1001')
        ->assertSee('Ali Khan')
        ->assertSee('Chicken Burger')
        ->assertDontSee('No sales recorded in this period');
});
