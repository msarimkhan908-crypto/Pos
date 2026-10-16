<?php

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/**
 * Share validation errors the way ShareErrorsFromSession does for real requests.
 *
 * @param  array<string, list<string>>  $messages
 */
function shareErrors(array $messages): void
{
    view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag($messages)));
}

test('button renders variants, sizes and links', function () {
    $this->blade('<x-ui.button variant="danger" size="sm" icon="x">Delete</x-ui.button>')
        ->assertSee('<button', false)->assertSee('type="button"', false)
        ->assertSee('bg-red-600')
        ->assertSee('text-xs')
        ->assertSee('<svg', false)
        ->assertSee('Delete');

    $this->blade('<x-ui.button type="submit" disabled>Save</x-ui.button>')
        ->assertSee('type="submit"', false)
        ->assertSee('disabled', false);

    $this->blade('<x-ui.button href="/somewhere" variant="secondary">Go</x-ui.button>')
        ->assertSee('<a href="/somewhere"', false)
        ->assertDontSee('<button', false);
});

test('input renders label, hint and accessible attributes', function () {
    $this->blade('<x-ui.input name="email" label="Email" type="email" value="a@b.com" required hint="Work email" />')
        ->assertSee('<label for="email"', false)
        ->assertSee('type="email"', false)
        ->assertSee('value="a@b.com"', false)
        ->assertSee('required', false)
        ->assertSee('aria-describedby="email-hint"', false)
        ->assertSee('Work email');
});

test('input converts array names to valid ids and error keys', function () {
    shareErrors(['items.0.qty' => ['Quantity is required.']]);

    $this->blade('<x-ui.input name="items[0][qty]" />')
        ->assertSee('id="items_0_qty"', false)
        ->assertSee('Quantity is required.');
});

test('password input never echoes its value', function () {
    $this->blade('<x-ui.input name="password" type="password" value="secret123" />')
        ->assertDontSee('secret123');
});

test('input and select show validation errors', function () {
    shareErrors(['email' => ['The email field is required.'], 'role' => ['Pick a role.']]);

    $this->blade('<x-ui.input name="email" label="Email" hint="Hidden when invalid" />')
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('aria-describedby="email-error"', false)
        ->assertSee('The email field is required.')
        ->assertDontSee('Hidden when invalid');

    $this->blade('<x-ui.select name="role" :options="[]" />')
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('Pick a role.');
});

test('select renders options with the selected value', function () {
    $this->blade('<x-ui.select name="role" label="Role" :options="[1 => \'Admin\', 2 => \'Cashier\']" selected="2" placeholder="Pick one" />')
        ->assertSeeInOrder(['Pick one', 'Admin', 'Cashier'])
        ->assertSee('<option value="2" selected', false)
        ->assertDontSee('<option value="1" selected', false);
});

test('alert renders each type with the right role', function (string $type, string $class, string $role) {
    $this->blade('<x-ui.alert :type="$type" title="Heads up">Message</x-ui.alert>', ['type' => $type])
        ->assertSee($class)
        ->assertSee("role=\"{$role}\"", false)
        ->assertSeeInOrder(['Heads up', 'Message']);
})->with([
    ['info', 'bg-sky-50', 'status'],
    ['success', 'bg-emerald-50', 'status'],
    ['warning', 'bg-amber-50', 'alert'],
    ['danger', 'bg-red-50', 'alert'],
]);

test('dismissible alert has a close button', function () {
    $this->blade('<x-ui.alert dismissible>Saved</x-ui.alert>')
        ->assertSee('x-data="{ open: true }"', false)
        ->assertSee('Dismiss');
});

test('badge renders color and dot', function () {
    $this->blade('<x-ui.badge color="warning" dot>Pending</x-ui.badge>')
        ->assertSee('bg-amber-50')
        ->assertSee('bg-amber-500')
        ->assertSee('Pending');
});

test('card renders title, actions, body and footer', function () {
    // Whitespace after </x-slot:...> is required: "</x-slot:actions>Body" compiles to "@endslotBody",
    // which Blade does not recognise, leaving the slot open.
    $this->blade('<x-ui.card title="Summary" description="Today"><x-slot:actions>Act</x-slot:actions> Body <x-slot:footer>Foot</x-slot:footer></x-ui.card>')
        ->assertSeeInOrder(['Summary', 'Today', 'Act', 'Body', 'Foot'])
        ->assertDontSee('@endslot');

    $this->blade('<x-ui.card :padding="false">Flush</x-ui.card>')
        ->assertDontSee('p-5')
        ->assertSee('Flush');
});

test('page header renders title, description and actions', function () {
    $this->blade('<x-ui.page-header title="Products" description="Manage items"><x-slot:actions>New</x-slot:actions></x-ui.page-header>')
        ->assertSee('<h1', false)
        ->assertSeeInOrder(['Products', 'Manage items', 'New']);
});

test('modal renders title, body, footer and open event name', function () {
    $this->blade('<x-ui.modal name="confirm" title="Are you sure?">Text<x-slot:footer>Ok</x-slot:footer></x-ui.modal>')
        ->assertSee("'confirm'", false)
        ->assertSee('Close')
        ->assertSeeInOrder(['Are you sure?', 'Text', 'Ok']);
});

test('empty state renders title and description', function () {
    $this->blade('<x-ui.empty-state icon="orders" title="No orders yet" description="They will show here." />')
        ->assertSeeInOrder(['No orders yet', 'They will show here.']);
});

test('dashboard stat card renders its values', function () {
    $this->blade('<x-dashboard.stat-card label="Today\'s Sales" value="$10.00" icon="banknote" description="Gross" tone="warning" />')
        ->assertSeeInOrder(['Today&#039;s Sales', '$10.00', 'Gross'], false)
        ->assertSee('bg-amber-50');
});

test('icon component renders known icons and ignores unknown ones', function () {
    $this->blade('<x-icon name="bell" class="size-4" />')
        ->assertSee('<path', false)
        ->assertSee('size-4');

    $this->blade('<x-icon name="nope" />')
        ->assertSee('<svg', false)
        ->assertDontSee('<path', false);
});
