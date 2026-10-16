<?php

use App\Models\User;

test('a user can register, log out and log back in', function () {
    $credentials = ['email' => 'owner@example.com', 'password' => 'Secret-Pass-123'];

    $this->post('/register', [
        'name' => 'Store Owner',
        'email' => $credentials['email'],
        'password' => $credentials['password'],
        'password_confirmation' => $credentials['password'],
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => $credentials['email'], 'name' => 'Store Owner']);

    $this->post('/logout')->assertRedirect('/');
    $this->assertGuest();

    $this->post('/login', $credentials)->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs(User::firstWhere('email', $credentials['email']));

    $this->get('/dashboard')->assertOk();
});

test('registration does not grant any role', function () {
    $this->post('/register', [
        'name' => 'New User',
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::firstWhere('email', 'new@example.com');

    expect($user->roles)->toBeEmpty()
        ->and($user->getAllPermissions())->toBeEmpty();
});

test('guests cannot access authenticated pages', function (string $uri) {
    $this->get($uri)->assertRedirect(route('login'));
    $this->assertGuest();
})->with(['/dashboard', '/profile']);

test('a logged out user can no longer access the dashboard', function () {
    $this->actingAs(User::factory()->create())->post('/logout');

    $this->get('/dashboard')->assertRedirect(route('login'));
});

test('authenticated users are redirected away from login and register', function (string $uri) {
    $this->actingAs(User::factory()->create())
        ->get($uri)
        ->assertRedirect(route('dashboard', absolute: false));
})->with(['/login', '/register']);
