<?php

use App\Enums\ReturnStatus;
use App\Enums\SaleReturnStatus;
use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Services\SaleService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->burger = Product::factory()->create(['name' => 'Burger', 'selling_price' => 500, 'tax_rate' => 18]);
    $this->coke = Product::factory()->create(['name' => 'Coke', 'selling_price' => 200, 'tax_rate' => 18]);
    $this->fries = Product::factory()->create(['name' => 'Fries', 'selling_price' => 300, 'tax_rate' => 18]);

    $this->actingAs($this->manager = userWith('Manager'));

    // Reference sale: Burger x2, Coke, Fries, 100 off the order, 18% tax -> total 1652.
    // Paid per line: Burger 1101.33, Coke 220.27, Fries 330.40.
    $this->sale = returnableSale();
    [$this->burgerLine, $this->cokeLine, $this->friesLine] = $this->sale->items->all();
});

function returnableSale(): Sale
{
    return app(SaleService::class)->checkout([
        'items' => [
            ['product_id' => test()->burger->id, 'quantity' => 2],
            ['product_id' => test()->coke->id, 'quantity' => 1],
            ['product_id' => test()->fries->id, 'quantity' => 1],
        ],
        'order_discount_type' => 'fixed',
        'order_discount_value' => '100',
    ], ['payment_method' => 'cash', 'cash_received' => '2000'], userWith('Cashier'))->load('items');
}

/**
 * @param  array<int, int>  $quantities  sale item id => quantity
 * @return array<string, mixed>
 */
function returnPayload(array $quantities, array $overrides = []): array
{
    return [
        'sale_id' => test()->sale->id,
        'reason' => 'damaged',
        'refund_method' => 'cash',
        'status' => 'completed',
        'items' => collect($quantities)->map(fn ($qty, $id) => ['sale_item_id' => $id, 'quantity' => $qty])->values()->all(),
        ...$overrides,
    ];
}

// ------------------------------------------------------------------ creating

test('the return form finds a sale by invoice number and lists its items', function () {
    $this->get('/returns/create')->assertOk()->assertSee('Find the sale');

    $this->get('/returns/create?invoice='.$this->sale->invoice_number)
        ->assertOk()
        ->assertSee('2. Choose items')
        ->assertSee('Burger', false)
        ->assertSee('Complete return');

    $this->get('/returns/create?invoice=INV-999999')->assertOk()->assertSee('No sale found');
});

test('a completed return refunds what the customer paid and updates the sale', function () {
    $this->post('/returns', returnPayload([$this->burgerLine->id => 1]))->assertRedirect();

    $return = SaleReturn::sole();
    expect($return)
        ->return_number->toBe('RET-000001')
        ->status->toBe(ReturnStatus::Completed)
        ->sale_id->toBe($this->sale->id)
        ->processed_by->toBe($this->manager->id)
        ->and((string) $return->refund_amount)->toBe('550.67')
        ->and((string) $return->subtotal)->toBe('500.00')
        ->and($return->return_date->isToday())->toBeTrue();

    expect(ReturnItem::sole())
        ->sale_item_id->toBe($this->burgerLine->id)
        ->product_id->toBe($this->burger->id)
        ->quantity->toBe(1)
        ->and((string) ReturnItem::sole()->unit_price)->toBe('500.00');

    expect($this->burgerLine->fresh()->returned_quantity)->toBe(1)
        ->and($this->sale->fresh()->return_status)->toBe(SaleReturnStatus::Partial)
        ->and((string) $this->sale->fresh()->returned_amount)->toBe('550.67');
});

test('refunds for a line add up exactly to what was paid for it', function () {
    $this->post('/returns', returnPayload([$this->burgerLine->id => 1]))->assertRedirect();
    $this->post('/returns', returnPayload([$this->burgerLine->id => 1]))->assertRedirect();

    expect(SaleReturn::orderBy('id')->pluck('refund_amount')->map(fn ($a) => (string) $a)->all())->toBe(['550.67', '550.66'])
        ->and($this->burgerLine->paidAmount())->toBe('1101.33');
});

test('returning everything refunds the whole sale and marks it fully returned', function () {
    $this->post('/returns', returnPayload([
        $this->burgerLine->id => 2, $this->cokeLine->id => 1, $this->friesLine->id => 1,
    ]))->assertRedirect();

    expect((string) SaleReturn::sole()->refund_amount)->toBe('1652.00')
        ->and($this->sale->fresh()->return_status)->toBe(SaleReturnStatus::Full);

    $this->get('/returns/create?sale='.$this->sale->id)->assertSee('Fully returned');
});

