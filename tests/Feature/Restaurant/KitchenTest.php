<?php

use App\Enums\KitchenStatus;
use App\Enums\OrderType;
use App\Enums\TableStatus;
use App\Models\Category;
use App\Models\KitchenOrder;
use App\Models\KitchenOrderItem;
use App\Models\KitchenStation;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->grill = KitchenStation::factory()->create(['name' => 'Grill']);
    $this->main = KitchenStation::factory()->create(['name' => 'Main Kitchen']);
    $this->drinks = KitchenStation::factory()->create(['name' => 'Drinks']);

    // Station via category (Burgers -> Grill), via product (Fries -> Main Kitchen, Coke -> Drinks).
    $burgers = Category::factory()->create(['name' => 'Burgers', 'kitchen_station_id' => $this->grill->id]);
    $this->burger = Product::factory()->for($burgers)->create(['name' => 'Chicken Burger', 'selling_price' => 650]);
    $this->fries = Product::factory()->create(['name' => 'Large Fries', 'selling_price' => 300, 'kitchen_station_id' => $this->main->id]);
    $this->coke = Product::factory()->create(['name' => 'Coke', 'selling_price' => 200, 'kitchen_station_id' => $this->drinks->id]);
    $this->giftCard = Product::factory()->create(['name' => 'Gift Card', 'selling_price' => 1000]); // retail: no station

    $this->table = RestaurantTable::factory()->create(['name' => 'Table 12']);

    $this->actingAs($this->cashier = userWith('Cashier'));
});

