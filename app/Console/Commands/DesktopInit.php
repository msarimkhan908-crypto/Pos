<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;

class DesktopInit extends Command
{
    protected $signature = 'desktop:init';

    protected $description = 'Seed roles/permissions and create the initial admin for a fresh desktop install.';

    public function handle(): int
    {
        // Roles and permissions seeder is idempotent — safe to run every time
        $this->call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);

        // Only create the initial admin on a truly empty database
        if (User::count() > 0) {
            return self::SUCCESS;
        }

        $email = 'admin@pos.local';
        $password = 'admin123';

        $user = User::create([
            'name' => 'Admin',
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
        ]);

        $user->assignRole('Super Admin');

        // Emit JSON so the Electron first-run dialog can surface the credentials.
        $this->line(json_encode(['email' => $email, 'password' => $password]));

        return self::SUCCESS;
    }
}
