<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $tio = User::updateOrCreate(
            ['email' => 'tiomuhamadnur@gmail.com'],
            [
                'name'              => 'Tio Muhamad Nur',
                'password'          => Hash::make('Baragajul'),
                'is_super_admin'    => true,
                'email_verified_at' => now(),
            ],
        );
        $tio->syncRoles(['super-admin']);

        $admin = User::updateOrCreate(
            ['email' => 'admin@mrt.local'],
            [
                'name'              => 'Administrator',
                'password'          => Hash::make('password'),
                'is_super_admin'    => true,
                'email_verified_at' => now(),
            ],
        );
        $admin->syncRoles(['super-admin']);
    }
}
