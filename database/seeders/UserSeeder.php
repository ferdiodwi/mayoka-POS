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
            'branch_id' => 1,
            'name' => 'Pemilik Toko',
            'username' => 'admin',
            'password' => 'password',
            'role' => 'owner',
            'is_active' => true,
        ]);

        User::create([
            'branch_id' => 1,
            'name' => 'ferdiodwi',
            'username' => 'ferdio',
            'password' => 'password',
            'role' => 'owner',
            'is_active' => true,
        ]);

    }
}
