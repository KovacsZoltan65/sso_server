<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->updateOrCreate(
            ['email' => 'superadmin@sso.test'],
            [
                'name' => 'SSO Superadmin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@sso.test'],
            [
                'name' => 'SSO Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $superadmin->syncRoles(['superadmin']);
        $admin->syncRoles(['admin']);
    }
}
