<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUsersSeeder extends Seeder
{
    /**
     * One login per role for local testing. All use the password "password".
     *
     * @var array<string, array{name: string, email: string}>
     */
    public const USERS = [
        'Super Admin' => ['name' => 'Super Admin', 'email' => 'superadmin@pos.test'],
        'Admin' => ['name' => 'Admin User', 'email' => 'admin@pos.test'],
        'Manager' => ['name' => 'Manager User', 'email' => 'manager@pos.test'],
        'Cashier' => ['name' => 'Cashier User', 'email' => 'cashier@pos.test'],
        'Kitchen Staff' => ['name' => 'Kitchen User', 'email' => 'kitchen@pos.test'],
    ];

    /**
     * Seed demo users. Never runs in production. Safe to run repeatedly.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Skipping demo users in production.');

            return;
        }

        foreach (self::USERS as $role => $attributes) {
            $user = User::firstOrNew(['email' => $attributes['email']]);

            $user->forceFill([
                'name' => $attributes['name'],
                'password' => 'password',
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            $user->syncRoles([$role]);
        }
    }
}
