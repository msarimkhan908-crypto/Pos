<?php

use App\Models\KitchenOrder;
use App\Models\KitchenStation;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Services\SaleService;
use App\Support\Money;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    $this->actingAs($this->admin = userWith('Admin'));
});

/**
 * A completed Dine In sale with a KOT, for print tests.
 */
function printableSale(): Sale
{
    $grill = KitchenStation::factory()->create(['name' => 'Grill']);
    $burger = Product::factory()->create(['name' => 'Burger', 'selling_price' => 500, 'tax_rate' => 10, 'kitchen_station_id' => $grill->id]);
    $table = RestaurantTable::factory()->create(['name' => 'Table 9']);

    return app(SaleService::class)->checkout([
        'order_type' => 'dine_in',
        'table_id' => $table->id,
        'customer_name' => 'Sara',
        'order_note' => 'Birthday',
        'items' => [['product_id' => $burger->id, 'quantity' => 2, 'note' => 'No onions']],
    ], ['payment_method' => 'cash', 'cash_received' => '2000'], userWith('Cashier'));
}

/**
 * @return array<string, mixed>
 */
function receiptSettings(array $overrides = []): array
{
    return [
        'width' => '80mm', 'font_family' => 'courier-prime', 'heading_font' => '', 'font_size' => 'md', 'font_weight' => 'bold',
        'header_align' => 'center', 'footer_align' => 'center',
        'item_layout' => 'detailed', 'logo_size' => 'md', 'header_text' => '', 'footer_text' => 'Thank you!', 'notes' => '',
        'show_logo' => '1', 'show_address' => '1', 'show_phone' => '1', 'show_email' => '0', 'show_website' => '0',
        'show_tax_number' => '1', 'show_customer' => '1', 'show_cashier' => '1', 'show_order_type' => '1',
        'show_discount' => '1', 'show_tax' => '1', 'show_payment' => '1', 'show_item_notes' => '1',
        'show_footer' => '1', 'show_borders' => '0', 'show_separators' => '1',
        ...$overrides,
    ];
}

/**
 * @return array<string, mixed>
 */
function kotSettings(array $overrides = []): array
{
    return [
        'width' => '80mm', 'font_size' => 'lg', 'item_size' => 'large', 'align' => 'center', 'header_text' => 'KOT (Kitchen)', 'footer_text' => '',
        'show_logo' => '0', 'show_invoice' => '1', 'show_table' => '1', 'show_order_type' => '1', 'show_customer' => '1',
        'show_cashier' => '1', 'show_notes' => '1', 'show_station_headings' => '1', 'show_separators' => '1',
        ...$overrides,
    ];
}

// ------------------------------------------------------------------ access

test('every settings section opens for administrators', function (string $section) {
    $this->get("/settings/{$section}")->assertOk()->assertSee('data-settings-form', false)->assertSee('Save settings');
})->with(['business', 'general', 'appearance', 'dashboard', 'pos', 'receipt', 'kot', 'tax']);

test('managers can view settings but not change them; cashiers cannot see them', function () {
    $this->actingAs(userWith('Manager'));
    $this->get('/settings/business')->assertOk()->assertSee('Only administrators can change them')->assertDontSee('Save settings');
    $this->put('/settings/business', ['name' => 'Hacked'])->assertForbidden();
    $this->put('/settings/receipt', receiptSettings())->assertForbidden();

    $this->actingAs(userWith('Cashier'));
    $this->get('/settings/business')->assertForbidden();
    $this->put('/settings/tax', ['default_rate' => 0, 'label' => 'x'])->assertForbidden();
    $this->postJson('/settings/preview/receipt', receiptSettings())->assertForbidden();

    expect(settings('business.name'))->not->toBe('Hacked');
});

test('the settings pages are linked from the sidebar', function () {
    $this->get('/dashboard')
        ->assertSee('href="'.route('settings.general').'"', false)
        ->assertSee('href="'.route('settings.pos').'"', false)
        ->assertSee('href="'.route('settings.receipt').'"', false)
        ->assertSee('href="'.route('settings.tax').'"', false)
        ->assertDontSee('Inventory');
});

// ------------------------------------------------------------------ business & logo

