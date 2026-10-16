<?php

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->burgers = Category::factory()->create(['name' => 'Burgers']);
    $this->drinks = Category::factory()->create(['name' => 'Drinks']);

    $this->burger = Product::factory()->for($this->burgers)->create(['name' => 'Burger', 'sku' => 'BRG-1', 'barcode' => '1000000000001', 'selling_price' => 500, 'tax_rate' => 18]);
    $this->coke = Product::factory()->for($this->drinks)->create(['name' => 'Coke', 'sku' => 'COK-1', 'barcode' => '1000000000002', 'selling_price' => 200, 'tax_rate' => 18]);
    $this->fries = Product::factory()->create(['name' => 'Fries', 'sku' => 'FRI-1', 'barcode' => '1000000000003', 'selling_price' => 300, 'tax_rate' => 18]);

    $this->actingAs($this->cashier = userWith('Cashier'));
});

/**
 * The reference cart: Burger x2, Coke x1, Fries x1 with a 100 order discount.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function referenceCart(array $overrides = []): array
{
    return [
        'items' => [
            ['product_id' => test()->burger->id, 'quantity' => 2],
            ['product_id' => test()->coke->id, 'quantity' => 1],
            ['product_id' => test()->fries->id, 'quantity' => 1],
        ],
        'customer_id' => null,
        'order_discount_type' => 'fixed',
        'order_discount_value' => '100',
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function cashCheckout(array $overrides = []): array
{
    return referenceCart(['payment_method' => 'cash', 'cash_received' => '2000', ...$overrides]);
}

// ------------------------------------------------------------------ access

test('the POS screen opens for roles that can sell', function (string $role) {
    $this->actingAs(userWith($role))
        ->get('/pos')
        ->assertOk()
        ->assertSee('data-pos', false)
        ->assertSee('Walk-in Customer')
        ->assertSee('Burgers')
        ->assertSee('Drinks');
})->with(['Super Admin', 'Admin', 'Manager', 'Cashier']);

test('users who cannot sell are refused on every POS endpoint', function () {
    $this->actingAs(userWith('Kitchen Staff'));
    $held = Sale::forceCreate(['status' => SaleStatus::Held]);

    $this->get('/pos')->assertForbidden();
    $this->getJson('/pos/products')->assertForbidden();
    $this->getJson('/pos/lookup?code=1000000000001')->assertForbidden();
    $this->postJson('/pos/quote', referenceCart())->assertForbidden();
    $this->getJson('/pos/held')->assertForbidden();
    $this->postJson('/pos/held', referenceCart())->assertForbidden();
    $this->postJson("/pos/held/{$held->id}/resume")->assertForbidden();
    $this->deleteJson("/pos/held/{$held->id}")->assertForbidden();
    $this->postJson('/sales', cashCheckout())->assertForbidden();

    expect(Sale::count())->toBe(1);
});

test('guests are sent to the login page', function () {
    auth()->logout();

    $this->get('/pos')->assertRedirect('/login');
    $this->postJson('/sales', cashCheckout())->assertUnauthorized();
});

test('the sidebar POS item links to the POS screen', function () {
    $this->get('/dashboard')->assertSee('href="'.route('pos.index').'"', false);
});

// ------------------------------------------------------------------ products, search, barcode, categories

test('the product grid lists sellable products', function () {
    Product::factory()->inactive()->create(['name' => 'Old Burger']);
    Product::factory()->for(Category::factory()->create(['is_active' => false]))->create(['name' => 'Hidden Shake']);
    Product::factory()->create(['name' => 'Archived Wrap'])->delete();

    $names = collect($this->getJson('/pos/products')->assertOk()->json('products'))->pluck('name');

    expect($names->all())->toBe(['Burger', 'Coke', 'Fries']);
});

test('products can be searched by name, SKU and barcode', function (string $term, array $expected) {
    Product::factory()->create(['name' => 'Burger Cheese', 'selling_price' => 650]);
    Product::factory()->create(['name' => 'Burger Chicken', 'selling_price' => 700]);

    $names = collect($this->getJson('/pos/products?search='.urlencode($term))->assertOk()->json('products'))->pluck('name');

    expect($names->all())->toBe($expected);
})->with([
    'name' => ['Burger', ['Burger', 'Burger Cheese', 'Burger Chicken']],
    'sku' => ['COK-1', ['Coke']],
    'barcode' => ['1000000000003', ['Fries']],
    'no match' => ['Pizza', []],
]);

test('products can be filtered by category', function () {
    $names = fn (string $query) => collect($this->getJson("/pos/products{$query}")->json('products'))->pluck('name')->all();

    expect($names("?category={$this->drinks->id}"))->toBe(['Coke'])
        ->and($names("?category={$this->burgers->id}"))->toBe(['Burger'])
        ->and($names(''))->toBe(['Burger', 'Coke', 'Fries']);
});

test('product cards carry price, variants and add-ons', function () {
    $meal = Product::factory()->create(['name' => 'Meal', 'selling_price' => 800, 'discount_type' => 'percent', 'discount_value' => 10]);
    ProductVariant::factory()->for($meal)->create(['name' => 'Large', 'selling_price' => 1000]);
    ProductVariant::factory()->for($meal)->create(['name' => 'Retired', 'is_active' => false]);
    $group = ModifierGroup::factory()->create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 2]);
    Modifier::factory()->for($group, 'group')->create(['name' => 'Cheese', 'price' => 100]);
    $meal->modifierGroups()->attach($group);

    $card = collect($this->getJson('/pos/products?search=Meal')->json('products'))->firstWhere('name', 'Meal');

    expect($card['price'])->toBe('720.00')                                    // product discount applied
        ->and($card['variants'])->toHaveCount(1)
        ->and($card['variants'][0])->toMatchArray(['name' => 'Large', 'price' => '900.00'])
        ->and($card['modifier_groups'][0]['modifiers'][0])->toMatchArray(['name' => 'Cheese', 'price' => '100.00']);
});

test('a scanned barcode finds the product', function () {
    $this->getJson('/pos/lookup?code=1000000000002')
        ->assertOk()
        ->assertJsonPath('product.name', 'Coke')
        ->assertJsonPath('variant_id', null);
});

test('a scanned variant barcode finds the variant', function () {
    $variant = ProductVariant::factory()->for($this->burger)->create(['name' => 'Double', 'barcode' => '2000000000009', 'selling_price' => 750]);

    $this->getJson('/pos/lookup?code=2000000000009')
        ->assertOk()
        ->assertJsonPath('product.name', 'Burger')
        ->assertJsonPath('variant_id', $variant->id);
});

test('an unknown barcode says product not found', function (string $code) {
    Product::factory()->inactive()->create(['barcode' => '3000000000000']);

    $this->getJson('/pos/lookup?code='.$code)
        ->assertNotFound()
        ->assertJson(['message' => 'Product not found.']);
})->with(['9999999999999', '3000000000000', '']);

// ------------------------------------------------------------------ pricing (quote)

test('the quote calculates the reference cart on the server', function () {
    $this->postJson('/pos/quote', referenceCart())
        ->assertOk()
        ->assertJson([
            'subtotal' => '1500.00',
            'discount' => '100.00',
            'tax' => '252.00',
            'total' => '1652.00',
        ])
        ->assertJsonPath('lines.0.total', '1000.00');
});

test('prices and totals sent by the browser are ignored', function () {
    $cart = referenceCart();
    $cart['items'][0] += ['unit_price' => '1', 'total' => '1', 'tax_rate' => '0'];
    $cart += ['subtotal' => '1', 'total' => '1', 'tax' => '0'];

    $this->postJson('/pos/quote', $cart)->assertOk()->assertJsonPath('total', '1652.00');
});

test('adding the same product on two rows prices both', function () {
    $this->postJson('/pos/quote', ['items' => [
        ['product_id' => $this->coke->id, 'quantity' => 1],
        ['product_id' => $this->coke->id, 'quantity' => 2],
    ]])->assertOk()->assertJsonPath('subtotal', '600.00');
});

test('item discounts are fixed or percentage and validated', function () {
    $this->postJson('/pos/quote', ['items' => [
        ['product_id' => $this->burger->id, 'quantity' => 1, 'discount_type' => 'fixed', 'discount_value' => '50'],
        ['product_id' => $this->coke->id, 'quantity' => 2, 'discount_type' => 'percent', 'discount_value' => '10'],
    ]])
        ->assertOk()
        ->assertJsonPath('lines.0.total', '450.00')
        ->assertJsonPath('lines.1.discount', '40.00')
        ->assertJsonPath('discount', '90.00');

    $this->postJson('/pos/quote', ['items' => [['product_id' => $this->burger->id, 'quantity' => 1, 'discount_type' => 'fixed', 'discount_value' => '501']]])
        ->assertUnprocessable()->assertJsonValidationErrors('items.0.discount_value');

    $this->postJson('/pos/quote', ['items' => [['product_id' => $this->burger->id, 'quantity' => 1, 'discount_type' => 'percent', 'discount_value' => '101']]])
        ->assertUnprocessable()->assertJsonValidationErrors('items.0.discount_value');

    $this->postJson('/pos/quote', ['items' => [['product_id' => $this->burger->id, 'quantity' => 1, 'discount_type' => 'fixed', 'discount_value' => '-5']]])
        ->assertUnprocessable()->assertJsonValidationErrors('items.0.discount_value');
});

test('order discounts are fixed or percentage and validated', function () {
    $this->postJson('/pos/quote', referenceCart(['order_discount_type' => 'percent', 'order_discount_value' => '10']))
        ->assertOk()
        ->assertJson(['discount' => '150.00', 'tax' => '243.00', 'total' => '1593.00']);

    $this->postJson('/pos/quote', referenceCart(['order_discount_value' => '1500.01']))
        ->assertUnprocessable()->assertJsonValidationErrors('order_discount_value');

    $this->postJson('/pos/quote', referenceCart(['order_discount_type' => 'percent', 'order_discount_value' => '100.5']))
        ->assertUnprocessable()->assertJsonValidationErrors('order_discount_value');
});

test('tax uses each product tax rate', function () {
    $water = Product::factory()->create(['selling_price' => 100, 'tax_rate' => 0]);

    $this->postJson('/pos/quote', ['items' => [
        ['product_id' => $this->coke->id, 'quantity' => 1],
        ['product_id' => $water->id, 'quantity' => 1],
    ]])->assertOk()->assertJson(['tax' => '36.00', 'total' => '336.00']);
});

test('cart rows are validated', function (array $items, string $error) {
    $this->postJson('/pos/quote', ['items' => $items])->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'empty cart' => [[], 'items'],
    'zero quantity' => [[['product_id' => 1, 'quantity' => 0]], 'items.0.quantity'],
    'fractional quantity' => [[['product_id' => 1, 'quantity' => 1.5]], 'items.0.quantity'],
    'huge quantity' => [[['product_id' => 1, 'quantity' => 10000]], 'items.0.quantity'],
    'unknown product' => [[['product_id' => 999, 'quantity' => 1]], 'items.0.product_id'],
]);

test('products that cannot be sold are rejected', function () {
    $inactive = Product::factory()->inactive()->create();
    $archived = Product::factory()->create();
    $archived->delete();

    $this->postJson('/pos/quote', ['items' => [
        ['product_id' => $inactive->id, 'quantity' => 1],
        ['product_id' => $archived->id, 'quantity' => 1],
    ]])->assertUnprocessable()->assertJsonValidationErrors(['items.0.product_id', 'items.1.product_id']);
});

test('variant products must be sold as one of their active variants at the variant price', function () {
    $large = ProductVariant::factory()->for($this->burger)->create(['name' => 'Large', 'selling_price' => 700]);
    $inherits = ProductVariant::factory()->for($this->burger)->create(['name' => 'Regular', 'selling_price' => null]);
    $retired = ProductVariant::factory()->for($this->burger)->create(['name' => 'Retired', 'is_active' => false]);
    $other = ProductVariant::factory()->for($this->coke)->create(['name' => 'Can']);

    $quote = fn (?int $variant) => $this->postJson('/pos/quote', ['items' => [['product_id' => $this->burger->id, 'variant_id' => $variant, 'quantity' => 1]]]);

    $quote($large->id)->assertOk()->assertJsonPath('subtotal', '700.00');
    $quote($inherits->id)->assertOk()->assertJsonPath('subtotal', '500.00');
    $quote(null)->assertUnprocessable()->assertJsonValidationErrors('items.0.variant_id');
    $quote($retired->id)->assertUnprocessable()->assertJsonValidationErrors('items.0.variant_id');
    $quote($other->id)->assertUnprocessable()->assertJsonValidationErrors('items.0.variant_id');

    $this->postJson('/pos/quote', ['items' => [['product_id' => $this->fries->id, 'variant_id' => $large->id, 'quantity' => 1]]])
        ->assertUnprocessable()->assertJsonValidationErrors('items.0.variant_id');
});

test('modifier prices are added to the item and selection rules are enforced', function () {
    $extras = ModifierGroup::factory()->create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 2]);
    $cheese = Modifier::factory()->for($extras, 'group')->create(['name' => 'Cheese', 'price' => 100]);
    $patty = Modifier::factory()->for($extras, 'group')->create(['name' => 'Extra Patty', 'price' => 250]);
    $sauce = Modifier::factory()->for($extras, 'group')->create(['name' => 'Sauce', 'price' => 0]);
    $this->burger->modifierGroups()->attach($extras);

    $size = ModifierGroup::factory()->create(['name' => 'Bun', 'min_select' => 1, 'max_select' => 1]);
    $plain = Modifier::factory()->for($size, 'group')->create(['name' => 'Plain', 'price' => 0]);
    $this->burger->modifierGroups()->attach($size);

    $unrelated = Modifier::factory()->for(ModifierGroup::factory(), 'group')->create();

    $quote = fn (array $ids) => $this->postJson('/pos/quote', ['items' => [['product_id' => $this->burger->id, 'quantity' => 2, 'modifier_ids' => $ids]]]);

    $quote([$cheese->id, $patty->id, $plain->id])->assertOk()
        ->assertJsonPath('lines.0.unit_price', '850.00')
        ->assertJsonPath('subtotal', '1700.00');

    $quote([$cheese->id])->assertUnprocessable()->assertJsonValidationErrors('items.0.modifier_ids');                          // required Bun missing
    $quote([$cheese->id, $patty->id, $sauce->id, $plain->id])->assertUnprocessable()->assertJsonValidationErrors('items.0.modifier_ids'); // max 2 extras
    $quote([$plain->id, $unrelated->id])->assertUnprocessable()->assertJsonValidationErrors('items.0.modifier_ids');           // not on this product
});

test('only active customers can be selected', function () {
    $inactive = Customer::factory()->create(['status' => 'inactive']);

    $this->postJson('/pos/quote', referenceCart(['customer_id' => $inactive->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('customer_id');
});

// ------------------------------------------------------------------ checkout

test('the reference cash sale is saved exactly', function () {
    $this->postJson('/sales', cashCheckout())
        ->assertCreated()
        ->assertJsonPath('sale.invoice_number', 'INV-000001')
        ->assertJsonPath('sale.total', '1652.00')
        ->assertJsonPath('sale.change_amount', '348.00');

    $sale = Sale::sole();

    expect($sale)
        ->invoice_number->toBe('INV-000001')
        ->status->toBe(SaleStatus::Completed)
        ->customer_id->toBeNull()
        ->user_id->toBe($this->cashier->id)
        ->payment_method->toBe(PaymentMethod::Cash)
        ->completed_at->not->toBeNull();

    expect([$sale->subtotal, $sale->discount, $sale->tax, $sale->total, $sale->cash_received, $sale->change_amount])
        ->toBe(['1500.00', '100.00', '252.00', '1652.00', '2000.00', '348.00']);

    expect($sale->items->map(fn (SaleItem $i) => [$i->product_name, $i->quantity, (string) $i->unit_price, (string) $i->total])->all())
        ->toBe([
            ['Burger', 2, '500.00', '1000.00'],
            ['Coke', 1, '200.00', '200.00'],
            ['Fries', 1, '300.00', '300.00'],
        ]);

    expect($sale->items->sum(fn (SaleItem $i) => (float) $i->tax))->toEqual(252.0);

    expect($sale->payments->map(fn (SalePayment $p) => [$p->payment_method, (string) $p->amount])->all())
        ->toBe([[PaymentMethod::Cash, '1652.00']]);
});

test('invoice numbers are sequential and unique', function () {
    $this->postJson('/sales', cashCheckout())->assertCreated();
    $this->postJson('/pos/held', referenceCart())->assertCreated(); // held sales take no number
    $this->postJson('/sales', cashCheckout())->assertCreated();

    expect(Sale::completed()->orderBy('id')->pluck('invoice_number')->all())->toBe(['INV-000001', 'INV-000002']);

    expect(fn () => Sale::forceCreate(['status' => SaleStatus::Completed, 'invoice_number' => 'INV-000001']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('a card payment records the total on the card', function () {
    $this->postJson('/sales', referenceCart(['payment_method' => 'card', 'card_amount' => '1652']))->assertCreated();

    $sale = Sale::sole();
    expect($sale->payment_method)->toBe(PaymentMethod::Card)
        ->and($sale->cash_received)->toBeNull()
        ->and((string) $sale->change_amount)->toBe('0.00')
        ->and($sale->payments->map(fn ($p) => [$p->payment_method->value, (string) $p->amount])->all())->toBe([['card', '1652.00']]);
});

test('a card amount that differs from the total is refused', function () {
    $this->postJson('/sales', referenceCart(['payment_method' => 'card', 'card_amount' => '1600']))
        ->assertUnprocessable()->assertJsonValidationErrors('card_amount');

    $this->postJson('/sales', referenceCart(['payment_method' => 'card']))
        ->assertUnprocessable()->assertJsonValidationErrors('card_amount');

    expect(Sale::count())->toBe(0);
});

test('a mixed payment saves one row per method', function () {
    $this->postJson('/sales', referenceCart(['payment_method' => 'mixed', 'cash_amount' => '1000', 'card_amount' => '652']))->assertCreated();

    $sale = Sale::sole();
    expect($sale->payment_method)->toBe(PaymentMethod::Mixed)
        ->and($sale->payments->map(fn ($p) => [$p->payment_method->value, (string) $p->amount])->all())
        ->toBe([['cash', '1000.00'], ['card', '652.00']]);
});

test('a mixed payment must balance', function (array $payment, string $error) {
    $this->postJson('/sales', referenceCart(['payment_method' => 'mixed', ...$payment]))
        ->assertUnprocessable()->assertJsonValidationErrors($error);

    expect(Sale::count())->toBe(0)->and(SalePayment::count())->toBe(0);
})->with([
    'short' => [['cash_amount' => '1000', 'card_amount' => '600'], 'cash_amount'],
    'over' => [['cash_amount' => '1000', 'card_amount' => '700'], 'cash_amount'],
    'card only' => [['cash_amount' => '0', 'card_amount' => '1652'], 'cash_amount'],
    'missing card' => [['cash_amount' => '1652'], 'card_amount'],
]);

test('insufficient cash is refused and nothing is saved', function () {
    $this->postJson('/sales', cashCheckout(['cash_received' => '1651.99']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cash_received');

    $this->postJson('/sales', cashCheckout(['cash_received' => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cash_received');

    expect(Sale::count())->toBe(0)->and(SaleItem::count())->toBe(0);
});

test('exact cash gives zero change', function () {
    $this->postJson('/sales', cashCheckout(['cash_received' => '1652']))->assertCreated()->assertJsonPath('sale.change_amount', '0.00');
});

test('an empty cart cannot be checked out', function () {
    $this->postJson('/sales', ['items' => [], 'payment_method' => 'cash', 'cash_received' => '10'])
        ->assertUnprocessable()->assertJsonValidationErrors('items');
});

test('an invalid payment method is refused', function () {
    $this->postJson('/sales', referenceCart(['payment_method' => 'bitcoin']))
        ->assertUnprocessable()->assertJsonValidationErrors('payment_method');
});

test('the selected customer is saved on the sale', function () {
    $ali = Customer::factory()->create(['name' => 'Ali']);

    $this->postJson('/sales', cashCheckout(['customer_id' => $ali->id]))->assertCreated();

    expect(Sale::sole()->customer->name)->toBe('Ali');
    $this->actingAs(userWith('Manager'))->get("/customers/{$ali->id}")->assertOk()->assertSee('INV-000001');
});

test('line discounts, modifiers and variants are snapshotted on the sale', function () {
    $large = ProductVariant::factory()->for($this->burger)->create(['name' => 'Large', 'sku' => 'BRG-L', 'selling_price' => 600]);
    $group = ModifierGroup::factory()->create(['name' => 'Extras']);
    $cheese = Modifier::factory()->for($group, 'group')->create(['name' => 'Cheese', 'price' => 100]);
    $this->burger->modifierGroups()->attach($group);

    $this->postJson('/sales', [
        'items' => [['product_id' => $this->burger->id, 'variant_id' => $large->id, 'quantity' => 2, 'modifier_ids' => [$cheese->id], 'discount_type' => 'percent', 'discount_value' => '10']],
        'payment_method' => 'cash',
        'cash_received' => '5000',
    ])->assertCreated();

    $item = SaleItem::sole();
    expect($item)
        ->product_name->toBe('Burger')
        ->variant_name->toBe('Large')
        ->sku->toBe('BRG-L')
        ->quantity->toBe(2);
    expect([(string) $item->unit_price, (string) $item->modifiers_total, (string) $item->discount, (string) $item->total, (string) $item->tax])
        ->toBe(['700.00', '100.00', '140.00', '1260.00', '226.80']);
    expect($item->modifiers->map->only(['group_name', 'name', 'price'])->all())
        ->toBe([['group_name' => 'Extras', 'name' => 'Cheese', 'price' => '100.00']]);

    // Later catalog changes do not alter the sale.
    $cheese->update(['name' => 'Cheddar', 'price' => 150]);
    $this->burger->update(['name' => 'Burger Deluxe']);
    expect($item->fresh()->product_name)->toBe('Burger')
        ->and($item->modifiers()->first()->name)->toBe('Cheese');
});

test('a failure part-way through checkout saves nothing', function () {
    SalePayment::creating(fn () => throw new RuntimeException('Payment write failed'));

    $this->withoutExceptionHandling();

    expect(fn () => $this->postJson('/sales', cashCheckout()))->toThrow(RuntimeException::class, 'Payment write failed');

    expect(Sale::count())->toBe(0)
        ->and(SaleItem::count())->toBe(0)
        ->and(DB::table('sale_item_modifiers')->count())->toBe(0)
        ->and(DB::table('number_sequences')->where('name', 'invoice')->value('value'))->toBeNull();
});

test('sales do not touch stock, because there is no inventory module', function () {
    $before = $this->burger->fresh()->getAttributes();

    $this->postJson('/sales', cashCheckout())->assertCreated();

    expect($this->burger->fresh()->getAttributes())->toBe($before)
        ->and(Schema::hasTable('stock_movements'))->toBeFalse()
        ->and(Schema::hasColumn('products', 'stock_quantity'))->toBeFalse();
});

// ------------------------------------------------------------------ held sales

test('a sale can be held without an invoice number or payments', function () {
    $ali = Customer::factory()->create(['name' => 'Ali']);

    $this->postJson('/pos/held', referenceCart(['customer_id' => $ali->id]))->assertCreated();

    $held = Sale::sole();
    expect($held->status)->toBe(SaleStatus::Held)
        ->and($held->invoice_number)->toBeNull()
        ->and($held->payments)->toHaveCount(0)
        ->and($held->items)->toHaveCount(3)
        ->and((string) $held->total)->toBe('1652.00');

    $this->getJson('/pos/held')
        ->assertOk()
        ->assertJsonPath('held.0.id', $held->id)
        ->assertJsonPath('held.0.customer', 'Ali')
        ->assertJsonPath('held.0.items.0.name', 'Burger')
        ->assertJsonPath('held.0.items.0.quantity', 2);
});

test('resuming a held sale restores the cart and removes it from the list', function () {
    $ali = Customer::factory()->create(['name' => 'Ali']);
    $cart = referenceCart(['customer_id' => $ali->id]);
    $cart['items'][0] += ['discount_type' => 'fixed', 'discount_value' => '50'];
    $this->postJson('/pos/held', $cart)->assertCreated();
    $held = Sale::sole();

    $this->postJson("/pos/held/{$held->id}/resume")
        ->assertOk()
        ->assertJsonPath('customer_id', $ali->id)
        ->assertJsonPath('order_discount_type', 'fixed')
        ->assertJsonPath('order_discount_value', '100.00')
        ->assertJsonPath('items.0.product_name', 'Burger')
        ->assertJsonPath('items.0.quantity', 2)
        ->assertJsonPath('items.0.discount_type', 'fixed')
        ->assertJsonPath('items.0.discount_value', '50.00')
        ->assertJsonPath('items.1.product_name', 'Coke')
        ->assertJsonPath('items.2.product_name', 'Fries')
        ->assertJsonPath('dropped', []);

    expect(Sale::count())->toBe(0);

    // A second till cannot resume the same sale.
    $this->postJson("/pos/held/{$held->id}/resume")->assertNotFound();
});

test('resuming drops items that can no longer be sold', function () {
    $this->postJson('/pos/held', referenceCart())->assertCreated();
    $this->coke->update(['is_active' => false]);

    $this->postJson('/pos/held/'.Sale::sole()->id.'/resume')
        ->assertOk()
        ->assertJsonCount(2, 'items')
        ->assertJsonPath('dropped', ['Coke']);
});

test('a held sale can be discarded', function () {
    $this->postJson('/pos/held', referenceCart())->assertCreated();
    $held = Sale::sole();

    $this->deleteJson("/pos/held/{$held->id}")->assertOk();

    expect(Sale::count())->toBe(0)->and(SaleItem::count())->toBe(0);
});

test('completed sales cannot be resumed or discarded as held sales', function () {
    $this->postJson('/sales', cashCheckout())->assertCreated();
    $sale = Sale::sole();

    $this->postJson("/pos/held/{$sale->id}/resume")->assertNotFound();
    $this->deleteJson("/pos/held/{$sale->id}")->assertNotFound();

    expect($sale->fresh()->status)->toBe(SaleStatus::Completed);
});

test('holding validates the cart like checkout does', function () {
    $this->postJson('/pos/held', ['items' => []])->assertUnprocessable()->assertJsonValidationErrors('items');
    expect(Sale::count())->toBe(0);
});

// ------------------------------------------------------------------ receipt

test('checkout returns a basic receipt', function () {
    $response = $this->postJson('/sales', cashCheckout())->assertCreated();
    $html = $response->json('receipt_html');

    expect($html)->toContain('INV-000001', 'Burger', 'Coke', 'Fries', '1,500', '100', '252', '1,652', 'Cash', '2,000', '348', 'Walk-in Customer')
        ->and($response->json('receipt_url'))->toBe(route('sales.receipt', Sale::sole()));
});

test('the receipt page shows a completed sale', function () {
    $this->postJson('/sales', referenceCart(['payment_method' => 'mixed', 'cash_amount' => '1000', 'card_amount' => '652']))->assertCreated();
    $sale = Sale::sole();

    $this->get("/sales/{$sale->id}/receipt")
        ->assertOk()
        ->assertSeeInOrder(['INV-000001', 'Burger', 'Coke', 'Fries', 'Subtotal', '1,500', 'Discount', '100', 'Tax', '252', 'Total Price', '1,652', 'Mixed', 'Cash', '1,000', 'Card', '652']);
});

test('only completed sales have a receipt, and only for users who can view sales', function () {
    $held = Sale::forceCreate(['status' => SaleStatus::Held]);
    $this->get("/sales/{$held->id}/receipt")->assertNotFound();

    $this->postJson('/sales', cashCheckout())->assertCreated();
    $sale = Sale::completed()->sole();

    $this->actingAs(userWith('Kitchen Staff'))->get("/sales/{$sale->id}/receipt")->assertForbidden();
});

// ------------------------------------------------------------------ customers

test('customers with sales cannot be deleted', function () {
    $ali = Customer::factory()->create(['name' => 'Ali']);
    $this->postJson('/sales', cashCheckout(['customer_id' => $ali->id]))->assertCreated();

    $this->actingAs(userWith('Admin'))
        ->delete("/customers/{$ali->id}")
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Customer::find($ali->id))->not->toBeNull();
});

// ------------------------------------------------------------------ customer details & delivery fee

test('the delivery fee is added to the total without tax or discount', function () {
    $this->postJson('/pos/quote', referenceCart(['order_type' => 'delivery', 'delivery_fee' => '150']))
        ->assertOk()
        ->assertJson(['subtotal' => '1500.00', 'discount' => '100.00', 'tax' => '252.00', 'delivery_fee' => '150.00', 'total' => '1802.00']);
});

test('typed customer details and the delivery fee are saved on the sale and printed', function () {
    $response = $this->postJson('/sales', cashCheckout([
        'customer_name' => 'Usman Khan',
        'customer_phone' => '0300 1234567',
        'customer_address' => "House 12, Street 4\nG-9, Islamabad",
        'order_type' => 'delivery',
        'delivery_fee' => '150',
        'cash_received' => '2000',
    ]))->assertCreated()->assertJsonPath('sale.total', '1802.00')->assertJsonPath('sale.change_amount', '198.00');

    $sale = Sale::sole();
    expect($sale)
        ->customer_id->toBeNull()
        ->customer_name->toBe('Usman Khan')
        ->customer_phone->toBe('0300 1234567')
        ->customer_address->toBe("House 12, Street 4\nG-9, Islamabad");
    expect([(string) $sale->delivery_fee, (string) $sale->total])->toBe(['150.00', '1802.00']);
    expect(Customer::count())->toBe(0); // not saved unless asked

    expect($response->json('receipt_html'))->toContain('Usman Khan', '0300 1234567', 'G-9, Islamabad', 'Delivery Charges', '150', '1,802');
});

test('a selected customer fills in blank details, and typed details win', function () {
    $ali = Customer::factory()->create(['name' => 'Ali', 'phone' => '0311 5555555', 'address' => 'F-7 Markaz']);

    $this->postJson('/sales', cashCheckout(['customer_id' => $ali->id]))->assertCreated();
    $this->postJson('/sales', cashCheckout(['customer_id' => $ali->id, 'customer_address' => 'Office, Blue Area']))->assertCreated();

    [$first, $second] = Sale::orderBy('id')->get();
    expect([$first->customer_name, $first->customer_phone, $first->customer_address])->toBe(['Ali', '0311 5555555', 'F-7 Markaz'])
        ->and([$second->customer_id, $second->customer_address])->toBe([$ali->id, 'Office, Blue Area']);
});

test('typed details can be saved as a new customer', function () {
    $this->actingAs(userWith('Manager'));
    $this->postJson('/sales', cashCheckout([
        'customer_name' => 'Ahmed',
        'customer_phone' => '0321 7654321',
        'customer_address' => 'DHA Phase 2',
        'save_customer' => true,
    ]))->assertCreated();

    $ahmed = Customer::sole();
    expect([$ahmed->name, $ahmed->phone, $ahmed->address])->toBe(['Ahmed', '0321 7654321', 'DHA Phase 2'])
        ->and(Sale::sole()->customer_id)->toBe($ahmed->id);
});

test('saving a new customer needs a name and permission, and is skipped for an existing customer', function () {
    $this->postJson('/sales', cashCheckout(['customer_phone' => '0300 1111111', 'save_customer' => true]))
        ->assertUnprocessable()->assertJsonValidationErrors('customer_name');

    $ali = Customer::factory()->create(['name' => 'Ali']);
    $this->postJson('/sales', cashCheckout(['customer_id' => $ali->id, 'customer_name' => 'Ali R.', 'save_customer' => true]))->assertCreated();
    expect(Customer::count())->toBe(1);

    $seller = User::factory()->create()->givePermissionTo('sales.create');
    $this->actingAs($seller)
        ->postJson('/sales', cashCheckout(['customer_name' => 'Zara', 'save_customer' => true]))
        ->assertUnprocessable()->assertJsonValidationErrors('save_customer');
    expect(Customer::count())->toBe(1);
});

test('customer details and delivery fee are validated', function (array $input, string $error) {
    $this->postJson('/pos/quote', referenceCart($input))->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'bad phone' => [['customer_phone' => 'call me'], 'customer_phone'],
    'long name' => [['customer_name' => str_repeat('a', 151)], 'customer_name'],
    'long address' => [['customer_address' => str_repeat('a', 501)], 'customer_address'],
    'negative fee' => [['delivery_fee' => '-1'], 'delivery_fee'],
    'fee with 3 decimals' => [['delivery_fee' => '1.005'], 'delivery_fee'],
]);

test('payments must cover the delivery fee', function () {
    $this->postJson('/sales', cashCheckout(['order_type' => 'delivery', 'delivery_fee' => '150', 'cash_received' => '1652']))
        ->assertUnprocessable()->assertJsonValidationErrors('cash_received');

    $this->postJson('/sales', referenceCart(['order_type' => 'delivery', 'delivery_fee' => '150', 'payment_method' => 'mixed', 'cash_amount' => '1000', 'card_amount' => '802']))
        ->assertCreated();
});

test('held sales keep the customer details and delivery fee', function () {
    $this->postJson('/pos/held', referenceCart([
        'customer_name' => 'Usman', 'customer_phone' => '0300 1234567', 'customer_address' => 'G-9', 'order_type' => 'delivery', 'delivery_fee' => '150',
    ]))->assertCreated();

    $this->getJson('/pos/held')
        ->assertJsonPath('held.0.customer', 'Usman')
        ->assertJsonPath('held.0.phone', '0300 1234567')
        ->assertJsonPath('held.0.delivery', true)
        ->assertJsonPath('held.0.total', '1802.00');

    $this->postJson('/pos/held/'.Sale::sole()->id.'/resume')
        ->assertOk()
        ->assertJson(['customer_name' => 'Usman', 'customer_phone' => '0300 1234567', 'customer_address' => 'G-9', 'delivery_fee' => '150.00']);
});