/**
 * The Day 5 reference order: Dine In, Table 12, 2 × Chicken Burger (no onions), 1 × Large Fries, 2 × Coke.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function dineInOrder(array $overrides = []): array
{
    return [
        'order_type' => 'dine_in',
        'table_id' => test()->table->id,
        'order_note' => 'Customer is waiting outside.',
        'items' => [
            ['product_id' => test()->burger->id, 'quantity' => 2, 'note' => 'No onions'],
            ['product_id' => test()->fries->id, 'quantity' => 1, 'note' => 'Extra crispy'],
            ['product_id' => test()->coke->id, 'quantity' => 2],
        ],
        'payment_method' => 'cash',
        'cash_received' => '5000',
        ...$overrides,
    ];
}

function kitchenStaff(): User
{
    return userWith('Kitchen Staff');
}

// ------------------------------------------------------------------ Dine In -> sale -> KOT

test('a dine in order saves the sale, occupies the table and sends a pending KOT', function () {
    $this->postJson('/sales', dineInOrder())
        ->assertCreated()
        ->assertJsonPath('sale.order_type', 'Dine In')
        ->assertJsonPath('sale.table', 'Table 12')
        ->assertJsonPath('kitchen_orders.0.kot_number', 'KOT-1')
        ->assertJsonPath('kitchen_orders.0.items', 3);

    $sale = Sale::sole();
    expect($sale->order_type)->toBe(OrderType::DineIn)
        ->and($sale->table_id)->toBe($this->table->id)
        ->and($sale->order_note)->toBe('Customer is waiting outside.')
        ->and($sale->items->pluck('note')->all())->toBe(['No onions', 'Extra crispy', null]);

    expect($this->table->fresh()->status)->toBe(TableStatus::Occupied);

    $kot = KitchenOrder::sole();
    expect($kot)
        ->kot_number->toBe('KOT-1')
        ->sale_id->toBe($sale->id)
        ->table_id->toBe($this->table->id)
        ->order_type->toBe(OrderType::DineIn)
        ->status->toBe(KitchenStatus::Pending)
        ->notes->toBe('Customer is waiting outside.')
        ->created_by->toBe($this->cashier->id);

    expect($kot->items->map(fn (KitchenOrderItem $i) => [$i->saleItem->product_name, $i->quantity, $i->station->name, $i->notes, $i->status])->all())
        ->toBe([
            ['Chicken Burger', 2, 'Grill', 'No onions', KitchenStatus::Pending],
            ['Large Fries', 1, 'Main Kitchen', 'Extra crispy', KitchenStatus::Pending],
            ['Coke', 2, 'Drinks', null, KitchenStatus::Pending],
        ]);

    expect($kot->items->pluck('product_id')->all())->toBe([$this->burger->id, $this->fries->id, $this->coke->id]);
});

test('the kitchen screen shows the new KOT with table, items and notes', function () {
    $this->postJson('/sales', dineInOrder())->assertCreated();
    $this->actingAs(kitchenStaff());

    $this->get('/kitchen')->assertOk()->assertSee('Kitchen Display')->assertSee('Grill')->assertSee('Drinks');

    $this->getJson('/kitchen/orders')
        ->assertOk()
        ->assertJsonPath('counts.pending', 1)
        ->assertJsonPath('orders.0.kot_number', 'KOT-1')
        ->assertJsonPath('orders.0.table', 'Table 12')
        ->assertJsonPath('orders.0.order_type_label', 'Dine In')
        ->assertJsonPath('orders.0.status', 'pending')
        ->assertJsonPath('orders.0.notes', 'Customer is waiting outside.')
        ->assertJsonPath('orders.0.items.0.name', 'Chicken Burger')
        ->assertJsonPath('orders.0.items.0.quantity', 2)
        ->assertJsonPath('orders.0.items.0.notes', 'No onions')
        ->assertJsonPath('orders.0.items.0.station', 'Grill')
        ->assertJsonCount(3, 'orders.0.items');
});

test('kitchen staff move a KOT from pending to preparing, ready and completed', function () {
    $this->postJson('/sales', dineInOrder())->assertCreated();
    $kot = KitchenOrder::sole();
    $this->actingAs(kitchenStaff());

    $this->postJson("/kitchen/orders/{$kot->id}/status", ['status' => 'preparing'])->assertOk()->assertJsonPath('order.status', 'preparing');
    expect($kot->fresh())->status->toBe(KitchenStatus::Preparing)->started_at->not->toBeNull();

    $this->postJson("/kitchen/orders/{$kot->id}/status", ['status' => 'ready'])->assertOk()->assertJsonPath('order.status', 'ready');
    expect($kot->fresh())->status->toBe(KitchenStatus::Ready)->ready_at->not->toBeNull();
    // Ready is not the end of the order: the table stays occupied.
    expect($this->table->fresh()->status)->toBe(TableStatus::Occupied);

    $this->postJson("/kitchen/orders/{$kot->id}/status", ['status' => 'completed'])->assertOk()->assertJsonPath('order.status', 'completed');
    expect($kot->fresh())->status->toBe(KitchenStatus::Completed)->completed_at->not->toBeNull()
        ->and(KitchenOrderItem::pluck('status')->unique()->all())->toBe([KitchenStatus::Completed]);

    // The order is finished: the table is free again.
    expect($this->table->fresh()->status)->toBe(TableStatus::Available);
});

test('statuses only move forward', function () {
    $this->postJson('/sales', dineInOrder())->assertCreated();
    $kot = KitchenOrder::sole();
    $this->actingAs(kitchenStaff());

    $this->postJson("/kitchen/orders/{$kot->id}/status", ['status' => 'ready'])->assertOk();
    $this->postJson("/kitchen/orders/{$kot->id}/status", ['status' => 'preparing'])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->postJson("/kitchen/orders/{$kot->id}/status", ['status' => 'ready'])->assertUnprocessable();
    $this->postJson("/kitchen/orders/{$kot->id}/status", ['status' => 'pending'])->assertUnprocessable();
    $this->postJson("/kitchen/orders/{$kot->id}/status", ['status' => 'cooking'])->assertUnprocessable();

    expect($kot->fresh()->status)->toBe(KitchenStatus::Ready);
});

test('a station screen shows and moves only its own items', function () {
    $this->postJson('/sales', dineInOrder())->assertCreated();
    $kot = KitchenOrder::sole();
    $this->actingAs(kitchenStaff());

    $this->getJson("/kitchen/orders?station={$this->grill->id}")
        ->assertJsonCount(1, 'orders')
        ->assertJsonCount(1, 'orders.0.items')
        ->assertJsonPath('orders.0.items.0.name', 'Chicken Burger');

    // Grill starts and finishes its burgers; the ticket is preparing until every station is ready.
    $this->postJson("/kitchen/orders/{$kot->id}/status", ['status' => 'ready', 'station' => $this->grill->id])
        ->assertOk()
        ->assertJsonPath('order.status', 'ready'); // the Grill view of the ticket
    expect($kot->fresh()->status)->toBe(KitchenStatus::Preparing);

    $this->postJson("/kitchen/orders/{$kot->id}/status", ['status' => 'ready', 'station' => $this->main->id])->assertOk();
    $this->postJson("/kitchen/orders/{$kot->id}/status", ['status' => 'ready', 'station' => $this->drinks->id])->assertOk();
    expect($kot->fresh()->status)->toBe(KitchenStatus::Ready);

    // A station with nothing left to move is refused.
    $this->postJson("/kitchen/orders/{$kot->id}/status", ['status' => 'ready', 'station' => $this->grill->id])->assertUnprocessable();

    // Stations with no items on this ticket don't see it.
    $pizza = KitchenStation::factory()->create(['name' => 'Pizza']);
    $this->getJson("/kitchen/orders?station={$pizza->id}")->assertJsonCount(0, 'orders');
});

test('the kitchen screen filters by status', function () {
    $this->postJson('/sales', dineInOrder())->assertCreated();
    $this->postJson('/sales', dineInOrder(['order_type' => 'take_away', 'table_id' => null]))->assertCreated();
    [$first, $second] = KitchenOrder::orderBy('id')->get();
    $this->actingAs(kitchenStaff());
    $this->postJson("/kitchen/orders/{$second->id}/status", ['status' => 'preparing'])->assertOk();

    $numbers = fn (string $status) => collect($this->getJson("/kitchen/orders?status={$status}")->json('orders'))->pluck('kot_number')->all();

    expect($numbers('all'))->toBe(['KOT-1', 'KOT-2'])
        ->and($numbers('pending'))->toBe(['KOT-1'])
        ->and($numbers('preparing'))->toBe(['KOT-2'])
        ->and($numbers('ready'))->toBe([])
        ->and($numbers('completed'))->toBe([]);

    $this->getJson('/kitchen/orders?status=bogus')->assertUnprocessable();
});

test('the KOT prints with table, order type, items by station and notes', function () {
    $this->postJson('/sales', dineInOrder())->assertCreated();
    $kot = KitchenOrder::sole();

    // Kitchen view requires kitchen.view; switch to kitchen staff to print the KOT.
    $this->actingAs(kitchenStaff());
    $this->get("/kitchen/orders/{$kot->id}")
        ->assertOk()
        ->assertSeeInOrder(['KOT-1', 'TABLE', 'Table 12', 'ORDER', 'Dine In', 'Grill', '2 x Chicken Burger', 'No onions', 'Main Kitchen', '1 x Large Fries', 'Extra crispy', 'Drinks', '2 x Coke', 'Note', 'Customer is waiting outside.'])
        ->assertSee('data-print-page', false)
        ->assertSee('Kitchen screen');
});

// ------------------------------------------------------------------ order types

test('take away orders need no table and still go to the kitchen', function () {
    $this->postJson('/sales', dineInOrder(['order_type' => 'take_away', 'table_id' => $this->table->id]))->assertCreated();

    $sale = Sale::sole();
    expect($sale->order_type)->toBe(OrderType::TakeAway)
        ->and($sale->table_id)->toBeNull()
        ->and(KitchenOrder::sole())->order_type->toBe(OrderType::TakeAway)->table_id->toBeNull()
        ->and($this->table->fresh()->status)->toBe(TableStatus::Available);
});

test('orders without an order type are take away, as before Day 5', function () {
    $order = dineInOrder();
    unset($order['order_type'], $order['table_id']);

    $this->postJson('/sales', $order)->assertCreated();

    expect(Sale::sole()->order_type)->toBe(OrderType::TakeAway);
});

test('delivery orders keep the customer, phone, address and delivery fee', function () {
    $this->postJson('/sales', dineInOrder([
        'order_type' => 'delivery',
        'table_id' => $this->table->id,
        'customer_name' => 'Usman',
        'customer_phone' => '0300 1234567',
        'customer_address' => 'G-9, Islamabad',
        'delivery_fee' => '150',
    ]))->assertCreated();

    $sale = Sale::sole();
    expect($sale->order_type)->toBe(OrderType::Delivery)
        ->and($sale->table_id)->toBeNull()
        ->and([$sale->customer_name, $sale->customer_phone, $sale->customer_address, (string) $sale->delivery_fee])
        ->toBe(['Usman', '0300 1234567', 'G-9, Islamabad', '150.00'])
        ->and(KitchenOrder::sole()->order_type)->toBe(OrderType::Delivery);
});

test('a delivery fee is only allowed on delivery orders', function () {
    $this->postJson('/sales', dineInOrder(['delivery_fee' => '150']))
        ->assertUnprocessable()->assertJsonValidationErrors('delivery_fee');
});

test('dine in orders need an active table that is not reserved', function (Closure $table, string $error) {
    $this->postJson('/sales', dineInOrder(['table_id' => $table()]))
        ->assertUnprocessable()->assertJsonValidationErrors(['table_id' => $error]);

    expect(Sale::count())->toBe(0)->and(KitchenOrder::count())->toBe(0);
})->with([
    'no table' => [fn () => null, 'Select a table'],
    'unknown table' => [fn () => 999, 'Select an active table'],
    'inactive table' => [fn () => RestaurantTable::factory()->create(['is_active' => false])->id, 'Select an active table'],
    'reserved table' => [fn () => RestaurantTable::factory()->create(['name' => 'Table 7', 'status' => 'reserved'])->id, 'Table 7 is reserved'],
]);

test('an occupied table can take another order and stays occupied until every KOT is completed', function () {
    $this->postJson('/sales', dineInOrder())->assertCreated();
    $this->postJson('/sales', dineInOrder(['items' => [['product_id' => $this->coke->id, 'quantity' => 1]]]))->assertCreated();
    [$first, $second] = KitchenOrder::orderBy('id')->get();
    $this->actingAs(kitchenStaff());

    $this->postJson("/kitchen/orders/{$first->id}/status", ['status' => 'completed'])->assertOk();
    expect($this->table->fresh()->status)->toBe(TableStatus::Occupied);

    $this->postJson("/kitchen/orders/{$second->id}/status", ['status' => 'completed'])->assertOk();
    expect($this->table->fresh()->status)->toBe(TableStatus::Available);
});

// ------------------------------------------------------------------ KOT rules

test('retail items without a kitchen station are not sent to the kitchen', function () {
    $this->postJson('/sales', dineInOrder([
        'order_type' => 'take_away',
        'items' => [['product_id' => $this->giftCard->id, 'quantity' => 1]],
    ]))->assertCreated()->assertJsonCount(0, 'kitchen_orders');

    expect(KitchenOrder::count())->toBe(0);

    $this->postJson('/sales', dineInOrder([
        'items' => [['product_id' => $this->giftCard->id, 'quantity' => 1], ['product_id' => $this->coke->id, 'quantity' => 1]],
    ]))->assertCreated();

    expect(KitchenOrder::sole()->items->pluck('product_id')->all())->toBe([$this->coke->id]);
});

test('a product station overrides its category, and inactive stations are skipped', function () {
    $this->burger->update(['kitchen_station_id' => $this->main->id]);
    $this->coke->kitchenStation->update(['is_active' => false]);

    $this->postJson('/sales', dineInOrder())->assertCreated();

    expect(KitchenOrder::sole()->items->map(fn ($i) => [$i->saleItem->product_name, $i->station?->name])->all())
        ->toBe([['Chicken Burger', 'Main Kitchen'], ['Large Fries', 'Main Kitchen']]);
});

test('KOT numbers are generated on the server, sequential and unique', function () {
    $this->postJson('/sales', dineInOrder())->assertCreated();
    $this->postJson('/sales', dineInOrder(['order_type' => 'take_away']))->assertCreated();

    expect(KitchenOrder::orderBy('id')->pluck('kot_number')->all())->toBe(['KOT-1', 'KOT-2']);

    expect(fn () => KitchenOrder::forceCreate(['kot_number' => 'KOT-1', 'sale_id' => Sale::first()->id, 'order_type' => 'take_away']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('a sale can have more than one KOT', function () {
    $this->postJson('/sales', dineInOrder())->assertCreated();
    $sale = Sale::sole();

    KitchenOrder::forceCreate(['kot_number' => 'KOT-900001', 'sale_id' => $sale->id, 'order_type' => 'dine_in']);

    expect($sale->kitchenOrders()->count())->toBe(2);
});

test('a failure while creating the KOT rolls back the whole order', function () {
    KitchenOrderItem::creating(fn () => throw new RuntimeException('KOT write failed'));
    $this->withoutExceptionHandling();

    expect(fn () => $this->postJson('/sales', dineInOrder()))->toThrow(RuntimeException::class, 'KOT write failed');

    expect(Sale::count())->toBe(0)
        ->and(SaleItem::count())->toBe(0)
        ->and(KitchenOrder::count())->toBe(0)
        ->and($this->table->fresh()->status)->toBe(TableStatus::Available)
        ->and(DB::table('number_sequences')->count())->toBe(0);
});

test('KOTs never change stock', function () {
    $before = Product::orderBy('id')->get()->map->getAttributes()->all();

    $this->postJson('/sales', dineInOrder())->assertCreated();
    $this->actingAs(kitchenStaff())->postJson('/kitchen/orders/'.KitchenOrder::sole()->id.'/status', ['status' => 'completed'])->assertOk();

    expect(Product::orderBy('id')->get()->map->getAttributes()->all())->toBe($before);
});

// ------------------------------------------------------------------ held sales, receipts

test('held sales keep order type, table and notes but send nothing to the kitchen', function () {
    $order = dineInOrder();
    unset($order['payment_method'], $order['cash_received']);

    $this->postJson('/pos/held', $order)->assertCreated();

    expect(KitchenOrder::count())->toBe(0)
        ->and($this->table->fresh()->status)->toBe(TableStatus::Available);

    $this->getJson('/pos/held')->assertJsonPath('held.0.order_type', 'Dine In')->assertJsonPath('held.0.table', 'Table 12');

    $this->postJson('/pos/held/'.Sale::sole()->id.'/resume')
        ->assertOk()
        ->assertJson([
            'order_type' => 'dine_in',
            'table_id' => $this->table->id,
            'table_name' => 'Table 12',
            'order_note' => 'Customer is waiting outside.',
        ])
        ->assertJsonPath('items.0.note', 'No onions');
});

test('the receipt shows the order type, table and item notes', function () {
    $this->postJson('/sales', dineInOrder())->assertCreated();

    $this->get('/sales/'.Sale::sole()->id.'/receipt')
        ->assertOk()
        ->assertSeeInOrder(['Order', 'Dine In', 'Table 12', 'Chicken Burger', 'No onions']);
});

// ------------------------------------------------------------------ permissions

test('only authorized roles see the kitchen and only kitchen roles change KOT status', function () {
    $this->postJson('/sales', dineInOrder())->assertCreated();
    $kot = KitchenOrder::sole();

    // Cashier no longer has kitchen.view — only Manager/Admin/Kitchen Staff do.
    $this->get('/kitchen')->assertForbidden();
    $this->getJson('/kitchen/orders')->assertForbidden();

    $this->actingAs(userWith('Manager'));
    $this->get('/kitchen')->assertOk();
    $this->getJson('/kitchen/orders')->assertOk();
    $this->postJson("/kitchen/orders/{$kot->id}/status", ['status' => 'preparing'])->assertOk();

    foreach (['Kitchen Staff', 'Manager', 'Admin'] as $role) {
        expect(userWith($role)->can('kitchen.manage'))->toBeTrue();
    }

    $nobody = User::factory()->create();
    $this->actingAs($nobody)->get('/kitchen')->assertForbidden();
    $this->actingAs($nobody)->get("/kitchen/orders/{$kot->id}")->assertForbidden();
});

test('kitchen staff cannot use the POS or pick tables', function () {
    $this->actingAs(kitchenStaff());

    $this->get('/pos')->assertForbidden();
    $this->getJson('/pos/tables')->assertForbidden();
    $this->postJson('/sales', dineInOrder())->assertForbidden();
});

test('the POS table picker lists active tables with their status', function () {
    RestaurantTable::factory()->create(['name' => 'Table 3', 'status' => 'reserved', 'sort_order' => 3]);
    RestaurantTable::factory()->create(['name' => 'Old Table', 'is_active' => false]);
    $this->postJson('/sales', dineInOrder())->assertCreated();

    // Manager has tables.status so can_change_status is true; Cashier cannot.
    $this->actingAs(userWith('Manager'));
    $tables = collect($this->getJson('/pos/tables')->assertOk()->assertJsonPath('can_change_status', true)->json('tables'))->keyBy('name');

    expect($tables->keys()->sort()->values()->all())->toBe(['Table 12', 'Table 3'])
        ->and($tables['Table 12'])->toMatchArray(['status' => 'occupied', 'status_label' => 'Occupied', 'open_orders' => 1])
        ->and($tables['Table 3']['status'])->toBe('reserved');
});
