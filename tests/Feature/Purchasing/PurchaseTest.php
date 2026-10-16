<?php

use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->supplier = Supplier::factory()->create(['name' => 'Metro Wholesale']);
    $this->rice = Product::factory()->create(['name' => 'Basmati Rice', 'purchase_price' => 180]);
    $this->oil = Product::factory()->create(['name' => 'Cooking Oil', 'purchase_price' => 450]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function purchasePayload(array $overrides = []): array
{
    return [
        'supplier_id' => test()->supplier->id,
        'purchase_date' => '2026-09-20',
        'payment_status' => 'unpaid',
        'status' => 'completed',
        'notes' => 'Invoice #778',
        'items' => [
            ['product_id' => test()->rice->id, 'quantity' => '10', 'purchase_price' => '180.00', 'discount' => '100', 'tax' => '0'],
            ['product_id' => test()->oil->id, 'quantity' => '2.5', 'purchase_price' => '450.00', 'discount' => '', 'tax' => '56.25'],
        ],
        ...$overrides,
    ];
}

test('purchase pages render', function () {
    $this->actingAs(userWith('Admin'))->post('/purchases', purchasePayload());
    $purchase = Purchase::sole();

    $this->get('/purchases')->assertOk()->assertSee($purchase->purchase_number)->assertSee('Metro Wholesale');
    $this->get('/purchases/create')->assertOk()->assertSee('Basmati Rice', false);
    $this->get("/purchases/{$purchase->id}")->assertOk()->assertSee('Basmati Rice')->assertSee('Invoice #778');
});

test('a purchase with multiple items is created with server-calculated totals', function () {
    $user = userWith('Manager');

    $this->actingAs($user)
        ->post('/purchases', purchasePayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $purchase = Purchase::with('items')->sole();

    // Rice: 10 x 180 = 1800 - 100 = 1700. Oil: 2.5 x 450 = 1125 + 56.25 = 1181.25.
    expect($purchase->purchase_number)->toBe('PUR-000001')
        ->and($purchase->supplier->is($this->supplier))->toBeTrue()
        ->and($purchase->creator->is($user))->toBeTrue()
        ->and($purchase->status)->toBe(PurchaseStatus::Completed)
        ->and($purchase->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and($purchase->subtotal)->toBe('2925.00')
        ->and($purchase->discount)->toBe('100.00')
        ->and($purchase->tax)->toBe('56.25')
        ->and($purchase->total)->toBe('2881.25')
        ->and($purchase->items)->toHaveCount(2)
        ->and($purchase->items->pluck('line_total')->all())->toBe(['1700.00', '1181.25'])
        ->and($purchase->items[1]->quantity)->toBe('2.500');
});

test('totals submitted by the browser are ignored', function () {
    $this->actingAs(userWith('Admin'))->post('/purchases', purchasePayload([
        'subtotal' => '1', 'discount' => '0', 'tax' => '0', 'total' => '1',
        'items' => [
            ['product_id' => $this->rice->id, 'quantity' => '2', 'purchase_price' => '10', 'line_total' => '0.01'],
        ],
    ]))->assertSessionHasNoErrors();

    $purchase = Purchase::with('items')->sole();

    expect($purchase->total)->toBe('20.00')
        ->and($purchase->items->sole()->line_total)->toBe('20.00');
});

test('purchase numbers are sequential and unique', function () {
    $this->actingAs(userWith('Admin'));

    foreach (range(1, 3) as $i) {
        $this->post('/purchases', purchasePayload())->assertSessionHasNoErrors();
    }

    expect(Purchase::orderBy('id')->pluck('purchase_number')->all())->toBe(['PUR-000001', 'PUR-000002', 'PUR-000003']);
});

test('purchase numbers are protected by a unique index', function () {
    $first = Purchase::factory()->create();
    $second = Purchase::factory()->create();

    expect(fn () => $second->forceFill(['purchase_number' => $first->purchase_number])->save())
        ->toThrow(UniqueConstraintViolationException::class);
});

test('purchase validation', function (array $overrides, string $field) {
    $this->actingAs(userWith('Admin'))
        ->post('/purchases', purchasePayload($overrides))
        ->assertSessionHasErrors($field);

    expect(Purchase::count())->toBe(0)->and(PurchaseItem::count())->toBe(0);
})->with([
    'supplier required' => [['supplier_id' => ''], 'supplier_id'],
    'unknown supplier' => [['supplier_id' => 9999], 'supplier_id'],
    'date required' => [['purchase_date' => ''], 'purchase_date'],
    'invalid date' => [['purchase_date' => 'next tuesday-ish'], 'purchase_date'],
    'at least one item' => [['items' => []], 'items'],
    'invalid payment status' => [['payment_status' => 'owed'], 'payment_status'],
    'cannot create as cancelled' => [['status' => 'cancelled'], 'status'],
    'product required' => [['items' => [['product_id' => '', 'quantity' => '1', 'purchase_price' => '1']]], 'items.0.product_id'],
    'unknown product' => [['items' => [['product_id' => 9999, 'quantity' => '1', 'purchase_price' => '1']]], 'items.0.product_id'],
    'zero quantity' => [['items' => [['product_id' => 1, 'quantity' => '0', 'purchase_price' => '1']]], 'items.0.quantity'],
    'negative quantity' => [['items' => [['product_id' => 1, 'quantity' => '-2', 'purchase_price' => '1']]], 'items.0.quantity'],
    'too many quantity decimals' => [['items' => [['product_id' => 1, 'quantity' => '1.2345', 'purchase_price' => '1']]], 'items.0.quantity'],
    'price required' => [['items' => [['product_id' => 1, 'quantity' => '1', 'purchase_price' => '']]], 'items.0.purchase_price'],
    'negative price' => [['items' => [['product_id' => 1, 'quantity' => '1', 'purchase_price' => '-5']]], 'items.0.purchase_price'],
    'price with 3 decimals' => [['items' => [['product_id' => 1, 'quantity' => '1', 'purchase_price' => '1.005']]], 'items.0.purchase_price'],
    'non-numeric price' => [['items' => [['product_id' => 1, 'quantity' => '1', 'purchase_price' => 'ten']]], 'items.0.purchase_price'],
    'negative discount' => [['items' => [['product_id' => 1, 'quantity' => '1', 'purchase_price' => '5', 'discount' => '-1']]], 'items.0.discount'],
    'discount above line amount' => [['items' => [['product_id' => 1, 'quantity' => '2', 'purchase_price' => '5', 'discount' => '10.01']]], 'items.0.discount'],
    'negative tax' => [['items' => [['product_id' => 1, 'quantity' => '1', 'purchase_price' => '5', 'tax' => '-1']]], 'items.0.tax'],
    'line too large for the database' => [['items' => [['product_id' => 1, 'quantity' => '1000000', 'purchase_price' => '9999999.99']]], 'items.0.quantity'],
]);

test('inactive suppliers cannot be used for new purchases', function () {
    $inactive = Supplier::factory()->inactive()->create();

    $this->actingAs(userWith('Admin'))
        ->post('/purchases', purchasePayload(['supplier_id' => $inactive->id]))
        ->assertSessionHasErrors('supplier_id');
});

test('variants are validated against their product', function () {
    $tee = Product::factory()->create(['name' => 'Tee', 'purchase_price' => 300]);
    $large = ProductVariant::factory()->for($tee)->create(['name' => 'Large']);
    $foreign = ProductVariant::factory()->create();
    $this->actingAs(userWith('Admin'));

    // A product sold as variants must be bought per variant.
    $this->post('/purchases', purchasePayload(['items' => [['product_id' => $tee->id, 'quantity' => '1', 'purchase_price' => '300']]]))
        ->assertSessionHasErrors('items.0.variant_id');

    // The variant must belong to the chosen product.
    $this->post('/purchases', purchasePayload(['items' => [['product_id' => $tee->id, 'variant_id' => $foreign->id, 'quantity' => '1', 'purchase_price' => '300']]]))
        ->assertSessionHasErrors('items.0.variant_id');

    $this->post('/purchases', purchasePayload(['items' => [['product_id' => $tee->id, 'variant_id' => $large->id, 'quantity' => '4', 'purchase_price' => '300']]]))
        ->assertSessionHasNoErrors();

    $item = PurchaseItem::sole();
    expect($item->variant->is($large))->toBeTrue()
        ->and($item->product->is($tee))->toBeTrue();
});

test('a failed save rolls back the whole purchase', function () {
    // Fail while writing the second item.
    $created = 0;
    PurchaseItem::creating(function () use (&$created) {
        if (++$created === 2) {
            throw new RuntimeException('Simulated failure');
        }
    });

    $user = userWith('Admin');

    expect(fn () => app(PurchaseService::class)->create(
        ['supplier_id' => $this->supplier->id, 'purchase_date' => '2026-09-20', 'payment_status' => 'unpaid', 'status' => 'completed'],
        purchasePayload()['items'],
        $user,
    ))->toThrow(RuntimeException::class, 'Simulated failure');

    expect(Purchase::count())->toBe(0)->and(PurchaseItem::count())->toBe(0);
});

test('a draft purchase can be edited and its items replaced', function () {
    $this->actingAs(userWith('Manager'))->post('/purchases', purchasePayload(['status' => 'draft']));
    $purchase = Purchase::sole();

    $this->get("/purchases/{$purchase->id}/edit")->assertOk();

    $this->put("/purchases/{$purchase->id}", purchasePayload([
        'status' => 'draft',
        'payment_status' => 'partially_paid',
        'items' => [['product_id' => $this->oil->id, 'quantity' => '4', 'purchase_price' => '440']],
    ]))->assertSessionHasNoErrors();

    $purchase->refresh()->load('items');

    expect($purchase->purchase_number)->toBe('PUR-000001')
        ->and($purchase->items)->toHaveCount(1)
        ->and($purchase->total)->toBe('1760.00')
        ->and($purchase->payment_status)->toBe(PaymentStatus::PartiallyPaid)
        ->and(PurchaseItem::count())->toBe(1);
});

test('completed and cancelled purchases cannot be edited or deleted', function () {
    $this->actingAs(userWith('Admin'))->post('/purchases', purchasePayload(['status' => 'completed']));
    $purchase = Purchase::sole();
    $totalBefore = $purchase->total;

    $this->get("/purchases/{$purchase->id}/edit")->assertRedirect(route('purchases.show', $purchase));
    $this->put("/purchases/{$purchase->id}", purchasePayload(['items' => [['product_id' => $this->oil->id, 'quantity' => '1', 'purchase_price' => '1']]]))
        ->assertSessionHas('error');
    $this->delete("/purchases/{$purchase->id}")->assertSessionHas('error');

    expect(Purchase::sole()->total)->toBe($totalBefore);
});

test('purchase status changes follow the allowed transitions', function () {
    $this->actingAs(userWith('Admin'))->post('/purchases', purchasePayload(['status' => 'draft']));
    $purchase = Purchase::sole();

    $this->patch("/purchases/{$purchase->id}/status", ['status' => 'completed'])->assertSessionHas('success');
    expect($purchase->fresh()->status)->toBe(PurchaseStatus::Completed);

    // Completed cannot go back to draft.
    $this->patch("/purchases/{$purchase->id}/status", ['status' => 'draft'])->assertSessionHas('error');
    expect($purchase->fresh()->status)->toBe(PurchaseStatus::Completed);

    $this->patch("/purchases/{$purchase->id}/status", ['status' => 'cancelled'])->assertSessionHas('success');
    expect($purchase->fresh()->status)->toBe(PurchaseStatus::Cancelled);

    // Cancelled is final.
    $this->patch("/purchases/{$purchase->id}/status", ['status' => 'completed'])->assertSessionHas('error');
    $this->patch("/purchases/{$purchase->id}/payment-status", ['payment_status' => 'paid'])->assertSessionHas('error');
    expect($purchase->fresh()->status)->toBe(PurchaseStatus::Cancelled)
        ->and($purchase->fresh()->payment_status)->toBe(PaymentStatus::Unpaid);
});

test('payment status can be updated on a completed purchase', function () {
    $this->actingAs(userWith('Manager'))->post('/purchases', purchasePayload());
    $purchase = Purchase::sole();

    $this->patch("/purchases/{$purchase->id}/payment-status", ['payment_status' => 'paid'])->assertSessionHas('success');
    $this->patch("/purchases/{$purchase->id}/payment-status", ['payment_status' => 'bogus'])->assertSessionHasErrors('payment_status');

    expect($purchase->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('a draft purchase can be deleted with its items', function () {
    $this->actingAs(userWith('Admin'))->post('/purchases', purchasePayload(['status' => 'draft']));
    $purchase = Purchase::sole();

    $this->delete("/purchases/{$purchase->id}")->assertRedirect(route('purchases.index'));

    expect(Purchase::count())->toBe(0)->and(PurchaseItem::count())->toBe(0);
});

test('purchases require the matching permission', function () {
    $purchase = Purchase::factory()->for($this->supplier)->create();

    // Cashier and kitchen staff: no access.
    foreach (['Cashier', 'Kitchen Staff'] as $role) {
        $this->actingAs(userWith($role));
        $this->get('/purchases')->assertForbidden();
        $this->get("/purchases/{$purchase->id}")->assertForbidden();
        $this->post('/purchases', purchasePayload())->assertForbidden();
        $this->patch("/purchases/{$purchase->id}/status", ['status' => 'cancelled'])->assertForbidden();
    }

    // Manager can create and edit but not delete.
    $this->actingAs(userWith('Manager'));
    $this->get('/purchases/create')->assertOk();
    $this->delete("/purchases/{$purchase->id}")->assertForbidden();

    auth()->logout();
    $this->get('/purchases')->assertRedirect(route('login'));

    expect(Purchase::count())->toBe(1)->and($purchase->fresh()->status)->toBe(PurchaseStatus::Draft);
});

test('purchase history can be searched and filtered', function () {
    $other = Supplier::factory()->create(['name' => 'Karachi Mills']);
    $service = app(PurchaseService::class);
    $item = [['product_id' => $this->rice->id, 'quantity' => '1', 'purchase_price' => '100']];

    $a = $service->create(['supplier_id' => $this->supplier->id, 'purchase_date' => '2026-09-01', 'payment_status' => 'paid', 'status' => 'completed'], $item, null);
    $b = $service->create(['supplier_id' => $other->id, 'purchase_date' => '2026-09-15', 'payment_status' => 'unpaid', 'status' => 'draft'], $item, null);
    $this->actingAs(userWith('Admin'));

    $this->get('/purchases?search=PUR-000002')->assertSee($b->purchase_number)->assertDontSee($a->purchase_number);
    $this->get('/purchases?search=karachi')->assertSee($b->purchase_number)->assertDontSee($a->purchase_number);
    $this->get("/purchases?supplier={$this->supplier->id}")->assertSee($a->purchase_number)->assertDontSee($b->purchase_number);
    $this->get('/purchases?status=draft')->assertSee($b->purchase_number)->assertDontSee($a->purchase_number);
    $this->get('/purchases?payment_status=paid')->assertSee($a->purchase_number)->assertDontSee($b->purchase_number);
    $this->get('/purchases?from=2026-09-10')->assertSee($b->purchase_number)->assertDontSee($a->purchase_number);
    $this->get('/purchases?to=2026-09-10')->assertSee($a->purchase_number)->assertDontSee($b->purchase_number);
    $this->get('/purchases?from=2026-09-20&to=2026-09-01')->assertSessionHasErrors('to');
});

test('purchase history is paginated', function () {
    Purchase::factory()->count(18)->for($this->supplier)->create();

    $this->actingAs(userWith('Admin'))
        ->get('/purchases')
        ->assertViewHas('purchases', fn ($purchases) => $purchases->count() === 15 && $purchases->total() === 18);
});

test('the purchase details page shows supplier, items and totals', function () {
    $this->actingAs(userWith('Admin'))->post('/purchases', purchasePayload());
    $purchase = Purchase::sole();

    $this->get("/purchases/{$purchase->id}")
        ->assertSeeInOrder(['Basmati Rice', 'Cooking Oil'])
        ->assertSee('2.5')
        ->assertSee('$2,881.25')
        ->assertSee('Metro Wholesale')
        ->assertSee($this->supplier->phone)
        ->assertSee('Completed')
        ->assertSee('Unpaid');
});

test('suppliers show their purchase history', function () {
    $this->actingAs(userWith('Admin'))->post('/purchases', purchasePayload());

    $this->get("/suppliers/{$this->supplier->id}")
        ->assertSee('PUR-000001')
        ->assertDontSee('No purchases found for this supplier.');
});

test('archived products stay visible on past purchases', function () {
    $this->actingAs(userWith('Admin'))->post('/purchases', purchasePayload());
    $this->rice->delete();

    $this->get('/purchases/'.Purchase::sole()->id)->assertOk()->assertSee('Basmati Rice')->assertSee('Archived');
});

test('purchases do not create any inventory records', function () {
    $this->actingAs(userWith('Admin'))->post('/purchases', purchasePayload())->assertSessionHasNoErrors();

    expect(Schema::hasTable('stock_movements'))->toBeFalse()
        ->and(Schema::hasColumn('products', 'stock_quantity'))->toBeFalse();
});