test('refund amounts and prices sent by the browser are ignored', function () {
    $payload = returnPayload([$this->cokeLine->id => 1], ['refund_amount' => '99999', 'subtotal' => '99999']);
    $payload['items'][0] += ['refund_amount' => '99999', 'unit_price' => '99999'];

    $this->post('/returns', $payload)->assertRedirect();

    expect((string) SaleReturn::sole()->refund_amount)->toBe('220.27')
        ->and((string) ReturnItem::sole()->unit_price)->toBe('200.00');
});

// ------------------------------------------------------------------ quantity rules

test('you cannot return more than was sold', function () {
    $this->post('/returns', returnPayload([$this->burgerLine->id => 3]))
        ->assertSessionHasErrors('items.0.quantity');

    $this->post('/returns', returnPayload([$this->burgerLine->id => 1]))->assertRedirect();
    $this->post('/returns', returnPayload([$this->burgerLine->id => 2]))
        ->assertSessionHasErrors(['items.0.quantity' => 'Only 1 of Burger can be returned.']);

    expect(SaleReturn::count())->toBe(1);
});

test('a fully returned item cannot be returned again', function () {
    $this->post('/returns', returnPayload([$this->cokeLine->id => 1]))->assertRedirect();

    $this->post('/returns', returnPayload([$this->cokeLine->id => 1]))
        ->assertSessionHasErrors(['items.0.quantity' => 'Coke has already been fully returned.']);
});

test('invalid return requests are rejected', function (Closure $payload, string $error) {
    $this->post('/returns', $payload())->assertSessionHasErrors($error);

    expect(SaleReturn::count())->toBe(0)->and(SaleItem::sum('returned_quantity'))->toEqual(0);
})->with([
    'negative quantity' => [fn () => returnPayload([test()->burgerLine->id => -1]), 'items.0.quantity'],
    'fractional quantity' => [fn () => returnPayload([test()->burgerLine->id => 1.5]), 'items.0.quantity'],
    'nothing to return' => [fn () => returnPayload([test()->burgerLine->id => 0]), 'items'],
    'no items' => [fn () => returnPayload([], ['items' => []]), 'items'],
    'unknown sale' => [fn () => returnPayload([test()->burgerLine->id => 1], ['sale_id' => 999]), 'sale_id'],
    'item of another sale' => [fn () => returnPayload([returnableSale()->items->first()->id => 1]), 'items.0.sale_item_id'],
    'bad reason' => [fn () => returnPayload([test()->burgerLine->id => 1], ['reason' => 'bored']), 'reason'],
    'bad refund method' => [fn () => returnPayload([test()->burgerLine->id => 1], ['refund_method' => 'crypto']), 'refund_method'],
    'duplicate lines' => [fn () => returnPayload([], ['items' => [['sale_item_id' => test()->burgerLine->id, 'quantity' => 1], ['sale_item_id' => test()->burgerLine->id, 'quantity' => 1]]]), 'items.0.sale_item_id'],
]);

test('held sales cannot be returned', function () {
    $held = Sale::forceCreate(['status' => SaleStatus::Held]);

    $this->post('/returns', returnPayload([$this->burgerLine->id => 1], ['sale_id' => $held->id]))
        ->assertSessionHasErrors('sale_id');
    $this->get('/returns/create?sale='.$held->id)->assertSee('Only completed sales can be returned.');
});

// ------------------------------------------------------------------ pending, complete, cancel, delete

test('a pending return reserves the items until it is completed or cancelled', function () {
    $this->post('/returns', returnPayload([$this->burgerLine->id => 2], ['status' => 'pending', 'refund_method' => 'card']))->assertRedirect();
    $return = SaleReturn::sole();

    expect($return->status)->toBe(ReturnStatus::Pending)
        ->and($this->burgerLine->fresh()->returned_quantity)->toBe(2)
        ->and((string) $this->sale->fresh()->returned_amount)->toBe('0.00');

    $this->patch("/returns/{$return->id}/status", ['status' => 'completed', 'refund_method' => 'bank'])->assertSessionHas('success');
    expect($return->fresh())->status->toBe(ReturnStatus::Completed)->refund_method->value->toBe('bank')
        ->and((string) $this->sale->fresh()->returned_amount)->toBe('1101.33');

    // Completed returns are final.
    $this->patch("/returns/{$return->id}/status", ['status' => 'cancelled'])->assertSessionHas('error');
    expect($return->fresh()->status)->toBe(ReturnStatus::Completed);
});

