<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->rent = ExpenseCategory::factory()->create(['name' => 'Rent']);
    $this->actingAs($this->manager = userWith('Manager'));
});

/**
 * @return array<string, mixed>
 */
function expensePayload(array $overrides = []): array
{
    return [
        'category_id' => test()->rent->id,
        'amount' => '45000',
        'expense_date' => today()->toDateString(),
        'payment_method' => 'bank',
        'description' => 'Shop rent for September',
        'reference' => 'LEASE-09',
        ...$overrides,
    ];
}

test('an expense is recorded with a number and who recorded it', function () {
    $this->get('/expenses/create')->assertOk()->assertSee('Rent');

    $this->post('/expenses', expensePayload())->assertRedirect();

    $expense = Expense::sole();
    expect($expense)
        ->expense_number->toBe('EXP-000001')
        ->category_id->toBe($this->rent->id)
        ->created_by->toBe($this->manager->id)
        ->reference->toBe('LEASE-09')
        ->payment_method->value->toBe('bank')
        ->and((string) $expense->amount)->toBe('45000.00');

    $this->get("/expenses/{$expense->id}")->assertOk()->assertSee('EXP-000001')->assertSee('Shop rent for September')->assertSee('Bank transfer');
});

test('expense numbers are sequential and unique, and use the prefix from settings', function () {
    $this->post('/expenses', expensePayload())->assertRedirect();
    settings()->set(['general.expense_prefix' => 'EX/']);
    $this->post('/expenses', expensePayload())->assertRedirect();

    expect(Expense::orderBy('id')->pluck('expense_number')->all())->toBe(['EXP-000001', 'EX/000002']);
    expect(fn () => Expense::factory()->create(['expense_number' => 'EXP-000001']))->toThrow(UniqueConstraintViolationException::class);
});

test('the expense number and author cannot be set from the form', function () {
    $this->post('/expenses', expensePayload(['expense_number' => 'HACK-1', 'created_by' => 999]))->assertRedirect();

    expect(Expense::sole())->expense_number->toBe('EXP-000001')->created_by->toBe($this->manager->id);
});

test('expenses are validated', function (array $input, string $error) {
    $this->post('/expenses', expensePayload($input))->assertSessionHasErrors($error);
    expect(Expense::count())->toBe(0);
})->with([
    'no category' => [['category_id' => null], 'category_id'],
    'unknown category' => [['category_id' => 999], 'category_id'],
    'zero amount' => [['amount' => '0'], 'amount'],
    'negative amount' => [['amount' => '-5'], 'amount'],
    'three decimals' => [['amount' => '1.005'], 'amount'],
    'future date' => [['expense_date' => today()->addDay()->toDateString()], 'expense_date'],
    'bad method' => [['payment_method' => 'cheque'], 'payment_method'],
    'long reference' => [['reference' => str_repeat('x', 101)], 'reference'],
]);

test('inactive categories cannot be used for new expenses but existing ones keep theirs', function () {
    $old = ExpenseCategory::factory()->create(['name' => 'Old category', 'status' => 'inactive']);
    $this->post('/expenses', expensePayload(['category_id' => $old->id]))->assertSessionHasErrors('category_id');

    $expense = Expense::factory()->create(['category_id' => $old->id]);
    $this->get("/expenses/{$expense->id}/edit")->assertOk()->assertSee('Old category');
    $this->put("/expenses/{$expense->id}", expensePayload(['category_id' => $old->id, 'amount' => '99']))->assertRedirect();
    expect((string) $expense->fresh()->amount)->toBe('99.00');
});

test('expenses can be edited and deleted', function () {
    $this->post('/expenses', expensePayload())->assertRedirect();
    $expense = Expense::sole();

    $this->put("/expenses/{$expense->id}", expensePayload(['amount' => '46000', 'payment_method' => 'cash']))->assertRedirect();
    expect($expense->fresh())->payment_method->value->toBe('cash')->and((string) $expense->fresh()->amount)->toBe('46000.00');

    $this->delete("/expenses/{$expense->id}")->assertForbidden(); // managers cannot delete

    $this->actingAs(userWith('Admin'))->delete("/expenses/{$expense->id}")->assertRedirect('/expenses');
    expect(Expense::count())->toBe(0);
});

test('only authorised users can see and record expenses', function (string $role, int $status) {
    $this->actingAs(userWith($role));

    $this->get('/expenses')->assertStatus($status);
    $this->get('/expense-categories')->assertStatus($status);
    $this->post('/expenses', expensePayload())->assertStatus($status === 200 ? 302 : 403);
})->with([
    'admin' => ['Admin', 200],
    'manager' => ['Manager', 200],
    'cashier' => ['Cashier', 403],
    'kitchen' => ['Kitchen Staff', 403],
]);

test('the expense list can be searched and filtered and shows the total', function () {
    $power = ExpenseCategory::factory()->create(['name' => 'Electricity']);
    Expense::factory()->create(['expense_number' => 'EXP-000001', 'category_id' => $this->rent->id, 'amount' => 1000, 'description' => 'Rent', 'expense_date' => today()]);
    Expense::factory()->create(['expense_number' => 'EXP-000002', 'category_id' => $power->id, 'amount' => 250, 'description' => 'Power bill', 'payment_method' => 'card', 'expense_date' => today()->subDays(10)]);

    $this->get('/expenses')->assertOk()->assertSeeInOrder(['EXP-000001', 'EXP-000002'])->assertSee('1,250');
    $this->get("/expenses?category={$power->id}")->assertSee('EXP-000002')->assertDontSee('EXP-000001');
    $this->get('/expenses?method=card')->assertSee('EXP-000002')->assertDontSee('EXP-000001');
    $this->get('/expenses?search=Power')->assertSee('EXP-000002')->assertDontSee('EXP-000001');
    $this->get('/expenses?from='.today()->subDays(2)->toDateString())->assertSee('EXP-000001')->assertDontSee('EXP-000002');
});

test('the expense list paginates', function () {
    Expense::factory()->count(16)->create(['category_id' => $this->rent->id]);

    $this->get('/expenses')->assertOk()->assertSee('?page=2', false);
});

test('expense categories are managed and protected from deletion when used', function () {
    $this->get('/expense-categories')->assertOk()->assertSee('Rent');

    $this->post('/expense-categories', ['name' => 'Internet', 'status' => 'active'])->assertRedirect('/expense-categories');
    $this->post('/expense-categories', ['name' => 'Internet', 'status' => 'active'])->assertSessionHasErrors('name');
    $internet = ExpenseCategory::where('name', 'Internet')->sole();

    $this->put("/expense-categories/{$internet->id}", ['name' => 'Internet & Phone', 'status' => 'inactive'])->assertRedirect();
    expect($internet->fresh())->name->toBe('Internet & Phone')->isActive()->toBeFalse();

    Expense::factory()->create(['category_id' => $this->rent->id]);
    $this->actingAs(userWith('Admin'));
    $this->delete("/expense-categories/{$this->rent->id}")->assertSessionHas('error');
    $this->delete("/expense-categories/{$internet->id}")->assertRedirect('/expense-categories');

    expect(ExpenseCategory::pluck('name')->all())->toBe(['Rent']);
});

test('no expense categories or expenses are created automatically', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(ExpenseCategory::count())->toBe(1)->and(Expense::count())->toBe(0);
});
