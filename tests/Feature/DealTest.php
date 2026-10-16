<?php

use App\Models\DealItem;
use App\Models\KitchenOrder;
use App\Models\KitchenStation;
use App\Models\Product;
use App\Models\Sale;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->grill = KitchenStation::factory()->create(['name' => 'Grill']);

    $this->zingerBurger = Product::factory()->create([
        'name' => 'Zinger Burger',
        'selling_price' => 600,
        'kitchen_station_id' => $this->grill->id,
    ]);

    $this->fries = Product::factory()->create([
        'name' => 'Fries',
        'selling_price' => 250,
        'kitchen_station_id' => $this->grill->id,
    ]);

    $this->coke = Product::factory()->create([
        'name' => 'Coke',
        'selling_price' => 150,
        'kitchen_station_id' => $this->grill->id,
    ]);

    // Zinger Combo = 1× Zinger Burger + 1× Fries + 1× Coke, sold at fixed price.
    $this->zingerCombo = Product::factory()->create([
        'name' => 'Zinger Combo',
        'selling_price' => 899,
        'is_deal' => true,
    ]);

    DealItem::create(['product_id' => $this->zingerCombo->id, 'component_id' => $this->zingerBurger->id, 'quantity' => 1, 'sort_order' => 0]);
    DealItem::create(['product_id' => $this->zingerCombo->id, 'component_id' => $this->fries->id, 'quantity' => 1, 'sort_order' => 1]);
    DealItem::create(['product_id' => $this->zingerCombo->id, 'component_id' => $this->coke->id, 'quantity' => 1, 'sort_order' => 2]);

    $this->actingAs(userWith('Cashier'));
});

// ---------------------------------------------------------------- model

test('a deal product can be created with components', function () {
    expect($this->zingerCombo->is_deal)->toBeTrue()
        ->and($this->zingerCombo->dealItems()->count())->toBe(3)
        ->and($this->zingerCombo->dealItems->pluck('component_id')->all())
        ->toBe([$this->zingerBurger->id, $this->fries->id, $this->coke->id]);
});

// ---------------------------------------------------------------- validation

test('a deal with no components fails validation', function () {
    $this->actingAs(userWith('Admin'));

    $this->post(route('products.store'), [
        'product_type' => 'simple',
        'name' => 'Empty Deal',
        'selling_price' => '500',
        'is_active' => true,
        'is_deal' => '1',
        'deal_items' => [],
    ])->assertSessionHasErrors('deal_items');
});

// ---------------------------------------------------------------- checkout

test('checking out a deal creates one parent SaleItem and N component SaleItems', function () {
    $this->postJson('/sales', [
        'items' => [['product_id' => $this->zingerCombo->id, 'quantity' => 1]],
        'payment_method' => 'cash',
        'cash_received' => '1000',
    ])->assertCreated();

    $sale = Sale::sole();

    // 4 items total: 1 deal parent + 3 components
    expect($sale->items->count())->toBe(4);

    $parent = $sale->items->firstWhere('product_id', $this->zingerCombo->id);
    expect($parent)->not->toBeNull()
        ->and($parent->parent_sale_item_id)->toBeNull()
        ->and((string) $parent->unit_price)->toBe('899.00');

    $components = $sale->items->filter(fn ($i) => $i->parent_sale_item_id !== null);
    expect($components->count())->toBe(3)
        ->and($components->every(fn ($c) => $c->parent_sale_item_id === $parent->id))->toBeTrue()
        ->and($components->every(fn ($c) => (string) $c->unit_price === '0.00'))->toBeTrue();
});

test('the deal total equals the deal selling_price, not the sum of components', function () {
    $this->postJson('/sales', [
        'items' => [['product_id' => $this->zingerCombo->id, 'quantity' => 1]],
        'payment_method' => 'cash',
        'cash_received' => '1000',
    ])->assertCreated();

    $sale = Sale::sole();
    $parent = $sale->items->firstWhere('product_id', $this->zingerCombo->id);

    // Deal total = deal price (899), not Burger+Fries+Coke = 1000.
    expect((string) $parent->total)->toBe('899.00')
        ->and((string) $sale->subtotal)->toBe('899.00');
});

test('KOT items are created for deal component lines not the deal parent', function () {
    $this->postJson('/sales', [
        'items' => [['product_id' => $this->zingerCombo->id, 'quantity' => 1]],
        'payment_method' => 'cash',
        'cash_received' => '1000',
    ])->assertCreated();

    $kot = KitchenOrder::sole();

    // All 3 components go to the kitchen.
    expect($kot->items()->count())->toBe(3);

    $kotProductIds = $kot->items()->pluck('product_id')->sort()->values()->all();
    $expectedIds = collect([$this->zingerBurger->id, $this->fries->id, $this->coke->id])->sort()->values()->all();
    expect($kotProductIds)->toBe($expectedIds);

    // The deal parent (zingerCombo) is NOT in the KOT.
    expect($kot->items()->where('product_id', $this->zingerCombo->id)->exists())->toBeFalse();
});

test('the receipt HTML contains the deal name and each component name indented', function () {
    $this->postJson('/sales', [
        'items' => [['product_id' => $this->zingerCombo->id, 'quantity' => 1]],
        'payment_method' => 'cash',
        'cash_received' => '1000',
    ])->assertCreated();

    $sale = Sale::sole();

    $this->get("/sales/{$sale->id}/receipt")
        ->assertOk()
        ->assertSee('Zinger Combo')
        ->assertSee('1× Zinger Burger')
        ->assertSee('1× Fries')
        ->assertSee('1× Coke');
});