test('business information and the logo are saved', function () {
    $this->put('/settings/business', [
        'name' => 'Karachi Grill', 'address' => 'Shop 4, Tariq Road', 'phone' => '021 1234567',
        'email' => 'hello@karachigrill.test', 'website' => 'https://karachigrill.test', 'tax_number' => 'NTN-123',
        'registration_number' => 'REG-9', 'logo' => UploadedFile::fake()->image('logo.png', 300, 120),
    ])->assertRedirect('/settings/business')->assertSessionHas('success');

    $business = settings()->business();
    expect($business)->name->toBe('Karachi Grill')->tax_number->toBe('NTN-123')->phone->toBe('021 1234567');
    Storage::disk('public')->assertExists($business['logo']);

    $this->get('/dashboard')->assertSee('Karachi Grill')->assertSee('data-business-logo', false);

    // Removing the logo deletes the file.
    $this->put('/settings/business', ['name' => 'Karachi Grill', 'remove_logo' => '1'])->assertRedirect();
    expect(settings('business.logo'))->toBeNull();
    Storage::disk('public')->assertMissing($business['logo']);
});

test('logo uploads are validated', function (Closure $file, string $message) {
    $this->put('/settings/business', ['name' => 'Shop', 'logo' => $file()])->assertSessionHasErrors('logo');
    expect(settings('business.logo'))->toBeNull();
})->with([
    'not an image' => [fn () => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'), 'type'],
    'svg' => [fn () => UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml'), 'type'],
    'too large' => [fn () => UploadedFile::fake()->image('logo.png', 100, 100)->size(6000), 'size'],
    'too many pixels' => [fn () => UploadedFile::fake()->image('logo.png', 4100, 10), 'dimensions'],
]);

test('business fields are validated', function () {
    $this->put('/settings/business', ['name' => '', 'email' => 'nope', 'website' => 'javascript:alert(1)', 'phone' => 'call me'])
        ->assertSessionHasErrors(['name', 'email', 'website', 'phone']);
});

// ------------------------------------------------------------------ general, appearance, tax, dashboard

test('general settings change currency, formats, timezone and number prefixes', function () {
    $this->put('/settings/general', [
        'business_day_start' => '00:00',
        'currency' => 'PKR', 'currency_symbol' => 'Rs', 'date_format' => 'd/m/Y', 'time_format' => 'h:i A',
        'timezone' => 'Asia/Karachi', 'invoice_prefix' => 'KG-', 'purchase_prefix' => 'PO-', 'return_prefix' => 'RT-',
        'expense_prefix' => 'EX-', 'kot_prefix' => 'K-',
    ])->assertRedirect()->assertSessionHas('success');

    expect(Money::format(1652))->toBe('Rs 1,652.00');

    $sale = printableSale();
    expect($sale->invoice_number)->toBe('KG-000001')
        ->and($sale->kitchenOrders()->first()->kot_number)->toBe('K-1');

    $this->get('/dashboard');
    expect(date_default_timezone_get())->toBe('Asia/Karachi');

    $this->get("/sales/{$sale->id}/receipt")->assertSee('Rs '.number_format((float) $sale->total, 2))->assertSee($sale->completed_at->format('d/m/Y'));
});

test('general settings are validated', function () {
    $this->put('/settings/general', [
        'currency' => 'rupees', 'date_format' => 'Y', 'time_format' => 'H', 'timezone' => 'Mars/Base',
        'invoice_prefix' => '<script>', 'purchase_prefix' => '', 'return_prefix' => 'OK-', 'expense_prefix' => 'OK-', 'kot_prefix' => 'OK-',
    ])->assertSessionHasErrors(['currency', 'date_format', 'time_format', 'timezone', 'invoice_prefix', 'purchase_prefix']);
});

test('appearance colours are applied to every page through CSS variables', function () {
    $this->put('/settings/appearance', [
        'theme' => 'custom', 'primary_color' => '#FF0000', 'secondary_color' => '#00aa00', 'button_color' => '#0000ff',
        'sidebar_color' => '#111827', 'sidebar_text_color' => '#d1d5db',
    ])->assertRedirect();

    expect(settings('appearance.primary_color'))->toBe('#ff0000');

    $this->get('/dashboard')
        ->assertSee('--color-primary-600: 255 0 0;', false)
        ->assertSee('--color-btn-600: 0 0 255;', false)
        ->assertSee('--sidebar-bg: 17 24 39;', false);

    $this->put('/settings/appearance', ['theme' => 'neon', 'primary_color' => 'red', 'secondary_color' => '#00aa00', 'button_color' => '#0000ff', 'sidebar_color' => '#fff', 'sidebar_text_color' => '#000000'])
        ->assertSessionHasErrors(['theme', 'primary_color', 'sidebar_color']);
});

test('the default tax rate is used for new products and the tax label on receipts', function () {
    $this->put('/settings/tax', ['default_rate' => '17', 'label' => 'GST'])->assertRedirect();

    $this->get('/products/create')->assertSee('value="17.00"', false);

    $sale = printableSale();
    $this->get("/sales/{$sale->id}/receipt")->assertSee('GST');

    $this->put('/settings/tax', ['default_rate' => '101', 'label' => ''])->assertSessionHasErrors(['default_rate', 'label']);
});

test('dashboard widgets can be switched off', function () {
    $this->get('/dashboard')->assertSee("Today's Purchases")->assertSee('Recent Purchases');

    $this->put('/settings/dashboard', ['widgets' => ['sales', 'customers']])->assertRedirect();

    $this->get('/dashboard')
        ->assertSee("Today's Sales")
        ->assertSee('Total Customers')
        ->assertDontSee("Today's Purchases")
        ->assertDontSee('Recent Purchases')
        ->assertDontSee('Sales Overview');

    $this->put('/settings/dashboard', ['widgets' => ['bogus']])->assertSessionHasErrors('widgets.0');
});

test('printing settings are saved and passed to the POS', function () {
    $this->put('/settings/pos', [
        'auto_receipt' => '0', 'auto_kot' => '1', 'layout' => 'separate',
        'bg_color' => '#f3f4f6', 'header_bg_color' => '#ffffff', 'cart_bg_color' => '#ffffff',
    ])->assertRedirect()->assertSessionHas('success');

    expect(settings('print.auto_receipt'))->toBeFalse()
        ->and(settings('print.auto_kot'))->toBeTrue()
        ->and(settings('print.layout'))->toBe('separate');

    $this->put('/settings/pos', ['layout' => 'triple'])->assertSessionHasErrors('layout');
});

// ------------------------------------------------------------------ receipt & KOT design

test('the customer receipt follows the receipt settings', function () {
    $sale = printableSale();
    settings()->set(['business.name' => 'Karachi Grill', 'business.address' => 'Tariq Road', 'business.phone' => '021 111']);

    $this->get("/sales/{$sale->id}/receipt")
        ->assertSee('paper-80mm', false)
        ->assertSeeInOrder(['Karachi Grill', 'Tariq Road', '021 111', 'Dine In', 'Table 9', 'Invoice #', $sale->invoice_number, 'Cashier', 'Customer', 'Sara', 'Burger', 'No onions', 'Subtotal', 'Tax', 'Total Price', 'Payment', 'Change', 'Thank you for your purchase!']);

    $this->put('/settings/receipt', receiptSettings([
        'width' => '58mm', 'font_size' => 'lg', 'footer_text' => 'Come again soon', 'notes' => 'No refunds after 7 days',
        'header_text' => 'Best burgers in town', 'show_cashier' => '0', 'show_address' => '0', 'show_payment' => '0',
        'show_item_notes' => '0', 'item_layout' => 'compact', 'header_align' => 'left', 'show_borders' => '1',
    ]))->assertRedirect('/settings/receipt');

    $this->get("/sales/{$sale->id}/receipt")
        ->assertSee('paper-58mm', false)
        ->assertSee('paper-text-lg', false)
        ->assertSee('paper-bordered', false)
        ->assertSee('Best burgers in town')
        ->assertSee('Come again soon')
        ->assertSee('No refunds after 7 days')
        ->assertDontSee('Tariq Road')
        ->assertDontSee('Cashier')
        ->assertDontSee('Change')
        ->assertDontSee('No onions')
        ->assertDontSee('2 × 500.00');

    $this->put('/settings/receipt', receiptSettings(['width' => 'a4']))->assertRedirect();
    $this->get("/sales/{$sale->id}/receipt")->assertSee('paper-a4', false);
});

test('receipt settings are validated, including the header image', function () {
    $this->put('/settings/receipt', receiptSettings(['width' => '120mm', 'font_size' => 'xl', 'header_align' => 'middle']))
        ->assertSessionHasErrors(['width', 'font_size', 'header_align']);

    $this->put('/settings/receipt', receiptSettings(['header_image' => UploadedFile::fake()->create('x.gif', 5, 'image/gif')]))
        ->assertSessionHasErrors('header_image');

    $this->put('/settings/receipt', receiptSettings(['header_image' => UploadedFile::fake()->image('header.jpg', 600, 150)]))->assertRedirect();
    Storage::disk('public')->assertExists(settings('receipt.header_image'));

    $sale = printableSale();
    $this->get("/sales/{$sale->id}/receipt")->assertSee('data-receipt-logo', false)->assertSee(basename(settings('receipt.header_image')));
});

test('the KOT follows the kitchen receipt settings', function () {
    $sale = printableSale();
    $kot = KitchenOrder::sole();

    $this->get("/kitchen/orders/{$kot->id}")->assertSeeInOrder(['KOT (Kitchen)', 'KOT-1', 'TABLE', 'Table 9', 'ORDER', 'Dine In', 'Order No.', $sale->invoice_number, 'Cashier', 'Customer', 'Sara', 'Grill', '2 x Burger', 'No onions', 'Birthday']);

    $this->put('/settings/kot', kotSettings([
        'width' => '58mm', 'header_text' => 'GRILL ORDER', 'footer_text' => 'Serve hot', 'show_table' => '0',
        'show_cashier' => '0', 'show_customer' => '0', 'show_notes' => '0', 'show_station_headings' => '0',
    ]))->assertRedirect('/settings/kot');

    $this->get("/kitchen/orders/{$kot->id}")
        ->assertSee('paper-58mm', false)
        ->assertSee('GRILL ORDER')
        ->assertSee('Serve hot')
        ->assertSee('2 x Burger')
        ->assertDontSee('Table 9')
        ->assertDontSee('Sara')
        ->assertDontSee('No onions')
        ->assertDontSee('Birthday');

    $this->put('/settings/kot', kotSettings(['width' => 'a4']))->assertSessionHasErrors('width');
});

test('the live previews use sample data and never create a sale or order', function () {
    $this->postJson('/settings/preview/receipt', receiptSettings(['footer_text' => 'Preview footer', 'width' => '58mm']))
        ->assertOk()
        ->assertJsonPath('html', fn (string $html) => str_contains($html, 'Sample data') && str_contains($html, 'Preview footer') && str_contains($html, 'paper-58mm'));

    $this->postJson('/settings/preview/kot', kotSettings(['header_text' => 'PREVIEW KOT']))
        ->assertOk()
        ->assertJsonPath('html', fn (string $html) => str_contains($html, 'PREVIEW KOT') && str_contains($html, 'Sample data'));

    // A chosen (unsaved) header image is shown in the preview without being stored.
    $this->post('/settings/preview/receipt', receiptSettings(['header_image' => UploadedFile::fake()->image('new.png', 200, 60)]), ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('html', fn (string $html) => str_contains($html, 'data:image/png;base64,'));

    $this->postJson('/settings/preview/receipt', receiptSettings(['width' => 'wide']))->assertUnprocessable()->assertJsonValidationErrors('width');

    expect(Sale::count())->toBe(0)->and(KitchenOrder::count())->toBe(0)->and(settings('receipt.header_image'))->toBeNull();
    Storage::disk('public')->assertDirectoryEmpty('/');

    $this->actingAs(userWith('Manager'))->postJson('/settings/preview/kot', kotSettings())->assertOk();
});

// ------------------------------------------------------------------ printing

test('print pages show the right document without the app navigation', function () {
    $sale = printableSale();
    $kot = KitchenOrder::sole();

    foreach (["/sales/{$sale->id}/receipt", "/sales/{$sale->id}/print", "/kitchen/orders/{$kot->id}"] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSee('data-print-page', false)
            ->assertSee('class="no-print', false)
            ->assertDontSee('app-sidebar', false)
            ->assertDontSee('global-search', false);
    }

    $this->get("/sales/{$sale->id}/print")->assertSeeInOrder(['data-slip="customer"', $sale->invoice_number, 'data-slip="kitchen"', 'KOT-1'], false);
    $this->get("/sales/{$sale->id}/receipt?print=1")->assertSee('window.print()', false);
});

test('receipts are hidden from users without sales access', function () {
    $sale = printableSale();

    $this->actingAs(userWith('Kitchen Staff'))->get("/sales/{$sale->id}/receipt")->assertForbidden();
    $this->actingAs(userWith('Kitchen Staff'))->get("/sales/{$sale->id}/print")->assertForbidden();
});

// ------------------------------------------------------------------ font family settings (Part B)

test('receipt font_family and heading_font are validated against the font whitelist', function () {
    $this->put('/settings/receipt', receiptSettings(['font_family' => 'times-new-roman']))->assertSessionHasErrors('font_family');
    $this->put('/settings/receipt', receiptSettings(['heading_font' => 'unknown-font']))->assertSessionHasErrors('heading_font');

    $this->put('/settings/receipt', receiptSettings(['font_family' => 'roboto-mono', 'heading_font' => 'roboto-slab']))->assertRedirect('/settings/receipt');
    expect(settings()->receipt()['font_family'])->toBe('roboto-mono')
        ->and(settings()->receipt()['heading_font'])->toBe('roboto-slab');
});

test('KOT font_family is validated against the font whitelist', function () {
    $this->put('/settings/kot', kotSettings(['font_family' => 'not-a-font']))->assertSessionHasErrors('font_family');

    $this->put('/settings/kot', kotSettings(['font_family' => 'jetbrains-mono']))->assertRedirect('/settings/kot');
    expect(settings()->kot()['font_family'])->toBe('jetbrains-mono');
});

test('receipt font settings are reflected in the CSS variables emitted by the theme', function () {
    $sale = printableSale();

    $this->put('/settings/receipt', receiptSettings(['font_family' => 'inter', 'heading_font' => 'roboto-slab']))->assertRedirect();

    $this->get("/sales/{$sale->id}/receipt")
        ->assertSee('--receipt-font: Inter, ui-sans-serif, system-ui, sans-serif;', false)
        ->assertSee('--receipt-heading-font: "Roboto Slab", Georgia, serif;', false);
});

test('appearance font_family setting is saved and emits the app-font CSS variable', function () {
    $this->put('/settings/appearance', [
        'theme' => 'indigo', 'font_family' => 'inter',
        'primary_color' => '#4f46e5', 'secondary_color' => '#059669', 'button_color' => '#4f46e5',
        'sidebar_color' => '#ffffff', 'sidebar_text_color' => '#4b5563',
    ])->assertRedirect();

    expect(settings('appearance.font_family'))->toBe('inter');

    $this->get('/dashboard')->assertSee('--app-font: Inter, ui-sans-serif, system-ui, sans-serif;', false);
});

test('appearance font_family rejects unknown fonts', function () {
    $this->put('/settings/appearance', [
        'theme' => 'indigo', 'font_family' => 'wingdings',
        'primary_color' => '#4f46e5', 'secondary_color' => '#059669', 'button_color' => '#4f46e5',
        'sidebar_color' => '#ffffff', 'sidebar_text_color' => '#4b5563',
    ])->assertSessionHasErrors('font_family');
});

// ------------------------------------------------------------------ KOT layout redesign (Part C)

test('the KOT uses quantity-first format with plain x separator', function () {
    $sale = printableSale();
    $kot = KitchenOrder::sole();

    $this->get("/kitchen/orders/{$kot->id}")
        ->assertSee('2 x Burger', false)
        ->assertDontSee('Burger × 2', false)
        ->assertDontSee('2 × Burger', false);
});

test('KOT item_size setting is validated and applied', function () {
    $sale = printableSale();
    $kot = KitchenOrder::sole();

    $this->put('/settings/kot', kotSettings(['item_size' => 'mega']))->assertSessionHasErrors('item_size');

    $this->put('/settings/kot', kotSettings(['item_size' => 'extra_large']))->assertRedirect();
    expect(settings()->kot()['item_size'])->toBe('extra_large');
    $this->get("/kitchen/orders/{$kot->id}")->assertSee('kot-items-xl', false);

    $this->put('/settings/kot', kotSettings(['item_size' => 'normal']))->assertRedirect();
    $this->get("/kitchen/orders/{$kot->id}")->assertDontSee('kot-items-', false);
});

test('the KOT Order number matches the sale order number', function () {
    $sale = printableSale();
    $kot = KitchenOrder::sole();

    $orderNo = $sale->order_no ?? $sale->id;
    $this->get("/kitchen/orders/{$kot->id}")->assertSee("Order: {$orderNo}", false);
});
