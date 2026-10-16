<?php

use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function modifierGroupPayload(array $overrides = []): array
{
    return [
        'name' => 'Burger Add-ons',
        'min_select' => '0',
        'max_select' => '',
        'is_active' => '1',
        'modifiers' => [
            ['name' => 'Cheese', 'price' => '100', 'is_active' => '1'],
            ['name' => 'Egg', 'price' => '50', 'is_active' => '1'],
            ['name' => 'Extra Patty', 'price' => '200', 'is_active' => '1'],
        ],
        ...$overrides,
    ];
}

test('modifier pages render', function () {
    $group = ModifierGroup::factory()->hasModifiers(2)->create(['name' => 'Toppings']);
    $this->actingAs(userWith('Admin'));

    $this->get('/modifier-groups')->assertOk()->assertSee('Toppings');
    $this->get('/modifier-groups/create')->assertOk();
    $this->get("/modifier-groups/{$group->id}/edit")->assertOk();
});

test('a modifier group can be created with its options', function () {
    $this->actingAs(userWith('Admin'))
        ->post('/modifier-groups', modifierGroupPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('modifier-groups.index'));

    $group = ModifierGroup::sole();

    expect($group->modifiers->pluck('price', 'name')->map(fn ($p) => (float) $p)->all())
        ->toBe(['Cheese' => 100.0, 'Egg' => 50.0, 'Extra Patty' => 200.0])
        ->and($group->modifiers->pluck('sort_order')->all())->toBe([0, 1, 2])
        ->and($group->isRequired())->toBeFalse();
});

test('products can have modifier groups and the add-on price is calculable', function () {
    $this->actingAs(userWith('Admin'))->post('/modifier-groups', modifierGroupPayload());
    $group = ModifierGroup::sole();

    $this->post('/products', [
        'name' => 'Zinger Burger',
        'selling_price' => '500',
        'is_active' => '1',
        'modifier_groups' => [$group->id],
    ])->assertSessionHasNoErrors();

    $product = Product::with('modifierGroups.modifiers')->firstWhere('name', 'Zinger Burger');
    $selected = $product->modifierGroups->sole()->modifiers->whereIn('name', ['Cheese', 'Egg']);

    expect($product->discountedPrice() + $selected->sum('price'))->toBe(650.0);
});

test('modifiers can be used on products without variants', function () {
    $group = ModifierGroup::factory()->hasModifiers(1)->create();
    $product = Product::factory()->create();
    $product->modifierGroups()->attach($group);

    expect($product->hasVariants())->toBeFalse()
        ->and($product->modifierGroups)->toHaveCount(1);
});

test('updating a group updates, adds and removes options', function () {
    $this->actingAs(userWith('Admin'))->post('/modifier-groups', modifierGroupPayload());
    $group = ModifierGroup::sole();
    [$cheese, $egg] = $group->modifiers->all();

    $this->put("/modifier-groups/{$group->id}", modifierGroupPayload([
        'modifiers' => [
            ['id' => $cheese->id, 'name' => 'Cheddar', 'price' => '120', 'is_active' => '1'],
            ['name' => 'Jalapeños', 'price' => '0', 'is_active' => '0'],
        ],
    ]))->assertSessionHasNoErrors();

    $modifiers = $group->fresh()->modifiers;

    expect($modifiers->pluck('name')->all())->toBe(['Cheddar', 'Jalapeños'])
        ->and($modifiers->first()->id)->toBe($cheese->id)
        ->and((float) $modifiers->first()->price)->toBe(120.0)
        ->and($modifiers->last()->is_active)->toBeFalse()
        ->and(Modifier::find($egg->id))->toBeNull();
});

test('option names can be swapped in one save', function () {
    $this->actingAs(userWith('Admin'))->post('/modifier-groups', modifierGroupPayload());
    $group = ModifierGroup::sole();
    [$cheese, $egg, $patty] = $group->modifiers->all();

    $this->put("/modifier-groups/{$group->id}", modifierGroupPayload([
        'modifiers' => [
            ['id' => $cheese->id, 'name' => 'Egg', 'price' => '100', 'is_active' => '1'],
            ['id' => $egg->id, 'name' => 'Cheese', 'price' => '50', 'is_active' => '1'],
            ['id' => $patty->id, 'name' => 'Extra Patty', 'price' => '200', 'is_active' => '1'],
        ],
    ]))->assertSessionHasNoErrors();

    expect($cheese->fresh()->name)->toBe('Egg')->and($egg->fresh()->name)->toBe('Cheese');
});

test('options from another group cannot be hijacked', function () {
    $other = ModifierGroup::factory()->hasModifiers(1)->create();
    $this->actingAs(userWith('Admin'))->post('/modifier-groups', modifierGroupPayload());
    $group = ModifierGroup::where('name', 'Burger Add-ons')->sole();

    $this->put("/modifier-groups/{$group->id}", modifierGroupPayload([
        'modifiers' => [['id' => $other->modifiers->first()->id, 'name' => 'Stolen', 'price' => '1', 'is_active' => '1']],
    ]))->assertSessionHasErrors('modifiers.0.id');
});

test('modifier group validation', function (array $data, string $field) {
    $this->actingAs(userWith('Admin'))
        ->post('/modifier-groups', modifierGroupPayload($data))
        ->assertSessionHasErrors($field);

    expect(ModifierGroup::count())->toBe(0);
})->with([
    'name required' => [['name' => ''], 'name'],
    'at least one option' => [['modifiers' => []], 'modifiers'],
    'option name required' => [['modifiers' => [['name' => '', 'price' => '1', 'is_active' => '1']]], 'modifiers.0.name'],
    'negative option price' => [['modifiers' => [['name' => 'Egg', 'price' => '-5', 'is_active' => '1']]], 'modifiers.0.price'],
    'duplicate option names' => [['modifiers' => [['name' => 'Egg', 'price' => '1', 'is_active' => '1'], ['name' => 'egg', 'price' => '2', 'is_active' => '1']]], 'modifiers.1.name'],
    'max below min' => [['min_select' => '2', 'max_select' => '1'], 'max_select'],
    'min above active options' => [['min_select' => '4'], 'min_select'],
]);

test('selection rules are described for the POS', function () {
    expect(ModifierGroup::make(['min_select' => 0, 'max_select' => null])->selectionRule())->toBe('Optional · any number')
        ->and(ModifierGroup::make(['min_select' => 1, 'max_select' => 1])->selectionRule())->toBe('Required · choose 1')
        ->and(ModifierGroup::make(['min_select' => 0, 'max_select' => 3])->selectionRule())->toBe('Optional · up to 3');
});

test('deleting a group detaches it from products', function () {
    $group = ModifierGroup::factory()->hasModifiers(2)->create();
    $product = Product::factory()->create();
    $product->modifierGroups()->attach($group);

    $this->actingAs(userWith('Admin'))->delete("/modifier-groups/{$group->id}");

    expect(ModifierGroup::count())->toBe(0)
        ->and(Modifier::count())->toBe(0)
        ->and($product->modifierGroups()->count())->toBe(0);
});

test('modifier groups require product permissions', function () {
    // Cashier has no products.view; Kitchen Staff does (can view but not manage).
    $this->actingAs(userWith('Cashier'))->get('/modifier-groups')->assertForbidden();
    $this->actingAs(userWith('Kitchen Staff'))->get('/modifier-groups')->assertOk();
    $this->actingAs(userWith('Manager'))->get('/modifier-groups')->assertOk();
});