test('cancelling a pending return makes the items returnable again', function () {
    $this->post('/returns', returnPayload([$this->burgerLine->id => 2], ['status' => 'pending']))->assertRedirect();
    $return = SaleReturn::sole();

    $this->patch("/returns/{$return->id}/status", ['status' => 'cancelled'])->assertSessionHas('success');

    expect($return->fresh()->status)->toBe(ReturnStatus::Cancelled)
        ->and($this->burgerLine->fresh()->returned_quantity)->toBe(0)
        ->and($this->sale->fresh()->return_status)->toBe(SaleReturnStatus::None);
});

test('only pending or cancelled returns can be deleted, by users allowed to', function () {
    $this->post('/returns', returnPayload([$this->cokeLine->id => 1]))->assertRedirect();
    $this->post('/returns', returnPayload([$this->burgerLine->id => 1], ['status' => 'pending']))->assertRedirect();
    [$completed, $pending] = SaleReturn::orderBy('id')->get();

    $this->delete("/returns/{$pending->id}")->assertForbidden(); // managers have no returns.delete

    $this->actingAs(userWith('Admin'));
    $this->delete("/returns/{$completed->id}")->assertSessionHas('error');
    $this->delete("/returns/{$pending->id}")->assertRedirect('/returns');

    expect(SaleReturn::pluck('id')->all())->toBe([$completed->id])
        ->and($this->burgerLine->fresh()->returned_quantity)->toBe(0);
});

// ------------------------------------------------------------------ numbering, permissions, history

test('return numbers are sequential, unique and use the prefix from settings', function () {
    $this->post('/returns', returnPayload([$this->cokeLine->id => 1]))->assertRedirect();
    settings()->set(['general.return_prefix' => 'R-']);
    $this->post('/returns', returnPayload([$this->friesLine->id => 1]))->assertRedirect();

    expect(SaleReturn::orderBy('id')->pluck('return_number')->all())->toBe(['RET-000001', 'R-000002']);

    expect(fn () => SaleReturn::forceCreate([
        'return_number' => 'RET-000001', 'sale_id' => $this->sale->id, 'return_date' => today(),
        'refund_method' => 'cash', 'reason' => 'other', 'status' => 'pending',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('only authorised users can see or process returns', function (string $role, int $view, int $create) {
    $this->actingAs(userWith($role));

    $this->get('/returns')->assertStatus($view);
    $this->get('/returns/create')->assertStatus($create);
    $this->post('/returns', returnPayload([$this->cokeLine->id => 1]))->assertStatus($create === 200 ? 302 : 403);
})->with([
    'admin' => ['Admin', 200, 200],
    'manager' => ['Manager', 200, 200],
    'cashier' => ['Cashier', 403, 403],
    'kitchen' => ['Kitchen Staff', 403, 403],
]);

test('return history can be searched and filtered', function () {
    $this->post('/returns', returnPayload([$this->cokeLine->id => 1]))->assertRedirect();
    $this->post('/returns', returnPayload([$this->friesLine->id => 1], ['status' => 'pending']))->assertRedirect();

    $this->get('/returns')->assertOk()->assertSeeInOrder(['RET-000002', 'RET-000001'])->assertSee($this->sale->invoice_number);
    $this->get('/returns?status=pending')->assertSee('RET-000002')->assertDontSee('RET-000001');
    $this->get('/returns?search=RET-000001')->assertSee('RET-000001')->assertDontSee('RET-000002');
    $this->get('/returns?from='.today()->addDay()->toDateString())->assertSee('No matching returns');
    $this->get('/returns?from=2026-01-10&to=2026-01-01')->assertSessionHasErrors('to');
});

test('the return details page shows items, refund and who processed it', function () {
    $this->post('/returns', returnPayload([$this->burgerLine->id => 1], ['notes' => 'Box was crushed']))->assertRedirect();
    $return = SaleReturn::sole();

    $this->get("/returns/{$return->id}")
        ->assertOk()
        ->assertSee('RET-000001')
        ->assertSee($this->sale->invoice_number)
        ->assertSee('Damaged / defective')
        ->assertSee('Box was crushed')
        ->assertSee($this->manager->name)
        ->assertSeeInOrder(['Burger', '1', '500', '550.67']);
});

test('returns never touch stock', function () {
    $this->post('/returns', returnPayload([$this->burgerLine->id => 2]))->assertRedirect();

    expect(Schema::hasTable('stock_movements'))->toBeFalse()
        ->and(Schema::hasColumn('products', 'stock_quantity'))->toBeFalse();
});
