<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeSuperAdmin extends Command
{
    protected $signature = 'admin:make-super {email}';

    protected $description = 'Assign the Super Admin role to a user by email';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('User not found: '.$this->argument('email'));

            return 1;
        }

        $user->assignRole('Super Admin');
        $this->info('Super Admin role assigned to '.$user->name.' ('.$user->email.')');

        return 0;
    }
}
