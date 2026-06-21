<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed default users for the POS system.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Pemilik Toko',
            'username' => 'admin',
            'password' => 'password',
            'role' => 'owner',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Ferdio',
            'username' => 'ferdio',
            'password' => 'password',
            'role' => 'kasir',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Albert',
            'username' => 'albert',
            'password' => 'password',
            'role' => 'kasir',
            'is_active' => true,
        ]);
    }
}
